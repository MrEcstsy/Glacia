<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\helmet;

use ecstsy\Glacia\server\enchantments\manager\AdvancedEnchantState;
use ecstsy\Glacia\server\enchantments\manager\EnchantCombatStateManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantDebugManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use pocketmine\player\Player;

final class ImplantsEnchant {

    /** @var array<string, int> */
    private static array $cooldowns = [];

    public static function tick(Player $player, int $level): void {
        $key = strtolower($player->getName());

        if ((self::$cooldowns[$key] ?? 0) > time()) {
            return;
        }

        if (EnchantCombatStateManager::areCustomEnchantsDisabled($player)) {
            return;
        }

        $cooldownSeconds = max(1, EnchantTuningManager::cooldownSeconds("implants", $level, 45));
        self::$cooldowns[$key] = time() + $cooldownSeconds;

        if ($player->isAlive()) {
            $heal = max(0.0, EnchantTuningManager::value("implants", "heal", $level, 1.0));
            $appliedHeal = 0.0;
            if (!AdvancedEnchantState::isAnatomyBlocked($player)) {
                $beforeHealth = $player->getHealth();
                $player->setHealth(min($player->getMaxHealth(), $player->getHealth() + $heal));
                $appliedHeal = max(0.0, $player->getHealth() - $beforeHealth);
            }

            $foodManager = $player->getHungerManager();
            $foodRestore = max(0, EnchantTuningManager::valueInt("implants", "food", $level, 1));
            $foodManager->setFood(min($foodManager->getMaxFood(), $foodManager->getFood() + $foodRestore));

            EnchantDebugManager::logPassiveValues($player, "implants", $level, [
                "heal" => $heal,
                "applied_heal" => $appliedHeal,
                "food_restore" => $foodRestore,
                "cooldown_seconds" => $cooldownSeconds,
            ]);
        }
    }

    public static function clearPlayer(Player $player): void
    {
        unset(self::$cooldowns[strtolower($player->getName())]);
    }
}
