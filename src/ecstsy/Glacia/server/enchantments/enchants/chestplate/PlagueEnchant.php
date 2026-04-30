<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\chestplate;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantCombatStateManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\enchantments\manager\ProcLuckManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class PlagueEnchant implements EntityDamageByEntityEnchantmentHandler
{
    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) return;
        $victim = $event->getEntity();
        $attacker = $event->getDamager();
        if (!$victim instanceof Player || !$attacker instanceof Player) return;

        $chance = EnchantTuningManager::getLevelInt("plague", "chance-by-level", $level, $level);
        if (!ProcLuckManager::roll($victim, "plague", $chance, 1, 6)) return;

        $radius = max(1.0, EnchantTuningManager::getFloat("plague", "radius", 5.0));
        $duration = max(1, EnchantTuningManager::getInt("plague", "duration-seconds", 4));
        $origin = $victim->getPosition();
        foreach ($victim->getWorld()->getPlayers() as $nearby) {
            if ($nearby->getPosition()->distanceSquared($origin) > ($radius * $radius)) continue;
            if (GlaciaUtils::isAlly($victim, $nearby)) continue;
            EnchantCombatStateManager::applyPlagueWeaken($nearby, $duration);
        }
    }
}
