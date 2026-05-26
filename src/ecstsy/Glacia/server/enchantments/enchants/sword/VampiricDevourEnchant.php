<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\sword;

use ecstsy\Glacia\entity\gkits\ForsakenChampion;
use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\AdvancedEnchantState;
use ecstsy\Glacia\server\enchantments\manager\EnchantDebugManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class VampiricDevourEnchant implements EntityDamageByEntityEnchantmentHandler
{
    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) {
            return;
        }

        $damager = $event->getDamager();
        if (!$damager instanceof Living) {
            return;
        }

        $item = $damager instanceof Player ? $damager->getInventory()->getItemInHand() : ($damager instanceof ForsakenChampion ? $damager->getCombatItem() : null);
        if ($item === null || !CustomEnchantmentManager::hasEnchantment($item, "vampiricdevour")) {
            return;
        }

        $forcedProc = $damager instanceof Player && BrotherhoodEnchant::consumeForcedProc($damager, "vampiricdevour");
        $session = $damager instanceof Player ? PlayerManager::getInstance()->getSession($damager) : null;
        $cooldownSeconds = EnchantTuningManager::cooldownSeconds("vampiricdevour", $level, 25);

        $result = null;
        if (!$forcedProc) {
            $result = EnchantProcManager::attempt(
                $damager,
                "vampiricdevour",
                $level,
                EnchantTuningManager::chance("vampiricdevour", $level, 1.0),
                $cooldownSeconds,
                static fn() => max(0, (int) ($session?->getCooldown("vampiricdevour") ?? 0))
            );
            if (!$result->success) {
                return;
            }
        }

        if ($damager instanceof Player && AdvancedEnchantState::isAnatomyBlocked($damager)) {
            return;
        }

        $beforeHealth = $damager->getHealth();
        if ((bool) EnchantTuningManager::getValue("vampiricdevour", "full-heal", true)) {
            $damager->setHealth($damager->getMaxHealth());
        } else {
            $heal = max(0.0, EnchantTuningManager::value("vampiricdevour", "heal", $level, $damager->getMaxHealth()));
            $damager->setHealth(min($damager->getMaxHealth(), $beforeHealth + $heal));
        }

        if ($damager instanceof Player) {
            $brotherhoodLevel = CustomEnchantmentManager::getLevel($item, "brotherhood");
            BrotherhoodEnchant::tryDistributeFromMasteryProc($damager, $brotherhoodLevel, "vampiricdevour");
        }

        if ($result !== null) {
            EnchantProcManager::applyCooldownIfNeeded($result, static function (int $seconds) use ($session): void {
                $session?->addCooldown("vampiricdevour", $seconds);
            });
            EnchantProcManager::debugAppliedValues($damager, $result, [
                "health_before" => $beforeHealth,
                "health_after" => $damager->getHealth(),
            ]);
        } else {
            EnchantDebugManager::logPassiveValues($damager, "vampiricdevour", $level, [
                "forced_proc" => true,
                "health_before" => $beforeHealth,
                "health_after" => $damager->getHealth(),
            ]);
        }
    }
}
