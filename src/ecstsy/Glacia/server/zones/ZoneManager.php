<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\zones;

use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\world\Position;

final class ZoneManager {

    /** @var array<string, array<string, mixed>> */
    private static array $zones = [];
    private static string $netherShortcutZone = "nether";

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

    public static function getPosition(array $zone) : ?Position
    {
        $world = Server::getInstance()->getWorldManager()->getWorldByName((string) $zone["world"]);
        if ($world === null) {
            return null;
        }

        return new Position(
            (float) ($zone["x"] ?? 0),
            (float) ($zone["y"] ?? 100),
            (float) ($zone["z"] ?? 0),
            $world
        );
    }

    public static function teleportPlayer(Player $player, array $zone) : bool
    {
        $position = self::getPosition($zone);
        if ($position === null) {
            return false;
        }

        $player->teleport($position);
        return true;
    }
}
