<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\all;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantments;
use pocketmine\item\Durable;
use pocketmine\player\Player;

final class ReforgedLogic {

    public static function tryReforge(Player $player, Durable $item): bool {
        /** @var CustomEnchantment|null $reforge */
        $reforge = CustomEnchantments::getEnchantmentByName("Reforged");
        if ($reforge === null) {
            return false; 
        }

        if (!CustomEnchantmentManager::hasEnchantment($item, "Reforged")) {
            return false;
        }

        $level = CustomEnchantmentManager::getLevel($item, "Reforged");
        if ($level <= 0) {
            return false;
        }

        $current = $item->getDamage();
        $max = $item->getMaxDurability();

        if ($current + 1 !== $max) {
            return false;
        }

        $chance = $level * 10;

        if (mt_rand(1, 100) > $chance) {
            return false; 
        }

        $enchants = CustomEnchantmentManager::getEnchantments($item);

        unset($enchants["Reforged"]);

        if (!empty($enchants)) {
            $names = array_keys($enchants);
            $chosenName = $names[array_rand($names)];

            $chosenEnchant = CustomEnchantments::getEnchantmentByName($chosenName);

            if ($chosenEnchant instanceof CustomEnchantment) {
                CustomEnchantmentManager::removeEnchantment($item, $chosenEnchant);
            }
        }

        $item->setDamage(0);

        return true;
    }

}