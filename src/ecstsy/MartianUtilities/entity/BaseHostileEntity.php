<?php

namespace ecstsy\MartianUtilities\entity;

use pocketmine\block\Block;
use pocketmine\block\Slab;
use pocketmine\block\Stair;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\world\Position;

abstract class BaseHostileEntity extends Living {
    protected const BASE_PATH_COOLDOWN_TICKS = 8;
    protected const STUCK_REPATH_THRESHOLD = 7;
    protected const HARD_STUCK_THRESHOLD = 14;
    protected const TARGET_NODE_SHIFT_REPATH_SQ = 1.0;

    protected ?Player $target = null;
    protected bool $persistent = false;
    protected ?Position $spawner = null;

    protected int $aiInterval = 3;
    protected int $aiTickCounter = 0;

    protected int $attackCooldown = 0;
    protected int $pathCooldown = 0;

    protected ?Pathfinder $pathfinder = null;
    /** @var Vector3[] */
    protected array $path = [];
    protected int $pathIndex = 0;

    protected ?Vector3 $lastTargetNode = null;
    protected ?Vector3 $lastPos = null;
    protected int $stuckTicks = 0;
    protected int $failedPathRecalculations = 0;

    public function __construct(Location $location, ?CompoundTag $nbt = null) {
        parent::__construct($location, $nbt);
        $this->pathfinder = new Pathfinder($this->getWorld());
    }

    public function setSpawner(?Position $spawner): void {
        $this->spawner = $spawner;
    }

    public function getSpawner(): ?Position {
        return $this->spawner;
    }

    protected function entityBaseTick(int $tickDiff = 1): bool {
        $hasUpdate = parent::entityBaseTick($tickDiff);

        $this->aiTickCounter += $tickDiff;

        if ($this->attackCooldown > 0) {
            $this->attackCooldown -= $tickDiff;
        }

        if (($this->ticksLived % 10) === 0) {
            $this->updateHealthNameTag();
        }

        if ($this->pathCooldown > 0) {
            $this->pathCooldown -= $tickDiff;
        }

        $this->trackStuckState();

        if (!$this->hasCustomMovement()) {
            $this->tickMovement();
        }

        if ($this->aiTickCounter >= $this->aiInterval) {
            $this->aiTickCounter = 0;
            $this->updateAI();
        }

        return $hasUpdate;
    }

    protected function updateAI(): void {
        $this->validateTarget();

        if ($this->target === null) {
            $this->target = $this->findClosestPlayer();
            if ($this->target === null) {
                $this->path = [];
                $this->pathIndex = 0;
                return;
            }

            $this->recalculatePath();
        }

        $this->lookAt($this->target->getPosition()->add(0, $this->target->getEyeHeight(), 0));

        if ($this->canAttack($this->target)) {
            $this->attackPlayer($this->target);
        }

        if ($this->shouldRecalculatePath()) {
            $this->recalculatePath();
        }
    }

    protected function tickMovement(): void {
        if ($this->target === null || !$this->isAlive()) {
            return;
        }

        $move = new Vector3(0, 0, 0);

        $nextNode = $this->getCurrentPathNode();
        if ($nextNode !== null) {
            $move = $this->buildMovementToward($nextNode, $this->getMoveSpeed());
        } else {
            $move = $this->buildMovementToward($this->target->getPosition(), $this->getMoveSpeed());
            if ($this->pathCooldown <= 0) {
                $this->recalculatePath();
            }
        }

        $move = $move->addVector($this->buildSeparationVector());

        $horizontalLenSq = ($move->x * $move->x) + ($move->z * $move->z);
        if ($horizontalLenSq > 0.0001) {
            $len = sqrt($horizontalLenSq);
            $speed = $this->getMoveSpeed();

            $this->motion->x = ($move->x / $len) * $speed;
            $this->motion->z = ($move->z / $len) * $speed;
        } else {
            $this->motion->x *= 0.6;
            $this->motion->z *= 0.6;
        }

        if ($this->shouldJumpAhead() || ($this->requiresJumpTowardElevatedNearbyTarget())) {
            $this->jump();
        }

        if ($this->stuckTicks >= 4) {
            $this->motion->y = max($this->motion->y, 0.42);
            $this->pathCooldown = 0;
        }
    }

