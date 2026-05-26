<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\gkits;

use ecstsy\MartianUtilities\entity\AdvancedHostileEntity;
use ecstsy\MartianUtilities\utils\ItemUtils;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\utils\TextFormat as C;

final class ForsakenChampion extends AdvancedHostileEntity
{
    private float $configuredAttackDamage = 8.0;
    private float $configuredMoveSpeed = 0.24;
    private float $configuredAttackReach = 2.2;
    private float $configuredTargetRange = 24.0;
    private float $configuredJumpHeight = 0.42;
    private int $configuredAttackCooldown = 9;
    private string $warlordId = "warlord";
    private string $displayName = "Forsaken Champion";
    /** @var Item[] */
    private array $customDrops = [];
    private Item $weapon;

    protected int $aiInterval = 1;

    protected function initEntity(CompoundTag $nbt): void
    {
        parent::initEntity($nbt);

        $this->weapon = VanillaItems::STONE_SWORD();

        $this->configuredAttackDamage = max(1.0, (float) $nbt->getInt("gkit_damage", 8));
        $this->configuredMoveSpeed = max(0.1, (float) $nbt->getFloat("gkit_move_speed", 0.24));
        $this->configuredAttackReach = max(1.5, (float) $nbt->getFloat("gkit_attack_reach", 2.2));
        $this->configuredTargetRange = max(8.0, (float) $nbt->getFloat("gkit_target_range", 24.0));
        $this->configuredJumpHeight = max(0.3, (float) $nbt->getFloat("gkit_jump_height", 0.42));
        $this->configuredAttackCooldown = max(4, (int) $nbt->getInt("gkit_attack_cooldown", 9));
        $health = max(20, (int) $nbt->getInt("gkit_health", 60));
        $this->setMaxHealth($health);
        $this->setHealth($health);
        $this->setNameTagAlwaysVisible();
        $this->warlordId = strtolower($nbt->getString("gkit_warlord", "warlord"));
        $this->displayName = $nbt->getString("gkit_name", "Forsaken Champion");
        $this->updateHealthNameTag();

        $rewardData = $nbt->getString("gkit_reward", "");
        if ($rewardData !== "") {
            try {
                $this->customDrops[] = ItemUtils::decodeItem($rewardData);
            } catch (\Throwable) {
            }
        }

        $weaponData = $nbt->getString("gkit_weapon", "");
        if ($weaponData !== "") {
            try {
                $this->weapon = ItemUtils::decodeItem($weaponData);
            } catch (\Throwable) {
                $this->weapon = VanillaItems::STONE_SWORD();
            }
        }

        $armorTag = $nbt->getCompoundTag("gkit_armor");
        if ($armorTag !== null) {
            $armorInv = $this->getArmorInventory();
            foreach ([0, 1, 2, 3] as $slot) {
                $value = $armorTag->getString((string) $slot, "");
                if ($value === "") {
                    continue;
                }

                try {
                    $armorInv->setItem($slot, ItemUtils::decodeItem($value));
                } catch (\Throwable) {
                }
            }
        }
    }

    public function saveNBT(): CompoundTag
    {
        $nbt = parent::saveNBT();
        $nbt->setInt("gkit_damage", (int) $this->configuredAttackDamage);
        $nbt->setFloat("gkit_move_speed", $this->configuredMoveSpeed);
        $nbt->setFloat("gkit_attack_reach", $this->configuredAttackReach);
        $nbt->setFloat("gkit_target_range", $this->configuredTargetRange);
        $nbt->setFloat("gkit_jump_height", $this->configuredJumpHeight);
        $nbt->setInt("gkit_attack_cooldown", $this->configuredAttackCooldown);
        $nbt->setString("gkit_warlord", $this->warlordId);
        $nbt->setInt("gkit_health", (int) $this->getMaxHealth());
        $nbt->setString("gkit_name", $this->displayName);

        if ($this->customDrops !== []) {
            $nbt->setString("gkit_reward", ItemUtils::encodeItem($this->customDrops[0]));
        }

        if (isset($this->weapon) && !$this->weapon->isNull()) {
            $nbt->setString("gkit_weapon", ItemUtils::encodeItem($this->weapon));
        }

        $armorTag = new CompoundTag();
        foreach ($this->getArmorInventory()->getContents() as $slot => $item) {
            if ($item->isNull()) {
                continue;
            }
            $armorTag->setString((string) $slot, ItemUtils::encodeItem($item));
        }
        $nbt->setTag("gkit_armor", $armorTag);
        return $nbt;
    }

    protected function getInitialSizeInfo(): EntitySizeInfo
    {
        return new EntitySizeInfo(2.0, 0.7);
    }

    public static function getNetworkTypeId(): string
    {
        return EntityIds::WITHER_SKELETON;
    }

    public function getName(): string
    {
        return C::clean($this->displayName);
    }

    public function getDrops(): array
    {
        return $this->customDrops;
    }

    public function getCombatItem(): Item
    {
        return clone $this->weapon;
    }

    public function getCombatArmorContents(): array
    {
        return $this->getArmorInventory()->getContents();
    }

    public function getXpDropAmount(): int
    {
        return 20;
    }

    public function getWarlordId(): string
    {
        return $this->warlordId;
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

    public function jump(): void
    {
        if (!$this->onGround) {
            return;
        }

        $this->motion->y = $this->configuredJumpHeight;
    }

    protected function entityBaseTick(int $tickDiff = 1): bool
    {
        if (($this->ticksLived % 10) === 0) {
            $this->updateHealthNameTag();
        }

        return parent::entityBaseTick($tickDiff);
    }

    protected function updateHealthNameTag(): void
    {
        $health = max(0, (int) floor($this->getHealth()));
        $max = max(1, (int) ceil($this->getMaxHealth()));
        $segments = 10;
        $filled = (int) floor(($health / $max) * $segments);
        $bar = str_repeat("❤", $filled) . "&7" . str_repeat("❤", max(0, $segments - $filled));

        $this->setNameTag(
            C::colorize($this->displayName . "\n&r&c{$bar}")
        );
        $this->setNameTagVisible(true);
        $this->setNameTagAlwaysVisible(true);
    }
}
