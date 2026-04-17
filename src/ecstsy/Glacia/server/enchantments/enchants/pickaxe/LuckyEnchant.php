<?php 

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\pickaxe;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\handler\BlockBreakEnchantmentHandler;
use pocketmine\event\block\BlockBreakEvent;

final class LuckyEnchant implements BlockBreakEnchantmentHandler {

    public static function handle(BlockBreakEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) {
            return;
        }

        $player = $event->getPlayer();
        $item = $player->getInventory()->getItemInHand();

        if (!CustomEnchantmentManager::hasEnchantment($item, "lucky")) {
            return;
        }

        $chance = 8 * $level; // 8%, 16%, 24%
        $xpGain = mt_rand(2, 4) * $level; 

        if (mt_rand(1, 100) <= $chance) {
            $player->getXpManager()->addXp($xpGain);
        }
    }
}