    protected function validateTarget(): void {
        if ($this->target === null) {
            return;
        }

        if (
            !$this->target->isAlive() ||
            !$this->target->isOnline() ||
            $this->target->isClosed() ||
            $this->target->isSpectator() ||
            $this->target->getWorld()->getFolderName() !== $this->getWorld()->getFolderName()
        ) {
            $this->clearTarget();
            return;
        }

        if ($this->target->getPosition()->distanceSquared($this->getPosition()) > ($this->getTargetRange() ** 2)) {
            $this->clearTarget();
        }
    }

    public function clearTarget(): void {
        $this->target = null;
        $this->path = [];
        $this->pathIndex = 0;
        $this->lastTargetNode = null;
    }

    public function hasTarget(): bool {
        return $this->target !== null;
    }

    public function findClosestPlayer(): ?Player {
        $closest = null;
        $closestDistSq = $this->getTargetRange() ** 2;

        foreach ($this->getWorld()->getPlayers() as $player) {
            if (
                !$player->isOnline() ||
                !$player->isAlive() ||
                $player->isSpectator()
            ) {
                continue;
            }

            $distSq = $player->getPosition()->distanceSquared($this->getPosition());
            if ($distSq < $closestDistSq) {
                $closestDistSq = $distSq;
                $closest = $player;
            }
        }

        return $closest;
    }

    protected function shouldRecalculatePath(): bool {
        if ($this->target === null || $this->pathfinder === null) {
            return false;
        }

        if ($this->pathCooldown <= 0) {
            return true;
        }

        if ($this->stuckTicks >= self::STUCK_REPATH_THRESHOLD) {
            return true;
        }

        $targetNode = $this->target->getPosition()->floor();
        if ($this->lastTargetNode === null) {
            return true;
        }

        return $this->lastTargetNode->distanceSquared($targetNode) >= self::TARGET_NODE_SHIFT_REPATH_SQ;
    }

    protected function recalculatePath(): void {
        if ($this->target === null || $this->pathfinder === null) {
            return;
        }

        $start = $this->getPosition()->floor();
        $goal = $this->target->getPosition()->floor();

        $newPath = $this->pathfinder->findPath($start, $goal, 24);

        $this->path = $newPath ?? [];
        $this->pathIndex = 0;
        $this->pathCooldown = self::BASE_PATH_COOLDOWN_TICKS;
        $this->lastTargetNode = $goal;
        if ($newPath === null || $newPath === []) {
            $this->failedPathRecalculations++;
            $this->pathCooldown = max(3, self::BASE_PATH_COOLDOWN_TICKS - min(4, $this->failedPathRecalculations));
        } else {
            $this->failedPathRecalculations = 0;
            $this->stuckTicks = 0;
        }
    }

    protected function getCurrentPathNode(): ?Vector3 {
        while (isset($this->path[$this->pathIndex])) {
            $node = $this->path[$this->pathIndex];
            $nodeCenter = new Vector3($node->x + 0.5, $node->y, $node->z + 0.5);

            if ($this->getPosition()->distanceSquared($nodeCenter) <= 0.7 * 0.7) {
                $this->pathIndex++;
                continue;
            }

            return $nodeCenter;
        }

        return null;
    }

    protected function buildMovementToward(Vector3 $target, float $speed): Vector3 {
        $dx = $target->x - $this->location->x;
        $dz = $target->z - $this->location->z;

        $lenSq = ($dx * $dx) + ($dz * $dz);
        if ($lenSq <= 0.0001) {
            return new Vector3(0, 0, 0);
        }

        $len = sqrt($lenSq);
        return new Vector3(($dx / $len) * $speed, 0, ($dz / $len) * $speed);
    }

