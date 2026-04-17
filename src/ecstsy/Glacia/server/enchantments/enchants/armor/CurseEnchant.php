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

final class CurseEnchant implements EntityDamageByEntityEnchantmentHandler {

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) return;

        $attacker = $event->getDamager();
        $victim   = $event->getEntity();

        if (!$attacker instanceof Player) return;
        if (!$victim instanceof Player) return;

        $hasCurse = false;
        foreach ($victim->getArmorInventory()->getContents() as $piece) {
            if (CustomEnchantmentManager::hasEnchantment($piece, "curse")) {
                $hasCurse = true;
                break;
            }
        }

        if (!$hasCurse) return;

        $session = PlayerManager::getInstance()->getSession($victim);

        if ($session->isOnCooldown("curse")) {
            return;
        }

        $cfg = match($level) {
            1 => ["chance" => 15, "duration" => 2, "amplifier" => 0, "cd" => 7],
            2 => ["chance" => 20, "duration" => 3, "amplifier" => 0, "cd" => 7],
            3 => ["chance" => 25, "duration" => 4, "amplifier" => 1, "cd" => 7],
            4 => ["chance" => 30, "duration" => 5, "amplifier" => 1, "cd" => 7],
            5 => ["chance" => 35, "duration" => 6, "amplifier" => 2, "cd" => 7],
            default => ["chance" => 15, "duration" => 2, "amplifier" => 0, "cd" => 7],
        };

        if (mt_rand(1, 100) > $cfg["chance"]) {
            return;
        }

        $session->addCooldown("curse", $cfg["cd"]);

        $attacker->getEffects()->add(
            new EffectInstance(
                VanillaEffects::MINING_FATIGUE(),
                $cfg["duration"] * 20,
                $cfg["amplifier"]
            )
        );
    }
}