<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\sword;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;

final class LifestealEnchant implements EntityDamageByEntityEnchantmentHandler {

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void {
        if ($event->isCancelled()) {
            return;
        }

        $damager = $event->getDamager();
        if (!$damager instanceof Player) {
            return;
        }

        $chance = match ($level) {
            1 => 4,
            2 => 6,
            3 => 8,
            4 => 10,
            5 => 12,
            default => 0
        };

        if (mt_rand(1, 100) > $chance) {
            return;
        }

        $heal = 0.5 + (0.5 * $level);

        Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(
            function() use ($damager, $heal): void {
                if (!$damager->isAlive()) {
                    return;
                }

                $damager->setHealth(min($damager->getMaxHealth(), $damager->getHealth() + $heal));
            }
        ), 1);
    }
}