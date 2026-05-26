<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\all;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantments;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
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

        $fallbackChance = max(0, min(100, $level * EnchantTuningManager::getInt("reforged", "save-chance-percent-per-level-mult", 10)));
        $chance = max(0, min(100, EnchantTuningManager::getLevelInt("reforged", "chance-by-level", $level, $fallbackChance)));

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
