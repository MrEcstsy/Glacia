<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\axe;

use ecstsy\Glacia\entity\gkits\ForsakenChampion;
use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\enchantments\manager\ProcLuckManager;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class DemonicFinisherEnchant implements EntityDamageByEntityEnchantmentHandler
{
    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) return;

        $damager = $event->getDamager();
        $victim = $event->getEntity();
        if (!$damager instanceof Living || !$victim instanceof Living) return;

        $item = $damager instanceof Player ? $damager->getInventory()->getItemInHand() : ($damager instanceof ForsakenChampion ? $damager->getCombatItem() : null);
        if ($item === null || !CustomEnchantmentManager::hasEnchantment($item, "demonicfinisher")) return;

        $thresholdHp = max(1.0, EnchantTuningManager::getFloat("demonicfinisher", "trigger-health-threshold", 8.0));
        if ($victim->getHealth() > $thresholdHp) return;

        $forcedProc = $damager instanceof Player && \ecstsy\Glacia\server\enchantments\enchants\sword\BrotherhoodEnchant::consumeForcedProc($damager, "demonicfinisher");
        if (!$forcedProc) {
            $chance = EnchantTuningManager::getLevelInt("demonicfinisher", "chance-by-level", $level, 4 + ($level * 2));
            if (!ProcLuckManager::roll($damager, "demonicfinisher", $chance, 2, 12)) return;
        }

        $session = $damager instanceof Player ? PlayerManager::getInstance()->getSession($damager) : null;
        $cooldown = max(0, EnchantTuningManager::getInt("demonicfinisher", "cooldown", 6));
        if (!$forcedProc && $session !== null && $cooldown > 0 && $session->isOnCooldown("demonicfinisher")) return;

        $bonusDamage = EnchantTuningManager::getLevelFloat("demonicfinisher", "bonus-damage-by-level", $level, min(10.0, 2.0 + ($level * 1.6)));
        $event->setBaseDamage($event->getBaseDamage() + $bonusDamage);

        if ($damager instanceof Player) {
            $brotherhoodLevel = CustomEnchantmentManager::getLevel($item, "brotherhood");
            \ecstsy\Glacia\server\enchantments\enchants\sword\BrotherhoodEnchant::tryDistributeFromMasteryProc($damager, $brotherhoodLevel, "demonicfinisher");
        }

        if (!$forcedProc && $session !== null && $cooldown > 0) {
            $session->addCooldown("demonicfinisher", $cooldown);
        }
    }
}

