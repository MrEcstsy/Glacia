<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\axe;

use ecstsy\Glacia\server\items\armor\abilities\YijkiLightningStrike;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\enchantments\manager\ProcLuckManager;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class ThunderfamineEnchant implements EntityDamageByEntityEnchantmentHandler
{
    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) return;
        $damager = $event->getDamager();
        $victim = $event->getEntity();
        if (!$damager instanceof Player || !$victim instanceof Player) return;

        $forcedProc = \ecstsy\Glacia\server\enchantments\enchants\sword\BrotherhoodEnchant::consumeForcedProc($damager, "thunderfamine");
        if (!$forcedProc) {
            $chance = EnchantTuningManager::getLevelInt("thunderfamine", "chance-by-level", $level, 4 * $level);
            if (!ProcLuckManager::roll($damager, "thunderfamine", $chance, 1, 8)) return;
        }

        $strikeDamage = max(1.0, EnchantTuningManager::getFloat("thunderfamine", "strike-damage", 4.0));
        YijkiLightningStrike::strike($damager, $victim->getPosition(), $strikeDamage, 3.0);
        YijkiLightningStrike::strike($damager, $victim->getPosition(), $strikeDamage, 3.0);
        YijkiLightningStrike::strike($damager, $victim->getPosition(), $strikeDamage, 3.0);
        $brotherhoodLevel = CustomEnchantmentManager::getLevel($damager->getInventory()->getItemInHand(), "brotherhood");
        \ecstsy\Glacia\server\enchantments\enchants\sword\BrotherhoodEnchant::tryDistributeFromMasteryProc($damager, $brotherhoodLevel, "thunderfamine");
        $victim->getHungerManager()->setFood(0);
        $victim->getHungerManager()->setSaturation(0.0);
    }
}
