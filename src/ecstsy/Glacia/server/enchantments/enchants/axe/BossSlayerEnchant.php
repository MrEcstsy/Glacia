<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\axe;

use ecstsy\Glacia\entity\boss\BossEntity;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use pocketmine\event\entity\EntityDamageByEntityEvent;

final class BossSlayerEnchant implements EntityDamageByEntityEnchantmentHandler {

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void {
        if ($event->isCancelled()) {
            return;
        }

        $victim = $event->getEntity();

        if (!$victim instanceof BossEntity) {
            return;
        }

        $event->setBaseDamage($event->getBaseDamage() * (1 + (0.05 * $level)));
    }
}