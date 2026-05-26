<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\customtnt\CustomTNTIds;
use ecstsy\Glacia\server\customtnt\CustomTNTPattern;
use ecstsy\Glacia\server\customtnt\LuckyTNTHelper;
use ecstsy\Glacia\server\customtnt\TNTBlastShape;
use ecstsy\Glacia\server\physics\CobwebMotion;
use ecstsy\Glacia\server\redstone\RedstoneManager;
use ecstsy\Glacia\server\customtnt\TacticalTNTHelper;
use ecstsy\Glacia\server\cannon\CannonRulesConfig;
use ecstsy\Glacia\server\cannon\WaterVulnerableExplosionBypass;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\Water;
use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\entity\object\PrimedTNT;
use pocketmine\event\entity\EntityPreExplodeEvent;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\world\Explosion;
use pocketmine\world\Position;
use function abs;
use function floor;
use function max;
use function min;
use function sqrt;

final class GlaciaPrimedTNT extends PrimedTNT {

    public const TAG_GLACIA_TNT_TYPE = "glacia_tnt_type";
    public const TAG_GLACIA_CANNON_MODE = "glacia_cannon_mode";
    public const TAG_GLACIA_DISPENSER_LAUNCHED = "glacia_dispenser_launched";
    public const TAG_GLACIA_STACKED_COUNT = "glacia_tnt_stacked_count";
    public const TAG_GLACIA_RAID_SOURCE_FACTION = "glacia_raid_source_faction";

    private const BASE_DRAG = 0.02;
    private const BASE_GRAVITY = 0.04;
    /** Upper bound on entities processed per explode-propulsion pass (prevents O(n^2)-style blowups). */
    private const MAX_PROPULSION_TARGETS_PER_EXPLOSION = 256;
    private const MAX_STACKED_COUNT = 256;
    private const TNT_MERGE_SCAN_EXPAND = 0.25;
    private const TNT_MERGE_MAX_DETONATION_TICK_DIFF = 4;
    private const TNT_MERGE_MIN_MOTION_DOT = 0.96;
    private const TNT_MERGE_MAX_POSITION_DELTA_SQ = 0.85 * 0.85;

    private string $tntType = "";
    private bool $cannonMode = false;
    /** Fired from a Glacia dispenser — enables optional gravity×(1+v) tuning from config. */
    private bool $dispenserLaunched = false;
    private int $explodeAtTick = 0;
    private int $stackedCount = 1;
    private ?string $raidSourceFactionId = null;

    public function __construct(Location $location, ?CompoundTag $nbt = null) {
        parent::__construct($location, $nbt);

        if ($nbt !== null) {
            $this->tntType = $nbt->getString(self::TAG_GLACIA_TNT_TYPE, "");
            $this->stackedCount = min(self::MAX_STACKED_COUNT, max(1, $nbt->getInt(self::TAG_GLACIA_STACKED_COUNT, 1)));
            $source = trim($nbt->getString(self::TAG_GLACIA_RAID_SOURCE_FACTION, ""));
            $this->raidSourceFactionId = $source !== "" ? $source : null;
        }
    }

    protected function initEntity(CompoundTag $nbt): void {
        parent::initEntity($nbt);
        $this->tntType = $nbt->getString(self::TAG_GLACIA_TNT_TYPE, "");
        $this->cannonMode = $nbt->getByte(self::TAG_GLACIA_CANNON_MODE, 0) === 1;
        $this->dispenserLaunched = $nbt->getByte(self::TAG_GLACIA_DISPENSER_LAUNCHED, 0) === 1;
        $this->stackedCount = min(self::MAX_STACKED_COUNT, max(1, $nbt->getInt(self::TAG_GLACIA_STACKED_COUNT, 1)));
        $source = trim($nbt->getString(self::TAG_GLACIA_RAID_SOURCE_FACTION, ""));
        $this->raidSourceFactionId = $source !== "" ? $source : null;
    }

