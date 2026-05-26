<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\chestplate;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\manager\AdvancedEnchantState;
use ecstsy\Glacia\server\enchantments\manager\EnchantDebugManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;

final class BloodLustEnchant {

    public static function triggerNearbyOwners(Player $victim): void
    {
        $radius = max(1.0, EnchantTuningManager::value("bloodlust", "radius-blocks", 1, 7.0));
        $radiusSquared = $radius * $radius;
        $origin = $victim->getPosition();

        foreach ($victim->getWorld()->getPlayers() as $nearby) {
            if ($nearby === $victim) {
                continue;
            }
            if (!$nearby->isAlive() || $nearby->getPosition()->distanceSquared($origin) > $radiusSquared) {
                continue;
            }
            if (GlaciaUtils::isAlly($nearby, $victim)) {
                continue;
            }

            $chestplate = $nearby->getArmorInventory()->getChestplate();
            if ($chestplate->isNull() || !CustomEnchantmentManager::hasEnchantment($chestplate, "bloodlust")) {
                continue;
            }

            $level = CustomEnchantmentManager::getLevel($chestplate, "bloodlust");
            if ($level <= 0) {
                continue;
            }

            self::trigger($nearby, $level);
        }
    }

    public static function trigger(Player $owner, int $level): void {
        $heal = max(0.0, EnchantTuningManager::value("bloodlust", "heal", $level, 1.0));

        Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(
            static function() use ($owner, $heal, $level): void {
                if (!$owner->isAlive() || AdvancedEnchantState::isAnatomyBlocked($owner)) {
                    return;
                }

                $beforeHealth = $owner->getHealth();
                $owner->setHealth(min($owner->getMaxHealth(), $beforeHealth + $heal));
                $applied = max(0.0, $owner->getHealth() - $beforeHealth);
                if ($applied <= 0.0) {
                    return;
                }

                EnchantDebugManager::logPassiveValues($owner, "bloodlust", $level, [
                    "heal" => $applied,
                    "health_after" => $owner->getHealth(),
                ]);
            }
        ), 1);
    }
}
