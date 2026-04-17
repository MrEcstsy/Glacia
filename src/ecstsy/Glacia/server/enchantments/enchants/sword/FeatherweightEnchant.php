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

final class FeatherweightEnchant implements EntityDamageByEntityEnchantmentHandler {
    
    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) return;

        $damager = $event->getDamager();
        if (!$damager instanceof Player) return;

        $session = PlayerManager::getInstance()->getSession($damager);

        $item = $damager->getInventory()->getItemInHand();
        if (!CustomEnchantmentManager::hasEnchantment($item, "featherweight")) return;

        $data = [
            1 => ["chance" => 35, "cd" => 2, "duration" => 60,  "effectLevel" => 0],
            2 => ["chance" => 55, "cd" => 2, "duration" => 80,  "effectLevel" => 1],
            3 => ["chance" => 75, "cd" => 2, "duration" => 100, "effectLevel" => 2],
        ];

        $cfg = $data[$level] ?? $data[1];

        if (mt_rand(1, 100) > $cfg["chance"]) return;
        if ($session->isOnCooldown("featherweight")) return;

        $session->addCooldown("featherweight", $cfg["cd"]);

        $damager->getEffects()->add(new EffectInstance(
            VanillaEffects::HASTE(),
            $cfg["duration"],
            $cfg["effectLevel"]
        ));
    }
}