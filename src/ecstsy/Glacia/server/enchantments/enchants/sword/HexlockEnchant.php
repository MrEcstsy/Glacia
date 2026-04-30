<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\sword;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantCombatStateManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\enchantments\manager\ProcLuckManager;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class HexlockEnchant implements EntityDamageByEntityEnchantmentHandler
{
    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) return;
        $damager = $event->getDamager();
        $victim = $event->getEntity();
        if (!$damager instanceof Player || !$victim instanceof Player) return;

        $forcedProc = BrotherhoodEnchant::consumeForcedProc($damager, "hexlock");
        if (!$forcedProc) {
            $chance = EnchantTuningManager::getLevelInt("hexlock", "chance-by-level", $level, $level * 4);
            if (!ProcLuckManager::roll($damager, "hexlock", $chance, 1, 6)) return;
        }

        $duration = max(1, EnchantTuningManager::getInt("hexlock", "duration-seconds", 15));
        EnchantCombatStateManager::disablePets($victim, $duration);
        EnchantCombatStateManager::grantProcBonus($damager, $duration);
        $brotherhoodLevel = \ecstsy\Glacia\server\enchantments\CustomEnchantmentManager::getLevel($damager->getInventory()->getItemInHand(), "brotherhood");
        BrotherhoodEnchant::tryDistributeFromMasteryProc($damager, $brotherhoodLevel, "hexlock");
    }
}
