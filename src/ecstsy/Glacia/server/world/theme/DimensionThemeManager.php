<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\world\theme;

use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\item\ItemBlock;
use pocketmine\item\StringToItemParser;
use pocketmine\player\Player;
use pocketmine\world\Position;

final class DimensionThemeManager {

    /** @var array<string, array<string, mixed>> */
    private static array $themes = [];
    /** @var array<string, array<string, int>> */
    private static array $playerAmbientCooldowns = [];

    public static function loadFromConfig() : void
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "dimension_themes.yml");
        self::$themes = [];

        foreach ((array) ($config?->get("themes", []) ?? []) as $themeId => $themeData) {
            if (!is_array($themeData)) {
                continue;
            }

            $id = strtolower(trim((string) $themeId));
            if ($id === "") {
                continue;
            }

            self::$themes[$id] = [
                "id" => $id,
                "display" => (string) ($themeData["display"] ?? ucfirst(str_replace("_", " ", $id))),
                "description" => (string) ($themeData["description"] ?? ""),
                "world" => strtolower(trim((string) ($themeData["world"] ?? ""))),
                "zone_id" => strtolower(trim((string) ($themeData["zone-id"] ?? ""))),
                "generator_preset" => strtolower(trim((string) ($themeData["generator-preset"] ?? $id))),
                "scoreboard_profile" => strtolower(trim((string) ($themeData["scoreboard-profile"] ?? "default"))),
                "terrain" => is_array($themeData["terrain"] ?? null) ? $themeData["terrain"] : [],
                "ambience" => is_array($themeData["ambience"] ?? null) ? $themeData["ambience"] : [],
                "resource_pack" => is_array($themeData["resource-pack"] ?? null) ? $themeData["resource-pack"] : [],
            ];
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function getThemes() : array
    {
        return self::$themes;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function getTheme(string $themeId) : ?array
    {
        return self::$themes[strtolower(trim($themeId))] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function getThemeByGeneratorPreset(string $preset) : ?array
    {
        $preset = strtolower(trim($preset));
        foreach (self::$themes as $theme) {
            if (($theme["generator_preset"] ?? "") === $preset || ($theme["id"] ?? "") === $preset) {
                return $theme;
            }
        }
        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function getThemeAt(Position $position) : ?array
    {
        $worldName = strtolower($position->getWorld()->getFolderName());
        $zone = \ecstsy\Glacia\server\zones\ZoneManager::getZoneAt($position);
        $zoneId = strtolower((string) ($zone["id"] ?? ""));

        foreach (self::$themes as $theme) {
            if (($theme["zone_id"] ?? "") !== "" && ($theme["zone_id"] ?? "") === $zoneId) {
                return $theme;
            }
        }

        foreach (self::$themes as $theme) {
            if (($theme["world"] ?? "") !== "" && ($theme["world"] ?? "") === $worldName) {
                return $theme;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function getThemeForPlayer(Player $player) : ?array
    {
        return self::getThemeAt($player->getPosition());
    }

    public static function tickPlayer(Player $player, int $tick) : void
    {
        $theme = self::getThemeForPlayer($player);
        if ($theme === null) {
            return;
        }

        $uuid = $player->getUniqueId()->toString();
        $ambience = (array) ($theme["ambience"] ?? []);
        $soundCfg = is_array($ambience["sound"] ?? null) ? $ambience["sound"] : [];

        if ((bool) ($soundCfg["enabled"] ?? false)) {
            $interval = max(20, (int) ($soundCfg["interval-ticks"] ?? 160));
            $nextAt = self::$playerAmbientCooldowns[$uuid]["sound"] ?? 0;
            if ($tick >= $nextAt) {
                $sound = trim((string) ($soundCfg["sound"] ?? ""));
                if ($sound !== "") {
                    PlayerUtils::playSound(
                        $player,
                        $sound,
                        (float) ($soundCfg["volume"] ?? 1.0),
                        (float) ($soundCfg["pitch"] ?? 1.0)
                    );
                }
                self::$playerAmbientCooldowns[$uuid]["sound"] = $tick + $interval;
            }
        }
    }

    public static function clearPlayerState(Player $player) : void
    {
        unset(self::$playerAmbientCooldowns[$player->getUniqueId()->toString()]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function getTerrainSettings(string $preset) : array
    {
        $theme = self::getThemeByGeneratorPreset($preset) ?? self::getTheme("astral_veil");
        return is_array($theme["terrain"] ?? null) ? $theme["terrain"] : [];
    }

    public static function resolveTerrainBlockStateId(string $identifier, int $fallbackStateId) : int
    {
        $item = StringToItemParser::getInstance()->parse($identifier);
        if ($item instanceof ItemBlock) {
            return $item->getBlock()->getStateId();
        }

        return $fallbackStateId;
    }

}