    public function saveNBT(): CompoundTag {
        $nbt = parent::saveNBT();
        $nbt->setString(self::TAG_GLACIA_TNT_TYPE, $this->tntType);
        $nbt->setByte(self::TAG_GLACIA_CANNON_MODE, $this->cannonMode ? 1 : 0);
        $nbt->setByte(self::TAG_GLACIA_DISPENSER_LAUNCHED, $this->dispenserLaunched ? 1 : 0);
        $nbt->setInt(self::TAG_GLACIA_STACKED_COUNT, min(self::MAX_STACKED_COUNT, max(1, $this->stackedCount)));
        if ($this->raidSourceFactionId !== null) {
            $nbt->setString(self::TAG_GLACIA_RAID_SOURCE_FACTION, $this->raidSourceFactionId);
        }
        return $nbt;
    }

    public function setTNTType(string $type): void {
        $this->tntType = $type;
    }

    public function getTNTType(): string {
        return $this->tntType;
    }

    public function setCannonMode(bool $cannonMode): void {
        $this->cannonMode = $cannonMode;
    }

    public function isCannonMode(): bool {
        return $this->cannonMode;
    }

    public function setDispenserLaunched(bool $dispenserLaunched): void {
        $this->dispenserLaunched = $dispenserLaunched;
    }

    public function isDispenserLaunched(): bool {
        return $this->dispenserLaunched;
    }

    public function setStackedCount(int $count): void {
        $this->stackedCount = min(self::MAX_STACKED_COUNT, max(1, $count));
    }

    public function getStackedCount(): int {
        return $this->stackedCount;
    }

    public function setRaidSourceFactionId(?string $factionId): void {
        $factionId = $factionId !== null ? trim($factionId) : "";
        $this->raidSourceFactionId = $factionId !== "" ? $factionId : null;
    }

    public function getRaidSourceFactionId(): ?string {
        return $this->raidSourceFactionId;
    }

    public function setExplodeAtTick(int $tick): void {
        $this->explodeAtTick = max(0, $tick);
    }

    /**
     * PocketMine adds liquid flow nudges in {@see Entity::checkBlockIntersections()} when this returns true.
     * Glacia optionally disables that for dispenser shots ({@see RedstoneManager::isPrimedTntLiquidCurrentsEnabled})
     * so streams do not bend axis-aligned cannon shots.
     */
    public function canBeMovedByCurrents(): bool {
        if (
            $this->dispenserLaunched
            && !RedstoneManager::getInstance()->isPrimedTntLiquidCurrentsEnabled()
        ) {
            return false;
        }

        return parent::canBeMovedByCurrents();
    }

    protected function tryChangeMovement(): void {
        parent::tryChangeMovement();
        if ($this->cannonMode) {
            return;
        }
        $this->applyCobwebInteraction();
    }

    protected function entityBaseTick(int $tickDiff = 1): bool {
        if ($this->explodeAtTick > 0) {
            $remaining = $this->explodeAtTick - Loader::getInstance()->getServer()->getTick();
            $this->setFuse(max(0, $remaining));
        }

        $manager = RedstoneManager::getInstance();
        $motion = $this->getMotion();
        $motionCap = $manager->getPrimedTntEffectiveMotionCap();
        if (($motion->x ** 2) + ($motion->y ** 2) + ($motion->z ** 2) > ($motionCap ** 2)) {
            $motion = self::clampMotion($motion, $motionCap);
            $this->setMotion($motion);
        }

        $this->mergeNearbyStackableTnt();

        if ($this->cannonMode) {
            $this->drag = self::BASE_DRAG * ($this->isTouchingWater() ? $manager->getCannonTntWaterDragMultiplier() : 1.0);
            $g = self::BASE_GRAVITY * $manager->getCannonTntGravityMultiplier();
            if (
                $this->dispenserLaunched
                && $this->hasGravity()
                && $manager->isDispenserPrimedGravityOnePlusVelocityEnabled()
            ) {
                $g *= $manager->getDispenserPrimedGravityOnePlusVelocityMultiplier($motion);
            }
            $this->gravity = $g;
        } elseif (
            $this->dispenserLaunched
            && $this->hasGravity()
            && $manager->isDispenserPrimedGravityOnePlusVelocityEnabled()
        ) {
            // Dispenser-fired, gravity on, cannon-mode physics off: scale toward gravity-multiplier × (1 + coef×|v|)
            $this->gravity = self::BASE_GRAVITY
                * $manager->getCannonTntGravityMultiplier()
                * $manager->getDispenserPrimedGravityOnePlusVelocityMultiplier($motion);
        }

        return parent::entityBaseTick($tickDiff);
    }

