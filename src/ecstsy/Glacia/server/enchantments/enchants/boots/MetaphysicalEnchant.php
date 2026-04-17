<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\boots;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityEffectAddEnchantmentHandler;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\entity\EntityEffectAddEvent;
use pocketmine\player\Player;

final class MetaphysicalEnchant implements EntityEffectAddEnchantmentHandler {

    public static function handle(EntityEffectAddEvent $event, CustomEnchantment $enchant, int $level): void {
        if ($event->isCancelled()) {
            return;
        }

        $entity = $event->getEntity();
        if (!$entity instanceof Player) {
            return;
        }

        $effect = $event->getEffect()->getType();
        if (!$effect === VanillaEffects::SLOWNESS()) {
            return;
        }

        $resistChance = match ($level) {
            1 => 40,
            2 => 55,
            3 => 70,
            4 => 90,
            default => 0
        };

        if (mt_rand(1, 100) <= $resistChance) {
            $event->cancel();
        }
    }
}