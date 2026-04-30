<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\chestplate;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\enchantments\manager\ProcLuckManager;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class PulseEnchant implements EntityDamageByEntityEnchantmentHandler
{
    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) return;
        $victim = $event->getEntity();
        $attacker = $event->getDamager();
        if (!$victim instanceof Player || !$attacker instanceof Player) return;

        $chance = EnchantTuningManager::getLevelInt("pulse", "chance-by-level", $level, 2 * $level);
        if (!ProcLuckManager::roll($victim, "pulse", $chance, 1, 6)) return;

        $dir = $attacker->getPosition()->subtractVector($victim->getPosition())->normalize();
        $kb = max(0.4, EnchantTuningManager::getFloat("pulse", "knockback-strength", 1.0));
        $motion = $attacker->getMotion();
        $motion->x += $dir->x * $kb;
        $motion->z += $dir->z * $kb;
        $motion->y = max($motion->y, 0.25);
        $attacker->setMotion($motion);
    }
}
