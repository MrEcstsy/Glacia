<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\zones;

use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\Liquid;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat as C;
use pocketmine\world\Position;
use pocketmine\world\World;

final class ZoneManager {

    private const SAFE_POSITION_ATTEMPTS = 64;
    private const NEARBY_SAFE_SEARCH_RADIUS = 6;

    /** @var int[] */
    private const UNSAFE_FOOTING = [
        BlockTypeIds::WATER,
        BlockTypeIds::LAVA,
        BlockTypeIds::CACTUS,
        BlockTypeIds::MAGMA,
        BlockTypeIds::FIRE,
        BlockTypeIds::SOUL_FIRE,
        BlockTypeIds::SWEET_BERRY_BUSH,
        BlockTypeIds::COBWEB
    ];

    /** @var array<string, array<string, mixed>> */
    private static array $zones = [];
    private static string $netherShortcutZone = "nether";
    private static ?string $lastBoundaryEditNote = null;
    /** @var array<string, int> */
    private static array $boundaryBypasses = [];

    public static function loadFromConfig() : void
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "zones.yml");
        self::$netherShortcutZone = strtolower((string) ($config?->get("nether-shortcut-zone", "nether") ?? "nether"));
        self::$zones = [];

        foreach ((array) ($config?->get("zones", []) ?? []) as $zoneId => $zoneData) {
            if (!is_array($zoneData)) {
                continue;
            }

            $world = trim((string) ($zoneData["world"] ?? ""));
            if ($world === "") {
                continue;
            }

            $pos1X = isset($zoneData["pos1-x"]) ? (float) $zoneData["pos1-x"] : null;
            $pos1Z = isset($zoneData["pos1-z"]) ? (float) $zoneData["pos1-z"] : null;
            $pos2X = isset($zoneData["pos2-x"]) ? (float) $zoneData["pos2-x"] : null;
            $pos2Z = isset($zoneData["pos2-z"]) ? (float) $zoneData["pos2-z"] : null;
            $pos1World = trim((string) ($zoneData["pos1-world"] ?? $world));
            $pos2World = trim((string) ($zoneData["pos2-world"] ?? $world));
            $minX = isset($zoneData["min-x"]) ? (float) $zoneData["min-x"] : null;
            $maxX = isset($zoneData["max-x"]) ? (float) $zoneData["max-x"] : null;
            $minZ = isset($zoneData["min-z"]) ? (float) $zoneData["min-z"] : null;
            $maxZ = isset($zoneData["max-z"]) ? (float) $zoneData["max-z"] : null;
            if ($pos1X !== null && $pos1Z !== null && $pos2X !== null && $pos2Z !== null && strtolower($pos1World) === strtolower($pos2World)) {
                $world = $pos1World;
                $minX = min($pos1X, $pos2X);
                $maxX = max($pos1X, $pos2X);
                $minZ = min($pos1Z, $pos2Z);
                $maxZ = max($pos1Z, $pos2Z);
            } elseif ($pos1X !== null && $pos1Z !== null && $pos2X !== null && $pos2Z !== null) {
                $minX = null;
                $maxX = null;
                $minZ = null;
                $maxZ = null;
            }

            self::$zones[strtolower((string) $zoneId)] = [
                "id" => strtolower((string) $zoneId),
                "display" => (string) ($zoneData["display"] ?? ucfirst((string) $zoneId)),
                "description" => (string) ($zoneData["description"] ?? ""),
                "recommended_level" => max(1, (int) ($zoneData["recommended-level"] ?? 1)),
                "unlock_level" => max(1, (int) ($zoneData["unlock-level"] ?? 1)),
                "world" => $world,
                "x" => (float) ($zoneData["x"] ?? 0),
                "y" => (float) ($zoneData["y"] ?? 100),
                "z" => (float) ($zoneData["z"] ?? 0),
                "radius" => max(0, (int) ($zoneData["radius"] ?? 0)),
                "pos1_x" => $pos1X,
                "pos1_z" => $pos1Z,
                "pos1_world" => $pos1World,
                "pos2_x" => $pos2X,
                "pos2_z" => $pos2Z,
                "pos2_world" => $pos2World,
                "min_x" => $minX,
                "max_x" => $maxX,
                "min_z" => $minZ,
                "max_z" => $maxZ,
                "full_world" => (bool) ($zoneData["full-world"] ?? false),
            ];
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function getZones() : array
    {
        return self::$zones;
    }

    public static function getZone(string $zoneId) : ?array
    {
        return self::$zones[strtolower($zoneId)] ?? null;
    }

    public static function getNetherShortcutZone() : ?array
    {
        return self::getZone(self::$netherShortcutZone);
    }

    public static function canAccess(int $level, array $zone) : bool
    {
        return $level >= (int) ($zone["unlock_level"] ?? 1);
    }

    public static function getUnlockRequirement(array $zone) : int
    {
        return max(1, (int) ($zone["unlock_level"] ?? 1));
    }

    public static function getWorld(array $zone) : ?World
    {
        $worldName = (string) ($zone["world"] ?? "");
        if ($worldName === "") {
            return null;
        }

        $manager = Server::getInstance()->getWorldManager();
        $world = $manager->getWorldByName($worldName);
        if (!$world instanceof World && $manager->loadWorld($worldName)) {
            $world = $manager->getWorldByName($worldName);
        }

        return $world instanceof World ? $world : null;
    }

    public static function getPosition(array $zone, bool $centered = false) : ?Position
    {
        $world = self::getWorld($zone);
        if (!$world instanceof World) {
            return null;
        }

        if ($centered) {
            [$x, $z] = self::getCenterXZ($zone);
        } else {
            $x = (float) ($zone["x"] ?? 0);
            $z = (float) ($zone["z"] ?? 0);
        }

        return new Position(
            $x,
            (float) ($zone["y"] ?? 100),
            $z,
            $world
        );
    }

    public static function getZoneAt(Position $position) : ?array
    {
        $worldName = strtolower($position->getWorld()->getFolderName());

        foreach (self::$zones as $zone) {
            if (strtolower((string) $zone["world"]) !== $worldName) {
                continue;
            }

            if ((bool) ($zone["full_world"] ?? false)) {
                return $zone;
            }

            $x = $position->getX();
            $z = $position->getZ();
            $minX = $zone["min_x"] ?? null;
            $maxX = $zone["max_x"] ?? null;
            $minZ = $zone["min_z"] ?? null;
            $maxZ = $zone["max_z"] ?? null;

            if ($minX !== null && $maxX !== null && $minZ !== null && $maxZ !== null) {
                if ($x >= min($minX, $maxX) && $x <= max($minX, $maxX) && $z >= min($minZ, $maxZ) && $z <= max($minZ, $maxZ)) {
                    return $zone;
                }

                continue;
            }

            $radius = (int) ($zone["radius"] ?? 0);
            if ($radius > 0) {
                $dx = $x - (float) ($zone["x"] ?? 0);
                $dz = $z - (float) ($zone["z"] ?? 0);
                if (($dx * $dx) + ($dz * $dz) <= ($radius * $radius)) {
                    return $zone;
                }
            }
        }

        return null;
    }

    public static function canPlayerAccess(Player $player, array $zone) : bool
    {
        $session = Loader::getPlayerManager()->getSession($player);
        return $session !== null && self::canAccess($session->getLevel(), $zone);
    }

    public static function setBoundaryPoint(string $zoneId, Position $position, string $point) : bool
    {
        self::$lastBoundaryEditNote = null;
        $zoneId = strtolower($zoneId);
        $zone = self::getZone($zoneId);
        if ($zone === null || !in_array($point, ["pos1", "pos2"], true)) {
            return false;
        }

        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "zones.yml");
        if ($config === null) {
            return false;
        }

        $zones = (array) ($config->get("zones", []) ?? []);
        $zoneData = (array) ($zones[$zoneId] ?? []);
        if ($zoneData === []) {
            return false;
        }

        $worldName = $position->getWorld()->getFolderName();
        $otherPoint = $point === "pos1" ? "pos2" : "pos1";
        $otherWorld = trim((string) ($zoneData[$otherPoint . "-world"] ?? $zoneData["world"] ?? ""));
        $otherPointExists = isset($zoneData[$otherPoint . "-x"], $zoneData[$otherPoint . "-z"]);

        if ($otherPointExists && $otherWorld !== "" && strtolower($otherWorld) !== strtolower($worldName)) {
            unset(
                $zoneData[$otherPoint . "-x"],
                $zoneData[$otherPoint . "-z"],
                $zoneData[$otherPoint . "-world"],
                $zoneData["min-x"],
                $zoneData["max-x"],
                $zoneData["min-z"],
                $zoneData["max-z"]
            );
            self::$lastBoundaryEditNote = "&r&eCleared {$otherPoint} because it was saved in &f{$otherWorld}&e. Set {$otherPoint} again in &f{$worldName}&e.";
        }

        $zoneData[$point . "-x"] = $position->getFloorX();
        $zoneData[$point . "-z"] = $position->getFloorZ();
        $zoneData[$point . "-world"] = $worldName;
        $zoneData["world"] = $worldName;
        $zoneData["x"] = $position->getFloorX();
        $zoneData["y"] = $position->getFloorY();
        $zoneData["z"] = $position->getFloorZ();
        $zoneData["radius"] = 0;
        $zoneData["full-world"] = false;

        if (isset($zoneData["pos1-x"], $zoneData["pos1-z"], $zoneData["pos2-x"], $zoneData["pos2-z"])) {
            $pos1World = trim((string) ($zoneData["pos1-world"] ?? $worldName));
            $pos2World = trim((string) ($zoneData["pos2-world"] ?? $worldName));
            if (strtolower($pos1World) === strtolower($pos2World)) {
                $zoneData["world"] = $pos1World;
                $zoneData["min-x"] = min((float) $zoneData["pos1-x"], (float) $zoneData["pos2-x"]);
                $zoneData["max-x"] = max((float) $zoneData["pos1-x"], (float) $zoneData["pos2-x"]);
                $zoneData["min-z"] = min((float) $zoneData["pos1-z"], (float) $zoneData["pos2-z"]);
                $zoneData["max-z"] = max((float) $zoneData["pos1-z"], (float) $zoneData["pos2-z"]);
                $zoneData["x"] = (float) (($zoneData["min-x"] + $zoneData["max-x"]) / 2);
                $zoneData["z"] = (float) (($zoneData["min-z"] + $zoneData["max-z"]) / 2);
            }
        }

        $zones[$zoneId] = $zoneData;
        $config->set("zones", $zones);
        $config->save();
        self::loadFromConfig();
        return true;
    }

    public static function getLastBoundaryEditNote() : ?string
    {
        return self::$lastBoundaryEditNote;
    }

    public static function teleportPlayer(Player $player, array $zone) : bool
    {
        $position = self::getRandomSafePosition($zone, 14) ?? self::getPosition($zone, true) ?? self::getPosition($zone);
        if ($position === null) {
            return false;
        }

        $currentZone = self::getZoneAt($player->getPosition());
        if ($currentZone !== null && ($currentZone["id"] ?? null) !== ($zone["id"] ?? null)) {
            self::allowNextBoundaryCrossing($player);
        }

        $player->teleport($position);
        $player->sendTitle(
            C::colorize((string) ($zone["display"] ?? "&bZone")),
            C::colorize("&r&7The air shifts around you..."),
            10,
            35,
            10
        );
        PlayerUtils::playSound($player, "mob.endermen.portal", 1.0, 0.85);
        return true;
    }

    public static function allowNextBoundaryCrossing(Player $player, int $seconds = 3) : void
    {
        self::$boundaryBypasses[$player->getUniqueId()->toString()] = time() + max(1, $seconds);
    }

    public static function consumeBoundaryCrossingBypass(Player $player) : bool
    {
        $key = $player->getUniqueId()->toString();
        $expires = self::$boundaryBypasses[$key] ?? 0;
        unset(self::$boundaryBypasses[$key]);
        return $expires >= time();
    }

    public static function getRandomSafePosition(array $zone, int $edgePadding = 0) : ?Position
    {
        $world = self::getWorld($zone);
        if (!$world instanceof World) {
            return null;
        }

        for ($attempt = 0; $attempt < self::SAFE_POSITION_ATTEMPTS; $attempt++) {
            $candidate = self::pickRandomZoneXZ($zone, $edgePadding);
            if ($candidate === null) {
                break;
            }

            [$x, $z] = $candidate;
            if (!$world->isChunkLoaded($x >> 4, $z >> 4)) {
                $world->loadChunk($x >> 4, $z >> 4);
                if (!$world->isChunkLoaded($x >> 4, $z >> 4)) {
                    continue;
                }
            }

            $safe = self::resolveSafeSpawn($world, $zone, $x, $z, $edgePadding);
            if ($safe !== null) {
                return $safe;
            }
        }

        $fallback = self::getPosition($zone, true) ?? self::getPosition($zone);
        if ($fallback !== null && self::isValidSpawnPosition($fallback, $zone)) {
            return $fallback;
        }

        return null;
    }

    public static function getRandomSafePositionNear(Position $origin, array $zone, int $radius, int $edgePadding = 0): ?Position
    {
        $world = self::getWorld($zone);
        if (!$world instanceof World || strtolower($world->getFolderName()) !== strtolower($origin->getWorld()->getFolderName())) {
            return null;
        }

        $radius = max(1, $radius);
        for ($attempt = 0; $attempt < self::SAFE_POSITION_ATTEMPTS; $attempt++) {
            $x = mt_rand($origin->getFloorX() - $radius, $origin->getFloorX() + $radius);
            $z = mt_rand($origin->getFloorZ() - $radius, $origin->getFloorZ() + $radius);

            if (!$world->isChunkLoaded($x >> 4, $z >> 4)) {
                $world->loadChunk($x >> 4, $z >> 4);
                if (!$world->isChunkLoaded($x >> 4, $z >> 4)) {
                    continue;
                }
            }

            $safe = self::resolveSafeSpawn($world, $zone, $x, $z, $edgePadding);
            if ($safe !== null) {
                return $safe;
            }
        }

        return null;
    }

    private static function pickRandomZoneXZ(array $zone, int $edgePadding = 0) : ?array
    {
        $minX = $zone["min_x"] ?? null;
        $maxX = $zone["max_x"] ?? null;
        $minZ = $zone["min_z"] ?? null;
        $maxZ = $zone["max_z"] ?? null;

        if ($minX !== null && $maxX !== null && $minZ !== null && $maxZ !== null) {
            $lowX = (int) floor(min($minX, $maxX) + $edgePadding);
            $highX = (int) ceil(max($minX, $maxX) - $edgePadding);
            $lowZ = (int) floor(min($minZ, $maxZ) + $edgePadding);
            $highZ = (int) ceil(max($minZ, $maxZ) - $edgePadding);
            if ($lowX > $highX || $lowZ > $highZ) {
                $lowX = (int) floor(min($minX, $maxX));
                $highX = (int) ceil(max($minX, $maxX));
                $lowZ = (int) floor(min($minZ, $maxZ));
                $highZ = (int) ceil(max($minZ, $maxZ));
            }

            return [
                mt_rand($lowX, $highX),
                mt_rand($lowZ, $highZ)
            ];
        }

        $radius = (int) ($zone["radius"] ?? 0);
        if ($radius > 0) {
            $angle = mt_rand(0, 62831) / 10000;
            $maxDistance = max(1, $radius - $edgePadding);
            $distance = mt_rand(0, $maxDistance);
            return [
                (int) round((float) ($zone["x"] ?? 0) + (cos($angle) * $distance)),
                (int) round((float) ($zone["z"] ?? 0) + (sin($angle) * $distance))
            ];
        }

        if ((bool) ($zone["full_world"] ?? false)) {
            return [
                (int) round((float) ($zone["x"] ?? 0)),
                (int) round((float) ($zone["z"] ?? 0))
            ];
        }

        return null;
    }

    private static function resolveSafeSpawn(World $world, array $zone, int $x, int $z, int $edgePadding = 0) : ?Position
    {
        $surfaceY = self::resolveSurfaceY($world, $x, $z);
        if ($surfaceY === null) {
            return null;
        }

        $candidate = new Position($x + 0.5, $surfaceY, $z + 0.5, $world);
        if (self::isValidSpawnPosition($candidate, $zone) && self::isInsideSpawnInset($candidate, $zone, $edgePadding)) {
            return $candidate;
        }

        return self::findNearbySafePosition($candidate, $zone, self::NEARBY_SAFE_SEARCH_RADIUS, $edgePadding);
    }

    private static function resolveSurfaceY(World $world, int $x, int $z) : ?int
    {
        $topY = $world->getHighestBlockAt($x, $z);
        if ($topY <= 0) {
            return null;
        }

        $topBlock = $world->getBlockAt($x, $topY, $z);
        if (!self::isUnsafeTopBlock($topBlock)) {
            return $topY + 1;
        }

        for ($y = $topY; $y >= max(1, $topY - 12); --$y) {
            $ground = $world->getBlockAt($x, $y, $z);
            if (!$ground->isSolid() || self::isUnsafeGround($ground)) {
                continue;
            }

            $feet = $world->getBlockAt($x, $y + 1, $z);
            $head = $world->getBlockAt($x, $y + 2, $z);
            if (self::isPassableForSpawn($feet) && self::isPassableForSpawn($head)) {
                return $y + 1;
            }
        }

        return null;
    }

    private static function findNearbySafePosition(Position $origin, array $zone, int $radius, int $edgePadding = 0) : ?Position
    {
        $world = $origin->getWorld();
        $originX = $origin->getFloorX();
        $originZ = $origin->getFloorZ();

        for ($r = 1; $r <= $radius; $r++) {
            for ($dx = -$r; $dx <= $r; $dx++) {
                for ($dz = -$r; $dz <= $r; $dz++) {
                    if (max(abs($dx), abs($dz)) !== $r) {
                        continue;
                    }

                    $x = $originX + $dx;
                    $z = $originZ + $dz;
                    if (!$world->isChunkLoaded($x >> 4, $z >> 4)) {
                        $world->loadChunk($x >> 4, $z >> 4);
                        if (!$world->isChunkLoaded($x >> 4, $z >> 4)) {
                            continue;
                        }
                    }

                    $surfaceY = self::resolveSurfaceY($world, $x, $z);
                    if ($surfaceY === null) {
                        continue;
                    }

                    $candidate = new Position($x + 0.5, $surfaceY, $z + 0.5, $world);
                    if (self::isValidSpawnPosition($candidate, $zone) && self::isInsideSpawnInset($candidate, $zone, $edgePadding)) {
                        return $candidate;
                    }
                }
            }
        }

        return null;
    }

    private static function isValidSpawnPosition(Position $position, array $zone) : bool
    {
        if ($position->getFloorY() <= 1) {
            return false;
        }

        if (!self::isInsideZone($position, $zone)) {
            return false;
        }

        $world = $position->getWorld();
        $x = $position->getFloorX();
        $y = $position->getFloorY();
        $z = $position->getFloorZ();

        $ground = $world->getBlockAt($x, $y - 1, $z);
        $feet = $world->getBlockAt($x, $y, $z);
        $head = $world->getBlockAt($x, $y + 1, $z);

        return $ground->isSolid()
            && !self::isUnsafeGround($ground)
            && self::isPassableForSpawn($feet)
            && self::isPassableForSpawn($head);
    }

    public static function isInsideZone(Position $position, array $zone) : bool
    {
        if (strtolower($position->getWorld()->getFolderName()) !== strtolower((string) ($zone["world"] ?? ""))) {
            return false;
        }

        if ((bool) ($zone["full_world"] ?? false)) {
            return true;
        }

        $detected = self::getZoneAt($position);
        return $detected !== null && ($detected["id"] ?? null) === ($zone["id"] ?? null);
    }

    public static function hasUsableBoundary(array $zone) : bool
    {
        return ((bool) ($zone["full_world"] ?? false)) || (
            ($zone["min_x"] ?? null) !== null &&
            ($zone["max_x"] ?? null) !== null &&
            ($zone["min_z"] ?? null) !== null &&
            ($zone["max_z"] ?? null) !== null
        ) || (int) ($zone["radius"] ?? 0) > 0;
    }

    public static function hasRectangularBoundary(array $zone) : bool
    {
        return ($zone["min_x"] ?? null) !== null &&
            ($zone["max_x"] ?? null) !== null &&
            ($zone["min_z"] ?? null) !== null &&
            ($zone["max_z"] ?? null) !== null;
    }

    public static function isInsideSpawnInset(Position $position, array $zone, int $edgePadding) : bool
    {
        if ($edgePadding <= 0 || (bool) ($zone["full_world"] ?? false)) {
            return self::isInsideZone($position, $zone);
        }

        $minX = $zone["min_x"] ?? null;
        $maxX = $zone["max_x"] ?? null;
        $minZ = $zone["min_z"] ?? null;
        $maxZ = $zone["max_z"] ?? null;
        if ($minX !== null && $maxX !== null && $minZ !== null && $maxZ !== null) {
            $x = $position->getX();
            $z = $position->getZ();
            return $x >= min($minX, $maxX) + $edgePadding &&
                $x <= max($minX, $maxX) - $edgePadding &&
                $z >= min($minZ, $maxZ) + $edgePadding &&
                $z <= max($minZ, $maxZ) - $edgePadding;
        }

        $radius = (int) ($zone["radius"] ?? 0);
        if ($radius > 0) {
            $dx = $position->getX() - (float) ($zone["x"] ?? 0);
            $dz = $position->getZ() - (float) ($zone["z"] ?? 0);
            $innerRadius = max(1, $radius - $edgePadding);
            return ($dx * $dx) + ($dz * $dz) <= ($innerRadius * $innerRadius);
        }

        return self::isInsideZone($position, $zone);
    }

    /**
     * @return array{0: float, 1: float}
     */
    private static function getCenterXZ(array $zone) : array
    {
        $minX = $zone["min_x"] ?? null;
        $maxX = $zone["max_x"] ?? null;
        $minZ = $zone["min_z"] ?? null;
        $maxZ = $zone["max_z"] ?? null;
        if ($minX !== null && $maxX !== null && $minZ !== null && $maxZ !== null) {
            return [
                (min($minX, $maxX) + max($minX, $maxX)) / 2,
                (min($minZ, $maxZ) + max($minZ, $maxZ)) / 2
            ];
        }

        return [(float) ($zone["x"] ?? 0), (float) ($zone["z"] ?? 0)];
    }

    private static function isPassableForSpawn(Block $block) : bool
    {
        return !$block->isSolid() && !($block instanceof Liquid) && !self::isUnsafeBlockId($block->getTypeId());
    }

    private static function isUnsafeGround(Block $block) : bool
    {
        return $block instanceof Liquid || !$block->isSolid() || $block->isTransparent() || self::isUnsafeBlockId($block->getTypeId());
    }

    private static function isUnsafeTopBlock(Block $block) : bool
    {
        return $block instanceof Liquid || self::isUnsafeBlockId($block->getTypeId());
    }

    private static function isUnsafeBlockId(int $typeId) : bool
    {
        return in_array($typeId, self::UNSAFE_FOOTING, true);
    }
}
