<?php 

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\handler;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use pocketmine\event\player\PlayerExhaustEvent;

interface PlayerExhaustEnchantmentHandler {
    public static function handle(PlayerExhaustEvent $event, CustomEnchantment $enchant, int $level): void;
}