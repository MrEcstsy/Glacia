<?php 

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments;

use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\item\Item;
use pocketmine\nbt\tag\CompoundTag;

final class CustomEnchantmentManager
{

    public static function applyEnchantment(Item $item, CustomEnchantment $enchantment, int $level): void
    {
        self::setEnchantmentLevel($item, $enchantment, $level, true);
    }

    public static function setEnchantmentLevel(Item $item, CustomEnchantment $enchantment, int $level, bool $respectMaxLevel = true): void
    {
        $level = max(1, $level);
        if ($respectMaxLevel) {
            $level = min($level, $enchantment->getMaxLevel());
        }

        $root = $item->getNamedTag();
        $enchTag = $root->getCompoundTag("GlaciaCES");

        if ($enchTag === null) {
            $enchTag = new CompoundTag();
        }

        $enchantmentName = $enchantment->getName();
        $enchTag->setInt($enchantmentName, $level);
        $root->setTag("GlaciaCES", $enchTag);

        GlaciaUtils::updateGlowEffect($item);
    }

    public static function removeEnchantment(Item $item, CustomEnchantment $enchantment): void {
        $root = $item->getNamedTag();
        $enchTag = $root->getCompoundTag("GlaciaCES");

        if ($enchTag === null) {
            return;
        }

        $enchantmentName = $enchantment->getName();
        $enchTag->removeTag($enchantmentName);

        if ($enchTag->count() === 0) {
            $root->removeTag("GlaciaCES");
        } else {
            $root->setTag("GlaciaCES", $enchTag);
        }

        GlaciaUtils::updateGlowEffect($item);
    }

    public static function getEnchantments(Item $item): array
    {
        $root = $item->getNamedTag();
        $enchTag = $root->getCompoundTag("GlaciaCES");
        if ($enchTag === null) {
            return [];
        }

        $result = [];
        foreach ($enchTag->getValue() as $name => $tag) {
            $level = $tag->getValue();
            $custom = CustomEnchantments::getEnchantmentByName((string)$name);
            if ($custom !== null) {
                $result[$custom->getName()] = $level;
            }
        }
        return $result;
    }

    /**
     * Raw count of entries under {@code GlaciaCES} (includes legacy id keys not mapped by {@see getEnchantments}).
     */
    public static function countGlaciaCesEntries(Item $item): int
    {
        $enchTag = $item->getNamedTag()->getCompoundTag("GlaciaCES");
        if ($enchTag === null) {
            return 0;
        }

        $n = 0;
        foreach ($enchTag->getValue() as $_) {
            $n++;
        }

        return $n;
    }

    public static function hasEnchantment(Item $item, string $enchantName): bool {
        $root = $item->getNamedTag();
        $tag = $root->getCompoundTag("GlaciaCES");
        if ($tag === null) {
            return false;
        }
        if ($tag->getTag($enchantName) !== null) {
            return true;
        }
        $legacy = CustomEnchantments::getLegacyIdForName($enchantName);
        return $legacy !== null && $tag->getTag($legacy) !== null;
    }
    
    public static function getLevel(Item $item, string $enchantName): int {
        $root = $item->getNamedTag();
        $tag = $root->getCompoundTag("GlaciaCES");
        if ($tag === null) {
            return 0;
        }
        if ($tag->getTag($enchantName) !== null) {
            return $tag->getInt($enchantName);
        }
        $legacy = CustomEnchantments::getLegacyIdForName($enchantName);
        if ($legacy !== null && $tag->getTag($legacy) !== null) {
            return $tag->getInt($legacy);
        }
        return 0;
    }

    // hmmm.... ._.
    public static function getEnchantment(string $name): ?CustomEnchantment {
        return CustomEnchantments::getEnchantmentByName($name);
    }

    public function sortEnchantmentsByRarity(array $enchantments): array
    {
        usort($enchantments, function (CustomEnchantmentInstance $enchantmentA, CustomEnchantmentInstance $enchantmentB) {
            return $enchantmentB->getEnchantment()->getRarity() - $enchantmentA->getEnchantment()->getRarity();
        });
        return $enchantments;
    }
}
