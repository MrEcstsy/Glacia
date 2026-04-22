<?php

namespace ecstsy\MartianUtilities\entity;

use pocketmine\block\Block;
use pocketmine\block\Slab;
use pocketmine\block\Stair;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\world\Position;

abstract class BaseHostileEntity extends Living {

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

        if ($this->shouldJumpAhead()) {
            $this->jump();
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

        if ($this->stuckTicks >= 6) {
            return true;
        }

        $targetNode = $this->target->getPosition()->floor();
        if ($this->lastTargetNode === null) {
            return true;
        }

        return $this->lastTargetNode->distanceSquared($targetNode) >= 2.0;
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
        $this->pathCooldown = 6;
        $this->lastTargetNode = $goal;
        $this->stuckTicks = 0;
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
    }

    protected function shouldJumpAhead(): bool {
        if (!$this->onGround) {
            return false;
        }

        $dir = $this->getHorizontalFacingVector();
        if ($dir === null) {
            return false;
        }

        $front = $this->getPosition()->add($dir->x, 0, $dir->z)->floor();
        $frontBlock = $this->getWorld()->getBlockAt($front->x, $front->y, $front->z);
        $aboveFront = $this->getWorld()->getBlockAt($front->x, $front->y + 1, $front->z);

        if (($frontBlock instanceof Slab || $frontBlock instanceof Stair) && !$aboveFront->isSolid()) {
            return true;
        }

        return $frontBlock->isSolid() && !$aboveFront->isSolid();
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

    protected function targetIsTooHigh(): bool {
        return $this->target !== null && (($this->target->getPosition()->y - $this->getPosition()->y) > 1.8);
    }

    protected function strafe(Vector3 $dir, float $speed): void {
        $this->motion->x = -$dir->z * $speed * 0.75;
        $this->motion->z =  $dir->x * $speed * 0.75;
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
    
}
