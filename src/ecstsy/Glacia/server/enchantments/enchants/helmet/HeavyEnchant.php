<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\helmet;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantDebugManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use pocketmine\entity\Living;
use pocketmine\entity\projectile\Arrow;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;

final class HeavyEnchant implements EntityDamageByEntityEnchantmentHandler {
    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) {
            return;
        }

        $victim = $event->getEntity();
        $damager = $event->getDamager();
        $projectile = $damager instanceof Arrow
            ? $damager
            : (($event instanceof EntityDamageByChildEntityEvent && $event->getChild() instanceof Arrow) ? $event->getChild() : null);

        if ($event->getCause() !== EntityDamageEvent::CAUSE_PROJECTILE) {
            return;
        }

        if (!$projectile instanceof Arrow) {
            return;
        }

        $reduction = max(0.0, EnchantTuningManager::value("heavy", "damage-reduction-fraction", $level, 0.02));
        $base = $event->getBaseDamage();
        if ($base <= 0) {
            return;
        }

        $newDamage = $base * (1 - $reduction);
        $event->setBaseDamage($newDamage);

        if ($victim instanceof Living) {
            EnchantDebugManager::logPassiveValues($victim, "heavy", $level, [
                "base_damage" => $base,
                "reduction_fraction" => $reduction,
                "final_damage" => $newDamage,
            ]);
        }
    }
}