    private function mergeNearbyStackableTnt(): void {
        if ($this->isFlaggedForDespawn() || $this->stackedCount >= self::MAX_STACKED_COUNT) {
            return;
        }

        $box = $this->getBoundingBox()->expandedCopy(
            self::TNT_MERGE_SCAN_EXPAND,
            self::TNT_MERGE_SCAN_EXPAND,
            self::TNT_MERGE_SCAN_EXPAND
        );

        foreach ($this->getWorld()->getNearbyEntities($box, $this) as $entity) {
            if (!$this->canMergeTntEntity($entity)) {
                continue;
            }

            /** @var GlaciaPrimedTNT $entity */
            $thisCount = $this->stackedCount;
            $otherCount = $entity->getStackedCount();
            $newCount = min(self::MAX_STACKED_COUNT, $thisCount + $otherCount);
            if ($newCount <= $thisCount) {
                return;
            }

            $this->setMotion($this->weightedMotion($entity, $thisCount, $otherCount, $newCount));
            $this->syncEarliestDetonation($entity);
            $this->stackedCount = $newCount;
            $entity->flagForDespawn();

            if ($this->stackedCount >= self::MAX_STACKED_COUNT) {
                return;
            }
        }
    }

    private function canMergeTntEntity(Entity $entity): bool {
        if (!$entity instanceof self || $entity === $this || $entity->isFlaggedForDespawn()) {
            return false;
        }
        if (
            $entity->getTNTType() !== $this->tntType
            || $entity->isCannonMode() !== $this->cannonMode
            || $entity->isDispenserLaunched() !== $this->dispenserLaunched
            || $entity->getRaidSourceFactionId() !== $this->raidSourceFactionId
            || $entity->worksUnderwater() !== $this->worksUnderwater()
            || $entity->hasGravity() !== $this->hasGravity()
        ) {
            return false;
        }

        $pos = $this->getPosition();
        $otherPos = $entity->getPosition();
        if (
            (int) floor($pos->x) !== (int) floor($otherPos->x)
            || (int) floor($pos->y) !== (int) floor($otherPos->y)
            || (int) floor($pos->z) !== (int) floor($otherPos->z)
        ) {
            return false;
        }

        $dx = $pos->x - $otherPos->x;
        $dy = $pos->y - $otherPos->y;
        $dz = $pos->z - $otherPos->z;
        if (($dx ** 2) + ($dy ** 2) + ($dz ** 2) > self::TNT_MERGE_MAX_POSITION_DELTA_SQ) {
            return false;
        }

        if (abs($this->getMergeDetonationTick() - $entity->getMergeDetonationTick()) > self::TNT_MERGE_MAX_DETONATION_TICK_DIFF) {
            return false;
        }

        return $this->hasCompatibleMotionDirection($entity);
    }

    private function getMergeDetonationTick(): int {
        if ($this->explodeAtTick > 0) {
            return $this->explodeAtTick;
        }

        return Loader::getInstance()->getServer()->getTick() + $this->getFuse();
    }

    private function hasCompatibleMotionDirection(self $entity): bool {
        $a = $this->getMotion();
        $b = $entity->getMotion();
        $aLenSq = ($a->x ** 2) + ($a->y ** 2) + ($a->z ** 2);
        $bLenSq = ($b->x ** 2) + ($b->y ** 2) + ($b->z ** 2);
        if ($aLenSq < 1.0e-6 || $bLenSq < 1.0e-6) {
            return true;
        }

        $dot = (($a->x * $b->x) + ($a->y * $b->y) + ($a->z * $b->z)) / sqrt($aLenSq * $bLenSq);
        return $dot >= self::TNT_MERGE_MIN_MOTION_DOT;
    }

