<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\customtnt;

use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use pocketmine\world\Explosion;
use pocketmine\world\Position;

final class CustomTNTPattern {

    public static function explodeExtra(string $type, Entity $source, float $radius = 4.0): void {
        $offsets = match ($type) {
            "tnt_gigantic" => self::getGiganticOffsets(),
            "tnt_lethal" => self::getLethalOffsets(),
            "tnt_lucky" => [],
            'tnt_tactical' => [],
            default => []
        };

        foreach ($offsets as $offset) {
            $pos = $source->getPosition()->add($offset->x, $offset->y, $offset->z);

            $explosion = new Explosion(new Position($pos->getX(), $pos->getY(), $pos->getZ(), $source->getWorld()), $radius, $source);
            // No block list: obsidian wear still uses the same offset positions (CustomTNTListener sphere scan); physical break is the primary cuboid only.
            $explosion->explodeB();
        }
    }

    private static function getGiganticOffsets(): array {
        return [
            new Vector3(0, 3, 0),
            new Vector3(0, -3, 0),
            new Vector3(3, 0, 3),
            new Vector3(-3, 0, -3),
        ];
    }

    /** Tight ring so each extra blast has its own particles/sound while obsidian near the core still catches all five pulses. */
    private static function getLethalOffsets(): array {
        return [
            new Vector3(1.0, 0.0, 0.0),
            new Vector3(-1.0, 0.0, 0.0),
            new Vector3(0.0, 1.0, 0.0),
            new Vector3(0.0, 0.0, 1.0),
        ];
    }
}