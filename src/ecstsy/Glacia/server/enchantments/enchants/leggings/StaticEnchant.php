<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\leggings;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class StaticEnchant implements EntityDamageByEntityEnchantmentHandler
{
    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) {
            return;
        }

        $victim = $event->getEntity();
        if (!$victim instanceof Player) {
            return;
        }

        $session = Loader::getPlayerManager()->getSession($victim);
        $cooldownSeconds = EnchantTuningManager::cooldownSeconds("static", $level, 5);
        $result = EnchantProcManager::attempt(
            $victim,
            "static",
            $level,
            EnchantTuningManager::chance("static", $level, 4.0),
            $cooldownSeconds,
            static fn() => max(0, (int) ($session?->getCooldown("static") ?? 0))
        );
        if (!$result->success || $session === null) {
            return;
        }

        $extraGappleCooldown = max(1, EnchantTuningManager::valueInt("static", "extra-gapple-cooldown-seconds", $level, 3));
        $remainingGappleCooldown = $session->getCooldown("golden_apple") ?? 0;
        $session->addCooldown("golden_apple", $remainingGappleCooldown + $extraGappleCooldown);
        EnchantProcManager::applyCooldownIfNeeded($result, static function (int $seconds) use ($session): void {
            $session->addCooldown("static", $seconds);
        });
        EnchantProcManager::debugAppliedValues($victim, $result, [
            "extra_gapple_cooldown_seconds" => $extraGappleCooldown,
            "gapple_cooldown_before" => $remainingGappleCooldown,
            "gapple_cooldown_after" => $remainingGappleCooldown + $extraGappleCooldown,
        ]);
    }
}
