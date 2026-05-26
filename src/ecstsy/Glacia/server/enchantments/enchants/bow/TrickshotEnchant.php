<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\bow;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use pocketmine\entity\Living;
use pocketmine\entity\projectile\Projectile;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;

final class TrickshotEnchant implements EntityDamageByEntityEnchantmentHandler
{
    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) return;
        $damager = $event->getDamager();
        $victim = $event->getEntity();
        $projectile = $damager instanceof Projectile
            ? $damager
            : (($event instanceof EntityDamageByChildEntityEvent && $event->getChild() instanceof Projectile) ? $event->getChild() : null);
        if (!$projectile instanceof Projectile || !$victim instanceof Living) return;

        $impactY = $projectile->getPosition()->y;
        $headY = $victim->getPosition()->y + ($victim->getEyeHeight() * 0.8);
        if ($impactY < $headY) {
            return;
        }

        $mult = EnchantTuningManager::getFloat("trickshot", "headshot-damage-multiplier", 3.0);
        $event->setBaseDamage($event->getBaseDamage() * $mult);
    }
}
