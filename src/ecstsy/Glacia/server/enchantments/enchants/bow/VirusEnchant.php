<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\bow;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\projectile\Arrow;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\entity\Living;

final class VirusEnchant implements EntityDamageByEntityEnchantmentHandler {

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void {
        if ($event->isCancelled()) {
            return;
        }

        if ($event->getCause() !== EntityDamageEvent::CAUSE_PROJECTILE) {
            return;
        }

        $victim = $event->getEntity();
        $damager = $event->getDamager();

        if (!$victim instanceof Living) {
            return;
        }

        if (!$damager instanceof Arrow) {
            return;
        }

        // Max level 4
        $level = min(4, max(1, $level));

        // Simple first version:
        // Poison duration scales with level
        // lvl 1 = 2s, lvl 2 = 3s, lvl 3 = 4s, lvl 4 = 5s
        $duration = 40 + ($level * 20);

        // Keep amplifier low for balance
        // lvl 1-2 => Poison I
        // lvl 3-4 => Poison II
        $amplifier = $level >= 3 ? 1 : 0;

        $effects = $victim->getEffects();
        $existing = $effects->get(VanillaEffects::POISON());

        if ($existing !== null) {
            // If already poisoned, refresh only if this is stronger or longer
            $duration = max($duration, $existing->getDuration());
            $amplifier = max($amplifier, $existing->getAmplifier());
        }

        $effects->add(new EffectInstance(
            VanillaEffects::POISON(),
            $duration,
            $amplifier,
            false
        ));
    }
}
