<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\sword;

use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class SlownessEnchant implements EntityDamageByEntityEnchantmentHandler {

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) return;

        $damager = $event->getDamager();
        $victim  = $event->getEntity();

        if (!$damager instanceof Player) return;
        if (!$victim instanceof Living) return;

        $session = PlayerManager::getInstance()->getSession($damager);

        $item = $damager->getInventory()->getItemInHand();
        if (!CustomEnchantmentManager::hasEnchantment($item, "slowness")) return;

        $data = [
            1 => ["chance" => 35, "cd" => 2, "duration" => 60,  "effectLevel" => 0],
            2 => ["chance" => 55, "cd" => 2, "duration" => 80,  "effectLevel" => 1],
            3 => ["chance" => 75, "cd" => 2, "duration" => 100, "effectLevel" => 2],
        ];

        $cfg = $data[$level] ?? $data[1];

        if (mt_rand(1, 100) > $cfg["chance"]) return;
        if ($session->isOnCooldown("slowness")) return;

        if (!$victim->getEffects()->has(VanillaEffects::SLOWNESS())) {
            $victim->getEffects()->add(new EffectInstance(
                VanillaEffects::SLOWNESS(),
                $cfg["duration"] * 20,
                $cfg["effectLevel"]
            ));
            $session->addCooldown("slowness", $cfg["cd"]);
        }
    }
}
