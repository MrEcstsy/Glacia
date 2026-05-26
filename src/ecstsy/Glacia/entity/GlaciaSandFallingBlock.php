<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\cannon\CannonSandSupport;
use ecstsy\Glacia\server\physics\CobwebMotion;
use ecstsy\Glacia\server\redstone\RedstoneManager;
use pocketmine\block\Air;
use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\Flowable;
use pocketmine\block\Sand;
use pocketmine\block\utils\Fallable;
use pocketmine\entity\Entity;
use pocketmine\entity\object\FallingBlock;
use pocketmine\event\entity\EntityBlockChangeEvent;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\world\World;
use pocketmine\world\sound\BlockBreakSound;
use ReflectionMethod;
use function abs;
use function ceil;
use function floor;
use function max;
use function min;
use function sqrt;

/**
 * Sand-only falling block with stacking / cannon-style flight tuning.
 * Swapped in by {@see \ecstsy\Glacia\listeners\SandFallingEntityListener}.
 */
class GlaciaSandFallingBlock extends FallingBlock {
    private const TAG_STACKED_COUNT = "glacia_sand_stacked_count";
    private const BLOCK_DATA_ROOT = "glacia_sand_stack";
    private const BLOCK_DATA_COUNT = "count";
    private const IMPACT_COMPRESS_MAX_RISE = 24;
    private const MAX_STACKED_COUNT = 4096;
    private const TNT_PROJECTILE_EXIT_SEARCH_DISTANCE = 16.0;
    private const TNT_PROJECTILE_STEP = 0.5;
    private const IMPACT_CLIMB_MAX_RISE = 8;
    /** Max blocks/tick of motion included in cobweb sweep (prevents absurd values from breaking collision). */
    private const COBWEB_MOTION_SWEEP_MAX = 48.0;
    private const COBWEB_CHECK_PADDING = 0.08;
    /** Frozen cobweb sand despawns if TNT has not touched it for this long. */
    private const COBWEB_IDLE_DESPAWN_TICKS = 200; // ~10s (cannon setup), false “above web” freeze removed
    /** After TNT impulse, ignore cobweb freeze briefly so it can be fired out. */
    private const COBWEB_TNT_BYPASS_TICKS = 8;

    /** Same bases as {@see GlaciaPrimedTNT} / {@see FallingBlock}: cannon multipliers match launched TNT flight. */
    private const BASE_GRAVITY = 0.04;
    private const BASE_DRAG = 0.02;

    /** Horizontal speed² above this ⇒ blast- or piston-launched; use cannon gravity/drag like cannon-mode TNT. */
    private const CANNON_LIKE_HORIZONTAL_SPEED_SQ = 0.0144;

    /** Upward motion above this (e.g. explosion lift) also triggers cannon-like physics. */
    private const CANNON_LIKE_UPWARD_MOTION = 0.15;

    private static ?ReflectionMethod $entityBaseTickEntity = null;
    private int $stackedCount = 1;
    private int $cobwebFreezeSinceTick = -1;
    private int $lastTntImpulseTick = -1;
    private int $cobwebFreezeBypassUntilTick = -1;

    protected function initEntity(CompoundTag $nbt): void {
        parent::initEntity($nbt);
        $this->stackedCount = min(self::MAX_STACKED_COUNT, max(1, $nbt->getInt(self::TAG_STACKED_COUNT, 1)));
    }

    public function saveNBT(): CompoundTag {
        $nbt = parent::saveNBT();
        $nbt->setInt(self::TAG_STACKED_COUNT, min(self::MAX_STACKED_COUNT, max(1, $this->stackedCount)));
        return $nbt;
    }

    public function setStackedCount(int $count): void {
        $this->stackedCount = min(self::MAX_STACKED_COUNT, max(1, $count));
    }

    public function getStackedCount(): int {
        return $this->stackedCount;
    }

    public function markHitByTnt(): void {
        $tick = Loader::getInstance()->getServer()->getTick();
        $this->lastTntImpulseTick = $tick;
        $this->cobwebFreezeBypassUntilTick = max($this->cobwebFreezeBypassUntilTick, $tick + self::COBWEB_TNT_BYPASS_TICKS);
        $this->cobwebFreezeSinceTick = -1;
        $this->onGround = false;
    }

    public function onUpdate(int $currentTick): bool {
        $inCobweb = $this->intersectsCobwebVolume();
        // Cobweb should dominate movement; do not apply cannon-like gravity/drag while inside.
        $this->applyCannonLikePhysicsProfile(!$inCobweb && $this->shouldUseCannonLikePhysics());
        return parent::onUpdate($currentTick);
    }

