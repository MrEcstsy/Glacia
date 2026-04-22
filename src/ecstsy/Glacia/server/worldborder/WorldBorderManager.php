<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\worldborder;

use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\player\Player;
use pocketmine\world\Position;
use pocketmine\world\World;
use pocketmine\Server;

final class WorldBorderManager {

    private static bool $enabled = true;
    private static int $radius = 24000;
    private static int $messageCooldown = 2;
    private static string $worldName = "";

    public static function loadFromConfig() : void
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "misc.yml");
        self::$enabled = (bool) ($config?->get("world-border-enabled", true) ?? true);
        $configuredRadius = $config?->get("world-border-radius");
        if ($configuredRadius !== null) {
            self::$radius = max(16, (int) $configuredRadius);
        } else {
            $legacySize = max(16, (int) ($config?->get("world-border-size", 24000) ?? 24000));
            self::$radius = (int) floor($legacySize / 2);
        }

        self::$radius -= self::$radius % 16;
        if (self::$radius <= 0) {
            self::$radius = 16;
        }

        self::$messageCooldown = max(0, (int) ($config?->get("world-border-message-cooldown", 2) ?? 2));
        self::$worldName = trim((string) ($config?->get("world-border-world", "") ?? ""));
    }

    public static function isEnabled() : bool
    {
        return self::$enabled;
    }

    public static function getRadius() : int
    {
        return self::$radius;
    }

    public static function getSize() : int
    {
        return self::$radius * 2;
    }

    public static function getMinX() : int
    {
        return -self::$radius;
    }

    public static function getMaxX() : int
    {
        return self::$radius + 15;
    }

    public static function getMinZ() : int
    {
        return self::getMinX();
    }

    public static function getMaxZ() : int
    {
        return self::getMaxX();
    }

    public static function getMessageCooldown() : int
    {
        return self::$messageCooldown;
    }

    public static function appliesToWorld(World $world) : bool
    {
        $target = self::$worldName;
        if ($target === "") {
            $defaultWorld = Server::getInstance()->getWorldManager()->getDefaultWorld();
            return $defaultWorld !== null && $defaultWorld->getFolderName() === $world->getFolderName();
        }

        return strcasecmp($world->getFolderName(), $target) === 0;
    }

    public static function isInside(Position $position) : bool
    {
        if (!self::$enabled) {
            return true;
        }

        if (!$position->isValid()) {
            return true;
        }

        $world = $position->getWorld();
        if (!self::appliesToWorld($world)) {
            return true;
        }

        $x = $position->getFloorX(); 
        $z = $position->getFloorZ();
        return $x >= self::getMinX() && $x <= self::getMaxX() && $z >= self::getMinZ() && $z <= self::getMaxZ();
    }

    public static function getDisplayText() : string
    {
        return "World Border: " . number_format(self::$radius) . " radius";
    }

    public static function getChunkAlignedSummary() : string
    {
        return self::getMinX() . " to " . self::getMaxX();
    }

    public static function isPlayerOutside(Player $player) : bool
    {
        return !self::isInside($player->getPosition());
    }
}
