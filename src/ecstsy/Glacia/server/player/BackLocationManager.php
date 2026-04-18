<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\player;

use pocketmine\player\Player;
use pocketmine\world\Position;

final class BackLocationManager {

    /** @var array<string, array{world: string, x: float, y: float, z: float, yaw: float, pitch: float}> */
    private static array $locations = [];

    public static function store(Player $player, Position $position, ?float $yaw = null, ?float $pitch = null) : void
    {
        self::$locations[$player->getUniqueId()->toString()] = [
            "world" => $position->getWorld()->getFolderName(),
            "x" => $position->getX(),
            "y" => $position->getY(),
            "z" => $position->getZ(),
            "yaw" => $yaw ?? $player->getLocation()->getYaw(),
            "pitch" => $pitch ?? $player->getLocation()->getPitch()
        ];
    }

    public static function get(Player $player) : ?array
    {
        return self::$locations[$player->getUniqueId()->toString()] ?? null;
    }
}