    public function hasMovementUpdate(): bool {
        return parent::hasMovementUpdate() || $this->intersectsCobwebVolume();
    }

    protected function tryChangeMovement(): void {
        parent::tryChangeMovement();
        $tick = Loader::getInstance()->getServer()->getTick();
        if (!$this->shouldFreezeInCobweb($tick)) {
            return;
        }

        // Park in-web sand as an entity (not a placed block) until TNT wakes it up.
        $this->motion = new Vector3(0.0, 0.0, 0.0);
    }

    protected function entityBaseTick(int $tickDiff = 1): bool {
        if (self::$entityBaseTickEntity === null) {
            $m = new ReflectionMethod(Entity::class, "entityBaseTick");
            $m->setAccessible(true);
            self::$entityBaseTickEntity = $m;
        }

        if ($this->closed) {
            return false;
        }

        /** @var bool $hasUpdate */
        $hasUpdate = self::$entityBaseTickEntity->invoke($this, $tickDiff);
        $tick = Loader::getInstance()->getServer()->getTick();

        if (!$this->isFlaggedForDespawn()) {
            $inCobweb = $this->intersectsCobwebVolume();
            $freezeInCobweb = $inCobweb && $this->shouldFreezeInCobweb($tick);
            // Do not merge while interacting with cobweb; merging into another entity causes inconsistent web behavior.
            if (!$inCobweb && $this->mergeFallingSandInColumn()) {
                return true;
            }

            $world = $this->getWorld();
            $pos = $this->location->add(-$this->size->getWidth() / 2, $this->size->getHeight(), -$this->size->getWidth() / 2)->floor();

            $this->block->position($world, $pos->x, $pos->y, $pos->z);

            $blockTarget = null;
            if ($this->block instanceof Fallable) {
                $blockTarget = $this->block->tickFalling();
            }

            // Park as an entity while frozen in cobweb; despawn stale parked sand if TNT does not touch it.
            if ($freezeInCobweb) {
                if ($this->cobwebFreezeSinceTick < 0) {
                    $this->cobwebFreezeSinceTick = $tick;
                }
                $despawnAnchor = max($this->cobwebFreezeSinceTick, $this->lastTntImpulseTick);
                if ($tick - $despawnAnchor >= self::COBWEB_IDLE_DESPAWN_TICKS) {
                    $this->flagForDespawn();
                    return true;
                }
                $this->onGround = false;
                return true;
            }
            $this->cobwebFreezeSinceTick = -1;

            if ($this->onGround || $blockTarget !== null) {
                $this->flagForDespawn();

                $blockResult = $blockTarget ?? $this->block;
                $block = $world->getBlock($pos);
                // Extra safeguard: if landing target is still cobweb, keep falling instead of dropping/placing.
                if ($block->getTypeId() === BlockTypeIds::COBWEB) {
                    $this->onGround = false;
                    return true;
                }
                $breakOnFragileLanding = $this->shouldBreakStackOnLanding($pos, $block);
                if (
                    $breakOnFragileLanding
                    || (
                    !$block->canBeReplaced()
                    || !$world->isInWorld($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ())
                    || ($this->onGround && abs($this->location->y - $this->location->getFloorY()) > 0.001)
                    )
                ) {
                    if ($breakOnFragileLanding || !$this->compressIntoColumnOnImpact($pos, $blockResult)) {
                        $this->dropStackedItems();
                        $world->addSound($pos->add(0.5, 0.5, 0.5), new BlockBreakSound($blockResult));
                    }
                } else {
                    $ev = new EntityBlockChangeEvent($this, $block, $blockResult);
                    $ev->call();
                    if (!$ev->isCancelled()) {
                        $b = $ev->getTo();
                        $this->placeStackedColumn($pos, $b);
                        if ($this->onGround && $b instanceof Fallable && ($sound = $b->getLandSound()) !== null) {
                            $world->addSound($pos->add(0.5, 0.5, 0.5), $sound);
                        }
                    }
                }
                $hasUpdate = true;
            }
        }

        return $hasUpdate;
    }

    private function shouldFreezeInCobweb(int $tick): bool {
        return $this->intersectsCobwebVolume() && $tick > $this->cobwebFreezeBypassUntilTick;
    }

    private function shouldBreakStackOnLanding(Vector3 $pos, Block $target): bool {
        if ($target instanceof Flowable && !$target instanceof Air) {
            return true;
        }

        if (!$this->onGround || abs($this->location->y - $this->location->getFloorY()) <= 0.001) {
            return false;
        }

        $support = $this->getWorld()->getBlockAt($pos->getFloorX(), $pos->getFloorY() - 1, $pos->getFloorZ());
        if ($support instanceof Sand || $support->isFullCube()) {
            return false;
        }
        if (CannonSandSupport::allowsSandStackedAbove($support)) {
            return false;
        }

        return true;
    }

