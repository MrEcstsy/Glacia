<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\sword;

use ecstsy\Glacia\entity\gkits\ForsakenChampion;
use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\enchantments\manager\ProcLuckManager;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class VampiricDevourEnchant implements EntityDamageByEntityEnchantmentHandler
{
    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) return;

        $damager = $event->getDamager();
        if (!$damager instanceof Living) return;

        $item = $damager instanceof Player ? $damager->getInventory()->getItemInHand() : ($damager instanceof ForsakenChampion ? $damager->getCombatItem() : null);
        if ($item === null || !CustomEnchantmentManager::hasEnchantment($item, "vampiricdevour")) return;

        $forcedProc = $damager instanceof Player && BrotherhoodEnchant::consumeForcedProc($damager, "vampiricdevour");
        if (!$forcedProc) {
            $chance = EnchantTuningManager::getLevelInt("vampiricdevour", "chance-by-level", $level, 1);
            if (!ProcLuckManager::roll($damager, "vampiricdevour", $chance, 1, 1)) return;
        }

        $session = $damager instanceof Player ? PlayerManager::getInstance()->getSession($damager) : null;
        $cooldown = max(0, EnchantTuningManager::getInt("vampiricdevour", "cooldown", 25));
        if (!$forcedProc && $session !== null && $cooldown > 0 && $session->isOnCooldown("vampiricdevour")) return;

        $damager->setHealth($damager->getMaxHealth());
        if ($damager instanceof Player) {
            $brotherhoodLevel = CustomEnchantmentManager::getLevel($item, "brotherhood");
            BrotherhoodEnchant::tryDistributeFromMasteryProc($damager, $brotherhoodLevel, "vampiricdevour");
        }

        if (!$forcedProc && $session !== null && $cooldown > 0) {
            $session->addCooldown("vampiricdevour", $cooldown);
        }
    }
}

