<?php 

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\helmet;

use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class EndershiftEnchant implements EntityDamageByEntityEnchantmentHandler {

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) return;

        $victim = $event->getEntity();

        if (!$victim instanceof Player) return;

        $hasEndershift = false;
        foreach ($victim->getArmorInventory()->getContents() as $armor) {
            if (CustomEnchantmentManager::hasEnchantment($armor, "endershift")) {
                $hasEndershift = true;
                break;
            }
        }

        if (!$hasEndershift) return;

        $session = PlayerManager::getInstance()->getSession($victim);
        if ($session === null) return;

        $data = [
            1 => ["chance" => 16, "cd" => 45, "speed" => 60, "speedLvl" => 0, "absorption" => 30, "absorptionLvl" => 0],
            2 => ["chance" => 17, "cd" => 60, "speed" => 60, "speedLvl" => 0, "absorption" => 30, "absorptionLvl" => 0],
            3 => ["chance" => 18, "cd" => 90, "speed" => 60, "speedLvl" => 1, "absorption" => 30, "absorptionLvl" => 1],
        ];

        $cfg = $data[$level] ?? $data[1];

        if ($victim->getHealth() >= 5) return;

        if ($session->isOnCooldown("endershift")) return;

        if (mt_rand(1, 100) > $cfg["chance"]) return;

        $session->addCooldown("endershift", $cfg["cd"]);

        $victim->getEffects()->add(new EffectInstance(VanillaEffects::SPEED(), $cfg["speed"] * 20, $cfg["speedLvl"]));
        $victim->getEffects()->add(new EffectInstance(VanillaEffects::ABSORPTION(), $cfg["absorption"] * 20, $cfg["absorptionLvl"]));

        $victim->sendMessage("§dYou were about to die, so you have entered the Ender Dimension!");
    }
}