    /**
     * Match {@see GlaciaPrimedTNT} cannon-mode gravity/drag while sand is moving like launched TNT.
     */
    private function applyCannonLikePhysicsProfile(bool $cannonLike): void {
        if (!$cannonLike) {
            $this->gravity = self::BASE_GRAVITY;
            $this->drag = self::BASE_DRAG;
            return;
        }

        $manager = RedstoneManager::getInstance();
        $this->gravity = self::BASE_GRAVITY * $manager->getCannonTntGravityMultiplier();
        $this->drag = self::BASE_DRAG * (GlaciaPrimedTNT::isEntityTouchingWater($this)
            ? $manager->getCannonTntWaterDragMultiplier()
            : 1.0);
    }

    private function shouldUseCannonLikePhysics(): bool {
        $m = $this->getMotion();
        $hx2 = $m->x * $m->x + $m->z * $m->z;
        if ($hx2 > self::CANNON_LIKE_HORIZONTAL_SPEED_SQ) {
            return true;
        }
        // Upward-only boosts (e.g. piston shove straight up) must use normal gravity — cannon multipliers leave sand hovering.
        return $m->y > self::CANNON_LIKE_UPWARD_MOTION && $hx2 > 1.0e-4;
    }

    private function cobwebSweepBox(): AxisAlignedBB {
        $box = $this->getBoundingBox()->expandedCopy(self::COBWEB_CHECK_PADDING, self::COBWEB_CHECK_PADDING, self::COBWEB_CHECK_PADDING);
        $motion = $this->motion;
        $lim = self::COBWEB_MOTION_SWEEP_MAX;

        if ($motion->x > 1.0e-6) {
            $box->maxX += min($lim, $motion->x);
        } elseif ($motion->x < -1.0e-6) {
            $box->minX += max(-$lim, $motion->x);
        }

        if ($motion->y > 1.0e-6) {
            $box->maxY += min($lim, $motion->y);
        } elseif ($motion->y < -1.0e-6) {
            $box->minY += max(-$lim, $motion->y);
        }

        if ($motion->z > 1.0e-6) {
            $box->maxZ += min($lim, $motion->z);
        } elseif ($motion->z < -1.0e-6) {
            $box->minZ += max(-$lim, $motion->z);
        }

        if ($this->gravityEnabled && $motion->y < -1.0e-6) {
            $box->minY -= $this->gravity;
        }

        return $box;
    }

    /**
     * Sample this tick’s motion segment (center of AABB → center + motion). Catches single-thick cobwebs the
     * axis-expanded sweep can miss on steep diagonals; complementary to {@see cobwebSweepBox()}.
     */
    private function motionPathIntersectsCobweb(): bool {
        $dx = $this->motion->x;
        $dy = $this->motion->y;
        $dz = $this->motion->z;
        $lenSq = $dx * $dx + $dy * $dy + $dz * $dz;
        if ($lenSq < 1.0e-10) {
            return false;
        }

        $bb = $this->getBoundingBox();
        $cx = ($bb->minX + $bb->maxX) * 0.5;
        $cy = ($bb->minY + $bb->maxY) * 0.5;
        $cz = ($bb->minZ + $bb->maxZ) * 0.5;
        $world = $this->getWorld();
        $len = sqrt($lenSq);
        $segments = min(128, max(4, (int) ceil($len / 0.125)));

        for ($i = 0; $i <= $segments; ++$i) {
            $t = $i / $segments;
            $ix = (int) floor($cx + $dx * $t);
            $iy = (int) floor($cy + $dy * $t);
            $iz = (int) floor($cz + $dz * $t);
            if (!$world->isInWorld($ix, $iy, $iz)) {
                continue;
            }
            if ($world->getBlockAt($ix, $iy, $iz)->getTypeId() === BlockTypeIds::COBWEB) {
                return true;
            }
        }

        return false;
    }

    private function intersectsCobwebVolume(): bool {
        if (CobwebMotion::boundingBoxIntersectsCobweb($this->getWorld(), $this->cobwebSweepBox())) {
            return true;
        }
        // Direct overlap check catches float-coordinate edge cases where swept voxel rounding misses the current block.
        if ($this->isInsideCobwebNow()) {
            return true;
        }
        return $this->motionPathIntersectsCobweb();
    }

    private function isInsideCobwebNow(): bool {
        foreach ($this->getBlocksIntersected(0.02) as $block) {
            if ($block->getTypeId() === BlockTypeIds::COBWEB) {
                return true;
            }
        }
        return false;
    }


