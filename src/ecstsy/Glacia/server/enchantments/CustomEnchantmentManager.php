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
        $maxLevel = $enchantment->getMaxLevel();
        if ($level > $maxLevel) {
            $level = $maxLevel;
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

    public static function hasEnchantment(Item $item, string $enchantName): bool {
        $root = $item->getNamedTag();
        $tag = $root->getCompoundTag("GlaciaCES");
        return $tag !== null && $tag->getTag($enchantName) !== null;
    }
    
    public static function getLevel(Item $item, string $enchantName): int {
        $root = $item->getNamedTag();
        $tag = $root->getCompoundTag("GlaciaCES");
        if ($tag === null || !$tag->getTag($enchantName)) {
            return 0;
        }
        return $tag->getInt($enchantName);
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