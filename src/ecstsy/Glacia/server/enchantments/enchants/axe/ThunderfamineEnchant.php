<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\axe;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantDebugManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\items\armor\abilities\YijkiLightningStrike;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class ThunderfamineEnchant implements EntityDamageByEntityEnchantmentHandler
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

        $forcedProc = \ecstsy\Glacia\server\enchantments\enchants\sword\BrotherhoodEnchant::consumeForcedProc($damager, "thunderfamine");
        $result = null;
        if (!$forcedProc) {
            $result = EnchantProcManager::attempt(
                $damager,
                "thunderfamine",
                $level,
                EnchantTuningManager::chance("thunderfamine", $level, 4.0),
                EnchantTuningManager::cooldownSeconds("thunderfamine", $level, 0)
            );
            if (!$result->success) {
                return;
            }
        }

        $strikeDamage = max(1.0, EnchantTuningManager::value("thunderfamine", "strike-damage", $level, 4.0));
        $strikeCount = max(1, EnchantTuningManager::valueInt("thunderfamine", "strike-count", $level, 3));
        for ($i = 0; $i < $strikeCount; $i++) {
            YijkiLightningStrike::strike($damager, $victim->getPosition(), $strikeDamage, 3.0);
        }

        $brotherhoodLevel = CustomEnchantmentManager::getLevel($damager->getInventory()->getItemInHand(), "brotherhood");
        \ecstsy\Glacia\server\enchantments\enchants\sword\BrotherhoodEnchant::tryDistributeFromMasteryProc($damager, $brotherhoodLevel, "thunderfamine");
        $victim->getHungerManager()->setFood(0);
        $victim->getHungerManager()->setSaturation(0.0);

        if ($result !== null) {
            EnchantProcManager::debugAppliedValues($damager, $result, [
                "strike_damage" => $strikeDamage,
                "strike_count" => $strikeCount,
                "target_food_after" => $victim->getHungerManager()->getFood(),
            ]);
        } else {
            EnchantDebugManager::logPassiveValues($damager, "thunderfamine", $level, [
                "forced_proc" => true,
                "strike_damage" => $strikeDamage,
                "strike_count" => $strikeCount,
                "target_food_after" => $victim->getHungerManager()->getFood(),
            ]);
        }
    }
}
