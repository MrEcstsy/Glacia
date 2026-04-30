<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\bow;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use pocketmine\entity\Living;
use pocketmine\entity\projectile\Projectile;
use pocketmine\event\entity\EntityDamageByEntityEvent;

final class TrickshotEnchant implements EntityDamageByEntityEnchantmentHandler
{
    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) return;
        $damager = $event->getDamager();
        $victim = $event->getEntity();
        if (!$damager instanceof Projectile || !$victim instanceof Living) return;

        $impactY = $damager->getPosition()->y;
        $headY = $victim->getPosition()->y + ($victim->getEyeHeight() * 0.8);
        if ($impactY < $headY) {
            return;
        }

        $event->setBaseDamage($event->getBaseDamage() * 3.0);
    }
}
