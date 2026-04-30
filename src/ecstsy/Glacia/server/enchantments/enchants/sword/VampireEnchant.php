<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\sword;

use ecstsy\Glacia\entity\gkits\ForsakenChampion;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\enchantments\manager\ProcLuckManager;
use pocketmine\entity\Living;
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

        if (!$damager instanceof Living || !$victim instanceof Living) {
            return;
        }

        $item = $damager instanceof Player ? $damager->getInventory()->getItemInHand() : ($damager instanceof ForsakenChampion ? $damager->getCombatItem() : null);
        if ($item === null) {
            return;
        }
        if (!CustomEnchantmentManager::hasEnchantment($item, "vampire")) {
            return;
        }

        $cfg = match ($level) {
            1 => ["chance" => 1, "healMin" => 0.5, "healMax" => 1.0],
            2 => ["chance" => 1, "healMin" => 0.75, "healMax" => 1.25],
            3 => ["chance" => 1, "healMin" => 1.0, "healMax" => 1.5],
            default => ["chance" => 1, "healMin" => 0.5, "healMax" => 1.0]
        };
        $cfg["chance"] = EnchantTuningManager::getLevelInt("vampire", "chance-by-level", $level, (int) $cfg["chance"]);
        $cfg["healMin"] = EnchantTuningManager::getLevelFloat("vampire", "heal-min-by-level", $level, (float) $cfg["healMin"]);
        $cfg["healMax"] = EnchantTuningManager::getLevelFloat("vampire", "heal-max-by-level", $level, (float) $cfg["healMax"]);

        if (!ProcLuckManager::roll($damager, "vampire", $cfg["chance"], 1, 1)) {
            return;
        }

        $heal = $cfg["healMin"] + (lcg_value() * ($cfg["healMax"] - $cfg["healMin"]));

        $newHealth = min($damager->getMaxHealth(), $damager->getHealth() + $heal);
        $damager->setHealth($newHealth);
    }
}
