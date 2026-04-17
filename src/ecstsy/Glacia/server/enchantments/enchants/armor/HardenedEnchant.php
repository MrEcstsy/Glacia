<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\armor;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;
use pocketmine\item\Durable;

final class HardenedEnchant implements EntityDamageByEntityEnchantmentHandler {

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void {
        if ($event->isCancelled()) {
            return;
        }

        $victim = $event->getEntity();
        if (!$victim instanceof Player) {
            return;
        }

        // % chance to reduce durability damage per level
        $chance = $level * 10; // lvl 1 = 10%

        $armorInventory = $victim->getArmorInventory();

        foreach ($armorInventory->getContents() as $slot => $armor) {
            if (!$armor instanceof Durable || $armor->isNull()) {
                continue;
            }

            $enchants = CustomEnchantmentManager::getEnchantments($armor);

            if (!isset($enchants[$enchant->getName()])) {
                continue;
            }

            if (mt_rand(1, 100) > $chance) {
                continue;
            }

            $currentDamage = $armor->getDamage();

            if ($currentDamage > 0) {
                $armor->setDamage($currentDamage - 1);
                $armorInventory->setItem($slot, $armor);
            }
        }
    }
}