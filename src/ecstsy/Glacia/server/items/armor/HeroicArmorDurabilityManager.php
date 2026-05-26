<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\items\armor;

use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\items\lore\ItemLoreLayout;
use pocketmine\color\Color;
use pocketmine\inventory\ArmorInventory;
use pocketmine\item\Armor;
use pocketmine\item\Durable;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\utils\TextFormat as C;

final class HeroicArmorDurabilityManager
{
    private const TAG_MAX = "heroic_dura_max";
    private const TAG_CURRENT = "heroic_dura_current";

    public static function isHeroicArmor(Item $item, ?string $requiredPiece = null): bool
    {
        if ($item->isNull()) {
            return false;
        }

        $glacia = $item->getNamedTag()->getCompoundTag("Glacia");
        if ($glacia === null) {
            return false;
        }
        // Heroic Upgrade state only.
        // Stormcaller/Yijki/etc use `customArmor=1` for set tracking and should not count as "heroic".
        $isHeroic = $glacia->getInt("heroicArmor", 0) === 1;
        if (!$isHeroic) {
            return false;
        }

        $piece = strtolower($glacia->getString("armorPiece", ""));
        if (!in_array($piece, ["helmet", "chestplate", "leggings", "boots"], true)) {
            return false;
        }

        return $requiredPiece === null ? true : $piece === $requiredPiece;
    }

    public static function initializeItem(Durable $item): void
    {
        if (!self::isHeroicArmor($item)) {
            return;
        }

        $root = $item->getNamedTag();
        $glacia = $root->getCompoundTag("Glacia");
        if ($glacia === null) {
            return;
        }

        $glacia->setInt("heroicArmor", 1);

        $piece = strtolower($glacia->getString("armorPiece", ""));
        $max = self::getConfiguredMaxBuffer($piece);
        if ($max <= 0) {
            return;
        }

        $current = $glacia->getInt(self::TAG_CURRENT, -1);
        $storedMax = max(0, $glacia->getInt(self::TAG_MAX, 0));
        if ($storedMax <= 0) {
            $glacia->setInt(self::TAG_MAX, $max);
        } elseif ($max > $storedMax) {
            $glacia->setInt(self::TAG_MAX, $max);
            if ($current >= 0) {
                $current = min($max, $current + ($max - $storedMax));
            }
        }
        if ($current < 0) {
            $glacia->setInt(self::TAG_CURRENT, $max);
        } else {
            $glacia->setInt(self::TAG_CURRENT, max(0, min($max, $current)));
        }

        $root->setTag("Glacia", $glacia);
        $item->setNamedTag($root);
        self::syncLore($item);
    }

    public static function upgradeItemToHeroic(Durable $item): Durable
    {
        $item = self::convertArmorToLeatherBase($item);
        $root = $item->getNamedTag();
        $glacia = $root->getCompoundTag("Glacia") ?? new \pocketmine\nbt\tag\CompoundTag();
        $piece = self::resolveArmorPiece($item);
        if ($piece !== "") {
            $glacia->setString("armorPiece", $piece);
        }
        $glacia->setInt("heroicArmor", 1);
        $root->setTag("Glacia", $glacia);
        $item->setNamedTag($root);

        if ($item instanceof Armor) {
            $set = strtolower($glacia->getString("armorSet", ""));
            if ($set === "") {
                // Default heroic tint for non-set armor: deep red.
                $item->setCustomColor(new Color(170, 20, 20));
            }
        }

        self::ensureHeroicStrengthLore($item);
        self::initializeItem($item);
        return $item;
    }

    public static function absorbObservedDamageIncrease(Durable $item, int $oldDamage): bool
    {
        if (!self::isHeroicArmor($item)) {
            return false;
        }

        self::initializeItem($item);

        $currentDamage = $item->getDamage();
        if ($currentDamage <= $oldDamage) {
            return false;
        }

        $delta = $currentDamage - $oldDamage;
        $refund = self::rollDurabilityRefund($item) ? 1 : 0;
        $effectiveDelta = max(0, $delta - $refund);
        $absorbed = self::consumeBuffer($item, $effectiveDelta);

        if ($absorbed <= 0 && $refund <= 0) {
            return false;
        }

        $item->setDamage(max($oldDamage, $currentDamage - $absorbed - $refund));
        self::syncLore($item);
        return true;
    }

    public static function applyVirtualDurabilityDamage(Durable $item, int $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        if (!self::isHeroicArmor($item)) {
            $item->applyDamage($amount);
            return;
        }

        self::initializeItem($item);
        $absorbed = self::consumeBuffer($item, $amount);
        $remaining = max(0, $amount - $absorbed);

        if ($remaining > 0) {
            $item->applyDamage($remaining);
        }
        self::syncLore($item);
    }

