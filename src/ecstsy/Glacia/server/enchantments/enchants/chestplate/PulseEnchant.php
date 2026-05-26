<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\chestplate;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class PulseEnchant implements EntityDamageByEntityEnchantmentHandler
{
    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) {
            return;
        }

        $victim = $event->getEntity();
        $attacker = $event->getDamager();
        if (!$victim instanceof Player || !$attacker instanceof Player) {
            return;
        }

        $chance = EnchantTuningManager::chance("pulse", $level, 2.0);
        $cooldownSeconds = EnchantTuningManager::cooldownSeconds("pulse", $level, 0);
        $result = EnchantProcManager::attempt($victim, "pulse", $level, $chance, $cooldownSeconds);
        if (!$result->success) {
            return;
        }

        $direction = $attacker->getPosition()->subtractVector($victim->getPosition())->normalize();
        $knockback = max(0.0, EnchantTuningManager::value("pulse", "knockback-strength", $level, 1.0));
        $upwardBoost = max(0.0, EnchantTuningManager::value("pulse", "upward-boost", $level, 0.25));
        $motion = $attacker->getMotion();
        $motion->x += $direction->x * $knockback;
        $motion->z += $direction->z * $knockback;
        $motion->y = max($motion->y, $upwardBoost);
        $attacker->setMotion($motion);

        EnchantProcManager::debugAppliedValues($victim, $result, [
            "knockback_strength" => $knockback,
            "upward_boost" => $upwardBoost,
        ]);
    }
}
