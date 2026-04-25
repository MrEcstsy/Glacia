<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\zones;

use ecstsy\Glacia\server\zones\ZoneMobManager;
use ecstsy\MartianUtilities\entity\BaseHostileEntity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\item\Item;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\utils\TextFormat as C;

class ZoneMob extends BaseHostileEntity {

    private string $zoneId = "";
    private string $mobId = "";
    private string $displayName = "Zone Mob";
    private float $configuredDamage = 4.0;
    private float $configuredSpeed = 0.19;
    private float $configuredTargetRange = 15.0;
    private int $configuredXp = 20;

    protected int $aiInterval = 3;

    protected function initEntity(CompoundTag $nbt): void
    {
        parent::initEntity($nbt);

        $this->zoneId = strtolower($nbt->getString("zone_id", ""));
        $this->mobId = strtolower($nbt->getString("zone_mob_id", ""));
        $this->displayName = $nbt->getString("zone_mob_name", "Zone Mob");
        $this->configuredDamage = max(1.0, $nbt->getFloat("zone_mob_damage", 4.0));
        $this->configuredSpeed = max(0.08, min(0.24, $nbt->getFloat("zone_mob_speed", 0.19)));
        $this->configuredTargetRange = max(8.0, min(22.0, $nbt->getFloat("zone_mob_target_range", 15.0)));
        $this->configuredXp = max(1, $nbt->getInt("zone_mob_xp", 20));

        $health = max(10, $nbt->getInt("zone_mob_health", 30));
        $this->setMaxHealth($health);
        $this->setHealth($health);
        $this->setNameTagAlwaysVisible();
        $this->setNameTagVisible();
        $this->updateHealthNameTag();
    }

    public function saveNBT(): CompoundTag
    {
        $nbt = parent::saveNBT();
        $nbt->setString("zone_id", $this->zoneId);
        $nbt->setString("zone_mob_id", $this->mobId);
        $nbt->setString("zone_mob_name", $this->displayName);
        $nbt->setFloat("zone_mob_damage", $this->configuredDamage);
        $nbt->setFloat("zone_mob_speed", $this->configuredSpeed);
        $nbt->setFloat("zone_mob_target_range", $this->configuredTargetRange);
        $nbt->setInt("zone_mob_xp", $this->configuredXp);
        $nbt->setInt("zone_mob_health", (int) $this->getMaxHealth());
        return $nbt;
    }

    protected function entityBaseTick(int $tickDiff = 1): bool
    {
        if (!$this->isFlaggedForDespawn() && !ZoneMobManager::isInsideSpawnArea($this)) {
            $this->flagForDespawn();
            return true;
        }

        if (($this->ticksLived % 10) === 0) {
            $this->updateHealthNameTag();
        }

        return parent::entityBaseTick($tickDiff);
    }

    protected function getInitialSizeInfo(): EntitySizeInfo
    {
        return new EntitySizeInfo(1.8, 0.6);
    }

    public static function getNetworkTypeId(): string
    {
        return EntityIds::ZOMBIE;
    }

    public function getName(): string
    {
        return C::clean($this->displayName);
    }

    public function getZoneId(): string
    {
        return $this->zoneId;
    }

    public function getMobId(): string
    {
        return $this->mobId;
    }

    /**
     * @return Item[]
     */
    public function getDrops(): array
    {
        return ZoneMobManager::rollDrops($this->zoneId, $this->mobId);
    }

    public function getLevelXpDropAmount(): int
    {
        return $this->configuredXp;
    }

    public function getXpDropAmount(): int
    {
        return max(1, (int) floor($this->configuredXp / 3));
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
        return 15;
    }

    protected function updateHealthNameTag(): void
    {
        $health = max(0, (int) ceil($this->getHealth()));
        $max = max(1, (int) ceil($this->getMaxHealth()));
        $color = $health <= ($max * 0.3) ? "&c" : ($health <= ($max * 0.6) ? "&e" : "&a");

        $this->setNameTag(C::colorize(
            "&r" . $this->displayName . "\n&r{$color}HP &f{$health}&7/&f{$max}"
        ));
    }

    public function getTicksLived(): int
    {
        return $this->ticksLived;
    }
}

final class ZoneMobSkeleton extends ZoneMob {
    protected function getInitialSizeInfo(): EntitySizeInfo { return new EntitySizeInfo(1.99, 0.6); }
    public static function getNetworkTypeId(): string { return EntityIds::SKELETON; }
}

final class ZoneMobSpider extends ZoneMob {
    protected function getInitialSizeInfo(): EntitySizeInfo { return new EntitySizeInfo(0.9, 1.4); }
    public static function getNetworkTypeId(): string { return EntityIds::SPIDER; }
}

final class ZoneMobCreeper extends ZoneMob {
    protected function getInitialSizeInfo(): EntitySizeInfo { return new EntitySizeInfo(1.7, 0.6); }
    public static function getNetworkTypeId(): string { return EntityIds::CREEPER; }
}

final class ZoneMobBlaze extends ZoneMob {
    protected function getInitialSizeInfo(): EntitySizeInfo { return new EntitySizeInfo(1.8, 0.6); }
    public static function getNetworkTypeId(): string { return EntityIds::BLAZE; }
}

final class ZoneMobEnderman extends ZoneMob {
    protected function getInitialSizeInfo(): EntitySizeInfo { return new EntitySizeInfo(2.9, 0.6); }
    public static function getNetworkTypeId(): string { return EntityIds::ENDERMAN; }
}

final class ZoneMobPigman extends ZoneMob {
    protected function getInitialSizeInfo(): EntitySizeInfo { return new EntitySizeInfo(1.95, 0.6); }
    public static function getNetworkTypeId(): string { return EntityIds::ZOMBIE_PIGMAN; }
}

final class ZoneMobSlime extends ZoneMob {
    protected function getInitialSizeInfo(): EntitySizeInfo { return new EntitySizeInfo(1.04, 1.04); }
    public static function getNetworkTypeId(): string { return EntityIds::SLIME; }
}

final class ZoneMobMagmaCube extends ZoneMob {
    protected function getInitialSizeInfo(): EntitySizeInfo { return new EntitySizeInfo(1.04, 1.04); }
    public static function getNetworkTypeId(): string { return EntityIds::MAGMA_CUBE; }
}

final class ZoneMobIronGolem extends ZoneMob {
    protected function getInitialSizeInfo(): EntitySizeInfo { return new EntitySizeInfo(2.7, 1.4); }
    public static function getNetworkTypeId(): string { return EntityIds::IRON_GOLEM; }
}

final class ZoneMobSnowGolem extends ZoneMob {
    protected function getInitialSizeInfo(): EntitySizeInfo { return new EntitySizeInfo(1.9, 0.7); }
    public static function getNetworkTypeId(): string { return EntityIds::SNOW_GOLEM; }
}
