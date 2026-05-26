<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\zones;

use ecstsy\Glacia\entity\FloatingTextEntity;
use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\Liquid;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Location;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\TextFormat as C;
use pocketmine\world\Position;
use pocketmine\world\World;

final class ZoneManager {

    private const BOUNDARY_WAND_TAG = "zone_boundary_wand";
    private const BOUNDARY_WAND_ZONE_TAG = "zone_boundary_zone";
    private const BOUNDARY_WAND_MODE_TAG = "zone_boundary_mode";
    private const BOUNDARY_WAND_SAFE_ID_TAG = "zone_boundary_safe_id";
    private const DEFAULT_ORE_RESPAWN_SECONDS = 30;

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
    /** @var array<string, int> */
    private static array $portalExitBypasses = [];
    /** @var array<string, FloatingTextEntity> */
    private static array $portalFloatingTexts = [];
    /** @var array<string, string> */
    private static array $boundaryEditSessions = [];
    /** @var array<string, bool> */
    private static array $pendingOreRespawns = [];

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
            $pos1Y = isset($zoneData["pos1-y"]) ? (float) $zoneData["pos1-y"] : null;
            $pos1Z = isset($zoneData["pos1-z"]) ? (float) $zoneData["pos1-z"] : null;
            $pos2X = isset($zoneData["pos2-x"]) ? (float) $zoneData["pos2-x"] : null;
            $pos2Y = isset($zoneData["pos2-y"]) ? (float) $zoneData["pos2-y"] : null;
            $pos2Z = isset($zoneData["pos2-z"]) ? (float) $zoneData["pos2-z"] : null;
            $pos1World = trim((string) ($zoneData["pos1-world"] ?? $world));
            $pos2World = trim((string) ($zoneData["pos2-world"] ?? $world));
            $minX = isset($zoneData["min-x"]) ? (float) $zoneData["min-x"] : null;
            $maxX = isset($zoneData["max-x"]) ? (float) $zoneData["max-x"] : null;
            $minY = isset($zoneData["min-y"]) ? (float) $zoneData["min-y"] : null;
            $maxY = isset($zoneData["max-y"]) ? (float) $zoneData["max-y"] : null;
            $minZ = isset($zoneData["min-z"]) ? (float) $zoneData["min-z"] : null;
            $maxZ = isset($zoneData["max-z"]) ? (float) $zoneData["max-z"] : null;
            if ($pos1X !== null && $pos1Z !== null && $pos2X !== null && $pos2Z !== null && strtolower($pos1World) === strtolower($pos2World)) {
                $world = $pos1World;
                $minX = min($pos1X, $pos2X);
                $maxX = max($pos1X, $pos2X);
                if ($pos1Y !== null && $pos2Y !== null) {
                    $minY = min($pos1Y, $pos2Y);
                    $maxY = max($pos1Y, $pos2Y);
                }
                $minZ = min($pos1Z, $pos2Z);
                $maxZ = max($pos1Z, $pos2Z);
            } elseif ($pos1X !== null && $pos1Z !== null && $pos2X !== null && $pos2Z !== null) {
                $minX = null;
                $maxX = null;
                $minY = null;
                $maxY = null;
                $minZ = null;
                $maxZ = null;
            }

