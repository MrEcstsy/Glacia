<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\sword;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\item\Bow;
use pocketmine\item\Sword;
use pocketmine\player\Player;

final class GreatswordEnchant implements EntityDamageByEntityEnchantmentHandler {

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void {
        if ($event->isCancelled()) {
            return;
        }

        $damager = $event->getDamager();
        $victim = $event->getEntity();

        if (!$damager instanceof Player || !$victim instanceof Player) {
            return;
        }

        $held = $damager->getInventory()->getItemInHand();
        if (!$held instanceof Sword) {
            return;
        }

        $victimHeld = $victim->getInventory()->getItemInHand();
        if (!$victimHeld instanceof Bow) {
            return;
        }

        $base = $event->getBaseDamage();
        if ($base <= 0) {
            return;
        }

        $multiplier = 1 + (0.02 * $level); // 2% per level
        $event->setBaseDamage($base * $multiplier);
    }
}