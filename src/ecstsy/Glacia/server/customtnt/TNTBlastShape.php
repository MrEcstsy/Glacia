<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\customtnt;

use pocketmine\block\Block;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use pocketmine\world\Explosion;
use pocketmine\world\Position;
use pocketmine\world\World;
use function deg2rad;
use function floor;
use function hypot;
use function round;
use function sin;
use function sqrt;

/**
 * Replaces vanilla spherical blast rays with axis-oriented cuboid / stepped volumes (photo reference).
 * Obsidian durability is unchanged — CustomTNTListener + ObsidianExplosionConfig still run per EntityExplodeEvent pulse.
 */
final class TNTBlastShape {

    private function __construct() {
    }

    /**
     * @phpstan-param array<int, Block>
     */
    public static function collectIntoExplosion(Explosion $explosion, Position $center, Entity $entity, string $glaciaTntType): void {
        $raw = self::collectAffectedBlocks($center, $entity, $glaciaTntType);
        /** @phpstan-var array<int, Block> $filtered */
        $filtered = [];
        foreach ($raw as $hash => $block) {
            $p = $block->getPosition();
            if (self::explosionRayDestroysBlock($center, $p->getFloorX(), $p->getFloorY(), $p->getFloorZ(), $explosion->radius)) {
                $filtered[$hash] = $block;
            }
        }
        $explosion->affectedBlocks = $filtered;
    }

    /**
     * @return array<int, Block>
     * @phpstan-return array<int, Block>
     */
    public static function collectAffectedBlocks(Position $center, Entity $entity, string $glaciaTntType): array {
        $world = $center->getWorld();
        $base = new Vector3(
            (int) floor($center->x),
            (int) floor($center->y),
            (int) floor($center->z)
        );

        $forward = self::snapHorizontalForward($entity);
        // Right-hand walk on XZ: perpendicular to horizontal forward (yaw / motion).
        $right = new Vector3(-$forward->z, 0, $forward->x);
        $up = new Vector3(0, 1, 0);

        $shape = self::shapeKindFor($glaciaTntType);

        /** @var array<int, Block> $out */
        $out = [];

        foreach (self::offsetsForShape($shape) as [$df, $dr, $du]) {
            $ox = (int) round($forward->x * $df + $right->x * $dr + $up->x * $du);
            $oy = (int) round($forward->y * $df + $right->y * $dr + $up->y * $du);
            $oz = (int) round($forward->z * $df + $right->z * $dr + $up->z * $du);

            $wx = $base->getFloorX() + $ox;
            $wy = $base->getFloorY() + $oy;
            $wz = $base->getFloorZ() + $oz;

            if (!$world->isInWorld($wx, $wy, $wz)) {
                continue;
            }

            $block = $world->getBlockAt($wx, $wy, $wz, true, false);
            if (!$block->getBreakInfo()->isBreakable()) {
                continue;
            }
            foreach ($block->getAffectedBlocks() as $b) {
                $p = $b->getPosition();
                $out[World::blockHash($p->x, $p->y, $p->z)] = $b;
            }
        }

        return $out;
    }

    /**
     * PocketMine {@see Explosion::explodeA()} ray physics: fluids/non-breakables still absorb blast along the path.
     * Without this, shaped blasts ignore water and break blocks on the far side of a pool.
     */
    private static function explosionRayDestroysBlock(Position $source, int $tx, int $ty, int $tz, float $radius): bool {
        $world = $source->getWorld();
        $registry = RuntimeBlockStateRegistry::getInstance();
        $stepLen = 0.3;

        $dx = ($tx + 0.5) - $source->x;
        $dy = ($ty + 0.5) - $source->y;
        $dz = ($tz + 0.5) - $source->z;
        $len = sqrt($dx * $dx + $dy * $dy + $dz * $dz);
        if ($len < 1e-9) {
            $b = $world->getBlockAt($tx, $ty, $tz, true, false);

            return $b->getBreakInfo()->isBreakable();
        }

        $shiftX = ($dx / $len) * $stepLen;
        $shiftY = ($dy / $len) * $stepLen;
        $shiftZ = ($dz / $len) * $stepLen;

        $pointerX = $source->x;
        $pointerY = $source->y;
        $pointerZ = $source->z;

        // Average ray strength (Explosion uses mt_rand(700,1300)/1000 per ray).
        $blastForce = $radius * 1.0;

        for ($guard = 0; $guard < 12000 && $blastForce > 0; $guard++, $blastForce -= $stepLen * 0.75) {
            $x = (int) $pointerX;
            $y = (int) $pointerY;
            $z = (int) $pointerZ;
            $vBlockX = $pointerX >= $x ? $x : $x - 1;
            $vBlockY = $pointerY >= $y ? $y : $y - 1;
            $vBlockZ = $pointerZ >= $z ? $z : $z - 1;

            $pointerX += $shiftX;
            $pointerY += $shiftY;
            $pointerZ += $shiftZ;

            if (!$world->isInWorld($vBlockX, $vBlockY, $vBlockZ)) {
                continue;
            }

            $state = $world->getBlockAt($vBlockX, $vBlockY, $vBlockZ, true, false)->getStateId();
            $blastResistance = $registry->blastResistance[$state] ?? 0;
            if ($blastResistance >= 0) {
                $blastForce -= ($blastResistance / 5 + 0.3) * $stepLen;
                if ($blastForce > 0 && $vBlockX === $tx && $vBlockY === $ty && $vBlockZ === $tz) {
                    $target = $world->getBlockAt($tx, $ty, $tz, true, false);

                    return $target->getBreakInfo()->isBreakable();
                }
            }
        }

        return false;
    }

