<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\spawn;

use ecstsy\Glacia\Loader;
use pocketmine\entity\InvalidSkinException;
use pocketmine\entity\Skin;
use pocketmine\Server;
use function file_get_contents;
use function imagealphablending;
use function imagecolorat;
use function imagecreatefrompng;
use function imagedestroy;
use function imageistruecolor;
use function imagepalettetotruecolor;
use function imagesavealpha;
use function imagesx;
use function imagesy;
use function in_array;
use function is_file;
use function pathinfo;
use function preg_replace;
use function strlen;
use function strtolower;
use function trim;

final class NpcSkinRepository
{
    private static string $lastResolvedSource = "fallback";

    public static function getLastResolvedSource(): string
    {
        return self::$lastResolvedSource;
    }

    public static function ensureSkinDirectory(): string
    {
        $dir = Loader::getInstance()->getDataFolder() . "skins" . DIRECTORY_SEPARATOR;
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        return $dir;
    }

    public static function hasSkin(string $name): bool
    {
        return is_file(self::resolvePath($name));
    }

    public static function resolvePath(string $name): string
    {
        $clean = trim($name);
        if ($clean === "") {
            return self::ensureSkinDirectory();
        }

        if (!str_ends_with(strtolower($clean), ".png")) {
            $clean .= ".png";
        }

        return self::ensureSkinDirectory() . $clean;
    }

    public static function loadSkin(string $name): ?Skin
    {
        $path = self::resolvePath($name);
        if (!is_file($path)) {
            return null;
        }

        $raw = self::readPngSkinData($path);
        if ($raw === null) {
            return null;
        }

        $baseName = pathinfo($path, PATHINFO_FILENAME);
        [$geometryName, $geometryData] = self::loadGeometrySidecar($path);

        try {
            self::$lastResolvedSource = $baseName . ".png";
            return new Skin(
                "glacia_npc_" . $baseName,
                $raw,
                "",
                $geometryName,
                $geometryData
            );
        } catch (InvalidSkinException) {
            return null;
        }
    }

    public static function resolveDefaultSkin(string $action): Skin
    {
        foreach (self::skinCandidatesFor($action) as $name) {
            $skin = self::loadSkin($name);
            if ($skin !== null) {
                return $skin;
            }
        }

        self::$lastResolvedSource = "built-in fallback";
        return self::createFallbackSkin();
    }

    public static function resolveSkin(?string $skinName, string $action): Skin
    {
        if ($skinName !== null && $skinName !== "") {
            $playerSkin = self::resolveOnlinePlayerSkin($skinName);
            if ($playerSkin !== null) {
                return $playerSkin;
            }

            $loaded = self::loadSkin($skinName);
            if ($loaded !== null) {
                return $loaded;
            }
        }

        return self::resolveDefaultSkin($action);
    }

    private static function resolveOnlinePlayerSkin(string $skinName): ?Skin
    {
        $lower = strtolower(trim($skinName));
        if (!str_starts_with($lower, "player:")) {
            return null;
        }

        $targetName = trim(substr($skinName, 7));
        if ($targetName === "") {
            return null;
        }

        $target = Server::getInstance()->getPlayerByPrefix($targetName);
        if ($target === null) {
            return null;
        }

        self::$lastResolvedSource = "player:" . $target->getName();
        return $target->getSkin();
    }

    /**
     * @return array{0:string,1:string}
     */
    private static function loadGeometrySidecar(string $pngPath): array
    {
        $geometryName = "geometry.humanoid.custom";
        $geometryData = "";

        $jsonPath = preg_replace('/\.png$/i', '.json', $pngPath);
        if (!is_file($jsonPath)) {
            return [$geometryName, $geometryData];
        }

        $contents = file_get_contents($jsonPath);
        if (!is_string($contents) || trim($contents) === "") {
            return [$geometryName, $geometryData];
        }

        $geometryData = $contents;
        $decoded = json_decode($contents, true);
        if (is_array($decoded)) {
            if (isset($decoded["geometry"]["default"]) && is_string($decoded["geometry"]["default"])) {
                $geometryName = $decoded["geometry"]["default"];
            } elseif (isset($decoded["minecraft:geometry"][0]["description"]["identifier"]) && is_string($decoded["minecraft:geometry"][0]["description"]["identifier"])) {
                $geometryName = $decoded["minecraft:geometry"][0]["description"]["identifier"];
            }
        }

        return [$geometryName, $geometryData];
    }

    private static function readPngSkinData(string $path): ?string
    {
        if (!function_exists("imagecreatefrompng") || !function_exists("imagecolorat")) {
            return null;
        }

        $image = @imagecreatefrompng($path);
        if ($image === false) {
            return null;
        }

        if (!imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        $width = imagesx($image);
        $height = imagesy($image);
        $expectedLength = $width * $height * 4;
        if (!in_array($expectedLength, Skin::ACCEPTED_SKIN_SIZES, true)) {
            imagedestroy($image);
            return null;
        }

        $raw = "";
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $argb = imagecolorat($image, $x, $y);
                $alpha = ($argb >> 24) & 0x7F;
                $red = ($argb >> 16) & 0xFF;
                $green = ($argb >> 8) & 0xFF;
                $blue = $argb & 0xFF;
                $raw .= chr($red) . chr($green) . chr($blue) . chr((int) round((127 - $alpha) * 255 / 127));
            }
        }
        imagedestroy($image);

        return $raw;
    }

    /**
     * @return string[]
     */
    private static function skinCandidatesFor(string $action): array
    {
        return match (strtolower(trim($action))) {
            "shop" => ["shop", "merchant", "clerk", "villager"],
            "blackmarket", "black_market", "bm", "bah" => ["blackmarket", "black_market", "dealer", "merchant"],
            "enchanter", "ce", "enchants" => ["enchanter", "wizard", "mage"],
            "quest", "quests" => ["quest", "quests", "librarian"],
            "questshop", "quest_shop", "qshop" => ["questshop", "merchant", "librarian"],
            "duel", "duels" => ["duel", "warrior", "knight"],
            "slotbot", "slots" => ["slotbot", "dealer", "merchant"],
            default => [$action, "npc", "default"],
        };
    }

    public static function createFallbackSkin(): Skin
    {
        $resourcePath = Loader::getInstance()->getResourcePath("skins/invalid_default.png");
        if (is_file($resourcePath)) {
            $raw = self::readPngSkinData($resourcePath);
            if ($raw !== null) {
                try {
                    return new Skin("glacia_npc_fallback", $raw, "", "geometry.humanoid.custom", "");
                } catch (InvalidSkinException) {
                }
            }
        }

        $skin = "\x9A\x7B\x5A\xFF";
        $shirt = "\x2B\x55\xAA\xFF";
        $raw = "";
        for ($y = 0; $y < 32; $y++) {
            for ($x = 0; $x < 64; $x++) {
                $torso = $x >= 20 && $x < 44 && $y >= 8 && $y < 20;
                $raw .= $torso ? $shirt : $skin;
            }
        }

        try {
            return new Skin("glacia_npc_fallback", $raw, "", "geometry.humanoid.custom", "");
        } catch (InvalidSkinException $e) {
            throw new \RuntimeException($e->getMessage(), 0, $e);
        }
    }
}