            $spawn = is_array($zoneData["spawn"] ?? null) ? $zoneData["spawn"] : [];
            $oreRegeneration = is_array($zoneData["ore-regeneration"] ?? null) ? $zoneData["ore-regeneration"] : [];
            $safeAreas = [];
            foreach ((array) ($zoneData["safe-areas"] ?? []) as $safeId => $safeAreaData) {
                if (!is_string($safeId) || trim($safeId) === "" || !is_array($safeAreaData)) {
                    continue;
                }

                $safePos1 = is_array($safeAreaData["pos1"] ?? null) ? $safeAreaData["pos1"] : [];
                $safePos2 = is_array($safeAreaData["pos2"] ?? null) ? $safeAreaData["pos2"] : [];
                $safeWorld = trim((string) (($safeAreaData["world"] ?? $safePos1["world"] ?? $safePos2["world"] ?? $world)));
                if ($safeWorld === "") {
                    $safeWorld = $world;
                }

                if (
                    !isset($safePos1["x"], $safePos1["y"], $safePos1["z"], $safePos2["x"], $safePos2["y"], $safePos2["z"])
                ) {
                    continue;
                }

                $safeAreas[strtolower(trim($safeId))] = [
                    "id" => strtolower(trim($safeId)),
                    "world" => $safeWorld,
                    "min_x" => min((float) $safePos1["x"], (float) $safePos2["x"]),
                    "max_x" => max((float) $safePos1["x"], (float) $safePos2["x"]),
                    "min_y" => min((float) $safePos1["y"], (float) $safePos2["y"]),
                    "max_y" => max((float) $safePos1["y"], (float) $safePos2["y"]),
                    "min_z" => min((float) $safePos1["z"], (float) $safePos2["z"]),
                    "max_z" => max((float) $safePos1["z"], (float) $safePos2["z"]),
                ];
            }
            $oreBlocks = self::resolveOreBlockTypeIds((array) ($oreRegeneration["blocks"] ?? []));
            if ($oreBlocks === []) {
                $oreBlocks = self::getDefaultOreBlockTypeIds();
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
                "pos1_y" => $pos1Y,
                "pos1_z" => $pos1Z,
                "pos1_world" => $pos1World,
                "pos2_x" => $pos2X,
                "pos2_y" => $pos2Y,
                "pos2_z" => $pos2Z,
                "pos2_world" => $pos2World,
                "min_x" => $minX,
                "max_x" => $maxX,
                "min_y" => $minY,
                "max_y" => $maxY,
                "min_z" => $minZ,
                "max_z" => $maxZ,
                "full_world" => (bool) ($zoneData["full-world"] ?? false),
                "pvp_enabled" => (bool) ($zoneData["pvp-enabled"] ?? true),
                "allow_flight" => (bool) ($zoneData["allow-flight"] ?? false),
                "safe_areas" => $safeAreas,
                "spawn" => [
                    "world" => (string) ($spawn["world"] ?? $world),
                    "x" => isset($spawn["x"]) ? (float) $spawn["x"] : null,
                    "y" => isset($spawn["y"]) ? (float) $spawn["y"] : null,
                    "z" => isset($spawn["z"]) ? (float) $spawn["z"] : null,
                    "yaw" => (float) ($spawn["yaw"] ?? 0.0),
                    "pitch" => (float) ($spawn["pitch"] ?? 0.0),
                ],
                "ore_regeneration" => [
                    "enabled" => (bool) ($oreRegeneration["enabled"] ?? false),
                    "respawn_seconds" => max(1, (int) ($oreRegeneration["respawn-seconds"] ?? self::DEFAULT_ORE_RESPAWN_SECONDS)),
                    "replace_with_bedrock" => (bool) ($oreRegeneration["replace-with-bedrock"] ?? true),
                    "block_type_ids" => $oreBlocks,
                ],
                "exit" => self::normalizeExitConfig($world, $zoneData),
            ];
        }

        self::refreshPortalFloatingTexts();
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

    public static function isPvpEnabled(array $zone) : bool
    {
        return (bool) ($zone["pvp_enabled"] ?? true);
    }

    public static function isInsideSafeArea(Position $position, array $zone) : bool
    {
        foreach ((array) ($zone["safe_areas"] ?? []) as $safeArea) {
            if (!is_array($safeArea)) {
                continue;
            }
            if (strtolower($position->getWorld()->getFolderName()) !== strtolower((string) ($safeArea["world"] ?? ($zone["world"] ?? "")))) {
                continue;
            }

            $x = $position->getX();
            $y = $position->getY();
            $z = $position->getZ();
            if (
                $x >= (float) ($safeArea["min_x"] ?? 0.0) &&
                $x <= (float) ($safeArea["max_x"] ?? 0.0) &&
                $y >= (float) ($safeArea["min_y"] ?? World::Y_MIN) &&
                $y <= (float) ($safeArea["max_y"] ?? World::Y_MAX) &&
                $z >= (float) ($safeArea["min_z"] ?? 0.0) &&
                $z <= (float) ($safeArea["max_z"] ?? 0.0)
            ) {
                return true;
            }
        }

        return false;
    }

    public static function allowsFlight(array $zone) : bool
    {
        return (bool) ($zone["allow_flight"] ?? false);
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

    public static function getSpawnPosition(array $zone) : ?Location
    {
        $spawn = $zone["spawn"] ?? null;
        if (!is_array($spawn)) {
            return null;
        }

        $worldName = trim((string) ($spawn["world"] ?? ($zone["world"] ?? "")));
        if ($worldName === "" || !isset($spawn["x"], $spawn["y"], $spawn["z"])) {
            return null;
        }

        $world = Server::getInstance()->getWorldManager()->getWorldByName($worldName);
        if (!$world instanceof World && Server::getInstance()->getWorldManager()->loadWorld($worldName)) {
            $world = Server::getInstance()->getWorldManager()->getWorldByName($worldName);
        }
        if (!$world instanceof World) {
            return null;
        }

        return new Location(
            (float) $spawn["x"],
            (float) $spawn["y"],
            (float) $spawn["z"],
            $world,
            (float) ($spawn["yaw"] ?? 0.0),
            (float) ($spawn["pitch"] ?? 0.0)
        );
    }

    public static function getZoneAt(Position $position) : ?array
    {
        try {
            $worldName = strtolower($position->getWorld()->getFolderName());
        } catch (AssumptionFailedError) {
            return null;
        }

        foreach (self::$zones as $zone) {
            if (strtolower((string) $zone["world"]) !== $worldName) {
                continue;
            }

            if ((bool) ($zone["full_world"] ?? false)) {
                return $zone;
            }

            $x = $position->getX();
            $y = $position->getY();
            $z = $position->getZ();
            $minX = $zone["min_x"] ?? null;
            $maxX = $zone["max_x"] ?? null;
            $minY = $zone["min_y"] ?? null;
            $maxY = $zone["max_y"] ?? null;
            $minZ = $zone["min_z"] ?? null;
            $maxZ = $zone["max_z"] ?? null;

            if ($minX !== null && $maxX !== null && $minZ !== null && $maxZ !== null) {
                $insideY = $minY === null || $maxY === null || ($y >= min($minY, $maxY) && $y <= max($minY, $maxY));
                if ($insideY && $x >= min($minX, $maxX) && $x <= max($minX, $maxX) && $z >= min($minZ, $maxZ) && $z <= max($minZ, $maxZ)) {
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
        $otherPointExists = isset($zoneData[$otherPoint . "-x"], $zoneData[$otherPoint . "-y"], $zoneData[$otherPoint . "-z"]);

        if ($otherPointExists && $otherWorld !== "" && strtolower($otherWorld) !== strtolower($worldName)) {
            unset(
                $zoneData[$otherPoint . "-x"],
                $zoneData[$otherPoint . "-y"],
                $zoneData[$otherPoint . "-z"],
                $zoneData[$otherPoint . "-world"],
                $zoneData["min-x"],
                $zoneData["max-x"],
                $zoneData["min-y"],
                $zoneData["max-y"],
                $zoneData["min-z"],
                $zoneData["max-z"]
            );
            self::$lastBoundaryEditNote = "&r&eCleared {$otherPoint} because it was saved in &f{$otherWorld}&e. Set {$otherPoint} again in &f{$worldName}&e.";
        }

        $zoneData[$point . "-x"] = $position->getFloorX();
        $zoneData[$point . "-y"] = $position->getFloorY();
        $zoneData[$point . "-z"] = $position->getFloorZ();
        $zoneData[$point . "-world"] = $worldName;
        $zoneData["world"] = $worldName;
        $zoneData["x"] = $position->getFloorX();
        $zoneData["y"] = $position->getFloorY();
        $zoneData["z"] = $position->getFloorZ();
        $zoneData["radius"] = 0;
        $zoneData["full-world"] = false;

        if (isset($zoneData["pos1-x"], $zoneData["pos1-y"], $zoneData["pos1-z"], $zoneData["pos2-x"], $zoneData["pos2-y"], $zoneData["pos2-z"])) {
            $pos1World = trim((string) ($zoneData["pos1-world"] ?? $worldName));
            $pos2World = trim((string) ($zoneData["pos2-world"] ?? $worldName));
            if (strtolower($pos1World) === strtolower($pos2World)) {
                $zoneData["world"] = $pos1World;
                $zoneData["min-x"] = min((float) $zoneData["pos1-x"], (float) $zoneData["pos2-x"]);
                $zoneData["max-x"] = max((float) $zoneData["pos1-x"], (float) $zoneData["pos2-x"]);
                $zoneData["min-y"] = min((float) $zoneData["pos1-y"], (float) $zoneData["pos2-y"]);
                $zoneData["max-y"] = max((float) $zoneData["pos1-y"], (float) $zoneData["pos2-y"]);
                $zoneData["min-z"] = min((float) $zoneData["pos1-z"], (float) $zoneData["pos2-z"]);
                $zoneData["max-z"] = max((float) $zoneData["pos1-z"], (float) $zoneData["pos2-z"]);
                $zoneData["x"] = (float) (($zoneData["min-x"] + $zoneData["max-x"]) / 2);
                $zoneData["y"] = (float) (($zoneData["min-y"] + $zoneData["max-y"]) / 2);
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

    public static function resetBoundary(string $zoneId) : bool
    {
        self::$lastBoundaryEditNote = null;
        $zoneId = strtolower($zoneId);
        $zone = self::getZone($zoneId);
        if ($zone === null) {
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

        unset(
            $zoneData["pos1-x"],
            $zoneData["pos1-y"],
            $zoneData["pos1-z"],
            $zoneData["pos1-world"],
            $zoneData["pos2-x"],
            $zoneData["pos2-y"],
            $zoneData["pos2-z"],
            $zoneData["pos2-world"],
            $zoneData["min-x"],
            $zoneData["max-x"],
            $zoneData["min-y"],
            $zoneData["max-y"],
            $zoneData["min-z"],
            $zoneData["max-z"]
        );

        $zoneData["radius"] = 0;
        $zoneData["full-world"] = false;

        $zones[$zoneId] = $zoneData;
        $config->set("zones", $zones);
        $config->save();
        self::loadFromConfig();
        return true;
    }

    public static function teleportPlayer(Player $player, array $zone) : bool
    {
        $position = self::getSpawnPosition($zone)
            ?? self::getPosition($zone)
            ?? self::getPosition($zone, true)
            ?? self::getRandomSafePosition($zone, 14);
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

    public static function setSpawnPoint(string $zoneId, Position $position, float $yaw = 0.0, float $pitch = 0.0) : bool
    {
        $zoneId = strtolower($zoneId);
        if (self::getZone($zoneId) === null) {
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

        $zoneData["spawn"] = [
            "world" => $position->getWorld()->getFolderName(),
            "x" => round($position->getX(), 2),
            "y" => round($position->getY(), 2),
            "z" => round($position->getZ(), 2),
            "yaw" => round($yaw, 2),
            "pitch" => round($pitch, 2),
        ];

        $zones[$zoneId] = $zoneData;
        $config->set("zones", $zones);
        $config->save();
        self::loadFromConfig();
        return true;
    }

    public static function setSafeAreaPoint(string $zoneId, string $safeId, Position $position, string $point) : bool
    {
        $zoneId = strtolower(trim($zoneId));
        $safeId = strtolower(trim($safeId));
        if (self::getZone($zoneId) === null || $safeId === "" || !in_array($point, ["pos1", "pos2"], true)) {
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

        $safeAreas = (array) ($zoneData["safe-areas"] ?? []);
        $safeArea = (array) ($safeAreas[$safeId] ?? []);
        $safeArea[$point] = [
            "world" => $position->getWorld()->getFolderName(),
            "x" => $position->getFloorX(),
            "y" => $position->getFloorY(),
            "z" => $position->getFloorZ(),
        ];
        $safeArea["world"] = $position->getWorld()->getFolderName();
        $safeAreas[$safeId] = $safeArea;
        $zoneData["safe-areas"] = $safeAreas;
        $zones[$zoneId] = $zoneData;
        $config->set("zones", $zones);
        $config->save();
        self::loadFromConfig();
        return true;
    }

    public static function resetSafeArea(string $zoneId, string $safeId) : bool
    {
        $zoneId = strtolower(trim($zoneId));
        $safeId = strtolower(trim($safeId));
        if (self::getZone($zoneId) === null || $safeId === "") {
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

        $safeAreas = (array) ($zoneData["safe-areas"] ?? []);
        unset($safeAreas[$safeId]);
        $zoneData["safe-areas"] = $safeAreas;
        $zones[$zoneId] = $zoneData;
        $config->set("zones", $zones);
        $config->save();
        self::loadFromConfig();
        return true;
    }

    public static function beginBoundaryEdit(Player $player, string $zoneId) : void
    {
        self::$boundaryEditSessions[$player->getUniqueId()->toString()] = strtolower($zoneId);
    }

    public static function endBoundaryEdit(Player $player) : void
    {
        unset(self::$boundaryEditSessions[$player->getUniqueId()->toString()]);
    }

    public static function getBoundaryEditZone(Player $player) : ?string
    {
        return self::$boundaryEditSessions[$player->getUniqueId()->toString()] ?? null;
    }

    public static function createBoundaryWand(string $zoneId, string $display) : Item
    {
        $item = VanillaItems::GOLDEN_AXE();
        $item->setCustomName(C::colorize("&r&bZone Wand &8(&f{$display}&8)"));
        $item->setLore([
            C::colorize("&r&7Left click a block to set &fpos1"),
            C::colorize("&r&7Right click a block to set &fpos2"),
            C::colorize("&r&8Zone: &f{$zoneId}"),
        ]);

        $root = $item->getNamedTag();
        $glacia = $root->getCompoundTag("Glacia") ?? new CompoundTag();
        $glacia->setByte(self::BOUNDARY_WAND_TAG, 1);
        $glacia->setString(self::BOUNDARY_WAND_ZONE_TAG, strtolower($zoneId));
        $glacia->setString(self::BOUNDARY_WAND_MODE_TAG, "zone");
        $root->setTag("Glacia", $glacia);
        $item->setNamedTag($root);
        return $item;
    }

    public static function createSafeAreaWand(string $zoneId, string $safeId, string $display) : Item
    {
        $item = VanillaItems::GOLDEN_AXE();
        $item->setCustomName(C::colorize("&r&dSafe Zone Wand &8(&f{$display}&8)"));
        $item->setLore([
            C::colorize("&r&7Left click a block to set &fpos1"),
            C::colorize("&r&7Right click a block to set &fpos2"),
            C::colorize("&r&8Zone: &f{$zoneId}"),
            C::colorize("&r&8Safe ID: &f{$safeId}"),
        ]);

        $root = $item->getNamedTag();
        $glacia = $root->getCompoundTag("Glacia") ?? new CompoundTag();
        $glacia->setByte(self::BOUNDARY_WAND_TAG, 1);
        $glacia->setString(self::BOUNDARY_WAND_ZONE_TAG, strtolower($zoneId));
        $glacia->setString(self::BOUNDARY_WAND_MODE_TAG, "safe");
        $glacia->setString(self::BOUNDARY_WAND_SAFE_ID_TAG, strtolower($safeId));
        $root->setTag("Glacia", $glacia);
        $item->setNamedTag($root);
        return $item;
    }

    public static function isBoundaryWand(Item $item) : bool
    {
        return ($item->getNamedTag()->getCompoundTag("Glacia")?->getByte(self::BOUNDARY_WAND_TAG, 0) ?? 0) === 1;
    }

    public static function getBoundaryWandZoneId(Item $item) : ?string
    {
        $zoneId = trim((string) ($item->getNamedTag()->getCompoundTag("Glacia")?->getString(self::BOUNDARY_WAND_ZONE_TAG, "") ?? ""));
        return $zoneId !== "" ? strtolower($zoneId) : null;
    }

    public static function getBoundaryWandMode(Item $item) : string
    {
        return strtolower(trim((string) ($item->getNamedTag()->getCompoundTag("Glacia")?->getString(self::BOUNDARY_WAND_MODE_TAG, "zone") ?? "zone")));
    }

    public static function getBoundaryWandSafeId(Item $item) : ?string
    {
        $safeId = trim((string) ($item->getNamedTag()->getCompoundTag("Glacia")?->getString(self::BOUNDARY_WAND_SAFE_ID_TAG, "") ?? ""));
        return $safeId !== "" ? strtolower($safeId) : null;
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

    public static function allowNextPortalExit(Player $player, int $seconds = 10) : void
    {
        self::$portalExitBypasses[$player->getUniqueId()->toString()] = time() + max(1, $seconds);
    }

    public static function clearPortalExitBypass(Player $player) : void
    {
        unset(self::$portalExitBypasses[$player->getUniqueId()->toString()]);
    }

    public static function consumePortalExitBypass(Player $player) : bool
    {
        $key = $player->getUniqueId()->toString();
        $expires = self::$portalExitBypasses[$key] ?? 0;
        unset(self::$portalExitBypasses[$key]);
        return $expires >= time();
    }

    public static function hasPortalOnlyExit(array $zone) : bool
    {
        return strtolower((string) (($zone["exit"]["mode"] ?? ""))) === "portal_only";
    }

    public static function canBypassExitRestrictions(Player $player, array $zone) : bool
    {
        $permission = trim((string) (($zone["exit"]["bypass_permission"] ?? "glacia.staff")));
        return $permission !== "" && $player->hasPermission($permission);
    }

    public static function isInsideExitPortal(Position $position, array $zone) : bool
    {
        $portal = $zone["exit"]["portal"] ?? null;
        if (!is_array($portal) || !($portal["enabled"] ?? false)) {
            return false;
        }

        $worldName = strtolower((string) ($portal["world"] ?? ($zone["world"] ?? "")));
        if (strtolower($position->getWorld()->getFolderName()) !== $worldName) {
            return false;
        }

        $x = $position->getX();
        $y = $position->getY();
        $z = $position->getZ();

        return $x >= (float) ($portal["min_x"] ?? 0.0) &&
            $x <= (float) ($portal["max_x"] ?? 0.0) + 1.0 &&
            $y >= (float) ($portal["min_y"] ?? 0.0) &&
            $y <= (float) ($portal["max_y"] ?? 0.0) + 2.0 &&
            $z >= (float) ($portal["min_z"] ?? 0.0) &&
            $z <= (float) ($portal["max_z"] ?? 0.0) + 1.0;
    }

    public static function getExitPortalDelaySeconds(Player $player, array $zone) : int
    {
        $baseSeconds = max(1, (int) (($zone["exit"]["portal"]["teleport_seconds"] ?? 8)));
        $xp = $player->getXpManager()->getCurrentTotalXp();
        return max(1, $baseSeconds - (int) ($xp / 100));
    }

    public static function getExitDestination(array $zone) : ?Position
    {
        $destination = $zone["exit"]["destination"] ?? null;
        if (!is_array($destination)) {
            return null;
        }

        $worldManager = Server::getInstance()->getWorldManager();
        $useDefaultSpawn = (bool) ($destination["use_default_world_spawn"] ?? false);
        $worldName = trim((string) ($destination["world"] ?? ""));

        if ($useDefaultSpawn) {
            $world = $worldName !== "" ? $worldManager->getWorldByName($worldName) : $worldManager->getDefaultWorld();
            if (!$world instanceof World && $worldName !== "" && $worldManager->loadWorld($worldName)) {
                $world = $worldManager->getWorldByName($worldName);
            }
            if ($world instanceof World) {
                return $world->getSpawnLocation();
            }
            return null;
        }

        if ($worldName === "") {
            return null;
        }

        $world = $worldManager->getWorldByName($worldName);
        if (!$world instanceof World && $worldManager->loadWorld($worldName)) {
            $world = $worldManager->getWorldByName($worldName);
        }
        if (!$world instanceof World) {
            return null;
        }

        return new Position(
            (float) ($destination["x"] ?? 0.5),
            (float) ($destination["y"] ?? 64.0),
            (float) ($destination["z"] ?? 0.5),
            $world
        );
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

    public static function isOreRegenerationEnabled(array $zone) : bool
    {
        return (bool) (($zone["ore_regeneration"]["enabled"] ?? false) === true);
    }

    public static function shouldRegenerateOre(array $zone, Block $block) : bool
    {
        if (!self::isOreRegenerationEnabled($zone)) {
            return false;
        }

        $allowed = (array) ($zone["ore_regeneration"]["block_type_ids"] ?? []);
        return in_array($block->getTypeId(), $allowed, true);
    }

    public static function queueOreRespawn(Position $position, Block $originalBlock, array $zone) : void
    {
        $oreConfig = (array) ($zone["ore_regeneration"] ?? []);
        $respawnSeconds = max(1, (int) ($oreConfig["respawn_seconds"] ?? self::DEFAULT_ORE_RESPAWN_SECONDS));
        $replaceWithBedrock = (bool) ($oreConfig["replace_with_bedrock"] ?? true);
        $worldName = strtolower($position->getWorld()->getFolderName());
        $key = $worldName . ":" . $position->getFloorX() . ":" . $position->getFloorY() . ":" . $position->getFloorZ();
        if (isset(self::$pendingOreRespawns[$key])) {
            return;
        }

        self::$pendingOreRespawns[$key] = true;
        $stateId = $originalBlock->getStateId();
        $x = $position->getFloorX();
        $y = $position->getFloorY();
        $z = $position->getFloorZ();

        Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(
            static function() use ($worldName, $x, $y, $z, $replaceWithBedrock): void {
                $world = Server::getInstance()->getWorldManager()->getWorldByName($worldName);
                if (!$world instanceof World) {
                    return;
                }

                if ($replaceWithBedrock && $world->getBlockAt($x, $y, $z)->isSameState(VanillaBlocks::AIR())) {
                    $world->setBlockAt($x, $y, $z, VanillaBlocks::BEDROCK(), true);
                }
            }
        ), 1);

        Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(
            static function() use ($key, $worldName, $x, $y, $z, $stateId): void {
                unset(self::$pendingOreRespawns[$key]);

                $world = Server::getInstance()->getWorldManager()->getWorldByName($worldName);
                if (!$world instanceof World) {
                    return;
                }

                $current = $world->getBlockAt($x, $y, $z);
                if (!$current->isSameState(VanillaBlocks::BEDROCK()) && !$current->isSameState(VanillaBlocks::AIR())) {
                    return;
                }

                $world->setBlockAt($x, $y, $z, RuntimeBlockStateRegistry::getInstance()->fromStateId($stateId), true);
            }
        ), max(20, $respawnSeconds * 20));
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

    /**
     * @param array<string, mixed> $zoneData
     * @return array<string, mixed>
     */
    private static function normalizeExitConfig(string $world, array $zoneData) : array
    {
        $exit = is_array($zoneData["exit"] ?? null) ? $zoneData["exit"] : [];
        $portal = is_array($exit["portal"] ?? null) ? $exit["portal"] : [];
        $destination = is_array($exit["destination"] ?? null) ? $exit["destination"] : [];
        $hologram = is_array($portal["hologram"] ?? null) ? $portal["hologram"] : [];

        $pos1 = is_array($portal["pos1"] ?? null) ? $portal["pos1"] : [];
        $pos2 = is_array($portal["pos2"] ?? null) ? $portal["pos2"] : [];

        $x1 = (float) ($pos1["x"] ?? 0.0);
        $y1 = (float) ($pos1["y"] ?? 0.0);
        $z1 = (float) ($pos1["z"] ?? 0.0);
        $x2 = (float) ($pos2["x"] ?? $x1);
        $y2 = (float) ($pos2["y"] ?? $y1);
        $z2 = (float) ($pos2["z"] ?? $z1);

        return [
            "mode" => strtolower((string) ($exit["mode"] ?? "free")),
            "bypass_permission" => (string) ($exit["bypass-permission"] ?? "glacia.staff"),
            "portal" => [
                "enabled" => (bool) ($portal["enabled"] ?? false),
                "world" => (string) ($portal["world"] ?? $world),
                "min_x" => min($x1, $x2),
                "max_x" => max($x1, $x2),
                "min_y" => min($y1, $y2),
                "max_y" => max($y1, $y2),
                "min_z" => min($z1, $z2),
                "max_z" => max($z1, $z2),
                "teleport_seconds" => max(1, (int) ($portal["teleport-seconds"] ?? 8)),
                "hologram" => [
                    "enabled" => (bool) ($hologram["enabled"] ?? true),
                    "offset_y" => (float) ($hologram["offset-y"] ?? 2.8),
                    "lines" => array_values(array_map("strval", (array) ($hologram["lines"] ?? [
                        "&r&d&lASTRAL VEIL EXIT",
                        "&7Stand inside to return to spawn",
                        "&fYour XP reduces the channel time",
                    ]))),
                ],
            ],
            "destination" => [
                "use_default_world_spawn" => (bool) ($destination["use-default-world-spawn"] ?? true),
                "world" => (string) ($destination["world"] ?? ""),
                "x" => (float) ($destination["x"] ?? 0.5),
                "y" => (float) ($destination["y"] ?? 64.0),
                "z" => (float) ($destination["z"] ?? 0.5),
            ],
        ];
    }

    private static function refreshPortalFloatingTexts() : void
    {
        foreach (self::$portalFloatingTexts as $entity) {
            if (!$entity->isClosed()) {
                $entity->flagForDespawn();
            }
        }
        self::$portalFloatingTexts = [];

        foreach (self::$zones as $zoneId => $zone) {
            $portal = $zone["exit"]["portal"] ?? null;
            $hologram = is_array($portal) ? ($portal["hologram"] ?? null) : null;
            if (!is_array($portal) || !($portal["enabled"] ?? false) || !is_array($hologram) || !($hologram["enabled"] ?? false)) {
                continue;
            }

            $world = Server::getInstance()->getWorldManager()->getWorldByName((string) ($portal["world"] ?? ""));
            if (!$world instanceof World && Server::getInstance()->getWorldManager()->loadWorld((string) ($portal["world"] ?? ""))) {
                $world = Server::getInstance()->getWorldManager()->getWorldByName((string) ($portal["world"] ?? ""));
            }
            if (!$world instanceof World) {
                continue;
            }

            $x = (((float) ($portal["min_x"] ?? 0.0)) + ((float) ($portal["max_x"] ?? 0.0)) + 1.0) / 2;
            $y = max((float) ($portal["max_y"] ?? 0.0), (float) ($zone["y"] ?? 64.0)) + (float) ($hologram["offset_y"] ?? 2.8);
            $z = (((float) ($portal["min_z"] ?? 0.0)) + ((float) ($portal["max_z"] ?? 0.0)) + 1.0) / 2;

            $entity = new FloatingTextEntity(new Location($x, $y, $z, $world, 0.0, 0.0));
            $entity->setText(implode("\n", (array) ($hologram["lines"] ?? [])));
            $entity->spawnToAll();
            self::$portalFloatingTexts[$zoneId] = $entity;
        }
    }

    /**
     * @return int[]
     */
    private static function getDefaultOreBlockTypeIds() : array
    {
        return [
            BlockTypeIds::COAL_ORE,
            BlockTypeIds::DEEPSLATE_COAL_ORE,
            BlockTypeIds::IRON_ORE,
            BlockTypeIds::DEEPSLATE_IRON_ORE,
            BlockTypeIds::COPPER_ORE,
            BlockTypeIds::DEEPSLATE_COPPER_ORE,
            BlockTypeIds::GOLD_ORE,
            BlockTypeIds::DEEPSLATE_GOLD_ORE,
            BlockTypeIds::REDSTONE_ORE,
            BlockTypeIds::DEEPSLATE_REDSTONE_ORE,
            BlockTypeIds::LAPIS_LAZULI_ORE,
            BlockTypeIds::DEEPSLATE_LAPIS_LAZULI_ORE,
            BlockTypeIds::DIAMOND_ORE,
            BlockTypeIds::DEEPSLATE_DIAMOND_ORE,
            BlockTypeIds::EMERALD_ORE,
            BlockTypeIds::DEEPSLATE_EMERALD_ORE,
            BlockTypeIds::NETHER_QUARTZ_ORE,
            BlockTypeIds::NETHER_GOLD_ORE,
            BlockTypeIds::ANCIENT_DEBRIS,
        ];
    }

    /**
     * @param array<int, mixed> $configured
     * @return int[]
     */
    private static function resolveOreBlockTypeIds(array $configured) : array
    {
        $resolved = [];
        $map = [
            "coal_ore" => BlockTypeIds::COAL_ORE,
            "deepslate_coal_ore" => BlockTypeIds::DEEPSLATE_COAL_ORE,
            "iron_ore" => BlockTypeIds::IRON_ORE,
            "deepslate_iron_ore" => BlockTypeIds::DEEPSLATE_IRON_ORE,
            "copper_ore" => BlockTypeIds::COPPER_ORE,
            "deepslate_copper_ore" => BlockTypeIds::DEEPSLATE_COPPER_ORE,
            "gold_ore" => BlockTypeIds::GOLD_ORE,
            "deepslate_gold_ore" => BlockTypeIds::DEEPSLATE_GOLD_ORE,
            "redstone_ore" => BlockTypeIds::REDSTONE_ORE,
            "deepslate_redstone_ore" => BlockTypeIds::DEEPSLATE_REDSTONE_ORE,
            "lapis_ore" => BlockTypeIds::LAPIS_LAZULI_ORE,
            "lapis_lazuli_ore" => BlockTypeIds::LAPIS_LAZULI_ORE,
            "deepslate_lapis_ore" => BlockTypeIds::DEEPSLATE_LAPIS_LAZULI_ORE,
            "deepslate_lapis_lazuli_ore" => BlockTypeIds::DEEPSLATE_LAPIS_LAZULI_ORE,
            "diamond_ore" => BlockTypeIds::DIAMOND_ORE,
            "deepslate_diamond_ore" => BlockTypeIds::DEEPSLATE_DIAMOND_ORE,
            "emerald_ore" => BlockTypeIds::EMERALD_ORE,
            "deepslate_emerald_ore" => BlockTypeIds::DEEPSLATE_EMERALD_ORE,
            "nether_quartz_ore" => BlockTypeIds::NETHER_QUARTZ_ORE,
            "nether_gold_ore" => BlockTypeIds::NETHER_GOLD_ORE,
            "ancient_debris" => BlockTypeIds::ANCIENT_DEBRIS,
        ];

        foreach ($configured as $value) {
            $key = strtolower(trim((string) $value));
            if ($key !== "" && isset($map[$key])) {
                $resolved[] = $map[$key];
            }
        }

        return array_values(array_unique($resolved));
    }
}
