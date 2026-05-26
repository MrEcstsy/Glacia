<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\armor;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\AdvancedEnchantState;
use ecstsy\Glacia\server\enchantments\manager\AngelicHealingManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantCombatStateManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class AngelicEnchant implements EntityDamageByEntityEnchantmentHandler {

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void {
        if ($event->isCancelled()) {
            return;
        }

        $victim = $event->getEntity();
        if (!$victim instanceof Player) {
            return;
        }

        $stackSum = self::sumAngelicArmorLevels($victim);
        if ($stackSum < 1) {
            return;
        }

        if (
            AngelicHealingManager::hasActive($victim) ||
            AngelicHealingManager::isOnCooldown($victim) ||
            AdvancedEnchantState::isAnatomyBlocked($victim) ||
            EnchantCombatStateManager::areCustomEnchantsDisabled($victim)
        ) {
            return;
        }

        $chancePercent = EnchantTuningManager::chance("angelic", $stackSum, 10.0);
        $cooldownSeconds = EnchantTuningManager::cooldownSeconds("angelic", $stackSum, 8);
        $result = EnchantProcManager::attempt(
            $victim,
            "angelic",
            $stackSum,
            $chancePercent,
            $cooldownSeconds,
            static fn() => AngelicHealingManager::secondsRemaining($victim)
        );
        if (!$result->success) {
            return;
        }

        $healPerPulse = max(0.0, EnchantTuningManager::value("angelic", "heal-per-pulse", $level, 1.0));
        $durationTicks = max(1, (int) round(EnchantTuningManager::value("angelic", "duration", $level, 1.5) * 20));
        $intervalTicks = max(1, EnchantTuningManager::valueInt("angelic", "pulse-interval-ticks", $level, 20));

        AngelicHealingManager::start($victim, $level, $healPerPulse, $durationTicks, $intervalTicks, $cooldownSeconds);
        EnchantProcManager::debugAppliedValues($victim, $result, [
            "stack_sum" => $stackSum,
            "heal_per_pulse" => $healPerPulse,
            "duration_ticks" => $durationTicks,
            "interval_ticks" => $intervalTicks,
        ]);
    }

    /** Sum Angelic tiers across armor; proc chance scales with this, not healing strength. */
    private static function sumAngelicArmorLevels(Player $player): int {
        $sum = 0;
        foreach ($player->getArmorInventory()->getContents() as $piece) {
            if ($piece->isNull()) {
                continue;
            }
            foreach (CustomEnchantmentManager::getEnchantments($piece) as $name => $pieceLevel) {
                if ($name === 'angelic') {
                    $sum += max(0, $pieceLevel);
                    break;
                }
            }
        }
        return $sum;
    }
}
