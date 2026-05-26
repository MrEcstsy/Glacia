<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\chestplate;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantCombatStateManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantDebugManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class FlourishEnchant implements EntityDamageByEntityEnchantmentHandler
{
    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) {
            return;
        }

        $victim = $event->getEntity();
        $attacker = $event->getDamager();
        if (!$victim instanceof Player || !$attacker instanceof Player) {
            return;
        }

        $chance = EnchantTuningManager::chance("flourish", $level, 3.0);
        $cooldownSeconds = EnchantTuningManager::cooldownSeconds("flourish", $level, 0);
        $result = EnchantProcManager::attempt($victim, "flourish", $level, $chance, $cooldownSeconds);
        if ($result->success) {
            $windowSeconds = max(1, EnchantTuningManager::valueInt("flourish", "window-seconds", $level, 6));
            EnchantCombatStateManager::registerIncomingAttacker($victim, $attacker, $windowSeconds);
            EnchantProcManager::debugAppliedValues($victim, $result, [
                "window_seconds" => $windowSeconds,
                "attacker" => $attacker->getName(),
            ]);
        }

        $activeAttackers = EnchantCombatStateManager::countRecentAttackers($victim);
        if ($activeAttackers <= 0) {
            return;
        }

        $reductionPercent = max(0.0, EnchantTuningManager::value("flourish", "reduction-per-attacker-percent", $level, 7.0) * $activeAttackers);
        $reductionPercent = min(
            max(0.0, EnchantTuningManager::value("flourish", "max-reduction-percent", $level, 70.0)),
            $reductionPercent
        );
        if ($reductionPercent <= 0.0) {
            return;
        }

        $beforeDamage = $event->getBaseDamage();
        $event->setBaseDamage($beforeDamage * max(0.0, 1.0 - ($reductionPercent / 100.0)));
        EnchantDebugManager::logPassiveValues($victim, "flourish", $level, [
            "active_attackers" => $activeAttackers,
            "reduction_percent" => $reductionPercent,
            "base_damage" => $beforeDamage,
            "final_damage" => $event->getBaseDamage(),
        ]);
    }
}
