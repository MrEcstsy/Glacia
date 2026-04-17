<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\armor;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\item\Sword;
use pocketmine\player\Player;

final class ValorEnchant implements EntityDamageByEntityEnchantmentHandler {

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void {
        if ($event->isCancelled()) {
            return;
        }

        $victim = $event->getEntity();
        if (!$victim instanceof Player) {
            return;
        }

        if ($event->getBaseDamage() <= 0) {
            return;
        }

        $held = $victim->getInventory()->getItemInHand();
        if (!$held instanceof Sword) {
            return;
        }

        $chance = match($level) {
            1 => 4,
            2 => 9,
            3 => 13,
            4 => 16,
            5 => 21,
            default => 0
        };

        if (mt_rand(1, 100) > $chance) {
            return;
        }

        $reductionPercent = match($level) {
            1 => 4.5,
            2 => 9.0,
            3 => 13.5,
            4 => 18.0,
            5 => 22.5,
            default => 0
        };

        $base = $event->getBaseDamage();
        $new = $base * (1 - ($reductionPercent / 100));
        $event->setBaseDamage($new);
    }
}