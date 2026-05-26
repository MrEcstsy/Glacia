<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\armor;

use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\AdvancedEnchantState;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class EnlightedEnchant implements EntityDamageByEntityEnchantmentHandler {

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void {
        if ($event->isCancelled()) {
            return;
        }

        $victim = $event->getEntity();
        if (!$victim instanceof Player) {
            return;
        }

        if (!$victim->isAlive() || AdvancedEnchantState::isAnatomyBlocked($victim)) {
            return;
        }

        $session = PlayerManager::getInstance()->getSession($victim);
        if ($session === null) {
            return;
        }

        $chance = EnchantTuningManager::chance("enlighted", $level, 4.0);
        $cooldownSeconds = EnchantTuningManager::cooldownSeconds("enlighted", $level, 10);
        $result = EnchantProcManager::attempt(
            $victim,
            "enlighted",
            $level,
            $chance,
            $cooldownSeconds,
            static fn() => max(0, (int) ($session?->getCooldown("enlighted") ?? 0))
        );
        if (!$result->success) {
            return;
        }

        $heal = max(0.0, EnchantTuningManager::value("enlighted", "heal", $level, 1.5));
        EnchantProcManager::applyCooldownIfNeeded($result, static function (int $seconds) use ($session): void {
            $session?->addCooldown("enlighted", $seconds);
        });
        EnchantProcManager::debugAppliedValues($victim, $result, ["heal" => $heal]);

        $victim->setHealth(min($victim->getMaxHealth(), $victim->getHealth() + $heal));
    }
}
