<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\boots;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\AdvancedEnchantState;
use ecstsy\Glacia\server\enchantments\manager\EnchantDebugManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;

final class EnderWalkerEnchant implements EntityDamageEnchantmentHandler {

    public static function handle(EntityDamageEvent $event, CustomEnchantment $enchant, int $level): void {
        if ($event->isCancelled()) {
            return;
        }

        $victim = $event->getEntity();
        if (!$victim instanceof Player || $event->getCause() !== EntityDamageEvent::CAUSE_MAGIC) {
            return;
        }

        $event->cancel();
        EnchantDebugManager::logPassiveValues($victim, "enderwalker", $level, [
            "cause" => "magic",
            "cancelled" => true,
        ]);

        $minimumLevel = max(1, EnchantTuningManager::valueInt("enderwalker", "heal-minimum-level", $level, 4));
        if ($level < $minimumLevel) {
            return;
        }

        $chance = EnchantTuningManager::chance("enderwalker", $level, 20.0);
        $result = EnchantProcManager::attempt($victim, "enderwalker", $level, $chance, EnchantTuningManager::cooldownSeconds("enderwalker", $level, 0));
        if (!$result->success) {
            return;
        }

        $heal = max(0.0, EnchantTuningManager::value("enderwalker", "heal", $level, 1.0));
        Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(
            static function() use ($victim, $heal, $level, $result): void {
                if (!$victim->isAlive() || AdvancedEnchantState::isAnatomyBlocked($victim)) {
                    return;
                }

                $before = $victim->getHealth();
                $victim->setHealth(min($victim->getMaxHealth(), $before + $heal));
                $applied = max(0.0, $victim->getHealth() - $before);
                EnchantDebugManager::logAppliedValues($victim, $result, [
                    "heal" => $applied,
                    "health_after" => $victim->getHealth(),
                ]);
            }
        ), 1);
    }
}
