<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\boots;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageEnchantmentHandler;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;

final class EnderWalkerEnchant implements EntityDamageEnchantmentHandler {

    public static function handle(EntityDamageEvent $event, CustomEnchantment $enchant, int $level): void {
        if ($event->isCancelled()) {
            return;
        }

        $victim = $event->getEntity();
        if (!$victim instanceof Player) {
            return;
        }

        if ($event->getCause() !== EntityDamageEvent::CAUSE_MAGIC) {
            return;
        }

        $event->cancel();

        if ($level >= 4 && mt_rand(1, 100) <= (5 * $level)) {
            Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(
                function() use ($victim): void {
                    if (!$victim->isAlive()) {
                        return;
                    }

                    $victim->setHealth(min($victim->getMaxHealth(), $victim->getHealth() + 1));
                }
            ), 1);
        }
    }
}