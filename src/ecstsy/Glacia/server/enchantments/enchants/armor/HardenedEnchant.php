<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\armor;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantDebugManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;
use pocketmine\item\Durable;

final class HardenedEnchant implements EntityDamageByEntityEnchantmentHandler {

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void {
        if ($event->isCancelled()) {
            return;
        }

        $victim = $event->getEntity();
        if (!$victim instanceof Player) {
            return;
        }

        $chance = EnchantTuningManager::chance("hardened", $level, $level * 10.0);
        $repairAmount = max(1, EnchantTuningManager::valueInt("hardened", "durability-repair", $level, 1));

        $armorInventory = $victim->getArmorInventory();
        $repairedPieces = 0;

        foreach ($armorInventory->getContents() as $slot => $armor) {
            if (!$armor instanceof Durable || $armor->isNull()) {
                continue;
            }

            $enchants = CustomEnchantmentManager::getEnchantments($armor);

            if (!isset($enchants[$enchant->getName()])) {
                continue;
            }

            if (mt_rand(1, 100) > $chance) {
                continue;
            }

            $currentDamage = $armor->getDamage();

            if ($currentDamage > 0) {
                $armor->setDamage(max(0, $currentDamage - $repairAmount));
                $armorInventory->setItem($slot, $armor);
                $repairedPieces++;
            }
        }

        if ($repairedPieces > 0) {
            EnchantDebugManager::logPassiveValues($victim, "hardened", $level, [
                "chance" => $chance,
                "repair_amount" => $repairAmount,
                "repaired_pieces" => $repairedPieces,
            ]);
        }
    }
}
