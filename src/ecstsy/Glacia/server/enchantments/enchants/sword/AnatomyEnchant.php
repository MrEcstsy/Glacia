<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\sword;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\AdvancedEnchantState;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class AnatomyEnchant implements EntityDamageByEntityEnchantmentHandler
{
    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) {
            return;
        }

        $damager = $event->getDamager();
        $victim = $event->getEntity();
        if (!$damager instanceof Living || !$victim instanceof Living) {
            return;
        }

        $result = EnchantProcManager::attempt(
            $damager,
            "anatomy",
            $level,
            EnchantTuningManager::chance("anatomy", $level, 1.0),
            EnchantTuningManager::cooldownSeconds("anatomy", $level, 0)
        );
        if (!$result->success) {
            return;
        }

        $damage = max(0.0, EnchantTuningManager::value("anatomy", "damage", $level, 1.0));
        if ($damage > 0.0) {
            $event->setBaseDamage($event->getBaseDamage() + $damage);
        }

        $seconds = 0;
        if ($victim instanceof Player) {
            $seconds = max(1, EnchantTuningManager::valueInt("anatomy", "anti-heal-seconds", $level, 3));
            AdvancedEnchantState::setAnatomyBlocked($victim, $seconds);
        }

        EnchantProcManager::debugAppliedValues($damager, $result, [
            "damage" => $damage,
            "anti_heal_seconds" => $seconds,
        ]);
    }
}
