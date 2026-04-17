<?php 

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\helmet;

use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class EndershiftEnchant implements EntityDamageByEntityEnchantmentHandler {

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) return;

        $victim = $event->getEntity();

        if (!$victim instanceof Living) return;

        $hasEndershift = false;
        foreach ($victim->getArmorInventory()->getContents() as $armor) {
            if (CustomEnchantmentManager::hasEnchantment($armor, "endershift")) {
                $hasEndershift = true;
                break;
            }
        }

        if (!$hasEndershift) return;

        $session = PlayerManager::getInstance()->getSession($victim);

        $data = [
            1 => ["chance" => 16, "cd" => 6,  "speed" => 120, "speedLvl" => 1, "absorption" => 80,  "absorptionLvl" => 0],
            2 => ["chance" => 17, "cd" => 8,  "speed" => 120, "speedLvl" => 1, "absorption" => 80,  "absorptionLvl" => 1],
            3 => ["chance" => 18, "cd" => 12, "speed" => 120, "speedLvl" => 1, "absorption" => 80,  "absorptionLvl" => 2],
        ];

        $cfg = $data[$level] ?? $data[1];

        if ($victim->getHealth() >= 6) return;

        if ($session->isOnCooldown("endershift")) return;

        if (mt_rand(1, 100) > $cfg["chance"]) return;

        $session->addCooldown("endershift", $cfg["cd"]);

        $victim->getEffects()->add(new EffectInstance(VanillaEffects::SPEED(), $cfg["speed"] * 20, $cfg["speedLvl"]));
        $victim->getEffects()->add(new EffectInstance(VanillaEffects::ABSORPTION(), $cfg["absorption"] * 20, $cfg["absorptionLvl"]));

        if ($victim instanceof Player) {
            $victim->sendMessage("§dYou were about to die, so you have entered the Ender Dimension!");
        }
    }
}