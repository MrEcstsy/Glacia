<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\chestplate;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class BlazedEnchant implements EntityDamageByEntityEnchantmentHandler {

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void {
        if ($event->isCancelled()) {
            return;
        }

        $victim = $event->getEntity();
        $attacker = $event->getDamager();
        if (!$victim instanceof Player || !$attacker instanceof Living) {
            return;
        }

        $chance = EnchantTuningManager::chance("blazed", $level, 20.0);
        $cooldownSeconds = EnchantTuningManager::cooldownSeconds("blazed", $level, 0);
        $result = EnchantProcManager::attempt($victim, "blazed", $level, $chance, $cooldownSeconds);
        if (!$result->success) {
            return;
        }

        $fireSeconds = max(0, EnchantTuningManager::valueInt("blazed", "fire-seconds", $level, 4));
        if ($fireSeconds > 0) {
            $attacker->setOnFire($fireSeconds);
        }

        EnchantProcManager::debugAppliedValues($victim, $result, [
            "fire_seconds" => $fireSeconds,
            "attacker" => $attacker->getNameTag(),
        ]);
    }
}
