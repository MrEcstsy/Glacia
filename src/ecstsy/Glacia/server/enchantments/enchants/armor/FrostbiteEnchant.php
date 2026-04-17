<?php 

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\armor;

use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class FrostbiteEnchant implements EntityDamageByEntityEnchantmentHandler {

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) return;

        $attacker = $event->getDamager();  
        $victim = $event->getEntity();  

        if (!$attacker instanceof Player || !$victim instanceof Player) return;

        $armor = $victim->getArmorInventory()->getContents();
        $hasFrostbite = false;

        foreach ($armor as $piece) {
            if (CustomEnchantmentManager::hasEnchantment($piece, "frostbite")) {
                $hasFrostbite = true;
                break; 
            }
        }

        if (!$hasFrostbite) return;

        $victimSession = PlayerManager::getInstance()->getSession($victim);

        $data = [
            1 => ["chance" => 5,  "cd" => 60, "duration" => 5,  "amplifier" => 4],
            2 => ["chance" => 10, "cd" => 55, "duration" => 5,  "amplifier" => 4],
            3 => ["chance" => 15, "cd" => 50, "duration" => 5,  "amplifier" => 4],
            4 => ["chance" => 20, "cd" => 45, "duration" => 5,  "amplifier" => 4],
            5 => ["chance" => 25, "cd" => 40, "duration" => 5,  "amplifier" => 4],
        ];

        $cfg = $data[$level] ?? $data[1];

        if ($victimSession->isOnCooldown("frostbite")) return;

        if (mt_rand(1, 100) > $cfg["chance"]) return;

        $victimSession->addCooldown("frostbite", $cfg["cd"]);

        $attacker->getEffects()->add(new EffectInstance(
            VanillaEffects::SLOWNESS(),
            $cfg["duration"] * 20,
            $cfg["amplifier"]
        ));
    }
}