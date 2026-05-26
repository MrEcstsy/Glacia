<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\armor;

use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\items\rings\RingManager;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\player\Player;

final class DodgeEnchant implements EntityDamageByEntityEnchantmentHandler {

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void {
        if ($event->isCancelled()) {
            return;
        }

        if ($event->getCause() !== EntityDamageEvent::CAUSE_ENTITY_ATTACK) {
            return;
        }

        $victim = $event->getEntity();
        if (!$victim instanceof Player) {
            return;
        }
        if (RingManager::playerBlocksEnchant($victim, "dodge")) {
            return;
        }

        $session = PlayerManager::getInstance()->getSession($victim);
        if ($session === null || $session->isOnCooldown("dodge")) {
            return;
        }

        $chance = EnchantTuningManager::chance("dodge", $level, 4.0 + (($level - 1) * 2.0));
        $sneakBonus = EnchantTuningManager::value("dodge", "percent-bonus-if-sneaking", $level, 6.0);
        if ($victim->isSneaking()) {
            $chance += $sneakBonus;
        }
        $chance = min($chance, EnchantTuningManager::getDodgeProcCapPercent());
        $cooldownSeconds = EnchantTuningManager::cooldownSeconds("dodge", $level, 8);
        $result = EnchantProcManager::attempt(
            $victim,
            "dodge",
            $level,
            $chance,
            $cooldownSeconds,
            static fn() => $session->isOnCooldown("dodge") ? 1 : 0
        );
        if (!$result->success) {
            return;
        }

        $session->addCooldown("dodge", $cooldownSeconds);
        $event->cancel();
        EnchantProcManager::debugAppliedValues($victim, $result, [
            "sneaking_bonus" => $victim->isSneaking() ? $sneakBonus : 0.0,
            "cancelled" => true,
        ]);
    }
}
