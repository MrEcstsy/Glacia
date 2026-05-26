<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\sword;

use ecstsy\Glacia\entity\gkits\ForsakenChampion;
use ecstsy\Glacia\entity\MummifiedRemnant;
use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\player\Player;

final class DoubleStrikeEnchant implements EntityDamageByEntityEnchantmentHandler
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

        $item = $damager instanceof Player ? $damager->getInventory()->getItemInHand() : (($damager instanceof ForsakenChampion || $damager instanceof MummifiedRemnant) ? $damager->getCombatItem() : null);
        if ($item === null || !CustomEnchantmentManager::hasEnchantment($item, "doublestrike")) {
            return;
        }

        $session = $damager instanceof Player ? PlayerManager::getInstance()->getSession($damager) : null;
        $cooldownSeconds = EnchantTuningManager::cooldownSeconds("doublestrike", $level, 6);
        $result = EnchantProcManager::attempt(
            $damager,
            "doublestrike",
            $level,
            EnchantTuningManager::chance("doublestrike", $level, 8.0),
            $cooldownSeconds,
            static fn() => max(0, (int) ($session?->getCooldown("doublestrike") ?? 0))
        );
        if (!$result->success) {
            return;
        }

        $strikes = max(1, min(4, EnchantTuningManager::valueInt("doublestrike", "followup-strikes", $level, 1)));
        $damageMultiplier = max(0.0, EnchantTuningManager::value("doublestrike", "followup-damage-multiplier", $level, 1.0));
        $damage = max(0.0, $event->getFinalDamage() * $damageMultiplier);
        for ($i = 0; $i < $strikes; $i++) {
            $victim->attack(new EntityDamageEvent($victim, EntityDamageEvent::CAUSE_CONTACT, $damage));
        }

        EnchantProcManager::applyCooldownIfNeeded($result, static function (int $seconds) use ($session): void {
            $session?->addCooldown("doublestrike", $seconds);
        });
        EnchantProcManager::debugAppliedValues($damager, $result, [
            "followup_strikes" => $strikes,
            "followup_damage" => $damage,
            "followup_damage_multiplier" => $damageMultiplier,
        ]);
    }
}
