<?php 

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\sword;

use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class InsomniaEnchant implements EntityDamageByEntityEnchantmentHandler {
    
    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) {
            return;
        }

        $damager = $event->getDamager();
        $victim  = $event->getEntity();

        if (!$damager instanceof Player) return;
        if (!$victim instanceof Living) return;

        $session = PlayerManager::getInstance()->getSession($damager);

        $data = [
            1 => ["chance" => 10, "cd" => 10, "slow" => 40,  "fatigue" => 60,  "nausea" => 40],
            2 => ["chance" => 12, "cd" => 10, "slow" => 80,  "fatigue" => 100, "nausea" => 80],
            3 => ["chance" => 14, "cd" => 10, "slow" => 100, "fatigue" => 120, "nausea" => 100],
            4 => ["chance" => 16, "cd" => 9,  "slow" => 120, "fatigue" => 140, "nausea" => 120],
            5 => ["chance" => 18, "cd" => 9,  "slow" => 140, "fatigue" => 160, "nausea" => 140],
            6 => ["chance" => 20, "cd" => 8,  "slow" => 160, "fatigue" => 180, "nausea" => 160],
            7 => ["chance" => 22, "cd" => 8,  "slow" => 180, "fatigue" => 200, "nausea" => 180],
        ];

        $cfg = $data[$level] ?? $data[1];

        $item = $damager->getInventory()->getItemInHand();

        if (!CustomEnchantmentManager::hasEnchantment($item, "insomnia")) {
            return;
        }

        if (mt_rand(1, 100) > $cfg["chance"]) {
            return;
        }

        if ($session->isOnCooldown("insomnia")) {
            return;
        }

        $session->addCooldown("insomnia", $cfg["cd"]);

        $victim->getEffects()->add(new EffectInstance(
            VanillaEffects::SLOWNESS(),
            $cfg["slow"] * 20,
            0
        ));

        $victim->getEffects()->add(new EffectInstance(
            VanillaEffects::MINING_FATIGUE(),
            $cfg["fatigue"] * 20,
            0
        ));

        $victim->getEffects()->add(new EffectInstance(
            VanillaEffects::NAUSEA(),
            $cfg["nausea"] * 20,
            0
        ));
    }
}