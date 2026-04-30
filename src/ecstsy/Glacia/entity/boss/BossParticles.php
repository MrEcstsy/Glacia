<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\boss;

use pocketmine\block\VanillaBlocks;
use pocketmine\math\Vector3;
use pocketmine\world\particle\BlockBreakParticle;
use pocketmine\world\particle\HugeExplodeParticle;
use pocketmine\world\particle\Particle;
use pocketmine\world\particle\PortalParticle;
use pocketmine\world\World;

/**
 * Helper for visualising boss ability footprints with vanilla particles.
 *
 * Centralises the "draw a ring", "draw a beam" and "puff a burst" patterns
 * so individual ability classes don't have to redo the trigonometry every
 * time they want to telegraph an AoE.
 */
final class BossParticles
{
    private function __construct() {}

    /**
     * Render a horizontal ring of particles at the given centre. Useful for
     * marking the radius of upcoming ground AoEs.
     */
    public static function ring(
        World $world,
        Vector3 $center,
        float $radius,
        Particle $particle,
        int $count = 24,
        float $yOffset = 0.1
    ): void {
        if ($count <= 0 || $radius <= 0.0) {
            return;
        }
        $step = (2.0 * M_PI) / $count;
        for ($i = 0; $i < $count; $i++) {
            $angle = $i * $step;
            $world->addParticle(
                new Vector3(
                    $center->x + (cos($angle) * $radius),
                    $center->y + $yOffset,
                    $center->z + (sin($angle) * $radius)
                ),
                $particle
            );
        }
    }

    /**
     * Render a straight line of particles from $from to $to. Density is the
     * number of particle samples; pick higher numbers for thicker beams.
     */
    public static function line(
        World $world,
        Vector3 $from,
        Vector3 $to,
        Particle $particle,
        int $density = 24
    ): void {
        if ($density <= 0) {
            return;
        }
        $dx = ($to->x - $from->x) / $density;
        $dy = ($to->y - $from->y) / $density;
        $dz = ($to->z - $from->z) / $density;
        for ($i = 0; $i <= $density; $i++) {
            $world->addParticle(
                new Vector3(
                    $from->x + ($dx * $i),
                    $from->y + ($dy * $i),
                    $from->z + ($dz * $i)
                ),
                $particle
            );
        }
    }

    /**
     * Spray a random cloud of particles around $center within $horizontalSpread
     * blocks horizontally and $verticalSpread blocks vertically.
     */
    public static function burst(
        World $world,
        Vector3 $center,
        Particle $particle,
        int $count = 30,
        float $horizontalSpread = 1.0,
        float $verticalSpread = 1.5
    ): void {
        if ($count <= 0) {
            return;
        }
        for ($i = 0; $i < $count; $i++) {
            $ox = ((mt_rand(-100, 100) / 100.0) * $horizontalSpread);
            $oy = ((mt_rand(0, 200) / 200.0) * $verticalSpread);
            $oz = ((mt_rand(-100, 100) / 100.0) * $horizontalSpread);
            $world->addParticle(
                new Vector3($center->x + $ox, $center->y + $oy, $center->z + $oz),
                $particle
            );
        }
    }

    public static function portalParticle(): Particle
    {
        return new PortalParticle();
    }

    public static function warningParticle(): Particle
    {
        return new BlockBreakParticle(VanillaBlocks::REDSTONE());
    }

    public static function explosionParticle(): Particle
    {
        return new HugeExplodeParticle();
    }
}
