<?php 

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\armor;

use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\item\Durable;
use pocketmine\player\Player;

final class DiminishEnchant implements EntityDamageByEntityEnchantmentHandler {

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) return;

        $damager = $event->getDamager();
        $victim = $event->getEntity();

        if (!$damager instanceof Player) return;
        if (!$victim instanceof Player) return;

        $session = PlayerManager::getInstance()->getSession($damager);

        $has = false;
        foreach ($damager->getArmorInventory()->getContents() as $piece) {
            if (CustomEnchantmentManager::hasEnchantment($piece, "diminish")) {
                $has = true;
                break;
            }
        }

        if (!$has) return;

        $data = [
            1 => ["chance" => 10, "cd" => 2, "durabilityDamage" => 1],
            2 => ["chance" => 15, "cd" => 2, "durabilityDamage" => 2],
            3 => ["chance" => 20, "cd" => 2, "durabilityDamage" => 3],
            4 => ["chance" => 25, "cd" => 2, "durabilityDamage" => 4],
        ];

        $cfg = $data[$level] ?? $data[1];

        if (mt_rand(1, 100) > $cfg["chance"]) return;
        if ($session->isOnCooldown("diminish")) return;

        $session->addCooldown("diminish", $cfg["cd"]);

        $armorInv = $victim->getArmorInventory();
        $items = $armorInv->getContents();

        foreach ($items as $slot => $armorItem) {
            if ($armorItem instanceof Durable) {

                $current = $armorItem->getDamage();
                $max = $armorItem->getMaxDurability();

                $new = min($max, $current + $cfg["durabilityDamage"]);

                $armorItem->setDamage($new);
                $armorInv->setItem($slot, $armorItem);
            }
        }
    }
}