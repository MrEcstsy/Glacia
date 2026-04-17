<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\armor;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use pocketmine\event\entity\EntityDamageByEntityEvent;

final class DeathbringerEnchant implements EntityDamageByEntityEnchantmentHandler {

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void {
        if ($event->isCancelled()) {
            return;
        }

        $chance = match ($level) {
            1 => 2,
            2 => 4,
            3 => 6,
            default => 0
        };

        if (mt_rand(1, 100) <= $chance) {
            $event->setBaseDamage($event->getBaseDamage() * 2);
        }
    }
}