    public static function syncAfterRepair(Durable $item, int $oldDamage, int $newDamage): void
    {
        if (!self::isHeroicArmor($item)) {
            return;
        }

        self::initializeItem($item);

        if ($newDamage >= $oldDamage) {
            return;
        }

        $refill = $oldDamage - $newDamage;

        $root = $item->getNamedTag();
        $glacia = $root->getCompoundTag("Glacia");
        if ($glacia === null) {
            return;
        }

        $max = max(0, $glacia->getInt(self::TAG_MAX, 0));
        if ($max <= 0) {
            return;
        }

        $current = max(0, $glacia->getInt(self::TAG_CURRENT, 0));
        $current = min($max, $current + $refill);
        if ($newDamage <= 0) {
            $current = $max;
        }

        $glacia->setInt(self::TAG_CURRENT, $current);
        $root->setTag("Glacia", $glacia);
        $item->setNamedTag($root);
        self::syncLore($item);
    }

    /**
     * Fully refills heroic buffer when depleted but vanilla durability is untouched (repair commands, etc.).
     */
    public static function replenishHeroicBufferFully(Durable $item): bool
    {
        if (!self::isHeroicArmor($item)) {
            return false;
        }

        self::initializeItem($item);

        $root = $item->getNamedTag();
        $glacia = $root->getCompoundTag("Glacia");
        if ($glacia === null) {
            return false;
        }

        $max = max(0, $glacia->getInt(self::TAG_MAX, 0));
        if ($max <= 0) {
            return false;
        }

        $current = max(0, $glacia->getInt(self::TAG_CURRENT, 0));
        if ($current >= $max) {
            return false;
        }

        $glacia->setInt(self::TAG_CURRENT, $max);
        $root->setTag("Glacia", $glacia);
        $item->setNamedTag($root);
        self::syncLore($item);
        return true;
    }

    /**
     * Repairs heroic buffer by a fraction of max buffer size (similar to shaving a % off vanilla durability).
     * Returns true when the buffer was increased.
     */
    public static function replenishHeroicBufferFractionOfMax(Durable $item, float $fraction): bool
    {
        if ($fraction <= 0.0 || !self::isHeroicArmor($item)) {
            return false;
        }

        self::initializeItem($item);

        $root = $item->getNamedTag();
        $glacia = $root->getCompoundTag("Glacia");
        if ($glacia === null) {
            return false;
        }

        $max = max(0, $glacia->getInt(self::TAG_MAX, 0));
        if ($max <= 0) {
            return false;
        }

        $current = max(0, $glacia->getInt(self::TAG_CURRENT, 0));
        if ($current >= $max) {
            return false;
        }

        $repairUnits = max(1, (int) ceil($max * $fraction));
        $after = min($max, $current + $repairUnits);
        if ($after <= $current) {
            return false;
        }

        $glacia->setInt(self::TAG_CURRENT, $after);
        $root->setTag("Glacia", $glacia);
        $item->setNamedTag($root);
        self::syncLore($item);
        return true;
    }

    private static function consumeBuffer(Durable $item, int $amount): int
    {
        $root = $item->getNamedTag();
        $glacia = $root->getCompoundTag("Glacia");
        if ($glacia === null) {
            return 0;
        }

        $current = max(0, $glacia->getInt(self::TAG_CURRENT, 0));
        if ($current <= 0) {
            return 0;
        }

        $used = min($current, $amount);
        $glacia->setInt(self::TAG_CURRENT, $current - $used);
        $root->setTag("Glacia", $glacia);
        $item->setNamedTag($root);
        return $used;
    }

    private static function syncLore(Durable $item): void
    {
        $root = $item->getNamedTag();
        $glacia = $root->getCompoundTag("Glacia");
        if ($glacia === null) {
            return;
        }

        $max = max(0, $glacia->getInt(self::TAG_MAX, 0));
        $current = max(0, $glacia->getInt(self::TAG_CURRENT, 0));
        if ($max <= 0) {
            return;
        }

        $piece = strtolower($glacia->getString("armorPiece", ""));
        $armorValue = self::getHeroicArmorValue($piece);

        $linePrefix = C::colorize("&r&7Heroic Durability: ");
        $newLine = C::colorize("&r&7Heroic Durability: {$current}/{$max}");

        $armorValueLine = $armorValue > 0
            ? C::colorize("&r&7+{$armorValue} Armor Value")
            : null;

        $lore = self::stripPlainNumericDurabilityLines($item->getLore());
        $replaced = false;
        $armorValueReplaced = false;
        foreach ($lore as $i => $line) {
            if (str_starts_with((string) $line, $linePrefix)) {
                $lore[$i] = $newLine;
                $replaced = true;
                break;
            }
        }

        foreach ($lore as $i => $line) {
            $clean = C::clean((string) $line);

            if ($armorValueLine !== null && !$armorValueReplaced && str_contains($clean, "Armor Value")) {
                $lore[$i] = $armorValueLine;
                $armorValueReplaced = true;
                continue;
            }
        }

        if (!$replaced) {
            $lore[] = "";
            $lore[] = $newLine;
        }

        if (!$armorValueReplaced && $armorValueLine !== null) {
            $lore[] = $armorValueLine;
        }

        $item->setLore($lore);
        ItemLoreLayout::normalizeForCombatGear($item);
    }

