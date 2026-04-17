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

final class FearEnchant implements EntityDamageByEntityEnchantmentHandler {

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) return;

        $damager = $event->getDamager();
        $victim  = $event->getEntity();

        if (!$damager instanceof Player || !$victim instanceof Living) return;

        $session = PlayerManager::getInstance()->getSession($damager);

        if ($session->isOnCooldown("fear")) return;

        $item = $damager->getInventory()->getItemInHand();
        if (!CustomEnchantmentManager::hasEnchantment($item, "fear")) return;

        $data = [
            1 => ["chance" => 13, "cd" => 4, "duration" => 40,  "amplifier" => 0],
            2 => ["chance" => 19, "cd" => 5, "duration" => 80,  "amplifier" => 0],
            3 => ["chance" => 24, "cd" => 6, "duration" => 120, "amplifier" => 0],
            4 => ["chance" => 30, "cd" => 7, "duration" => 160, "amplifier" => 0],
            5 => ["chance" => 35, "cd" => 8, "duration" => 200, "amplifier" => 0],
        ];

        $cfg = $data[$level] ?? $data[1];

        if (mt_rand(1, 100) > $cfg["chance"]) return;

        $session->addCooldown("fear", $cfg["cd"]);

        $victim->getEffects()->add(new EffectInstance(
            VanillaEffects::WEAKNESS(),
            $cfg["duration"] * 20,
            $cfg["amplifier"]
        ));
    }
}
