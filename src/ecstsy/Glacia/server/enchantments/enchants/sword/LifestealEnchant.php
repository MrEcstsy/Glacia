<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\sword;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\ProcLuckManager;
use ecstsy\Glacia\server\items\runes\RuneManager;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;

final class LifestealEnchant implements EntityDamageByEntityEnchantmentHandler {

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void {
        if ($event->isCancelled()) {
            return;
        }

        $damager = $event->getDamager();
        if (!$damager instanceof Living) {
            return;
        }

        $chance = match ($level) {
            1 => 1,
            2 => 1,
            3 => 2,
            4 => 2,
            5 => 2,
            default => 0
        };

        if (!ProcLuckManager::roll($damager, "lifesteal", $chance, 1, 1)) {
            return;
        }

        $heal = match ($level) {
            1 => 1.0,
            2 => 1.5,
            3 => 2.0,
            4 => 2.5,
            5 => 3.0,
            default => 1.0
        };

        $victim = $event->getEntity();
        if ($victim instanceof Player) {
            $heal *= max(0.0, 1.0 - (RuneManager::getLifestealMitigationPercent($victim) / 100));
        }

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
