<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\sword;

use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\SilenceManager;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class SilenceEnchant implements EntityDamageByEntityEnchantmentHandler {

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void {
        if ($event->isCancelled()) {
            return;
        }

        $damager = $event->getDamager();
        $victim  = $event->getEntity();

        if (!$damager instanceof Player || !$victim instanceof Player) {
            return;
        }

        $item = $damager->getInventory()->getItemInHand();
        if (!CustomEnchantmentManager::hasEnchantment($item, "silence")) {
            return;
        }

        $session = PlayerManager::getInstance()->getSession($damager);

        if ($session->isOnCooldown("silence")) {
            return;
        }

        $cfg = match ($level) {
            1 => ["chance" => 10, "duration" => 1.5, "cd" => 12],
            2 => ["chance" => 15, "duration" => 2.5, "cd" => 12],
            3 => ["chance" => 20, "duration" => 3.5, "cd" => 12],
            4 => ["chance" => 25, "duration" => 4.5, "cd" => 12],
            5 => ["chance" => 30, "duration" => 5.0, "cd" => 12],
            default => ["chance" => 10, "duration" => 1.5, "cd" => 12],
        };

        if (mt_rand(1, 100) > $cfg["chance"]) {
            return;
        }

        SilenceManager::silence($victim, $cfg["duration"]);

        $session->addCooldown("silence", $cfg["cd"]);
    }
}