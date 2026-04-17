<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\handler;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use pocketmine\event\entity\EntityDeathEvent;

interface EntityDeathEnchantmentHandler {
    public static function handle(EntityDeathEvent $event, CustomEnchantment $enchant, int $level): void;
}