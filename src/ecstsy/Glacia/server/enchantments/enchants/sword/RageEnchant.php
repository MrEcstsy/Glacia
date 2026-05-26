<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\sword;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\essence\EnchantEssenceManager;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\AdvancedEnchantState;
use ecstsy\Glacia\server\enchantments\manager\EnchantDebugManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class RageEnchant implements EntityDamageByEntityEnchantmentHandler {

    private static array $hits = [];
    private static array $active = [];
    private static array $lastHit = [];
    private static array $protected = [];

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void {
        $damager = $event->getDamager();

        if (!$damager instanceof Player) {
            return;
        }
        if (AdvancedEnchantState::consumeSoulCrusherCurrentHit($damager)) {
            return;
        }
        if (!CustomEnchantmentManager::hasEnchantment($damager->getInventory()->getItemInHand(), "rage")) {
            return;
        }

        $name = strtolower($damager->getName());
        $comboResetSeconds = max(0, EnchantTuningManager::valueInt("rage", "combo-reset-seconds", $level, 4));

        if (isset(self::$lastHit[$name]) && time() - self::$lastHit[$name] >= $comboResetSeconds) {
            self::reset($name);
        }

        self::$lastHit[$name] = time();

        $chance = EnchantTuningManager::chance("rage", $level, 6.0);

        if (!(self::$active[$name] ?? false)) {
            $result = EnchantProcManager::attempt($damager, "rage", $level, $chance);
            if (!$result->success) {
                return;
            }

            self::$active[$name] = true;
            self::$hits[$name] = 1;
            EnchantProcManager::debugAppliedValues($damager, $result, [
                "stack" => 1,
                "combo_reset_seconds" => $comboResetSeconds,
            ]);
        } else {
            self::$hits[$name]++;
        }

        $capDefault = max(1, EnchantTuningManager::valueInt("rage", "max-stacks", $level, 4));
        $capSteadfast = max(1, EnchantTuningManager::valueInt("rage", "max-stacks-with-steadfast", $level, 10));
        $hasSteadfast = CustomEnchantmentManager::getLevel($damager->getInventory()->getItemInHand(), "bloodrush") > 0;
        if ($hasSteadfast) {
            self::$protected[$name] = true;
        }
        $cap = $hasSteadfast ? $capSteadfast : $capDefault;
        self::$hits[$name] = min(self::$hits[$name], max(1, $cap));

        $stack = self::$hits[$name];

        $damagePerStack = EnchantTuningManager::value("rage", "damage-per-stack", $level, 0.5);
        $hand = $damager->getInventory()->getItemInHand();
        $stackBonus = EnchantEssenceManager::resolveGearEssenceFloat($hand, "rage", "stack-damage-bonus-fraction", 0.0);
        $stackMult = 1.0 + max(0.0, $stackBonus);
        $damageBonus = $stack * $damagePerStack * $stackMult;
        $event->setBaseDamage($event->getBaseDamage() + $damageBonus);
        EnchantDebugManager::logPassiveValues($damager, "rage", $level, [
            "stack" => $stack,
            "damage_per_stack" => $damagePerStack,
            "stack_multiplier" => $stackMult,
            "damage_bonus" => $damageBonus,
            "cap" => $cap,
        ]);

        $damager->sendPopup(C::colorize("&r&6&lRage &r&7x{$stack}"));
    }

    public static function reset(string $playerName): void {
        $name = strtolower($playerName);
        unset(self::$hits[$name], self::$active[$name], self::$lastHit[$name], self::$protected[$name]);
    }

    public static function shouldPreserveOnDamage(Player $player): bool
    {
        $name = strtolower($player->getName());
        if (!(self::$active[$name] ?? false) || (self::$hits[$name] ?? 0) < 1) {
            return false;
        }

        if (CustomEnchantmentManager::getLevel($player->getInventory()->getItemInHand(), "bloodrush") > 0) {
            return true;
        }

        return (self::$protected[$name] ?? false) === true;
    }

    public static function clearPlayer(Player $player): void
    {
        self::reset($player->getName());
    }
}
