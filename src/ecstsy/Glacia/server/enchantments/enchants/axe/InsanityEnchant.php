<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\axe;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\item\Axe;
use pocketmine\item\Sword;
use pocketmine\player\Player;

final class InsanityEnchant implements EntityDamageByEntityEnchantmentHandler
{
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

        if (!$damager->getInventory()->getItemInHand() instanceof Axe) {
            return;
        }

        if (!$victim->getInventory()->getItemInHand() instanceof Sword) {
            return;
        }

        $baseDamage = $event->getBaseDamage();
        if ($baseDamage <= 0) {
            return;
        }

        $multiplier = EnchantTuningManager::getLevelFloat("insanity", "damage-multiplier-by-level", $level, 1.0 + (0.04 * $level));
        $event->setBaseDamage($baseDamage * max(1.0, $multiplier));
    }
}
