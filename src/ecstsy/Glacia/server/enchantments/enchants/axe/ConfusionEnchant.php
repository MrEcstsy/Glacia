<?php 

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\axe;

use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class ConfusionEnchant implements EntityDamageByEntityEnchantmentHandler {

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) return;

        $damager = $event->getDamager();
        $victim  = $event->getEntity();

        if (!$damager instanceof Player) return;
        if (!$victim instanceof Living) return;

        $session = PlayerManager::getInstance()->getSession($damager);

        $data = [
            1 => ["chance" => 13, "cd" => 4, "duration" => 40],
            2 => ["chance" => 19, "cd" => 5, "duration" => 80],
            3 => ["chance" => 24, "cd" => 6, "duration" => 120],
        ];

        $cfg = $data[$level] ?? $data[1];
        $item = $damager->getInventory()->getItemInHand();

        if (!CustomEnchantmentManager::hasEnchantment($item, "confusion")) {
            return;
        }

        if (mt_rand(1, 100) > $cfg["chance"]) {
            return;
        }

        if ($session->isOnCooldown("confusion")) {
            return;
        }

        $session->addCooldown("confusion", $cfg["cd"]);

        $victim->getEffects()->add(
            new EffectInstance(
                VanillaEffects::NAUSEA(),
                $cfg["duration"] * 20, 
                0
            )
        );
    }
}