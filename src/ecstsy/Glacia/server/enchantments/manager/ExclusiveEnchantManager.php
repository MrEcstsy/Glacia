<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\manager;

use ecstsy\Glacia\server\enchantments\CustomEnchantments;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\items\lore\ItemLoreLayout;
use ecstsy\Glacia\server\watchdogs\ChunkWatchdogManager;
use pocketmine\inventory\ArmorInventory;
use pocketmine\item\Item;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class ExclusiveEnchantManager
{
    private const USE_TAG = "GlaciaExclusiveUses";
    private const USE_LORE_PREFIX = "Exclusive Use:";

    /** @var string[] */
    private const EXCLUSIVE_ENCHANTS = [
        "mummification",
        "leviathan",
        "gloryhunter",
        "armageddon",
        "aristocratic",
        "triggerhappy",
    ];

    public static function isExclusiveEnchant(string $enchantId): bool
    {
        return in_array(strtolower($enchantId), self::EXCLUSIVE_ENCHANTS, true);
    }

    /**
     * @return string[]
     */
    public static function getExclusiveEnchantments(Item $item): array
    {
        $ids = [];
        foreach (CustomEnchantmentManager::getEnchantments($item) as $id => $level) {
            if ($level > 0 && self::isExclusiveEnchant($id)) {
                $ids[] = strtolower($id);
            }
        }

        return $ids;
    }

    public static function getDefaultUses(string $enchantId): int
    {
        return max(0, EnchantTuningManager::getInt($enchantId, "default-uses", 0));
    }

    public static function getRemainingUses(Item $item, string $enchantId): int
    {
        $enchantId = strtolower($enchantId);
        if (!self::isExclusiveEnchant($enchantId)) {
            return 0;
        }

        $tag = $item->getNamedTag()->getCompoundTag(self::USE_TAG);
        if ($tag !== null && $tag->getTag($enchantId) !== null) {
            return max(0, $tag->getInt($enchantId));
        }

        return self::getDefaultUses($enchantId);
    }

    public static function addUsesToItem(Item $item, int $amount): Item
    {
        $amount = max(1, $amount);
        $exclusiveEnchants = self::getExclusiveEnchantments($item);
        if ($exclusiveEnchants === []) {
            return $item;
        }

        $root = $item->getNamedTag();
        $useTag = $root->getCompoundTag(self::USE_TAG) ?? new CompoundTag();
        foreach ($exclusiveEnchants as $enchantId) {
            if (self::getDefaultUses($enchantId) <= 0 && $useTag->getTag($enchantId) === null) {
                continue;
            }
            $current = self::getRemainingUses($item, $enchantId);
            $useTag->setInt($enchantId, $current + $amount);
        }
        if ($useTag->count() > 0) {
            $root->setTag(self::USE_TAG, $useTag);
            $item->setNamedTag($root);
        }

        return self::refreshItemLore($item);
    }

    public static function consumeHeldUse(Player $player, string $enchantId): bool
    {
        $item = $player->getInventory()->getItemInHand();
        if (!CustomEnchantmentManager::hasEnchantment($item, $enchantId)) {
            return false;
        }

        $updated = self::consumeUse($item, $enchantId, $player);
        if ($updated === null) {
            return false;
        }

        $player->getInventory()->setItemInHand(self::refreshItemLore($updated));
        return true;
    }

    public static function consumeArmorUse(Player $player, string $enchantId, int $slot = ArmorInventory::SLOT_LEGS): bool
    {
        $item = $player->getArmorInventory()->getItem($slot);
        if (!CustomEnchantmentManager::hasEnchantment($item, $enchantId)) {
            return false;
        }

        $updated = self::consumeUse($item, $enchantId, $player);
        if ($updated === null) {
            return false;
        }

        $player->getArmorInventory()->setItem($slot, self::refreshItemLore($updated));
        return true;
    }

    public static function consumeAnyArmorUse(Player $player, string $enchantId): bool
    {
        $inventory = $player->getArmorInventory();
        foreach ($inventory->getContents() as $slot => $item) {
            if (!CustomEnchantmentManager::hasEnchantment($item, $enchantId)) {
                continue;
            }

            $updated = self::consumeUse($item, $enchantId, $player);
            if ($updated === null) {
                continue;
            }

            $inventory->setItem($slot, self::refreshItemLore($updated));
            return true;
        }

        return false;
    }

    private static function consumeUse(Item $item, string $enchantId, ?Player $player = null): ?Item
    {
        $root = $item->getNamedTag();
        $useTag = $root->getCompoundTag(self::USE_TAG);
        if ($useTag === null && self::getDefaultUses($enchantId) <= 0) {
            return $item;
        }

        $remaining = self::getRemainingUses($item, $enchantId);
        if ($remaining <= 0) {
            return null;
        }

        if ($player !== null && ChunkWatchdogManager::shouldPreserveExclusiveUse($player)) {
            return self::refreshItemLore($item);
        }

        $useTag ??= new CompoundTag();
        $useTag->setInt(strtolower($enchantId), $remaining - 1);
        $root->setTag(self::USE_TAG, $useTag);
        $item->setNamedTag($root);

        return self::refreshItemLore($item);
    }

    public static function refreshItemLore(Item $item): Item
    {
        $exclusiveEnchants = self::getExclusiveEnchantments($item);
        if ($exclusiveEnchants === []) {
            return $item;
        }

        $lore = array_values(array_filter(
            $item->getLore(),
            static fn(string $line): bool => !str_starts_with(C::clean($line), self::USE_LORE_PREFIX)
        ));

        if ($lore !== [] && C::clean((string) end($lore)) !== "") {
            $lore[] = "";
        }

        foreach ($exclusiveEnchants as $enchantId) {
            $level = max(1, CustomEnchantmentManager::getLevel($item, $enchantId));
            $remaining = max(0, self::getRemainingUses($item, $enchantId));
            $displayName = CustomEnchantments::getEnchantmentByName($enchantId)?->getName() ?? ucfirst($enchantId);
            $lore[] = C::colorize("&r&6Exclusive Use: &f{$displayName} {$level} &7({$remaining} uses left)");
        }

        $item->setLore($lore);
        return ItemLoreLayout::normalizeForCombatGear($item);
    }
}
