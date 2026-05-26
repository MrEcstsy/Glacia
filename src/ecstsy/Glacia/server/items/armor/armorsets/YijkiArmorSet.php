<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\items\armor\armorsets;

use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use pocketmine\item\Item;

final class YijkiArmorSet {

    public static function applyEnchantments(Item $item, string $piece): void {
        switch (strtolower($piece)) {
            case 'helmet':
                self::apply($item, 'armored', mt_rand(1, 2));
                self::apply($item, 'drunk', mt_rand(1, 2));
                self::apply($item, 'angelic', mt_rand(1, 2));
                self::apply($item, 'tank', mt_rand(1, 2));
                self::apply($item, 'valor', mt_rand(1, 2));
                break;

            case 'chestplate':
                self::apply($item, 'armored', mt_rand(1, 2));
                self::apply($item, 'overload', 1);
                self::apply($item, 'angelic', mt_rand(1, 2));
                self::apply($item, 'tank', mt_rand(1, 2));
                break;

            case 'leggings':
                self::apply($item, 'armored', mt_rand(1, 2));
                self::apply($item, 'clarity', 1);
                self::apply($item, 'valor', mt_rand(1, 2));
                self::apply($item, 'angelic', mt_rand(1, 2));
                self::apply($item, 'tank', mt_rand(1, 2));
                self::apply($item, 'lavabound', 1);
                break;

            case 'boots':
                self::apply($item, 'armored', 1);
                self::apply($item, 'gears', 1);
                self::apply($item, 'valor', mt_rand(1, 2));
                self::apply($item, 'tank', mt_rand(1, 2));
                self::apply($item, 'bounce', 1);
                break;
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
