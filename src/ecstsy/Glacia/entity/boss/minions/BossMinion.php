<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\boss\minions;

use ecstsy\Glacia\entity\boss\BossEntity;
use ecstsy\MartianUtilities\entity\BaseHostileEntity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Location;
use pocketmine\entity\NeverSavedWithChunkEntity;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

abstract class BossMinion extends BaseHostileEntity implements NeverSavedWithChunkEntity
{
    protected int $ownerBossId = -1;
    protected string $displayName = "Boss Minion";
    protected float $configuredDamage = 6.0;
    protected float $configuredSpeed = 0.24;
    protected float $configuredTargetRange = 24.0;
    protected float $configuredAttackReach = 2.2;
    protected int $configuredAttackCooldown = 10;
    protected bool $reportedRemoval = false;
    protected int $orbitDirection = 1;
    protected int $nextOrbitSwapTick = 0;

    public function __construct(Location $location, ?CompoundTag $nbt = null)
    {
        parent::__construct($location, $nbt);
        $this->aiInterval = 1;
    }

    public function getName(): string
    {
        return $this->displayName;
    }

    protected function getInitialSizeInfo(): EntitySizeInfo
    {
        return new EntitySizeInfo(1.8, 0.6);
    }

    protected function initEntity(CompoundTag $nbt): void
    {
        parent::initEntity($nbt);

        $this->ownerBossId = $nbt->getInt("boss_minion_owner_id", -1);
        $this->displayName = $nbt->getString("boss_minion_name", "Boss Minion");
        $this->configuredDamage = max(1.0, $nbt->getFloat("boss_minion_damage", 6.0));
        $this->configuredSpeed = max(0.08, min(0.40, $nbt->getFloat("boss_minion_speed", 0.24)));
        $this->configuredTargetRange = max(8.0, min(36.0, $nbt->getFloat("boss_minion_target_range", 24.0)));
        $this->configuredAttackReach = max(1.4, min(4.0, $nbt->getFloat("boss_minion_attack_reach", 2.2)));
        $this->configuredAttackCooldown = max(6, $nbt->getInt("boss_minion_attack_cooldown", 10));

        $health = max(8, $nbt->getInt("boss_minion_health", 40));
        $this->setMaxHealth($health);
        $this->setHealth($health);
        $this->setNameTagAlwaysVisible();
        $this->setNameTagVisible();
        $this->updateHealthNameTag();
    }

    public function saveNBT(): CompoundTag
    {
        $nbt = parent::saveNBT();
        $nbt->setInt("boss_minion_owner_id", $this->ownerBossId);
        $nbt->setString("boss_minion_name", $this->displayName);
        $nbt->setFloat("boss_minion_damage", $this->configuredDamage);
        $nbt->setFloat("boss_minion_speed", $this->configuredSpeed);
        $nbt->setFloat("boss_minion_target_range", $this->configuredTargetRange);
        $nbt->setFloat("boss_minion_attack_reach", $this->configuredAttackReach);
        $nbt->setInt("boss_minion_attack_cooldown", $this->configuredAttackCooldown);
        $nbt->setInt("boss_minion_health", (int) $this->getMaxHealth());
        return $nbt;
    }

    protected function getTargetRange(): float
    {
        return $this->configuredTargetRange;
    }

    protected function getMoveSpeed(): float
    {
        return $this->configuredSpeed;
    }

    protected function getAttackDamage(): float
    {
        return $this->configuredDamage;
    }

    protected function getAttackCooldownTicks(): int
    {
        return $this->configuredAttackCooldown;
    }

    protected function getAttackReach(): float
    {
        return $this->configuredAttackReach;
    }

    protected function tickMovement(): void
    {
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

        $move = $move->addVector($this->buildOrbitVector());
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

        if ($this->shouldJumpAhead() || $this->requiresJumpTowardElevatedNearbyTarget()) {
            $this->jump();
        }

        if ($this->stuckTicks >= 4) {
            $this->motion->y = max($this->motion->y, 0.42);
            $this->pathCooldown = 0;
        }
    }

    protected function buildSeparationVector(): Vector3
    {
        $pushX = 0.0;
        $pushZ = 0.0;

        $radius = 1.5;
        $bb = $this->getBoundingBox()->expandedCopy($radius, 0.5, $radius);
        $ownerBossId = $this->ownerBossId;

        foreach ($this->getWorld()->getNearbyEntities($bb) as $entity) {
            if (!$entity instanceof BaseHostileEntity || $entity === $this || $entity->isClosed()) {
                continue;
            }

            $samePack = $entity instanceof self && $entity->ownerBossId === $ownerBossId;
            if (!$samePack && !$entity instanceof BossEntity) {
                continue;
            }

            $dx = $this->location->x - $entity->location->x;
            $dz = $this->location->z - $entity->location->z;
            $distSq = ($dx * $dx) + ($dz * $dz);
            if ($distSq <= 0.0001 || $distSq > ($radius * $radius)) {
                continue;
            }

            $dist = sqrt($distSq);
            $strength = (1.0 - ($dist / $radius)) * ($samePack ? 0.42 : 0.22);
            $pushX += ($dx / $dist) * $strength;
            $pushZ += ($dz / $dist) * $strength;
        }

        return new Vector3($pushX, 0, $pushZ);
    }

    protected function buildOrbitVector(): Vector3
    {
        if (!$this->target instanceof Player) {
            return new Vector3(0, 0, 0);
        }

        $distanceSq = $this->getPosition()->distanceSquared($this->target->getPosition());
        if ($distanceSq < 4.0 || $distanceSq > 81.0) {
            return new Vector3(0, 0, 0);
        }

        if ($this->ticksLived >= $this->nextOrbitSwapTick) {
            $this->orbitDirection = mt_rand(0, 1) === 0 ? -1 : 1;
            $this->nextOrbitSwapTick = $this->ticksLived + mt_rand(18, 34);
        }

        $toward = $this->buildMovementToward($this->target->getPosition(), 1.0);
        $lateral = new Vector3(-$toward->z * $this->orbitDirection, 0, $toward->x * $this->orbitDirection);
        $engagedCount = count($this->getWorld()->getPlayers()) > 1 ? 0.16 : 0.28;

        return new Vector3($lateral->x * $engagedCount, 0, $lateral->z * $engagedCount);
    }

    public function getOwnerBoss(): ?BossEntity
    {
        $boss = $this->getWorld()->getEntity($this->ownerBossId);
        return $boss instanceof BossEntity ? $boss : null;
    }

    public function attackPlayer(Player $player): void
    {
        $boss = $this->getOwnerBoss();
        if ($boss !== null && $boss->isMinionIntermissionActive()) {
            parent::attackPlayer($player);
            return;
        }

        parent::attackPlayer($player);
    }

    protected function onDeath(): void
    {
        parent::onDeath();
        $this->reportRemovalToOwner();
    }

    protected function onDispose(): void
    {
        $this->reportRemovalToOwner();
        parent::onDispose();
    }

    protected function updateHealthNameTag(): void
    {
        $health = max(0, (int) ceil($this->getHealth()));
        $max = max(1, (int) ceil($this->getMaxHealth()));
        $this->setNameTag(C::colorize("&r" . $this->displayName . "\n&r&cHP &f{$health}&7/&f{$max}"));
    }

    private function reportRemovalToOwner(): void
    {
        if ($this->reportedRemoval) {
            return;
        }

        $this->reportedRemoval = true;
        $boss = $this->getOwnerBoss();
        if ($boss !== null) {
            $boss->handleMinionRemoved($this);
        }
    }
}
