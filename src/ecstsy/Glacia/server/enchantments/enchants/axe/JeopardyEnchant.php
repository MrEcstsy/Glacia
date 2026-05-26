<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\axe;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantCombatStateManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class JeopardyEnchant implements EntityDamageByEntityEnchantmentHandler
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

        $result = EnchantProcManager::attempt(
            $damager,
            "jeopardy",
            $level,
            EnchantTuningManager::chance("jeopardy", $level, 1.0),
            EnchantTuningManager::cooldownSeconds("jeopardy", $level, 0)
        );
        if (!$result->success) {
            return;
        }

        $seconds = max(1, EnchantTuningManager::valueInt("jeopardy", "disable-seconds", $level, 5));
        EnchantCombatStateManager::disablePotions($victim, $seconds);
        EnchantProcManager::debugAppliedValues($damager, $result, [
            "disable_seconds" => $seconds,
        ]);
    }
}
