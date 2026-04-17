<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\handler;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use pocketmine\event\entity\EntityDamageByEntityEvent;

interface EntityDamageByEntityEnchantmentHandler {
    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void;
}