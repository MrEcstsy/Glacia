<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\items\armor\armorsets;

use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use pocketmine\item\Item;

final class YetiArmorSet {

    public static function applyEnchantments(Item $item, string $piece): void {
        switch (strtolower($piece)) {
            case 'helmet':
                self::apply($item, 'armored', mt_rand(3, 4));
                self::apply($item, 'drunk', mt_rand(2, 4));
                self::apply($item, 'angelic', mt_rand(3, 5));
                self::apply($item, 'tank', mt_rand(2, 4));
                self::apply($item, 'valor', mt_rand(3, 5));
                self::apply($item, 'endershift', mt_rand(1, 3));
                break;

            case 'chestplate':
                self::apply($item, 'armored', mt_rand(3, 4));
                self::apply($item, 'overload', mt_rand(1, 3));
                self::apply($item, 'angelic', mt_rand(3, 5));
                self::apply($item, 'tank', mt_rand(2, 4));
                break;

            case 'leggings':
                self::apply($item, 'armored', mt_rand(2, 4));
                self::apply($item, 'clarity', mt_rand(1, 3));
                self::apply($item, 'valor', mt_rand(3, 5));
                self::apply($item, 'angelic', mt_rand(3, 5));
                self::apply($item, 'tank', mt_rand(2, 4));
                self::apply($item, 'lavabound', 1);
                break;

            case 'boots':
                self::apply($item, 'armored', mt_rand(2, 4));
                self::apply($item, 'gears', mt_rand(1, 3));
                self::apply($item, 'valor', mt_rand(3, 5));
                self::apply($item, 'tank', mt_rand(2, 4));
                self::apply($item, 'bounce', mt_rand(1, 3));
                break;
            case "yeti_sword":
                self::apply($item, 'vampire', 3);
                self::apply($item, 'silence', 5);
        }
    }

    private static function apply(Item $item, string $enchant, int $level): void {
        $instance = CustomEnchantmentManager::getEnchantment($enchant);
        if ($instance === null) {
            return;
        }

        CustomEnchantmentManager::applyEnchantment($item, $instance, $level);
    }
}