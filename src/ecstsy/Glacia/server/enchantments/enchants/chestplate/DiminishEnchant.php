<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\chestplate;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantDebugManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class DiminishEnchant implements EntityDamageByEntityEnchantmentHandler {

    /** @var array<string, float> */
    private static array $pendingCaps = [];

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void {
        if ($event->isCancelled()) {
            return;
        }

        $victim = $event->getEntity();
        if (!$victim instanceof Player) {
            return;
        }

        $key = strtolower($victim->getName());
        $consumedExistingCap = false;
        if (isset(self::$pendingCaps[$key])) {
            $beforeDamage = $event->getBaseDamage();
            $cap = self::$pendingCaps[$key];
            $event->setBaseDamage(min($beforeDamage, $cap));
            unset(self::$pendingCaps[$key]);
            $consumedExistingCap = true;

            EnchantDebugManager::logPassiveValues($victim, "diminish", $level, [
                "consumed_cap" => $cap,
                "base_damage" => $beforeDamage,
                "final_damage" => $event->getBaseDamage(),
            ]);
        }

        if ($consumedExistingCap) {
            return;
        }

        $chance = EnchantTuningManager::chance("diminish", $level, 3.0);
        $result = EnchantProcManager::attempt($victim, "diminish", $level, $chance, EnchantTuningManager::cooldownSeconds("diminish", $level, 0));
        if (!$result->success) {
            return;
        }

        $divisor = max(1e-3, EnchantTuningManager::value("diminish", "next-hit-damage-divisor", $level, 2.0));
        $floor = max(0.0, EnchantTuningManager::value("diminish", "capped-hit-damage-floor", $level, 0.5));
        $pendingCap = max($floor, $event->getBaseDamage() / $divisor);
        self::$pendingCaps[$key] = $pendingCap;

        EnchantProcManager::debugAppliedValues($victim, $result, [
            "next_hit_cap" => $pendingCap,
            "source_damage" => $event->getBaseDamage(),
            "damage_divisor" => $divisor,
            "damage_floor" => $floor,
        ]);
    }
}