    private function dropStackedItems(?int $count = null): void {
        $remaining = max(1, $count ?? $this->stackedCount);
        $template = $this->block->asItem();
        $maxStack = max(1, $template->getMaxStackSize());
        while ($remaining > 0) {
            $piece = clone $template;
            $piece->setCount(min($maxStack, $remaining));
            $this->getWorld()->dropItem($this->location, $piece);
            $remaining -= $piece->getCount();
        }
    }

    private function mergeFallingSandInColumn(): bool {
        $box = $this->getBoundingBox()->expandedCopy(0.2, 5.0, 0.2);
        $floorX = $this->location->getFloorX();
        $floorZ = $this->location->getFloorZ();
        $keeper = $this;
        $keeperY = $this->location->y;

        foreach ($this->getWorld()->getNearbyEntities($box, $this) as $entity) {
            if (!$this->canMergeFallingSandEntity($entity, $floorX, $floorZ)) {
                continue;
            }
            // Keep the lowest (leading) entity, so the stack respects cobweb/ground at the front edge.
            if ($entity->location->y < $keeperY) {
                $keeper = $entity;
                $keeperY = $entity->location->y;
            }
        }

        if ($keeper !== $this) {
            /** @var self $keeper */
            $keeper->setStackedCount(min(self::MAX_STACKED_COUNT, $keeper->getStackedCount() + $this->stackedCount));
            $this->flagForDespawn();
            return true;
        }

        foreach ($this->getWorld()->getNearbyEntities($box, $this) as $entity) {
            if (!$this->canMergeFallingSandEntity($entity, $floorX, $floorZ)) {
                continue;
            }
            /** @var self $entity */
            $this->stackedCount = min(self::MAX_STACKED_COUNT, $this->stackedCount + $entity->getStackedCount());
            $entity->flagForDespawn();
        }

        return false;
    }

    private function canMergeFallingSandEntity(Entity $entity, int $floorX, int $floorZ): bool {
        return $entity instanceof self
            && !$entity->isFlaggedForDespawn()
            && $entity->location->getFloorX() === $floorX
            && $entity->location->getFloorZ() === $floorZ
            && $entity->block->getStateId() === $this->block->getStateId();
    }

    /**
     * If impact is blocked (wall/solid), keep cannon compression by merging this stack into the nearest
     * sand column above impact instead of dropping broken pieces.
     */
    private function compressIntoColumnOnImpact(Vector3 $impactPos, Block $blockResult): bool {
        $world = $this->getWorld();
        $x = $impactPos->getFloorX();
        $y = $impactPos->getFloorY();
        $z = $impactPos->getFloorZ();

        for ($yy = $y; $yy <= $y + self::IMPACT_COMPRESS_MAX_RISE; ++$yy) {
            if (!$world->isInWorld($x, $yy, $z)) {
                break;
            }

            $at = $world->getBlockAt($x, $yy, $z);
            if ($at instanceof Sand || $at->canBeReplaced()) {
                $this->placeStackedColumn(new Vector3($x, $yy, $z), $blockResult);
                return true;
            }
            if (CannonSandSupport::allowsSandStackedAbove($at)) {
                continue;
            }
        }

        return false;
    }

    private function placeStackedColumn(Vector3 $basePos, Block $blockResult): void {
        $world = $this->getWorld();
        $x = $basePos->getFloorX();
        $z = $basePos->getFloorZ();
        $remaining = min(self::MAX_STACKED_COUNT, max(1, $this->stackedCount));
        $lastStackPos = null;
        $blockedY = null;

        $remaining = $this->placeStackedColumnAt(
            $world,
            $x,
            $basePos->getFloorY(),
            $z,
            $remaining,
            $blockResult,
            $blockedY,
            $lastStackPos
        );

        if ($remaining > 0 && $blockedY !== null) {
            foreach ($this->getClimbCandidateBases($x, $blockedY, $z) as $candidate) {
                [$cx, $cy, $cz] = $candidate;
                $candidateBlockedY = null;
                $remaining = $this->placeStackedColumnAt(
                    $world,
                    $cx,
                    $cy,
                    $cz,
                    $remaining,
                    $blockResult,
                    $candidateBlockedY,
                    $lastStackPos
                );

                if ($remaining <= 0) {
                    break;
                }
            }
        }

        if ($remaining > 0 && $lastStackPos !== null) {
            [$lastX, $lastY, $lastZ] = $lastStackPos;
            self::saveStackedCountAt(
                $world,
                $lastX,
                $lastY,
                $lastZ,
                min(self::MAX_STACKED_COUNT, self::readStackedCountAt($world, $lastX, $lastY, $lastZ) + $remaining)
            );
        }
    }

