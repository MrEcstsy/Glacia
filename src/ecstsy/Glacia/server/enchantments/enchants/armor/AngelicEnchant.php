<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\armor;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\AngelicHealingManager;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class AngelicEnchant implements EntityDamageByEntityEnchantmentHandler {

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void {
        if ($event->isCancelled()) {
            return;
        }

        $victim = $event->getEntity();
        if (!$victim instanceof Player) {
            return;
        }

        if (AngelicHealingManager::hasActive($victim) || AngelicHealingManager::isOnCooldown($victim)) {
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

        AngelicHealingManager::start($victim, $level);
    }
}