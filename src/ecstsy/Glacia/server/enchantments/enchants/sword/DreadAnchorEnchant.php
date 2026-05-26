<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\sword;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use pocketmine\entity\Living;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\entity\EntityDamageByEntityEvent;

final class DreadAnchorEnchant implements EntityDamageByEntityEnchantmentHandler
{
    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) {
            return;
        }

        $damager = $event->getDamager();
        $victim = $event->getEntity();
        if (!$damager instanceof Living || !$victim instanceof Living) {
            return;
        }

        $result = EnchantProcManager::attempt(
            $damager,
            "dreadanchor",
            $level,
            EnchantTuningManager::chance("dreadanchor", $level, 0.25),
            EnchantTuningManager::cooldownSeconds("dreadanchor", $level, 0)
        );
        if (!$result->success) {
            return;
        }

        $damage = max(0.0, EnchantTuningManager::value("dreadanchor", "damage", $level, 5.0));
        $slowSeconds = max(0.1, EnchantTuningManager::value("dreadanchor", "slowness-seconds", $level, 3.0));
        $slowAmplifier = min(4, max(0, EnchantTuningManager::valueInt("dreadanchor", "slowness-amplifier", $level, 4)));

        if ($damage > 0.0) {
            $event->setBaseDamage($event->getBaseDamage() + $damage);
        }

        $victim->getEffects()->add(new EffectInstance(
            VanillaEffects::SLOWNESS(),
            max(1, (int) round($slowSeconds * 20)),
            $slowAmplifier,
            false
        ));

        EnchantProcManager::debugAppliedValues($damager, $result, [
            "damage" => $damage,
            "slowness_seconds" => $slowSeconds,
            "slowness_amplifier" => $slowAmplifier,
        ]);
    }
}
