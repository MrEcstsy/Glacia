<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\axe;

use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class CleaveEnchant implements EntityDamageByEntityEnchantmentHandler {

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) {
            return;
        }

        $damager = $event->getDamager();
        $victim = $event->getEntity();
        if (!$damager instanceof Player || !$victim instanceof Player) {
            return;
        }

        $session = PlayerManager::getInstance()->getSession($damager);
        $item = $damager->getInventory()->getItemInHand();
        if (!CustomEnchantmentManager::hasEnchantment($item, "cleave")) {
            return;
        }

        $cooldownSeconds = EnchantTuningManager::cooldownSeconds("cleave", $level, 8);
        $result = EnchantProcManager::attempt(
            $damager,
            "cleave",
            $level,
            EnchantTuningManager::chance("cleave", $level, 5.0),
            $cooldownSeconds,
            static fn() => max(0, (int) ($session?->getCooldown("cleave") ?? 0))
        );
        if (!$result->success) {
            return;
        }

        $radius = max(1.0, EnchantTuningManager::value("cleave", "aoe-radius-blocks", $level, 1.0));
        $extraDamageMin = EnchantTuningManager::valueInt("cleave", "extra-damage-min", $level, 1);
        $extraDamageMax = max($extraDamageMin, EnchantTuningManager::valueInt("cleave", "extra-damage-max", $level, 3));
        $extraDamage = mt_rand($extraDamageMin, $extraDamageMax);
        $baseDamage = $event->getFinalDamage();
        $affected = 0;

        $entities = $damager->getWorld()->getNearbyEntities(
            $damager->getBoundingBox()->expandedCopy($radius, $radius, $radius),
            $damager
        );
        foreach ($entities as $entity) {
            if (!$entity instanceof Player || $entity === $victim || $entity === $damager) {
                continue;
            }

            $damage = $baseDamage + $extraDamage;
            $aoe = new EntityDamageByEntityEvent(
                $damager,
                $entity,
                EntityDamageByEntityEvent::CAUSE_ENTITY_ATTACK,
                $damage
            );
            $entity->attack($aoe);
            $affected++;
        }

        EnchantProcManager::applyCooldownIfNeeded($result, static function (int $seconds) use ($session): void {
            $session?->addCooldown("cleave", $seconds);
        });
        EnchantProcManager::debugAppliedValues($damager, $result, [
            "aoe_radius" => $radius,
            "extra_damage" => $extraDamage,
            "affected_targets" => $affected,
        ]);
    }
}
