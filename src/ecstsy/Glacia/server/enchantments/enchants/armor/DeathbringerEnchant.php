<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\armor;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use pocketmine\event\entity\EntityDamageByEntityEvent;

final class DeathbringerEnchant implements EntityDamageByEntityEnchantmentHandler {

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void {
        if ($event->isCancelled()) {
            return;
        }

        $c = match ($level) {
            1 => 2,
            2 => 4,
            3 => 6,
            default => 0
        };
        $chance = EnchantTuningManager::getLevelInt("deathbringer", "double-damage-proc-percent-by-level", $level, $c);

        if (mt_rand(1, 100) <= $chance) {
            $mult = EnchantTuningManager::getFloat("deathbringer", "double-damage-hit-multiplier", 2.0);
            $event->setBaseDamage($event->getBaseDamage() * $mult);
        }
    }
}