    protected function buildSeparationVector(): Vector3 {
        $pushX = 0.0;
        $pushZ = 0.0;

        $radius = 0.9;
        $bb = $this->getBoundingBox()->expandedCopy($radius, 0.5, $radius);

        foreach ($this->getWorld()->getNearbyEntities($bb) as $entity) {
            if (!$entity instanceof BaseHostileEntity || $entity === $this || $entity->isClosed()) {
                continue;
            }

            $dx = $this->location->x - $entity->location->x;
            $dz = $this->location->z - $entity->location->z;
            $distSq = ($dx * $dx) + ($dz * $dz);

            if ($distSq <= 0.0001 || $distSq > ($radius * $radius)) {
                continue;
            }

            $dist = sqrt($distSq);
            $strength = (1.0 - ($dist / $radius)) * $this->getSeparationStrength();

            $pushX += ($dx / $dist) * $strength;
            $pushZ += ($dz / $dist) * $strength;
        }

        return new Vector3($pushX, 0, $pushZ);
    }

    protected function trackStuckState(): void {
        $current = $this->getPosition();

        if ($this->lastPos !== null) {
            $movedSq = $current->distanceSquared($this->lastPos);

            if ($this->target !== null && $movedSq < 0.01) {
                $this->stuckTicks++;
            } else {
                $this->stuckTicks = 0;
            }
        }

        $this->lastPos = $current->asVector3();

        if ($this->stuckTicks >= self::HARD_STUCK_THRESHOLD && $this->target !== null) {
            $this->performStuckRecoveryMove();
            $this->pathCooldown = 0; // force repath on next AI cycle
            $this->stuckTicks = 0;
        }
    }

    protected function shouldJumpAhead(): bool {
        if (!$this->onGround) {
            return false;
        }

        $dir = $this->getHorizontalIntentVector();
        if ($dir === null) {
            return false;
        }

        $world = $this->getWorld();
        $floorY = $this->getPosition()->getFloorY();
        $front = $this->getPosition()->add($dir->x, 0, $dir->z)->floor();

        // Sample using floor Y anchor (fixes lip ledges where the cell below the step is empty).
        $frontBlock = $world->getBlockAt((int) $front->x, $floorY, (int) $front->z);
        $aboveFront = $world->getBlockAt((int) $front->x, $floorY + 1, (int) $front->z);
        $twoAboveFront = $world->getBlockAt((int) $front->x, $floorY + 2, (int) $front->z);
        $threeAboveFront = $world->getBlockAt((int) $front->x, $floorY + 3, (int) $front->z);

        $nextNode = $this->getCurrentPathNode();
        if ($nextNode !== null && (($nextNode->y - $this->getPosition()->getY()) > 0.45) && !$aboveFront->isSolid()) {
            return true;
        }

        if (($frontBlock instanceof Slab || $frontBlock instanceof Stair) && !$aboveFront->isSolid()) {
            return true;
        }

        // Standard same-level step-up: solid block face with headroom.
        if ($frontBlock->isSolid() && !$aboveFront->isSolid() && !$twoAboveFront->isSolid()) {
            return true;
        }

        // One-block lip / platform ahead: feet column is empty at standing level but the next tier is solid walkable surface.
        if (
            !$frontBlock->isSolid() &&
            $aboveFront->isSolid() &&
            !$twoAboveFront->isSolid() &&
            !$threeAboveFront->isSolid()
        ) {
            return true;
        }

        return false;
    }

