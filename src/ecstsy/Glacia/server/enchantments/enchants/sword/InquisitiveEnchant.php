<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\sword;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDeathEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\player\Player;

final class InquisitiveEnchant implements EntityDeathEnchantmentHandler {

    public static function handle(EntityDeathEvent $event, CustomEnchantment $enchant, int $level): void
    {
        $entity = $event->getEntity();
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
            "inquisitive",
            $level,
            EnchantTuningManager::chance("inquisitive", $level, 30.0),
            EnchantTuningManager::cooldownSeconds("inquisitive", $level, 0)
        );
        if (!$result->success) {
            return;
        }

        $xp = $event->getXpDropAmount();
        if ($xp <= 0) {
            return;
        }

        $multiplier = max(0.0, EnchantTuningManager::value("inquisitive", "xp-multiplier", $level, 1.25));
        $bonus = (int) floor($xp * $multiplier);
        $event->setXpDropAmount($bonus);
        EnchantProcManager::debugAppliedValues($damager, $result, [
            "xp_before" => $xp,
            "xp_after" => $bonus,
            "xp_multiplier" => $multiplier,
        ]);
    }
}
