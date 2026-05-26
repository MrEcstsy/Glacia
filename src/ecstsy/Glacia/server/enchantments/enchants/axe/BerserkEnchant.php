<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\axe;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class BerserkEnchant implements EntityDamageByEntityEnchantmentHandler {

    private const COOLDOWN_KEY = 'enchant.berserk.cooldown';

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void {
        if ($event->isCancelled()) {
            return;
        }

        $damager = $event->getDamager();
        if (!$damager instanceof Player) {
            return;
        }

        $session = Loader::getPlayerManager()->getSession($damager);
        $cooldownSeconds = EnchantTuningManager::cooldownSeconds("berserk", $level, 20);
        $result = EnchantProcManager::attempt(
            $damager,
            "berserk",
            $level,
            EnchantTuningManager::chance("berserk", $level, 4.0),
            $cooldownSeconds,
            static fn() => max(0, (int) ($session?->getCooldown(self::COOLDOWN_KEY) ?? 0))
        );
        if (!$result->success) {
            return;
        }

        $strengthDuration = max(1, EnchantTuningManager::valueInt("berserk", "strength-duration-ticks", $level, 80));
        $strengthAmplifier = max(0, EnchantTuningManager::valueInt("berserk", "strength-amplifier", $level, 0));
        $fatigueDuration = max(1, EnchantTuningManager::valueInt("berserk", "mining-fatigue-duration-ticks", $level, 100));
        $fatigueAmplifier = max(0, EnchantTuningManager::valueInt("berserk", "mining-fatigue-amplifier", $level, 0));

        $damager->getEffects()->add(new EffectInstance(
            VanillaEffects::STRENGTH(),
            $strengthDuration,
            $strengthAmplifier,
            false
        ));
        $damager->getEffects()->add(new EffectInstance(
            VanillaEffects::MINING_FATIGUE(),
            $fatigueDuration,
            $fatigueAmplifier,
            false
        ));

        EnchantProcManager::applyCooldownIfNeeded($result, static function (int $seconds) use ($session): void {
            $session?->addCooldown(self::COOLDOWN_KEY, $seconds);
        });
        EnchantProcManager::debugAppliedValues($damager, $result, [
            "strength_duration_ticks" => $strengthDuration,
            "strength_amplifier" => $strengthAmplifier,
            "fatigue_duration_ticks" => $fatigueDuration,
            "fatigue_amplifier" => $fatigueAmplifier,
        ]);
    }
}
