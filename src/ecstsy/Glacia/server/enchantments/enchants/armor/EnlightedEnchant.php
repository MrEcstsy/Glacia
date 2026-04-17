<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\armor;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;

final class EnlightedEnchant implements EntityDamageByEntityEnchantmentHandler {

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void {
        if ($event->isCancelled()) {
            return;
        }

        $victim = $event->getEntity();
        if (!$victim instanceof Player) {
            return;
        }

        $chance = match ($level) {
            1 => 4,
            2 => 7,
            3 => 10,
            default => 0
        };

        if (mt_rand(1, 100) > $chance) {
            return;
        }

        $heal = 1 + ($level * 0.5);

        Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(
            function() use ($victim, $heal): void {
                if (!$victim->isAlive()) {
                    return;
                }

                $victim->setHealth(min($victim->getMaxHealth(), $victim->getHealth() + $heal));
            }
        ), 1);
    }
}