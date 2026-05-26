<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity;

use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\Glacia\server\staff\HiddenStaffTargeting;
use ecstsy\MartianUtilities\entity\AdvancedHostileEntity;
use ecstsy\MartianUtilities\utils\ItemUtils;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Location;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\MobArmorEquipmentPacket;
use pocketmine\network\mcpe\protocol\MobEquipmentPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\inventory\ContainerIds;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStack;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat as C;

final class MummifiedRemnant extends AdvancedHostileEntity
{
    private string $ownerUuid = "";
    private string $targetUuid = "";
    private string $displayName = "&6Mummified Remnant";
    private int $despawnAtTick = PHP_INT_MAX;
    private float $configuredAttackDamage = 6.0;
    private float $configuredMoveSpeed = 0.26;
    private float $configuredAttackReach = 2.2;
    private float $configuredTargetRange = 20.0;
    private int $configuredAttackCooldown = 9;
    private Item $weapon;

    protected int $aiInterval = 1;

    protected function initEntity(CompoundTag $nbt): void
    {
        parent::initEntity($nbt);

        $this->weapon = VanillaItems::STONE_SWORD();
        $this->ownerUuid = strtolower($nbt->getString("mummy_owner_uuid", ""));
        $this->targetUuid = strtolower($nbt->getString("mummy_target_uuid", ""));
        $this->displayName = $nbt->getString("mummy_name", "&6Mummified Remnant");
        $this->configuredAttackDamage = max(1.0, $nbt->getFloat("mummy_damage", 6.0));
        $this->configuredMoveSpeed = max(0.12, min(0.40, $nbt->getFloat("mummy_speed", 0.26)));
        $this->configuredAttackReach = max(1.5, min(3.2, $nbt->getFloat("mummy_reach", 2.2)));
        $this->configuredTargetRange = max(8.0, min(32.0, $nbt->getFloat("mummy_target_range", 20.0)));
        $this->configuredAttackCooldown = max(5, $nbt->getInt("mummy_attack_cooldown", 9));

        $health = max(12, $nbt->getInt("mummy_health", 40));
        $this->setMaxHealth($health);
        $this->setHealth($health);
        $this->setNameTagAlwaysVisible();
        $this->setNameTagVisible();

        $weaponData = $nbt->getString("mummy_weapon", "");
        if ($weaponData !== "") {
            try {
                $this->weapon = ItemUtils::decodeItem($weaponData);
            } catch (\Throwable) {
                $this->weapon = VanillaItems::STONE_SWORD();
            }
        }

        $armorTag = $nbt->getCompoundTag("mummy_armor");
        if ($armorTag !== null) {
            foreach ([0, 1, 2, 3] as $slot) {
                $encoded = $armorTag->getString((string) $slot, "");
                if ($encoded === "") {
                    continue;
                }
                try {
                    $this->getArmorInventory()->setItem($slot, ItemUtils::decodeItem($encoded));
                } catch (\Throwable) {
                }
            }
        }

        $lifetimeTicks = max(20, $nbt->getInt("mummy_lifetime_ticks", 100));
        $this->despawnAtTick = $this->ticksLived + $lifetimeTicks;
        $this->updateHealthNameTag();
    }

    public function saveNBT(): CompoundTag
    {
        $nbt = parent::saveNBT();
        $nbt->setString("mummy_owner_uuid", $this->ownerUuid);
        $nbt->setString("mummy_target_uuid", $this->targetUuid);
        $nbt->setString("mummy_name", $this->displayName);
        $nbt->setInt("mummy_health", (int) $this->getMaxHealth());
        $nbt->setFloat("mummy_damage", $this->configuredAttackDamage);
        $nbt->setFloat("mummy_speed", $this->configuredMoveSpeed);
        $nbt->setFloat("mummy_reach", $this->configuredAttackReach);
        $nbt->setFloat("mummy_target_range", $this->configuredTargetRange);
        $nbt->setInt("mummy_attack_cooldown", $this->configuredAttackCooldown);
        $nbt->setInt("mummy_lifetime_ticks", max(20, $this->despawnAtTick - $this->ticksLived));
        $nbt->setString("mummy_weapon", ItemUtils::encodeItem($this->weapon));

        $armorTag = new CompoundTag();
        foreach ($this->getArmorInventory()->getContents() as $slot => $item) {
            if ($item->isNull()) {
                continue;
            }
            $armorTag->setString((string) $slot, ItemUtils::encodeItem($item));
        }
        $nbt->setTag("mummy_armor", $armorTag);

        return $nbt;
    }

    protected function getInitialSizeInfo(): EntitySizeInfo
    {
        return new EntitySizeInfo(1.95, 0.7);
    }

    public static function getNetworkTypeId(): string
    {
        return EntityIds::HUSK;
    }

    public function getCombatItem(): Item
    {
        return clone $this->weapon;
    }

    /**
     * @return Item[]
     */
    public function getCombatArmorContents(): array
    {
        return $this->getArmorInventory()->getContents();
    }

    public function getDrops(): array
    {
        return [];
    }

    public function getXpDropAmount(): int
    {
        return 0;
    }

    public function spawnTo(Player $player): void
    {
        parent::spawnTo($player);
        $this->syncEquipmentTo($player);
    }

    public function getName(): string
    {
        return C::clean($this->displayName);
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

    public function attack(EntityDamageEvent $source): void
    {
        if ($source instanceof \pocketmine\event\entity\EntityDamageByEntityEvent) {
            $damager = $source->getDamager();
            if ($damager instanceof Player) {
                $this->targetUuid = strtolower($damager->getUniqueId()->toString());
            }
        }

        parent::attack($source);
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

        return parent::entityBaseTick($tickDiff);
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
        return $this->configuredAttackDamage;
    }

    protected function getAttackCooldownTicks(): int
    {
        return $this->configuredAttackCooldown;
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

    private function syncEquipmentTo(Player $viewer): void
    {
        if (!$viewer->isConnected()) {
            return;
        }

        $converter = TypeConverter::getInstance();
        $viewer->getNetworkSession()->sendDataPacket(MobEquipmentPacket::create(
            $this->getId(),
            ItemStackWrapper::legacy($converter->coreItemStackToNet($this->weapon)),
            0,
            0,
            ContainerIds::INVENTORY
        ));
        $viewer->getNetworkSession()->sendDataPacket(MobArmorEquipmentPacket::create(
            $this->getId(),
            ItemStackWrapper::legacy($converter->coreItemStackToNet($this->getArmorInventory()->getHelmet())),
            ItemStackWrapper::legacy($converter->coreItemStackToNet($this->getArmorInventory()->getChestplate())),
            ItemStackWrapper::legacy($converter->coreItemStackToNet($this->getArmorInventory()->getLeggings())),
            ItemStackWrapper::legacy($converter->coreItemStackToNet($this->getArmorInventory()->getBoots())),
            new ItemStackWrapper(0, ItemStack::null())
        ));
    }
}
