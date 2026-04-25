<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\helmet;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use pocketmine\entity\projectile\Arrow;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;

final class HeavyEnchant implements EntityDamageByEntityEnchantmentHandler {
    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) {
            return;
        }

        $victim = $event->getEntity();
        $damager = $event->getDamager();

        if ($event->getCause() !== EntityDamageEvent::CAUSE_PROJECTILE) {
            return;
        }

        if (!$damager instanceof Arrow) {
            return;
        }

        $reduction = 0.02 * $level;  

        $base = $event->getBaseDamage();
        if ($base <= 0) {
            return;
        }

        $newDamage = $base * (1 - $reduction);
        $event->setBaseDamage($newDamage);
    }
}
