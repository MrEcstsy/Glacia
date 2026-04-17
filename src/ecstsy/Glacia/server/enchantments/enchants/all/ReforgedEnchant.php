<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\all;

use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use pocketmine\item\Item;
use pocketmine\player\Player;
use ecstsy\Glacia\server\enchantments\EnchantManager;
use pocketmine\item\Durable;

final class ReforgedEnchant {

    public const ID = "reforged";

    public static function tryReforged(Player $player, Item $item) : Item {
        if(!$item instanceof Durable){
            return $item;
        }

        $enchant = CustomEnchantmentManager::getEnchantment(self::ID);
        if($enchant === null){
            return $item;
        }

        $level = CustomEnchantmentManager::getLevel($item, self::ID);
        if($level <= 0){
            return $item;
        }

        $maxDur = $item->getMaxDurability();
        $current = $item->getDamage();

        if($current + 1 < $maxDur){
            return $item; 
        }

        $chance = $level * 10;
        if(mt_rand(1, 100) > $chance){
            return $item; 
        }

        $item->setDamage(0);

        $enchants = CustomEnchantmentManager::getEnchantments($item);

        if(count($enchants) > 1){
            unset($enchants[self::ID]);
            $keys = array_keys($enchants);
            $remove = $keys[array_rand($keys)];
            CustomEnchantmentManager::removeEnchantment($item, CustomEnchantmentManager::getEnchantment($remove));
        }

        $player->sendMessage("§b§l* REFORGED saved your item but consumed a random enchant!");

        return $item;
    }
}
