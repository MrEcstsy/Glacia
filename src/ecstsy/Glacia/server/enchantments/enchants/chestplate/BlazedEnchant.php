<?php 

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\chestplate;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class BlazedEnchant implements EntityDamageByEntityEnchantmentHandler {

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void {
        if ($event->isCancelled()) {
            return;
        }

        $victim = $event->getEntity();     
        $attacker = $event->getDamager(); 

        if (!$victim instanceof Player) return;
        if (!$attacker instanceof Living) return;

        $chestplate = $victim->getArmorInventory()->getChestplate();
        if ($chestplate === null || $chestplate->isNull()) return;

        if (!CustomEnchantmentManager::hasEnchantment($chestplate, "blazed")) {
            return;
        }

        $level = CustomEnchantmentManager::getLevel($chestplate, "blazed");

        $chance = $level * 20; 
        if (mt_rand(1, 100) > $chance) {
            return;
        }

        $attacker->setOnFire(3 + $level);
    }
}