    /**
     * Places upward until a real solid blocker is reached. Do not skip solids; that is what made
     * stacked sand look like it phased through obsidian/cobble cannon lips.
     *
     * @param array{0:int, 1:int, 2:int}|null $lastStackPos
     */
    private function placeStackedColumnAt(
        World $world,
        int $x,
        int $baseY,
        int $z,
        int $remaining,
        Block $blockResult,
        ?int &$blockedY,
        ?array &$lastStackPos
    ): int {
        $blockedY = null;
        $maxY = $baseY + self::IMPACT_COMPRESS_MAX_RISE + $remaining;

        for ($yy = $baseY; $remaining > 0 && $yy <= $maxY && $world->isInWorld($x, $yy, $z); ++$yy) {
            $at = $world->getBlockAt($x, $yy, $z);
            if ($at instanceof Sand) {
                $lastStackPos = [$x, $yy, $z];
                continue;
            }
            if (!$at->canBeReplaced()) {
                if (CannonSandSupport::allowsSandStackedAbove($at)) {
                    continue;
                }
                $blockedY = $yy;
                break;
            }

            $world->setBlockAt($x, $yy, $z, clone $blockResult);
            $lastStackPos = [$x, $yy, $z];
            --$remaining;
        }

        return $remaining;
    }

    /**
     * @return list<array{0:int, 1:int, 2:int}>
     */
    private function getClimbCandidateBases(int $blockedX, int $blockedY, int $blockedZ): array {
        $offsets = $this->getMotionBiasedOffsets();
        $candidates = [];

        foreach ($offsets as [$dx, $dz]) {
            $x = $blockedX + $dx;
            $z = $blockedZ + $dz;
            $topY = $this->findObstacleTopY($x, $blockedY, $z);
            if ($topY !== null) {
                $candidates[] = [$x, $topY, $z];
            }
        }

        return $candidates;
    }

    /**
     * @return list<array{0:int, 1:int}>
     */
    private function getMotionBiasedOffsets(): array {
        $motion = $this->getMotion();
        $dx = 0;
        $dz = 0;

        if (abs($motion->x) >= abs($motion->z) && abs($motion->x) > 1.0e-4) {
            $dx = $motion->x > 0.0 ? 1 : -1;
        } elseif (abs($motion->z) > 1.0e-4) {
            $dz = $motion->z > 0.0 ? 1 : -1;
        }

        if ($dx !== 0) {
            return [[$dx, 0], [0, 0], [$dx, 1], [$dx, -1], [0, 1], [0, -1], [-$dx, 0]];
        }
        if ($dz !== 0) {
            return [[0, $dz], [0, 0], [1, $dz], [-1, $dz], [1, 0], [-1, 0], [0, -$dz]];
        }

        return [[0, 0], [1, 0], [-1, 0], [0, 1], [0, -1]];
    }

    private function findObstacleTopY(int $x, int $startY, int $z): ?int {
        $world = $this->getWorld();
        $sawObstacle = false;

        for ($yy = $startY; $yy <= $startY + self::IMPACT_CLIMB_MAX_RISE; ++$yy) {
            if (!$world->isInWorld($x, $yy, $z)) {
                return null;
            }

            $at = $world->getBlockAt($x, $yy, $z);
            if ($at instanceof Sand) {
                return $yy;
            }
            if ($at->canBeReplaced()) {
                return $sawObstacle ? $yy : null;
            }

            $sawObstacle = true;
        }

        return null;
    }

    private static function readStackedCountAt(World $world, int $x, int $y, int $z): int {
        $data = Loader::getBlockData()->getAt($world, $x, $y, $z);
        if (!is_array($data)) {
            return 1;
        }

        $root = $data[self::BLOCK_DATA_ROOT] ?? null;
        if (!is_array($root)) {
            return 1;
        }

        return min(self::MAX_STACKED_COUNT, max(1, (int) ($root[self::BLOCK_DATA_COUNT] ?? 1)));
    }

    private static function saveStackedCountAt(World $world, int $x, int $y, int $z, int $count): void {
        $count = min(self::MAX_STACKED_COUNT, max(1, $count));
        $data = Loader::getBlockData()->getAt($world, $x, $y, $z);
        $payload = is_array($data) ? $data : [];
        $payload[self::BLOCK_DATA_ROOT] = [
            self::BLOCK_DATA_COUNT => $count
        ];
        Loader::getBlockData()->setAt($world, $x, $y, $z, $payload);
    }
}
