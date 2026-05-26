<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\spawn;

use pocketmine\entity\Location;
use pocketmine\player\Player;
use pocketmine\world\World;

final class InteractiveSpawnPlacement
{
    /** Extra lift above the block surface for end crystals. */
    private const CRYSTAL_ABOVE_SURFACE = 0.75;

    public static function atPlayerFeet(Player $player, bool $isCrystal, float $yOffset = 0.0): Location
    {
        $loc = $player->getLocation();
        $world = $player->getWorld();
        $x = $loc->x;
        $z = $loc->z;
        $blockX = (int) floor($x);
        $blockZ = (int) floor($z);

        $surfaceY = self::findSurfaceY($world, $blockX, $blockZ, $loc->y);
        $spawnY = $isCrystal
            ? $surfaceY + self::CRYSTAL_ABOVE_SURFACE
            : max($loc->y, $surfaceY);
        $spawnY += $yOffset;

        return new Location($x, $spawnY, $z, $world, $loc->yaw, 0.0);
    }

    /**
     * Y coordinate of the top face of the solid block under the player.
     */
    private static function findSurfaceY(World $world, int $blockX, int $blockZ, float $feetY): float
    {
        return (float) (self::findSupportBlockY($world, $blockX, $blockZ, $feetY) + 1);
    }

    private static function findSupportBlockY(World $world, int $blockX, int $blockZ, float $feetY): int
    {
        $minY = $world->getMinY();
        $startY = (int) floor($feetY - 0.2);
        for ($y = $startY; $y >= $minY; $y--) {
            if ($world->getBlockAt($blockX, $y, $blockZ)->isSolid()) {
                return $y;
            }
        }

        return max($minY, $startY - 1);
    }
}
