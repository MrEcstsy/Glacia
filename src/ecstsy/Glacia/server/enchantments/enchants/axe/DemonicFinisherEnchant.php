<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\axe;

use ecstsy\Glacia\entity\gkits\ForsakenChampion;
use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantDebugManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class DemonicFinisherEnchant implements EntityDamageByEntityEnchantmentHandler
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

        $item = $damager instanceof Player ? $damager->getInventory()->getItemInHand() : ($damager instanceof ForsakenChampion ? $damager->getCombatItem() : null);
        if ($item === null || !CustomEnchantmentManager::hasEnchantment($item, "demonicfinisher")) {
            return;
        }

        $thresholdHp = max(1.0, EnchantTuningManager::value("demonicfinisher", "trigger-health-threshold", $level, 8.0));
        if ($victim->getHealth() > $thresholdHp) {
            return;
        }

        $forcedProc = $damager instanceof Player && \ecstsy\Glacia\server\enchantments\enchants\sword\BrotherhoodEnchant::consumeForcedProc($damager, "demonicfinisher");
        $session = $damager instanceof Player ? PlayerManager::getInstance()->getSession($damager) : null;
        $cooldownSeconds = EnchantTuningManager::cooldownSeconds("demonicfinisher", $level, 6);

        $result = null;
        if (!$forcedProc) {
            $result = EnchantProcManager::attempt(
                $damager,
                "demonicfinisher",
                $level,
                EnchantTuningManager::chance("demonicfinisher", $level, 6.0),
                $cooldownSeconds,
                static fn() => max(0, (int) ($session?->getCooldown("demonicfinisher") ?? 0))
            );
            if (!$result->success) {
                return;
            }
        }

        $bonusDamage = max(0.0, EnchantTuningManager::value("demonicfinisher", "bonus-damage", $level, 3.6));
        $event->setBaseDamage($event->getBaseDamage() + $bonusDamage);

        if ($damager instanceof Player) {
            $brotherhoodLevel = CustomEnchantmentManager::getLevel($item, "brotherhood");
            \ecstsy\Glacia\server\enchantments\enchants\sword\BrotherhoodEnchant::tryDistributeFromMasteryProc($damager, $brotherhoodLevel, "demonicfinisher");
        }

        if ($result !== null) {
            EnchantProcManager::applyCooldownIfNeeded($result, static function (int $seconds) use ($session): void {
                $session?->addCooldown("demonicfinisher", $seconds);
            });
            EnchantProcManager::debugAppliedValues($damager, $result, [
                "trigger_health_threshold" => $thresholdHp,
                "bonus_damage" => $bonusDamage,
            ]);
        } else {
            EnchantDebugManager::logPassiveValues($damager, "demonicfinisher", $level, [
                "forced_proc" => true,
                "trigger_health_threshold" => $thresholdHp,
                "bonus_damage" => $bonusDamage,
            ]);
        }
    }
}
