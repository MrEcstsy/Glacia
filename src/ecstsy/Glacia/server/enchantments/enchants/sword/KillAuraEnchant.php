<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\sword;

use ecstsy\Glacia\entity\SpawnerEntity;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDeathEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\player\Player;

final class KillAuraEnchant implements EntityDeathEnchantmentHandler {

    public static function handle(EntityDeathEvent $event, CustomEnchantment $enchant, int $level): void
    {
        $entity = $event->getEntity();
        if (!$entity instanceof SpawnerEntity || $entity->getStack() <= 0) {
            return;
        }

        $cause = $entity->getLastDamageCause();
        if (!$cause instanceof EntityDamageByEntityEvent) {
            return;
        }

        $damager = $cause->getDamager();
        if (!$damager instanceof Player) {
            return;
        }

        $result = EnchantProcManager::attempt(
            $damager,
            "killaura",
            $level,
            EnchantTuningManager::chance("killaura", $level, 10.0),
            EnchantTuningManager::cooldownSeconds("killaura", $level, 0)
        );
        if (!$result->success) {
            return;
        }

        $maxExtraKills = max(1, EnchantTuningManager::valueInt("killaura", "extra-kills-max", $level, 1));
        $extraKills = min($entity->getStack(), mt_rand(1, $maxExtraKills));
        for ($i = 0; $i < $extraKills; $i++) {
            if (!$entity->isAlive() || $entity->getStack() <= 0) {
                break;
            }
            $entity->kill();
        }

        EnchantProcManager::debugAppliedValues($damager, $result, [
            "extra_kills" => $extraKills,
            "extra_kills_max" => $maxExtraKills,
        ]);
    }
}