    /**
     * Movement-facing (motion), else intent toward path node or chase target — so jump checks still run when motion damping zeroes xz.
     */
    protected function getHorizontalIntentVector(): ?Vector3
    {
        $mx = $this->motion->x;
        $mz = $this->motion->z;
        $lenSq = ($mx * $mx) + ($mz * $mz);
        if ($lenSq > 0.0004) {
            $len = sqrt($lenSq);
            return new Vector3($mx / $len, 0, $mz / $len);
        }

        $goal = $this->getCurrentPathNode();
        if ($goal === null && $this->target !== null) {
            $goal = $this->target->getPosition()->asVector3();
        }

        if ($goal === null) {
            return null;
        }

        $dx = $goal->x - $this->location->x;
        $dz = $goal->z - $this->location->z;
        $gLenSq = ($dx * $dx) + ($dz * $dz);
        if ($gLenSq <= 0.0004) {
            return null;
        }

        $gLen = sqrt($gLenSq);
        return new Vector3($dx / $gLen, 0, $dz / $gLen);
    }

    protected function getHorizontalFacingVector(): ?Vector3 {
        $mx = $this->motion->x;
        $mz = $this->motion->z;

        $lenSq = ($mx * $mx) + ($mz * $mz);
        if ($lenSq <= 0.0001) {
            return null;
        }

        $len = sqrt($lenSq);
        return new Vector3($mx / $len, 0, $mz / $len);
    }

    public function jump(): void {
        if (!$this->onGround) {
            return;
        }

        $this->motion->y = 0.42;
    }

    protected function canAttack(Player $player): bool {
        if ($this->attackCooldown > 0) {
            return false;
        }

        return $this->getPosition()->distanceSquared($player->getPosition()) <= ($this->getAttackReach() ** 2);
    }

    public function attackPlayer(Player $player): void {
        $event = new EntityDamageByEntityEvent(
            $this,
            $player,
            EntityDamageByEntityEvent::CAUSE_ENTITY_ATTACK,
            $this->getAttackDamage()
        );
        $player->attack($event);

        if (!$event->isCancelled()) {
            $this->attackCooldown = $this->getAttackCooldownTicks();
            $dx = $player->getPosition()->x - $this->location->x;
            $dz = $player->getPosition()->z - $this->location->z;
            $player->knockBack($dx, $dz);
        }
    }

    public function getFrontBlock(float $y = 0): Block {
        $dir = $this->getHorizontalFacingVector() ?? $this->getDirectionVector();
        $pos = $this->getPosition()->add($dir->x, $y, $dir->z)->floor();
        return $this->getWorld()->getBlockAt($pos->x, $pos->y, $pos->z);
    }

    public function setPersistence(bool $persistent): self {
        $this->persistent = $persistent;
        return $this;
    }

    /**
     * One-block rises (lip chases): old threshold required >1.8; players one block up never triggered jumps.
     */
    protected function requiresJumpTowardElevatedNearbyTarget(): bool
    {
        if ($this->target === null || !$this->onGround || !$this->target->isAlive()) {
            return false;
        }

        $tp = $this->target->getPosition();
        $mp = $this->getPosition();
        $yDiff = $tp->y - $mp->y;
        // Roughly half-block to slightly over one block climb; avoid sky bases & huge gaps.
        if ($yDiff < 0.38 || $yDiff > 2.4) {
            return false;
        }

        $dx = $tp->x - $mp->x;
        $dz = $tp->z - $mp->z;
        if (($dx * $dx) + ($dz * $dz) > 81.0) {
            return false;
        }

        return true;
    }

    protected function strafe(Vector3 $dir, float $speed): void {
        $this->motion->x = -$dir->z * $speed * 0.75;
        $this->motion->z =  $dir->x * $speed * 0.75;
    }

    protected function performStuckRecoveryMove(): void
    {
        // First check if we're physically embedded inside terrain.
        // If so, teleport vertically into the nearest open column rather
        // than trying to walk it off — sideways nudges can't escape that.
        if ($this->escapeIfClippedIntoBlocks()) {
            return;
        }

        $side = (mt_rand(0, 1) === 0) ? -1.0 : 1.0;
        $speed = max(0.12, $this->getMoveSpeed() * 0.7);
        $forward = $this->buildMovementToward($this->target->getPosition(), $speed);

        // Quick side-step nudge to break local collision deadlocks.
        $this->motion->x = ($forward->x * 0.35) + ((-$forward->z) * $side * 0.85);
        $this->motion->z = ($forward->z * 0.35) + (($forward->x) * $side * 0.85);

        // Force a vertical hop even when onGround is unreliable (we're stuck).
        // Using motion directly avoids jump()'s onGround guard.
        $this->motion->y = max($this->motion->y, 0.42);
    }

