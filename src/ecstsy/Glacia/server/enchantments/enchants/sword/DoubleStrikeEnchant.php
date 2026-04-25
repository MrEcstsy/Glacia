<?php 

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\sword;

use ecstsy\Glacia\entity\gkits\ForsakenChampion;
use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\ProcLuckManager;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\player\Player;

final class DoubleStrikeEnchant implements EntityDamageByEntityEnchantmentHandler {

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) return;

        $damager = $event->getDamager();
        $victim  = $event->getEntity();

        if (!$damager instanceof Living) return;
        if (!$victim instanceof Living) return;

        $item = $damager instanceof Player ? $damager->getInventory()->getItemInHand() : ($damager instanceof ForsakenChampion ? $damager->getCombatItem() : null);
        if ($item === null) return;
        if (!CustomEnchantmentManager::hasEnchantment($item, "doublestrike")) return;

        $session = $damager instanceof Player ? PlayerManager::getInstance()->getSession($damager) : null;

        $chance = match($level) {
            1 => 8,
            2 => 13,
            3 => 19,
            default => 8,
        };

        $cooldown = 6;

        if (!ProcLuckManager::roll($damager, "doublestrike", $chance, 2, 10)) return;
        if ($session !== null && $session->isOnCooldown("doublestrike")) return;

        if ($session !== null) {
            $session->addCooldown("doublestrike", $cooldown);
        }

        $damage = $event->getFinalDamage();
        $victim->attack(new EntityDamageEvent($victim, EntityDamageEvent::CAUSE_CONTACT, $damage));

        if ($damager instanceof Player) {
            $damager->sendMessage("§6§l** §6DOUBLE STRIKE §6§l**");
        }
    }
}
