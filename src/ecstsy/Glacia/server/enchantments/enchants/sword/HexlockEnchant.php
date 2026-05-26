<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\sword;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantCombatStateManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantDebugManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class HexlockEnchant implements EntityDamageByEntityEnchantmentHandler
{
    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) {
            return;
        }

        $damager = $event->getDamager();
        $victim = $event->getEntity();
        if (!$damager instanceof Player || !$victim instanceof Player) {
            return;
        }

        $forcedProc = BrotherhoodEnchant::consumeForcedProc($damager, "hexlock");
        $result = null;
        if (!$forcedProc) {
            $result = EnchantProcManager::attempt(
                $damager,
                "hexlock",
                $level,
                EnchantTuningManager::chance("hexlock", $level, 4.0),
                EnchantTuningManager::cooldownSeconds("hexlock", $level, 0)
            );
            if (!$result->success) {
                return;
            }
        }

        $duration = max(1, EnchantTuningManager::valueInt("hexlock", "duration-seconds", $level, 15));
        $procBonus = max(0, EnchantTuningManager::valueInt("hexlock", "proc-bonus-percent", $level, 5));
        EnchantCombatStateManager::disablePets($victim, $duration);
        EnchantCombatStateManager::grantProcBonus($damager, $duration, $procBonus);
        $brotherhoodLevel = \ecstsy\Glacia\server\enchantments\CustomEnchantmentManager::getLevel($damager->getInventory()->getItemInHand(), "brotherhood");
        BrotherhoodEnchant::tryDistributeFromMasteryProc($damager, $brotherhoodLevel, "hexlock");

        if ($result !== null) {
            EnchantProcManager::debugAppliedValues($damager, $result, [
                "duration_seconds" => $duration,
                "proc_bonus_percent" => $procBonus,
            ]);
        } else {
            EnchantDebugManager::logPassiveValues($damager, "hexlock", $level, [
                "forced_proc" => true,
                "duration_seconds" => $duration,
                "proc_bonus_percent" => $procBonus,
            ]);
        }
    }
}
