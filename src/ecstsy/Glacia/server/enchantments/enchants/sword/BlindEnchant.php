<?php 

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\sword;

use ecstsy\Glacia\entity\gkits\ForsakenChampion;
use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\enchantments\manager\ProcLuckManager;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class BlindEnchant implements EntityDamageByEntityEnchantmentHandler {
    
    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) return;

        $damager = $event->getDamager();
        if (!$damager instanceof Living) return;

        $victim = $event->getEntity();
        if (!$victim instanceof Living) return;

        $item = $damager instanceof Player ? $damager->getInventory()->getItemInHand() : ($damager instanceof ForsakenChampion ? $damager->getCombatItem() : null);
        if ($item === null) return;
        if (!CustomEnchantmentManager::hasEnchantment($item, "blind")) return;

        $session = $damager instanceof Player ? PlayerManager::getInstance()->getSession($damager) : null;

        $data = [
            1 => ["chance" => 25, "cd" => 2, "duration" => 60,  "effectLevel" => 0],
            2 => ["chance" => 35, "cd" => 2, "duration" => 80,  "effectLevel" => 1],
            3 => ["chance" => 45, "cd" => 2, "duration" => 100, "effectLevel" => 2],
        ];

        $cfg = $data[$level] ?? $data[1];
        $cfg["chance"] = EnchantTuningManager::getLevelInt("blind", "chance-by-level", $level, (int) $cfg["chance"]);
        $cfg["duration"] = EnchantTuningManager::getLevelInt("blind", "effect-duration-seconds-by-level", $level, (int) $cfg["duration"]);
        $cfg["effectLevel"] = EnchantTuningManager::getLevelInt("blind", "effect-amplifier-by-level", $level, (int) $cfg["effectLevel"]);

        if (!ProcLuckManager::roll($damager, "blind", $cfg["chance"], 2, 12)) return;
        if ($session !== null && $session->isOnCooldown("blind")) return;

        if ($session !== null) {
            $session->addCooldown("blind", max(0, EnchantTuningManager::getInt("blind", "cooldown", 1)));
        }

        $victim->getEffects()->add(
            new EffectInstance(
                VanillaEffects::BLINDNESS(),
                $cfg["duration"] * 20,
                $cfg["effectLevel"]
            )
        );
    }
}
