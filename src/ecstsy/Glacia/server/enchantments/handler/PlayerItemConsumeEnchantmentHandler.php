<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\handler;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use pocketmine\event\player\PlayerItemConsumeEvent;

interface PlayerItemConsumeEnchantmentHandler {
    public static function handle(PlayerItemConsumeEvent $event, CustomEnchantment $enchant, int $level): void;
}
