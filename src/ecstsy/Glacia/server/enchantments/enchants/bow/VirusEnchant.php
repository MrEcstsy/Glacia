<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\bow;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\enchantments\manager\ProcLuckManager;
use ecstsy\Glacia\server\enchantments\manager\VirusManager;
use pocketmine\entity\Living;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\projectile\Projectile;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\player\Player;

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
        $projectile = $damager instanceof Projectile
            ? $damager
            : (($event instanceof EntityDamageByChildEntityEvent && $event->getChild() instanceof Projectile) ? $event->getChild() : null);

        if (!$victim instanceof Living) {
            return;
        }

        if (!$projectile instanceof Projectile) {
            return;
        }

        $owner = $projectile->getOwningEntity();
        if (!$owner instanceof Player) {
            return;
        }

        $level = min(4, max(1, $level));
        $chance = EnchantTuningManager::getLevelInt("virus", "chance-by-level", $level, 8 + ($level * 4));
        if (!ProcLuckManager::roll($owner, "virus", $chance, 1, 5)) {
            return;
        }

        $infectionSeconds = EnchantTuningManager::getLevelInt("virus", "infection-seconds-by-level", $level, 3 + $level);
        $damageMultiplier = EnchantTuningManager::getLevelFloat("virus", "damage-multiplier-by-level", $level, 1.15 + ($level * 0.15));
        VirusManager::infect($victim, $infectionSeconds * 20, $damageMultiplier);

        $duration = EnchantTuningManager::getLevelInt("virus", "poison-seconds-by-level", $level, 2 + $level) * 20;
        $amplifier = max(0, EnchantTuningManager::getLevelInt("virus", "poison-amplifier-by-level", $level, $level >= 3 ? 1 : 0));

        $effects = $victim->getEffects();
        $existing = $effects->get(VanillaEffects::POISON());

        if ($existing !== null) {
            $duration = max($duration, $existing->getDuration());
            $amplifier = max($amplifier, $existing->getAmplifier());
        }

        $effects->add(new EffectInstance(
            VanillaEffects::POISON(),
            $duration,
            $amplifier,
            false
        ));

        $regenStripChance = EnchantTuningManager::getLevelInt("virus", "regeneration-strip-chance-by-level", $level, 5 + ($level * 5));
        if ($effects->has(VanillaEffects::REGENERATION()) && mt_rand(1, 100) <= $regenStripChance) {
            $effects->remove(VanillaEffects::REGENERATION());
        }
    }
}
