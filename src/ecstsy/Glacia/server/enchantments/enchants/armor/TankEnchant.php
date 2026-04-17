<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\armor;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\item\Axe;
use pocketmine\player\Player;

final class TankEnchant implements EntityDamageByEntityEnchantmentHandler {

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void {
        if ($event->isCancelled()) {
            return;
        }

        $victim = $event->getEntity();
        if (!$victim instanceof Player) {
            return;
        }

        $damager = $event->getDamager();
        if (!$damager instanceof Player) {
            return;
        }

        $weapon = $damager->getInventory()->getItemInHand();
        if (!$weapon instanceof Axe) {
            return;
        }

        $reductionPercent = match($level) {
            1 => 2,
            2 => 4,
            3 => 6,
            4 => 8,
            5 => 10,
            default => 0
        };

        if ($reductionPercent <= 0) {
            return;
        }

        $base = $event->getBaseDamage();
        if ($base <= 0) {
            return;
        }

        $newDamage = $base * (1 - ($reductionPercent / 100));
        $event->setBaseDamage($newDamage);
    }
}