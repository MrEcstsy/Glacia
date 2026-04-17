<?php 

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\handler;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\player\PlayerInteractEvent;

interface InteractEnchantmentHandler {
    public static function handle(PlayerInteractEvent $event, CustomEnchantment $enchant, int $level): void;
}