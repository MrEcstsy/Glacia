<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\sword;

use ecstsy\Glacia\entity\gkits\ForsakenChampion;
use ecstsy\Glacia\entity\MummifiedRemnant;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\AdvancedEnchantState;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
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

        $item = $damager instanceof Player ? $damager->getInventory()->getItemInHand() : (($damager instanceof ForsakenChampion || $damager instanceof MummifiedRemnant) ? $damager->getCombatItem() : null);
        if ($item === null) {
            return;
        }
        if (!CustomEnchantmentManager::hasEnchantment($item, "vampire")) {
            return;
        }

        $chance = EnchantTuningManager::chance("vampire", $level, 1.0);
        $result = EnchantProcManager::attempt($damager, "vampire", $level, $chance, 0, null, 1, 1.0);
        if (!$result->success) {
            return;
        }

        if ($damager instanceof Player && AdvancedEnchantState::isAnatomyBlocked($damager)) {
            EnchantProcManager::debugAppliedValues($damager, $result, ["blocked" => "anatomy"]);
            return;
        }

        $heal = max(0.0, EnchantTuningManager::value("vampire", "heal", $level, 2.0));
        EnchantProcManager::debugAppliedValues($damager, $result, ["heal" => $heal]);

        $newHealth = min($damager->getMaxHealth(), $damager->getHealth() + $heal);
        $damager->setHealth($newHealth);
    }
}
