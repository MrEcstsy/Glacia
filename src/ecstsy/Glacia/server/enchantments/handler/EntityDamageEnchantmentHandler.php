<?php 

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\handler;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use pocketmine\event\entity\EntityDamageEvent;

interface EntityDamageEnchantmentHandler {
    public static function handle(EntityDamageEvent $event, CustomEnchantment $enchant, int $level): void;
}