    /**
     * Detects when the entity bounding box overlaps a non-passable block and
     * teleports the entity straight up to the nearest open column. Returns
     * true when a teleport occurred.
     */
    protected function escapeIfClippedIntoBlocks(): bool
    {
        $world = $this->getWorld();
        $pos = $this->location;
        $bb = $this->getBoundingBox();

        $minX = (int) floor($bb->minX + 0.001);
        $maxX = (int) floor($bb->maxX - 0.001);
        $minZ = (int) floor($bb->minZ + 0.001);
        $maxZ = (int) floor($bb->maxZ - 0.001);
        $minY = (int) floor($bb->minY + 0.001);
        $maxY = (int) floor($bb->maxY - 0.001);

        $clipped = false;
        for ($y = $minY; $y <= $maxY && !$clipped; $y++) {
            for ($x = $minX; $x <= $maxX && !$clipped; $x++) {
                for ($z = $minZ; $z <= $maxZ; $z++) {
                    $block = $world->getBlockAt($x, $y, $z);
                    if ($block->isSolid()) {
                        $clipped = true;
                        break;
                    }
                }
            }
        }

        if (!$clipped) {
            return false;
        }

        $height = max(1, (int) ceil($bb->maxY - $bb->minY));
        $blockX = (int) floor($pos->x);
        $blockZ = (int) floor($pos->z);
        $startY = (int) floor($pos->y);

        // Walk upward looking for a vertical gap tall enough for the entity.
        for ($y = $startY; $y < $startY + 16; $y++) {
            $clear = true;
            for ($h = 0; $h < $height; $h++) {
                if ($world->getBlockAt($blockX, $y + $h, $blockZ)->isSolid()) {
                    $clear = false;
                    break;
                }
            }
            if ($clear) {
                $this->teleport(new \pocketmine\math\Vector3($blockX + 0.5, (float) $y, $blockZ + 0.5));
                $this->motion->x = 0.0;
                $this->motion->y = 0.0;
                $this->motion->z = 0.0;
                return true;
            }
        }

        return false;
    }

    protected function hasCustomMovement(): bool {
        return false;
    }

    protected function getMoveSpeed(): float {
        return 0.20;
    }

    protected function getTargetRange(): float {
        return 16.0;
    }

    protected function getAttackReach(): float {
        return 1.8;
    }

    protected function getAttackDamage(): float {
        return 6.0;
    }

    protected function getAttackCooldownTicks(): int {
        return 12;
    }

    protected function getSeparationStrength(): float {
        return 0.18;
    }

    protected function updateHealthNameTag(): void {
        $health = max(0, (int) ceil($this->getHealth()));
        $max = max(1, (int) ceil($this->getMaxHealth()));
        $color = TextFormat::RED;
        $bar = $this->buildHealthAuraBar($health, $max);

        $this->setNameTag(TextFormat::colorize(
            "&r&l" . $this->getName() . "\n&r" . $color . $bar . " &8(&f{$health}&7/&f{$max}&8)"
        ));
        $this->setNameTagVisible(true);
        $this->setNameTagAlwaysVisible(true);
    }

    protected function buildHealthAuraBar(int $health, int $max): string
    {
        $segments = 10;
        $ratio = $max > 0 ? max(0.0, min(1.0, $health / $max)) : 0.0;
        $filled = (int) floor($ratio * $segments);

        return str_repeat("❤", $filled) . TextFormat::GRAY . str_repeat("❤", max(0, $segments - $filled));
    }

    public function attack(EntityDamageEvent $source): void
    {
        parent::attack($source);
        $this->updateHealthNameTag();
    }
    
}
