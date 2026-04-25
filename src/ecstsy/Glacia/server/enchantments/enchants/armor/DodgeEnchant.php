<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\armor;

use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\player\Player;

final class DodgeEnchant implements EntityDamageByEntityEnchantmentHandler {

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void {
        if ($event->isCancelled()) {
            return;
        }

        if ($event->getCause() !== EntityDamageEvent::CAUSE_ENTITY_ATTACK) {
            return;
        }

        $victim = $event->getEntity();
        if (!$victim instanceof Player) {
            return;
        }

        $session = PlayerManager::getInstance()->getSession($victim);
        if ($session === null || $session->isOnCooldown("dodge")) {
            return;
        }

        $chance = match ($level) {
            1 => 4,
            2 => 6,
            3 => 8,
            4 => 10,
            5 => 12,
            default => 0
        };

        if ($victim->isSneaking()) {
            $chance += 6;
        }

        if (mt_rand(1, 100) <= $chance) {
            $session->addCooldown("dodge", 8);
            $event->cancel();
        }
    }
}
