<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\armor;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantDebugManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Sword;
use pocketmine\player\Player;

final class ArmoredEnchant implements EntityDamageByEntityEnchantmentHandler {

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void {
        if ($event->isCancelled()) {
            return;
        }

        if ($event->getCause() !== EntityDamageEvent::CAUSE_ENTITY_ATTACK) {
            return;
        }

        $victim = $event->getEntity();
        $damager = $event->getDamager();

        if (!$victim instanceof Player || !$damager instanceof Player) {
            return;
        }

        $weapon = $damager->getInventory()->getItemInHand();
        if (!$weapon instanceof Sword) {
            return;
        }

        $base = $event->getBaseDamage();
        if ($base <= 0) {
            return;
        }

        $appliedReduction = max(0.0, EnchantTuningManager::value("armored", "damage-reduction-fraction", $level, 0.0075));
        $newDamage = $base * (1 - $appliedReduction);
        $event->setBaseDamage($newDamage);

        EnchantDebugManager::logPassiveValues($victim, "armored", $level, [
            "base_damage" => $base,
            "reduction_fraction" => $appliedReduction,
            "final_damage" => $newDamage,
        ]);
    }
}
