<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\pickaxe;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\BlockBreakEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\enchantments\manager\ProcLuckManager;
use pocketmine\event\block\BlockBreakEvent;

final class DetonateEnchant implements BlockBreakEnchantmentHandler
{
    public static function handle(BlockBreakEvent $event, CustomEnchantment $enchant, int $level): void
    {
        $player = $event->getPlayer();
        $chance = EnchantTuningManager::getLevelInt("detonate", "chance-by-level", $level, min(100, $level * 5));
        if (!ProcLuckManager::roll($player, "detonate", $chance, 1, 10)) {
            return;
        }

        $radius = min(1, max(0, EnchantTuningManager::getInt("detonate", "radius", 1)));
        $world = $event->getBlock()->getPosition()->getWorld();
        $center = $event->getBlock()->getPosition();
        for ($x = -$radius; $x <= $radius; $x++) {
            for ($y = -$radius; $y <= $radius; $y++) {
                for ($z = -$radius; $z <= $radius; $z++) {
                    $pos = $center->add($x, $y, $z);
                    $block = $world->getBlock($pos);
                    if ($block->isSolid()) {
                        $world->useBreakOn($pos, $player->getInventory()->getItemInHand(), $player);
                    }
                }
            }
        }
    }
}
