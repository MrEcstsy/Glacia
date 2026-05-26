<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\pickaxe;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\BlockBreakEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantDebugManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\item\Durable;

final class TrixalEnchant implements BlockBreakEnchantmentHandler
{
    public static function handle(BlockBreakEvent $event, CustomEnchantment $enchant, int $level): void
    {
        $player = $event->getPlayer();
        $item = $player->getInventory()->getItemInHand();
        if (!$item instanceof Durable) {
            return;
        }

        $repairPoints = max(0, EnchantTuningManager::valueInt("trixal", "durability-repaired", $level, 1));
        if ($item->getDamage() > 0 && $repairPoints > 0) {
            $beforeDamage = $item->getDamage();
            $item->setDamage(max(0, $item->getDamage() - $repairPoints));
            $player->getInventory()->setItemInHand($item);
            EnchantDebugManager::logPassiveValues($player, "trixal", $level, [
                "repair_points" => $repairPoints,
                "damage_before" => $beforeDamage,
                "damage_after" => $item->getDamage(),
            ]);
        }
    }
}