    private function weightedMotion(self $entity, int $thisCount, int $otherCount, int $newCount): Vector3 {
        $a = $this->getMotion();
        $b = $entity->getMotion();

        $cap = RedstoneManager::getInstance()->getPrimedTntEffectiveMotionCap();

        return self::clampMotion(new Vector3(
            (($a->x * $thisCount) + ($b->x * $otherCount)) / $newCount,
            (($a->y * $thisCount) + ($b->y * $otherCount)) / $newCount,
            (($a->z * $thisCount) + ($b->z * $otherCount)) / $newCount
        ), $cap);
    }

    private function syncEarliestDetonation(self $entity): void {
        $tick = min($this->getMergeDetonationTick(), $entity->getMergeDetonationTick());
        $this->explodeAtTick = $tick;
        $remaining = $tick - Loader::getInstance()->getServer()->getTick();
        $this->setFuse(max(0, $remaining));
    }

    /**
     * Cobweb: heavy motion dampening while intersecting webs; when nearly stopped inside a column, snap into the air block above it (stack on top).
     */
    private function applyCobwebInteraction(): void {
        $inCobweb = false;
        foreach ($this->getBlocksIntersected(0.02) as $block) {
            if ($block->getTypeId() === BlockTypeIds::COBWEB) {
                $inCobweb = true;
                break;
            }
        }

        if (!$inCobweb) {
            return;
        }

        $m = $this->getMotion();
        $rm = RedstoneManager::getInstance();
        $maxFall = min(0.5, max(0.001, $rm->getSandCobwebMaxFallPerTick()));
        $this->setMotion(CobwebMotion::dampMotion(
            $m,
            $rm->getSandCobwebHorizontalDamp(),
            $rm->getSandCobwebVerticalDamp(),
            $maxFall,
            false
        ));

        if ($m->lengthSquared() > 1.0e-5) {
            return;
        }

        $this->settleAboveCobwebColumn();
    }

    private function settleAboveCobwebColumn(): void {
        $world = $this->getWorld();
        $pos = $this->getPosition();
        $wx = (int) floor($pos->x);
        $wy = (int) floor($pos->y);
        $wz = (int) floor($pos->z);

        if ($world->getBlockAt($wx, $wy, $wz)->getTypeId() !== BlockTypeIds::COBWEB) {
            return;
        }

        $top = $wy;
        for ($y = $wy + 1; $y < $wy + 32; $y++) {
            if (!$world->isInWorld($wx, $y, $wz)) {
                break;
            }
            if ($world->getBlockAt($wx, $y, $wz)->getTypeId() === BlockTypeIds::COBWEB) {
                $top = $y;
            } else {
                break;
            }
        }

        $airY = $top + 1;
        if (!$world->isInWorld($wx, $airY, $wz)) {
            return;
        }

        $above = $world->getBlockAt($wx, $airY, $wz);
        if (!$above->canBeReplaced()) {
            return;
        }

        $this->teleport(new Location(
            $wx + 0.5,
            (float) $airY + 0.001,
            $wz + 0.5,
            $world,
            $this->getLocation()->yaw,
            $this->getLocation()->pitch
        ));
        $this->setMotion(new Vector3(0.0, 0.0, 0.0));
    }

    public static function isEntityTouchingWater(Entity $entity): bool {
        $world = $entity->getWorld();
        $x = (int) floor($entity->getLocation()->x);
        $y = (int) floor($entity->getLocation()->y);
        $z = (int) floor($entity->getLocation()->z);

        return $world->getBlockAt($x, $y, $z) instanceof Water
            || $world->getBlockAt($x, $y + 1, $z) instanceof Water
            || $world->getBlockAt($x, $y - 1, $z) instanceof Water;
    }

    private function isTouchingWater(): bool {
        return self::isEntityTouchingWater($this);
    }

