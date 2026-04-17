<?php 

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\axe;

use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class CleaveEnchant implements EntityDamageByEntityEnchantmentHandler {

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) return;

        $damager = $event->getDamager();
        $victim = $event->getEntity();

        if (!$damager instanceof Player) return;
        if (!$victim instanceof Player) return;

        $session = PlayerManager::getInstance()->getSession($damager);

        $item = $damager->getInventory()->getItemInHand();
        if (!CustomEnchantmentManager::hasEnchantment($item, "cleave")) return;

        $data = [
            1 => ["chance" => 5, "cd" => 8, "radius" => 1],
            2 => ["chance" => 7, "cd" => 8, "radius" => 2],
            3 => ["chance" => 9, "cd" => 9, "radius" => 3],
            4 => ["chance" => 12, "cd" => 10, "radius" => 4],
        ];

        $cfg = $data[$level] ?? $data[1];

        if (mt_rand(1, 100) > $cfg["chance"]) return;
        if ($session->isOnCooldown("cleave")) return;

        $session->addCooldown("cleave", $cfg["cd"]);

        $radius = $cfg["radius"];

        $world = $damager->getWorld();
        $entities = $world->getNearbyEntities(
            $damager->getBoundingBox()->expandedCopy($radius, $radius, $radius),
            $damager
        );

        $extraDamage = mt_rand(1, 3); 
        $baseDamage = $event->getFinalDamage();

        foreach ($entities as $entity) {
            if (!$entity instanceof Player) continue;
            if ($entity === $victim) continue;
            if ($entity === $damager) continue;

            $damage = $baseDamage + $extraDamage;

            $aoe = new EntityDamageByEntityEvent(
                $damager,
                $entity,
                EntityDamageByEntityEvent::CAUSE_ENTITY_ATTACK,
                $damage
            );

            $entity->attack($aoe);
        }
    }
}