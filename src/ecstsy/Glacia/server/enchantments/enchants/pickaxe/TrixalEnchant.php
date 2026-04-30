<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\pickaxe;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\BlockBreakEnchantmentHandler;
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

        if ($item->getDamage() > 0) {
            $item->setDamage(max(0, $item->getDamage() - 1));
            $player->getInventory()->setItemInHand($item);
        }
    }
}
