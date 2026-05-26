<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\cannon;

use pocketmine\block\Block;
use pocketmine\block\BrewingStand;
use pocketmine\block\Carpet;
use pocketmine\block\DaylightSensor;
use pocketmine\block\FenceGate;
use pocketmine\block\MobHead;
use pocketmine\block\MonsterSpawner;
use pocketmine\world\Explosion;
use pocketmine\world\Position;
use pocketmine\world\World;

/**
 * Thin / fragile blocks that many raid metas still break underwater when blast block-break is otherwise suppressed.
 * Fence gates (not full fence lengths); skulls include player heads.
 */
final class WaterVulnerableExplosionBypass {

    public static function isWaterVulnerableBlock(Block $block): bool {
        return $block instanceof FenceGate
            || $block instanceof BrewingStand
            || $block instanceof Carpet
            || $block instanceof DaylightSensor
            || $block instanceof MonsterSpawner
            || $block instanceof MobHead;
    }

    /** Append vulnerable blocks inside the spherical blast footprint when explodeA did not populate the list. */
    public static function mergeSphereIntoExplosion(Explosion $explosion, Position $center, float $radius): void {
        if (!CannonRulesConfig::enabled() || !CannonRulesConfig::waterVulnerableBypassEnabled()) {
            return;
        }

        $world = $center->getWorld();
        $r = max(1.0e-6, $radius);
        $ri = (int) ceil($r);
        $cx = (int) floor($center->x);
        $cy = (int) floor($center->y);
        $cz = (int) floor($center->z);

        for ($x = -$ri; $x <= $ri; ++$x) {
            for ($y = -$ri; $y <= $ri; ++$y) {
                for ($z = -$ri; $z <= $ri; ++$z) {
                    $vx = $cx + $x;
                    $vy = $cy + $y;
                    $vz = $cz + $z;

                    $dx = $vx + 0.5 - $center->x;
                    $dy = $vy + 0.5 - $center->y;
                    $dz = $vz + 0.5 - $center->z;
                    if (($dx ** 2) + ($dy ** 2) + ($dz ** 2) > $r * $r) {
                        continue;
                    }

                    if (!$world->isInWorld($vx, $vy, $vz)) {
                        continue;
                    }

                    $_block = $world->getBlockAt($vx, $vy, $vz, true, false);
                    if (!self::isWaterVulnerableBlock($_block)) {
                        continue;
                    }

                    foreach ($_block->getAffectedBlocks() as $affected) {
                        $p = $affected->getPosition();
                        $h = World::blockHash((int) $p->getX(), (int) $p->getY(), (int) $p->getZ());
                        $explosion->affectedBlocks[$h] = $affected;
                    }
                }
            }
        }
    }
}
