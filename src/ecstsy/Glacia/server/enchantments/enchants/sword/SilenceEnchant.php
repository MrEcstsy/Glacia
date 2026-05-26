<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\sword;

use ecstsy\Glacia\entity\MummifiedRemnant;
use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\enchantments\manager\MirrorbrandProcManager;
use ecstsy\Glacia\server\enchantments\manager\SilenceManager;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class SilenceEnchant implements EntityDamageByEntityEnchantmentHandler {

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void {
        if ($event->isCancelled()) {
            return;
        }

        $damager = $event->getDamager();
        $victim  = $event->getEntity();

        if (!$damager instanceof Living || !$victim instanceof Player) {
            return;
        }

        $item = $damager instanceof Player ? $damager->getInventory()->getItemInHand() : ($damager instanceof MummifiedRemnant ? $damager->getCombatItem() : null);
        if ($item === null) {
            return;
        }
        if (!CustomEnchantmentManager::hasEnchantment($item, "silence")) {
            return;
        }

        $session = $damager instanceof Player ? PlayerManager::getInstance()->getSession($damager) : null;

        $chance = EnchantTuningManager::chance("silence", $level, 10.0);
        $cooldownSeconds = EnchantTuningManager::cooldownSeconds("silence", $level, 12);
        $durationSeconds = max(0.1, EnchantTuningManager::value("silence", "duration", $level, 1.5));
        $result = EnchantProcManager::attempt(
            $damager,
            "silence",
            $level,
            $chance,
            $cooldownSeconds,
            static fn() => $session?->getCooldown("silence") ?? 0
        );
        if (!$result->success) {
            return;
        }

        SilenceManager::silence($victim, $durationSeconds);
        if ($damager instanceof Player) {
            MirrorbrandProcManager::record($damager, "silence", static function (Player $reflector, Player $attacker) use ($durationSeconds): bool {
                SilenceManager::silence($attacker, $durationSeconds);
                return true;
            });
        }
        EnchantProcManager::applyCooldownIfNeeded($result, static fn(int $seconds) => $session?->addCooldown("silence", $seconds));
        EnchantProcManager::debugAppliedValues($damager, $result, ["duration_seconds" => $durationSeconds]);
    }
}
