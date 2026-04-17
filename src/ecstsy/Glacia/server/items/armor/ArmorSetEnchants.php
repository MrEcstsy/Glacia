<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\items\armor;

use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\items\armor\armorsets\PhantomArmorSet;
use ecstsy\Glacia\server\items\armor\armorsets\YetiArmorSet;
use ecstsy\Glacia\server\items\armor\armorsets\YijkiArmorSet;
use pocketmine\item\Item;

final class ArmorSetEnchants {

    public static function apply(Item $item, string $set, string $piece): void {
        match (strtolower($set)) {
            'phantom' => PhantomArmorSet::applyEnchantments($item, $piece),
            'yeti' => YetiArmorSet::applyEnchantments($item, $piece),
            'yijki' => YijkiArmorSet::applyEnchantments($item, $piece),
            default => null
        };
    }
}