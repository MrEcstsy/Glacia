<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\axe;

use ecstsy\Glacia\entity\boss\BossEntity;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantDebugManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
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

        $baseDamage = $event->getBaseDamage();
        $multiplier = max(1.0, EnchantTuningManager::value("bossslayer", "damage-multiplier", $level, 1.05));
        $event->setBaseDamage($baseDamage * $multiplier);
        EnchantDebugManager::logPassiveValues($event->getDamager(), "bossslayer", $level, [
            "damage_multiplier" => $multiplier,
            "base_damage" => $baseDamage,
            "final_damage" => $event->getBaseDamage(),
        ]);
    }
}
