<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\chestplate;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantCombatStateManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\enchantments\manager\ProcLuckManager;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class FlourishEnchant implements EntityDamageByEntityEnchantmentHandler
{
    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) return;
        $victim = $event->getEntity();
        $attacker = $event->getDamager();
        if (!$victim instanceof Player || !$attacker instanceof Player) return;

        $chance = EnchantTuningManager::getInt("flourish", "activation-chance", 3);
        if (ProcLuckManager::roll($victim, "flourish", $chance, 1, 4)) {
            EnchantCombatStateManager::registerIncomingAttacker($victim, $attacker, max(1, EnchantTuningManager::getInt("flourish", "duration-seconds", 6)));
        }

        $reduction = EnchantCombatStateManager::getFlourishReductionPercent($victim);
        if ($reduction > 0) {
            $event->setBaseDamage($event->getBaseDamage() * max(0.0, 1.0 - ($reduction / 100)));
        }
    }
}