    /**
     * Removes standalone "{max} Durability" lore lines; heroic ratio already encodes max.
     *
     * @param list<string> $lore
     * @return list<string>
     */
    private static function stripPlainNumericDurabilityLines(array $lore): array
    {
        $out = [];
        foreach ($lore as $line) {
            $clean = C::clean((string) $line);
            if (preg_match('/^[\d,]+\s+Durability$/', $clean) === 1) {
                continue;
            }
            $out[] = $line;
        }

        return $out;
    }

    private static function getHeroicArmorValue(string $piece): int
    {
        return match ($piece) {
            "helmet" => 3,
            "chestplate" => 8,
            "leggings" => 6,
            "boots" => 3,
            default => 0,
        };
    }

    private static function getConfiguredMaxBuffer(string $piece): int
    {
        return match ($piece) {
            "helmet" => max(0, EnchantTuningManager::getInt("heroicarmor", "helmet-buffer-max", 420)),
            "chestplate" => max(0, EnchantTuningManager::getInt("heroicarmor", "chestplate-buffer-max", 720)),
            "leggings" => max(0, EnchantTuningManager::getInt("heroicarmor", "leggings-buffer-max", 620)),
            "boots" => max(0, EnchantTuningManager::getInt("heroicarmor", "boots-buffer-max", 480)),
            default => 0,
        };
    }

    private static function rollDurabilityRefund(Durable $item): bool
    {
        $root = $item->getNamedTag();
        $glacia = $root->getCompoundTag("Glacia");
        if ($glacia === null) {
            return false;
        }

        $piece = strtolower($glacia->getString("armorPiece", ""));
        $chance = match ($piece) {
            "helmet" => EnchantTuningManager::getInt("heroicarmor", "helmet-durability-refund-chance", 45),
            "chestplate" => EnchantTuningManager::getInt("heroicarmor", "chestplate-durability-refund-chance", 65),
            "leggings" => EnchantTuningManager::getInt("heroicarmor", "leggings-durability-refund-chance", 55),
            "boots" => EnchantTuningManager::getInt("heroicarmor", "boots-durability-refund-chance", 50),
            default => 0,
        };

        return $chance > 0 && mt_rand(1, 100) <= min(100, $chance);
    }

    private static function convertArmorToLeatherBase(Durable $item): Durable
    {
        if (!$item instanceof Armor) {
            return $item;
        }

        $slot = $item->getArmorSlot();
        $target = match ($slot) {
            ArmorInventory::SLOT_HEAD => VanillaItems::LEATHER_CAP(),
            ArmorInventory::SLOT_CHEST => VanillaItems::LEATHER_TUNIC(),
            ArmorInventory::SLOT_LEGS => VanillaItems::LEATHER_PANTS(),
            ArmorInventory::SLOT_FEET => VanillaItems::LEATHER_BOOTS(),
            default => null,
        };

        if (!$target instanceof Durable) {
            return $item;
        }
        if ($target->getTypeId() === $item->getTypeId()) {
            return $item;
        }

        $target->setCount($item->getCount());
        $target->setCustomName($item->getCustomName());
        $target->setLore($item->getLore());
        $target->setNamedTag(clone $item->getNamedTag());
        $target->setDamage(min($item->getDamage(), $target->getMaxDurability()));
        foreach ($item->getEnchantments() as $enchantment) {
            $target->addEnchantment($enchantment);
        }

        return $target;
    }

    private static function resolveArmorPiece(Durable $item): string
    {
        if (!$item instanceof Armor) {
            return "";
        }

        return match ($item->getArmorSlot()) {
            ArmorInventory::SLOT_HEAD => "helmet",
            ArmorInventory::SLOT_CHEST => "chestplate",
            ArmorInventory::SLOT_LEGS => "leggings",
            ArmorInventory::SLOT_FEET => "boots",
            default => "",
        };
    }

    private static function ensureHeroicStrengthLore(Durable $item): void
    {
        $oldLine = C::colorize("&r&cThis item is stronger than diamond!");
        $newLine = C::colorize("&r&cThis armor is stronger than diamond.");

        $lore = $item->getLore();
        foreach ($lore as $i => $line) {
            if (C::clean($line) === C::clean($oldLine)) {
                $lore[$i] = $newLine;
                $item->setLore($lore);
                return;
            }
            if (C::clean($line) === C::clean($newLine)) {
                return;
            }
        }

        $lore[] = $newLine;
        $item->setLore($lore);
        ItemLoreLayout::normalizeForCombatGear($item);
    }
}

