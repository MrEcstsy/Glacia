<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\sword;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantDebugManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\item\Bow;
use pocketmine\item\Sword;
use pocketmine\player\Player;

final class GreatswordEnchant implements EntityDamageByEntityEnchantmentHandler {

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void {
        if ($event->isCancelled()) {
            return;
        }

        $damager = $event->getDamager();
        $victim = $event->getEntity();
        if (!$damager instanceof Player || !$victim instanceof Player) {
            return;
        }

        $held = $damager->getInventory()->getItemInHand();
        if (!$held instanceof Sword) {
            return;
        }

        $victimHeld = $victim->getInventory()->getItemInHand();
        if (!$victimHeld instanceof Bow) {
            return;
        }

        $base = $event->getBaseDamage();
        if ($base <= 0) {
            return;
        }

        $multiplier = max(1.0, EnchantTuningManager::value("greatsword", "damage-multiplier", $level, 1.02));
        $event->setBaseDamage($base * $multiplier);
        EnchantDebugManager::logPassiveValues($damager, "greatsword", $level, [
            "damage_multiplier" => $multiplier,
            "base_damage" => $base,
            "final_damage" => $event->getBaseDamage(),
        ]);
    }
}
