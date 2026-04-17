<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\sword;

use ecstsy\Glacia\entity\SpawnerEntity;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDeathEnchantmentHandler;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\player\Player;

final class KillAuraEnchant implements EntityDeathEnchantmentHandler {

    public static function handle(EntityDeathEvent $event, CustomEnchantment $enchant, int $level): void
    {
        $entity = $event->getEntity();
        if (!$entity instanceof SpawnerEntity || $entity->getStack() <= 0) {
            return;
        }

        $cause = $entity->getLastDamageCause();
        if (!$cause instanceof EntityDamageByEntityEvent) {
            return;
        }

        $damager = $cause->getDamager();
        if (!$damager instanceof Player) {
            return;
        }

        $chance = match ($level) {
            1 => 10,
            2 => 15,
            3 => 20,
            4 => 25,
            5 => 30,
            default => 0
        };

        if ($chance <= 0 || mt_rand(1, 100) > $chance) {
            return;
        }

        $extraKills = min($entity->getStack(), mt_rand(1, $level));
        for ($i = 0; $i < $extraKills; $i++) {
            if (!$entity->isAlive() || $entity->getStack() <= 0) {
                break;
            }

            $entity->kill();
        }
    }
}
