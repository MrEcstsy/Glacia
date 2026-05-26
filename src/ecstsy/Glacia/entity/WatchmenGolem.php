<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity;

use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\Glacia\server\staff\HiddenStaffTargeting;
use ecstsy\MartianUtilities\entity\BaseHostileEntity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\NeverSavedWithChunkEntity;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat as C;

final class WatchmenGolem extends BaseHostileEntity implements NeverSavedWithChunkEntity
{
    private string $ownerUuid = "";
    private string $targetUuid = "";
    private string $displayName = "Watchman";
    private int $despawnAtTick = PHP_INT_MAX;
    private float $configuredDamage = 6.0;
    private float $configuredMoveSpeed = 0.27;
    private float $configuredAttackReach = 2.4;
    private float $configuredTargetRange = 18.0;
    private int $configuredAttackCooldown = 10;

    protected int $aiInterval = 1;

    protected function initEntity(CompoundTag $nbt): void
    {
        parent::initEntity($nbt);

        $this->ownerUuid = strtolower($nbt->getString("watchmen_owner_uuid", ""));
        $this->targetUuid = strtolower($nbt->getString("watchmen_target_uuid", ""));
        $this->displayName = $nbt->getString("watchmen_name", "Watchman");
        $this->configuredDamage = max(1.0, $nbt->getFloat("watchmen_damage", 6.0));
        $this->configuredMoveSpeed = max(0.12, min(0.40, $nbt->getFloat("watchmen_speed", 0.27)));
        $this->configuredAttackReach = max(1.6, min(3.8, $nbt->getFloat("watchmen_reach", 2.4)));
        $this->configuredTargetRange = max(8.0, min(32.0, $nbt->getFloat("watchmen_target_range", 18.0)));
        $this->configuredAttackCooldown = max(6, $nbt->getInt("watchmen_attack_cooldown", 10));

        $health = max(10, $nbt->getInt("watchmen_health", 40));
        $this->setMaxHealth($health);
        $this->setHealth($health);
        $this->despawnAtTick = $this->ticksLived + max(20, $nbt->getInt("watchmen_lifetime_ticks", 240));

        $this->setNameTagAlwaysVisible();
        $this->setNameTagVisible();
        $this->setNoClientPredictions(true);
        $this->updateHealthNameTag();
    }

    public function saveNBT(): CompoundTag
    {
        $nbt = parent::saveNBT();
        $nbt->setString("watchmen_owner_uuid", $this->ownerUuid);
        $nbt->setString("watchmen_target_uuid", $this->targetUuid);
        $nbt->setString("watchmen_name", $this->displayName);
        $nbt->setInt("watchmen_health", (int) $this->getMaxHealth());
        $nbt->setFloat("watchmen_damage", $this->configuredDamage);
        $nbt->setFloat("watchmen_speed", $this->configuredMoveSpeed);
        $nbt->setFloat("watchmen_reach", $this->configuredAttackReach);
        $nbt->setFloat("watchmen_target_range", $this->configuredTargetRange);
        $nbt->setInt("watchmen_attack_cooldown", $this->configuredAttackCooldown);
        $nbt->setInt("watchmen_lifetime_ticks", max(20, $this->despawnAtTick - $this->ticksLived));
        return $nbt;
    }

    public static function getNetworkTypeId(): string
    {
        return EntityIds::IRON_GOLEM;
    }

    public function getName(): string
    {
        return "Watchmen Golem";
    }

    protected function getInitialSizeInfo(): EntitySizeInfo
    {
        return new EntitySizeInfo(2.9, 1.4);
    }

    public function canSaveWithChunk(): bool
    {
        return false;
    }

    public function attack(EntityDamageEvent $source): void
    {
        if ($source instanceof EntityDamageByEntityEvent) {
            $damager = $source->getDamager();
            if ($damager instanceof Player && $this->canTargetPlayer($damager)) {
                $this->targetUuid = strtolower($damager->getUniqueId()->toString());
            }
        }

        parent::attack($source);
        $this->updateHealthNameTag();
    }

    protected function entityBaseTick(int $tickDiff = 1): bool
    {
        if ($this->ticksLived >= $this->despawnAtTick) {
            $this->flagForDespawn();
        }

        $owner = $this->findPlayerByUuid($this->ownerUuid);
        if ($owner === null || !$owner->isAlive() || !$owner->isOnline()) {
            $this->flagForDespawn();
        }

        $updated = parent::entityBaseTick($tickDiff);
        if (($this->ticksLived % 10) === 0) {
            $this->updateHealthNameTag();
        }

        return $updated;
    }

    public function findClosestPlayer(): ?Player
    {
        $preferred = $this->findPlayerByUuid($this->targetUuid);
        if ($preferred !== null && $this->canTargetPlayer($preferred)) {
            return $preferred;
        }

        $closest = null;
        $closestDistSq = $this->getTargetRange() ** 2;
        foreach ($this->getWorld()->getPlayers() as $player) {
            if (!$this->canTargetPlayer($player)) {
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

    protected function validateTarget(): void
    {
        parent::validateTarget();
        if ($this->target !== null && !$this->canTargetPlayer($this->target)) {
            $this->clearTarget();
        }
    }

    protected function getTargetRange(): float
    {
        return $this->configuredTargetRange;
    }

    protected function getMoveSpeed(): float
    {
        return $this->configuredMoveSpeed;
    }

    protected function getAttackReach(): float
    {
        return $this->configuredAttackReach;
    }

    protected function getAttackDamage(): float
    {
        return $this->configuredDamage;
    }

    protected function getAttackCooldownTicks(): int
    {
        return $this->configuredAttackCooldown;
    }

    public function setWatchmenData(
        Player $owner,
        Player $target,
        string $displayName,
        int $lifetimeTicks,
        float $damage,
        int $health
    ): void {
        $this->ownerUuid = strtolower($owner->getUniqueId()->toString());
        $this->targetUuid = strtolower($target->getUniqueId()->toString());
        $this->displayName = $displayName;
        $this->despawnAtTick = $this->ticksLived + max(20, $lifetimeTicks);
        $this->configuredDamage = max(1.0, $damage);
        $this->setMaxHealth(max(10, $health));
        $this->setHealth(min($this->getMaxHealth(), max(1, $health)));
        $this->updateHealthNameTag();
    }

    protected function updateHealthNameTag(): void
    {
        $health = max(0, (int) ceil($this->getHealth()));
        $max = max(1, (int) ceil($this->getMaxHealth()));
        $this->setNameTag(C::colorize($this->displayName . "\n&r&e{$health}&7/&e{$max}"));
    }

    private function canTargetPlayer(Player $player): bool
    {
        if (
            !$player->isAlive() ||
            !$player->isOnline() ||
            $player->isSpectator() ||
            HiddenStaffTargeting::isUntargetable($player) ||
            strtolower($player->getUniqueId()->toString()) === $this->ownerUuid
        ) {
            return false;
        }

        $owner = $this->findPlayerByUuid($this->ownerUuid);
        if ($owner instanceof Player && GlaciaUtils::isAlly($owner, $player)) {
            return false;
        }

        return $player->getPosition()->distanceSquared($this->getPosition()) <= ($this->getTargetRange() ** 2);
    }

    private function findPlayerByUuid(string $uuid): ?Player
    {
        if ($uuid === "") {
            return null;
        }

        foreach (Server::getInstance()->getOnlinePlayers() as $player) {
            if (strtolower($player->getUniqueId()->toString()) === $uuid) {
                return $player;
            }
        }

        return null;
    }
}
