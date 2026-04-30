<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\armor;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\essence\EnchantEssenceManager;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
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

        $perLevelReduction = EnchantEssenceManager::resolveArmorValue($victim, "armored", "damage-reduction-per-level", 0.0075);
        $reduction = $perLevelReduction * $level;

        $event->setBaseDamage($base * (1 - $reduction));
    }
}
