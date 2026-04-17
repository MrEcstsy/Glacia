<?php 

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\handler;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use pocketmine\event\block\BlockBreakEvent;

interface BlockBreakEnchantmentHandler {
    
    public static function handle(BlockBreakEvent $event, CustomEnchantment $enchant, int $level): void;
}