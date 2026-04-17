<?php 

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\handler;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use pocketmine\event\entity\EntityEffectAddEvent;

interface EntityEffectAddEnchantmentHandler {
    public static function handle(EntityEffectAddEvent $event, CustomEnchantment $enchant, int $level): void;
}