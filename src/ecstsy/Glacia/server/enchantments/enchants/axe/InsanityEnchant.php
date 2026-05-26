<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\axe;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantDebugManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\item\Axe;
use pocketmine\item\Bow;
use pocketmine\item\Hoe;
use pocketmine\item\Item;
use pocketmine\item\Pickaxe;
use pocketmine\item\Shovel;
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

        if (!$damager->getInventory()->getItemInHand() instanceof Axe || !self::isInsanityVictimWeapon($victim->getInventory()->getItemInHand())) {
            return;
        }

        $baseDamage = $event->getBaseDamage();
        if ($baseDamage <= 0) {
            return;
        }

        $multiplier = max(1.0, EnchantTuningManager::value("insanity", "damage-multiplier", $level, 1.04));
        $event->setBaseDamage($baseDamage * $multiplier);
        EnchantDebugManager::logPassiveValues($damager, "insanity", $level, [
            "damage_multiplier" => $multiplier,
            "base_damage" => $baseDamage,
            "final_damage" => $event->getBaseDamage(),
        ]);
    }

    private static function isInsanityVictimWeapon(Item $item): bool
    {
        return $item instanceof Sword
            || $item instanceof Axe
            || $item instanceof Pickaxe
            || $item instanceof Shovel
            || $item instanceof Hoe
            || $item instanceof Bow;
    }
}
