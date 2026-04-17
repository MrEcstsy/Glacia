<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\chestplate;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class DiminishEnchant implements EntityDamageByEntityEnchantmentHandler {

    /** @var array<string, float> */
    private static array $pendingCaps = [];

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void {
        if ($event->isCancelled()) {
            return;
        }

        $victim = $event->getEntity();
        if (!$victim instanceof Player) {
            return;
        }

        $key = strtolower($victim->getName());

        if (isset(self::$pendingCaps[$key])) {
            $event->setBaseDamage(min($event->getBaseDamage(), self::$pendingCaps[$key]));
            unset(self::$pendingCaps[$key]);
        }

        $chance = match ($level) {
            1 => 3,
            2 => 5,
            3 => 7,
            4 => 9,
            5 => 11,
            6 => 13,
            default => 0
        };

        if (mt_rand(1, 100) <= $chance) {
            self::$pendingCaps[$key] = max(0.5, $event->getBaseDamage() / 2);
        }
    }
}