    /** Block cell containing explosion center (fixes edge cases where the entity feet miss water briefly). */
    private static function isExplosionEpicenterInWater(Position $center): bool {
        $w = $center->getWorld();
        $x = (int) floor($center->x);
        $y = (int) floor($center->y);
        $z = (int) floor($center->z);

        return $w->getBlockAt($x, $y, $z, true, false) instanceof Water;
    }

    public static function shouldSuppressAquaticExplosionBlockBreak(Entity $entity, Position $center): bool {
        if (!RedstoneManager::getInstance()->isPrimedTntWaterSuppressesExplosionBlockBreak()) {
            return false;
        }

        return self::isEntityTouchingWater($entity) || self::isExplosionEpicenterInWater($center);
    }

    private function shouldSuppressBlockBreakForAquaticExplosion(Position $center): bool {
        return self::shouldSuppressAquaticExplosionBlockBreak($this, $center);
    }

    public function explode(): void {
        if (Loader::getGraceInstance()->isEnabled()) {
            return;
        }

        $ev = new EntityPreExplodeEvent($this, 4.0);
        $ev->call();

        if ($ev->isCancelled()) {
            return;
        }

        $center = Position::fromObject(
            $this->getLocation()->add(0, $this->getSize()->getHeight() / 2, 0),
            $this->getWorld()
        );

        if ($ev->isBlockBreaking() && $this->shouldSuppressBlockBreakForAquaticExplosion($center)) {
            $ev->setBlockBreaking(false);
        }

        if (CannonRulesConfig::suppressExplosionFullyAt($center)) {
            $this->applyCannonPropulsion($center);

            return;
        }

        if ($this->tntType === CustomTNTIds::TACTICAL) {
            TacticalTNTHelper::affectSpawnersOnly($center, $ev->getRadius());
            return;
        }

        if ($this->tntType === CustomTNTIds::LUCKY && $ev->isBlockBreaking()) {
            LuckyTNTHelper::dropSpawnersInRadius($center, $ev->getRadius());
        }
        $this->applyCannonPropulsion($center);

        $explosion = new Explosion(
            $center,
            $ev->getRadius(),
            $this,
            $ev->getFireChance()
        );

        $aquaticSuppressedBlocks = !$ev->isBlockBreaking()
            && self::shouldSuppressAquaticExplosionBlockBreak($this, $center);

        if ($ev->isBlockBreaking()) {
            if (TNTBlastShape::usesAxisShapedBlast($this->tntType)) {
                TNTBlastShape::collectIntoExplosion($explosion, $center, $this, $this->tntType);
            } else {
                $explosion->explodeA();
            }
        } elseif ($aquaticSuppressedBlocks) {
            WaterVulnerableExplosionBypass::mergeSphereIntoExplosion($explosion, $center, $ev->getRadius());
        }

        $explosion->explodeB();

        CustomTNTPattern::explodeExtra($this->tntType, $this, $ev->getRadius());
    }

