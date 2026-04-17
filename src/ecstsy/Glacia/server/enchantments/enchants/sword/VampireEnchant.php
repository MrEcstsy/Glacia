<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\sword;

use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class VampireEnchant implements EntityDamageByEntityEnchantmentHandler {

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) {
            return;
        }

        
        $damager = $event->getDamager();
        $victim  = $event->getEntity();

        if (!$damager instanceof Player || !$victim instanceof Player) {
            return;
        }

        $item = $damager->getInventory()->getItemInHand();
        if (!CustomEnchantmentManager::hasEnchantment($item, "vampire")) {
            return;
        }

        $session = PlayerManager::getInstance()->getSession($damager);

        if ($session->isOnCooldown("vampire")) {
            return;
        }

        $cfg = match ($level) {
            1 => ["chance" => 12, "healMin" => 1, "healMax" => 2, "cd" => 10],
            2 => ["chance" => 18, "healMin" => 1, "healMax" => 3, "cd" => 10],
            3 => ["chance" => 24, "healMin" => 2, "healMax" => 3, "cd" => 10],
            default => ["chance" => 10, "healMin" => 1, "healMax" => 2, "cd" => 10]
        };

        if (mt_rand(1, 100) > $cfg["chance"]) {
            return;
        }

        $heal = mt_rand($cfg["healMin"], $cfg["healMax"]);

        $newHealth = min($damager->getMaxHealth(), $damager->getHealth() + $heal);
        $damager->setHealth($newHealth);

        $session->addCooldown("vampire", $cfg["cd"]);
    }
}