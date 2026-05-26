<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\chestplate;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantCombatStateManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class PlagueEnchant implements EntityDamageByEntityEnchantmentHandler
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

        $chance = EnchantTuningManager::chance("plague", $level, 1.0);
        $cooldownSeconds = EnchantTuningManager::cooldownSeconds("plague", $level, 0);
        $result = EnchantProcManager::attempt($victim, "plague", $level, $chance, $cooldownSeconds);
        if (!$result->success) {
            return;
        }

        $radius = max(1.0, EnchantTuningManager::value("plague", "radius-blocks", $level, 5.0));
        $durationSeconds = max(1, EnchantTuningManager::valueInt("plague", "duration-seconds", $level, 4));
        $origin = $victim->getPosition();
        $affected = 0;
        foreach ($victim->getWorld()->getPlayers() as $nearby) {
            if ($nearby->getPosition()->distanceSquared($origin) > ($radius * $radius)) {
                continue;
            }
            if (GlaciaUtils::isAlly($victim, $nearby)) {
                continue;
            }
            EnchantCombatStateManager::applyPlagueWeaken($nearby, $durationSeconds);
            $affected++;
        }

        EnchantProcManager::debugAppliedValues($victim, $result, [
            "radius_blocks" => $radius,
            "duration_seconds" => $durationSeconds,
            "affected_players" => $affected,
        ]);
    }
}