    private function applyCannonPropulsion(Position $center): void
    {
        $manager = RedstoneManager::getInstance();
        if (!$manager->isCannonTntPropulsionEnabled()) {
            return;
        }

        $radius = $manager->getCannonTntPropulsionRadius();
        $power = $manager->getCannonTntPropulsionPower() * $this->stackedCount;
        if ($radius <= 0.0 || $power <= 0.0) {
            return;
        }

        // Match falloff denominator: inclusion uses the same outer reach so diagonal "rim"
        // targets (inside the old ±r AABB but with dist > r) still get pushed; pad=0 is legacy sphere r.
        $reach = $radius + $manager->getCannonTntPropulsionFalloffRadiusPad();

        $box = new AxisAlignedBB(
            $center->x - $reach,
            $center->y - $reach,
            $center->z - $reach,
            $center->x + $reach,
            $center->y + $reach,
            $center->z + $reach
        );

        $processed = 0;
        foreach ($this->getWorld()->getNearbyEntities($box, $this) as $entity) {
            if (++$processed > self::MAX_PROPULSION_TARGETS_PER_EXPLOSION) {
                break;
            }
            if (!$entity instanceof PrimedTNT && !$entity instanceof GlaciaSandFallingBlock) {
                continue;
            }

            if (
                $entity instanceof PrimedTNT
                && $manager->isCannonTntPropulsionOnlyDispenserPrimedTargets()
                && (!($entity instanceof self) || !$entity->isDispenserLaunched())
            ) {
                continue;
            }

            $targetCenter = $entity->getLocation()->add(0, $entity->getSize()->getHeight() / 2, 0);
            $delta = new Vector3(
                $targetCenter->x - $center->x,
                $targetCenter->y - $center->y,
                $targetCenter->z - $center->z
            );

            $distance = sqrt($delta->x ** 2 + $delta->y ** 2 + $delta->z ** 2);
            if ($distance > $reach) {
                continue;
            }

            // linear = 1 - dist/reach: at dist≈r with pad>0, rim strength stays >0. pad=0 → reach=r.
            $denom = max(1e-6, $reach);
            $linearFalloff = 1.0 - ($distance / $denom);
            $falloff = min(1.0, max($manager->getCannonTntPropulsionMinFalloff(), $linearFalloff));
            if ($falloff <= 0.0) {
                continue;
            }

            $direction = $distance > 0.0001
                ? $delta->divide($distance)
                : new Vector3(0.0, 1.0, 0.0);

            $strength = $power * $falloff;
            if ($manager->isPropulsionStrengthOnePlusVelocityEnabled()) {
                $strength *= $manager->getPropulsionStrengthOnePlusVelocityMultiplier($entity->getMotion());
            }
            if (self::isEntityTouchingWater($entity)) {
                $strength *= $manager->getCannonTntPropulsionWaterMultiplier();
            }

            // Radial push + additive vertical-boost. strength already includes falloff × water × one-plus; $power is pre-falloff source cap.
            $vbScale = 1.0;
            if (!$manager->isCannonTntPropulsionAdditiveVerticalBoostEnabled()) {
                $vbScale = 0.0;
            } elseif ($manager->isCannonTntPropulsionVerticalBoostScalesWithHitStrength()) {
                $vbScale = max(0.0, min(1.0, $strength / max(1e-9, $power)));
            } elseif ($manager->isCannonTntPropulsionVerticalBoostFull()) {
                $vbScale = 1.0;
            } else {
                $vbScale = max(0.35, $falloff);
            }

            $push = new Vector3(
                $direction->x * $strength,
                ($direction->y * $strength) + ($manager->getCannonTntPropulsionVerticalBoost() * $vbScale),
                $direction->z * $strength
            );

            $motion = $entity->getMotion();
            $combined = new Vector3(
                $motion->x + $push->x,
                $motion->y + $push->y,
                $motion->z + $push->z
            );
            $effectiveCap = $manager->getPrimedTntEffectiveMotionCap();
            $newMotion = self::clampMotion($combined, $effectiveCap);
            if ($entity instanceof GlaciaSandFallingBlock) {
                if ($entity->isFlaggedForDespawn()) {
                    $replacement = new GlaciaSandFallingBlock($entity->getLocation(), $entity->getBlock());
                    $replacement->setStackedCount($entity->getStackedCount());
                    $replacement->markHitByTnt();
                    $replacement->setMotion($newMotion);
                    $replacement->spawnToAll();
                    continue;
                }
                $entity->markHitByTnt();
                $entity->setMotion($newMotion);
            } else {
                $entity->setMotion($newMotion);
            }
        }
    }

    private static function clampMotion(Vector3 $motion, float $maxMotion): Vector3
    {
        $length = sqrt(($motion->x ** 2) + ($motion->y ** 2) + ($motion->z ** 2));
        if ($length <= 0.0 || $length <= $maxMotion) {
            return $motion;
        }

        $scale = $maxMotion / $length;
        return new Vector3(
            $motion->x * $scale,
            $motion->y * $scale,
            $motion->z * $scale
        );
    }
}
