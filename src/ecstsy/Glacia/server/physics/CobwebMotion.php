<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\physics;

use pocketmine\block\BlockTypeIds;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use function floor;

/**
 * Minecraft Java–style cobweb drag: horizontal velocity (x/z) × ¼ per tick, vertical (y) × 1/20 per tick.
 * PocketMine does not implement this for {@see \pocketmine\player\Player} (movement is client-led on Bedrock).
 *
 * Glacia uses one set of factors from {@see \ecstsy\Glacia\server\redstone\RedstoneManager} (sand-cobweb in redstone.yml, /cc)
 * so falling sand, primed TNT, and Glacia hostiles match the same tuning.
 */
final class CobwebMotion {
    /** Vanilla Java horizontal factor (per tick) for x and z while in cobweb. */
    public const JAVA_HORIZONTAL = 0.25;
    /** Vanilla Java vertical factor (per tick) for y while in cobweb. */
    public const JAVA_VERTICAL = 0.05;
    /** Vanilla Java–style terminal downward speed in blocks/tick (sink cap). */
    public const JAVA_MAX_FALL_PER_TICK = 0.00392;

    /**
     * After gravity/drag integration: apply cobweb multipliers and cap downward speed.
     *
     * @param bool $noUpward If true, positive vy is forced to 0 (falling sand settling above web).
     */
    public static function dampMotion(
        Vector3 $motion,
        float $horizontalFactor,
        float $verticalFactor,
        float $maxFallPerTick,
        bool $noUpward,
    ): Vector3 {
        $vx = $motion->x * $horizontalFactor;
        $vy = $motion->y * $verticalFactor;
        $vz = $motion->z * $horizontalFactor;
        if ($noUpward && $vy > 0.0) {
            $vy = 0.0;
        }
        if ($vy < -$maxFallPerTick) {
            $vy = -$maxFallPerTick;
        }

        return new Vector3($vx, $vy, $vz);
    }

    public static function boundingBoxIntersectsCobweb(World $world, AxisAlignedBB $box): bool {
        $inset = 0.0;
        $minX = (int) floor($box->minX + $inset);
        $minY = (int) floor($box->minY + $inset);
        $minZ = (int) floor($box->minZ + $inset);
        $maxX = (int) floor($box->maxX - $inset);
        $maxY = (int) floor($box->maxY - $inset);
        $maxZ = (int) floor($box->maxZ - $inset);

        if ($minX > $maxX) {
            $maxX = $minX;
        }
        if ($minY > $maxY) {
            $maxY = $minY;
        }
        if ($minZ > $maxZ) {
            $maxZ = $minZ;
        }

        for ($z = $minZ; $z <= $maxZ; ++$z) {
            for ($x = $minX; $x <= $maxX; ++$x) {
                for ($y = $minY; $y <= $maxY; ++$y) {
                    if (!$world->isInWorld($x, $y, $z)) {
                        continue;
                    }
                    if ($world->getBlockAt($x, $y, $z)->getTypeId() === BlockTypeIds::COBWEB) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
