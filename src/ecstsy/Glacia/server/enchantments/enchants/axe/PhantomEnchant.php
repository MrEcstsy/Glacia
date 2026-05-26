<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\axe;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantDebugManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class PhantomEnchant implements EntityDamageByEntityEnchantmentHandler
{
    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) {
            return;
        }

        $damager = $event->getDamager();
        if (!$damager instanceof Player || !$damager->getWorld()->isNight()) {
            return;
        }

        $result = EnchantProcManager::attempt(
            $damager,
            "phantom",
            $level,
            EnchantTuningManager::chance("phantom", $level, 1.0),
            EnchantTuningManager::cooldownSeconds("phantom", $level, 0)
        );
        if (!$result->success) {
            return;
        }

        $baseDamage = $event->getBaseDamage();
        $multiplier = max(1.1, EnchantTuningManager::value("phantom", "night-damage-multiplier", $level, 2.0));
        $event->setBaseDamage($baseDamage * $multiplier);
        EnchantDebugManager::logAppliedValues($damager, $result, [
            "damage_multiplier" => $multiplier,
            "base_damage" => $baseDamage,
            "final_damage" => $event->getBaseDamage(),
        ]);
    }
}
