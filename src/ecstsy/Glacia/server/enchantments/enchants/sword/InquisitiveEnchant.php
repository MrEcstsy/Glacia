<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\sword;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDeathEnchantmentHandler;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\player\Player;

final class InquisitiveEnchant implements EntityDeathEnchantmentHandler {

    public static function handle(EntityDeathEvent $event, CustomEnchantment $enchant, int $level): void
    {
        $entity = $event->getEntity();
        $cause = $entity->getLastDamageCause();

        if ($cause === null) {
            return;
        }

        if (!$cause instanceof EntityDamageByEntityEvent) {
            return;
        }

        $damager = $cause->getDamager();
        
        if (!$damager instanceof Player) {
            return; 
        }

        $multiplier = 1.0 + ($level * 0.25);

        $chance = match($level) {
            1 => 30,
            2 => 35,
            3 => 40,
            4 => 45,
            default => 0
        };

        if (mt_rand(1, 100) > $chance) {
            return;
        }

        $xp = $event->getXpDropAmount();
        if ($xp <= 0) {
            return;
        }

        $bonus = (int)floor($xp * $multiplier);
        $event->setXpDropAmount($bonus);
    }
}