    private static function shapeKindFor(string $glaciaTntType): string {
        return match ($glaciaTntType) {
            CustomTNTIds::LETHAL => "lethal",
            CustomTNTIds::GIGANTIC => "giga",
            default => "normal",
        };
    }

    /** Axis-sculpted cuboid blasts (photo-style). Everything else should use {@see \pocketmine\world\Explosion::explodeA()} vanilla rays. */
    public static function usesAxisShapedBlast(string $glaciaTntType): bool {
        return $glaciaTntType === CustomTNTIds::LETHAL || $glaciaTntType === CustomTNTIds::GIGANTIC;
    }

    /**
     * @return iterable<int, array{int, int, int}>
     */
    private static function offsetsForShape(string $shape): iterable {
        return match ($shape) {
            "lethal" => self::rectangularFaceDepth(4, 4, 3),
            "giga" => self::gigaStepped(),
            default => self::rectangularFaceDepth(3, 3, 2),
        };
    }

    /**
     * Face is dr × du (centered on the blast origin); depth df steps along forward [0 .. depth-1].
     *
     * @return iterable<int, array{int, int, int}>
     */
    private static function rectangularFaceDepth(int $width, int $height, int $depth): iterable {
        $drStart = -((int) floor($width / 2));
        $drEnd = (int) floor(($width - 1) / 2);
        $duStart = -((int) floor($height / 2));
        $duEnd = (int) floor(($height - 1) / 2);

        for ($df = 0; $df < $depth; $df++) {
            for ($dr = $drStart; $dr <= $drEnd; $dr++) {
                for ($du = $duStart; $du <= $duEnd; $du++) {
                    yield [$df, $dr, $du];
                }
            }
        }
    }

    /**
     * Stepped crater: full 7×7 for first two depth slices, 5×5 for next two, 3×3 at the back (photo-style).
     *
     * @return iterable<int, array{int, int, int}>
     */
    private static function gigaStepped(): iterable {
        for ($df = 0; $df <= 4; $df++) {
            $extent = match ($df) {
                0, 1 => 3,
                2, 3 => 2,
                default => 1,
            };
            for ($dr = -$extent; $dr <= $extent; $dr++) {
                for ($du = -$extent; $du <= $extent; $du++) {
                    yield [$df, $dr, $du];
                }
            }
        }
    }

    private static function snapHorizontalForward(Entity $entity): Vector3 {
        $mot = $entity->getMotion();
        $hLen = hypot($mot->x, $mot->z);
        if ($hLen > 0.05) {
            return self::snapCardinalHorizontal($mot->x / $hLen, $mot->z / $hLen);
        }

        $yaw = $entity->getLocation()->getYaw();
        $rad = deg2rad($yaw);
        $vx = -sin($rad);
        $vz = cos($rad);

        return self::snapCardinalHorizontal($vx, $vz);
    }

    private static function snapCardinalHorizontal(float $x, float $z): Vector3 {
        $len = hypot($x, $z);
        if ($len < 1e-6) {
            return new Vector3(0, 0, 1);
        }

        $nx = $x / $len;
        $nz = $z / $len;

        $bestDot = -INF;
        $best = new Vector3(0, 0, 1);
        foreach ([[1.0, 0.0], [-1.0, 0.0], [0.0, 1.0], [0.0, -1.0]] as [$cx, $cz]) {
            $d = $nx * $cx + $nz * $cz;
            if ($d > $bestDot) {
                $bestDot = $d;
                $best = new Vector3($cx, 0, $cz);
            }
        }

        return $best;
    }
}
