<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\chestplate;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageEnchantmentHandler;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\player\Player;

final class BlastwardEnchant implements EntityDamageEnchantmentHandler
{
    public static function handle(EntityDamageEvent $event, CustomEnchantment $enchant, int $level): void
    {
        $p = $event->getEntity();
        if (!$p instanceof Player) {
            return;
        }
        $c = $event->getCause();
        if ($c !== EntityDamageEvent::CAUSE_BLOCK_EXPLOSION && $c !== EntityDamageEvent::CAUSE_ENTITY_EXPLOSION) {
            return;
        }
        if ($level < 1) {
            return;
        }
        $event->cancel();
    }
}
