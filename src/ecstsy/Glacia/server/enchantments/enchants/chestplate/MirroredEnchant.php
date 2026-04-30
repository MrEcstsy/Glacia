<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\chestplate;

use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\enchantments\manager\ProcLuckManager;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\player\Player;

final class MirroredEnchant implements EntityDamageByEntityEnchantmentHandler
{
    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) return;

        $victim = $event->getEntity();
        $damager = $event->getDamager();
        if (!$victim instanceof Player || !$damager instanceof Player) return;

        $chance = EnchantTuningManager::getLevelInt("mirrored", "chance-by-level", $level, 3 + ($level * 4));
        if (!ProcLuckManager::roll($victim, "mirrored", $chance, 2, 12)) return;

        $session = PlayerManager::getInstance()->getSession($victim);
        $cooldown = max(0, EnchantTuningManager::getInt("mirrored", "cooldown", 5));
        if ($session !== null && $cooldown > 0 && $session->isOnCooldown("mirrored")) return;

        $reflectPct = EnchantTuningManager::getLevelFloat("mirrored", "reflect-percent-by-level", $level, 20.0 + ($level * 8.0));
        $reflected = max(0.5, $event->getFinalDamage() * ($reflectPct / 100));
        $damager->attack(new EntityDamageEvent($damager, EntityDamageEvent::CAUSE_MAGIC, $reflected));

        if ($session !== null && $cooldown > 0) {
            $session->addCooldown("mirrored", $cooldown);
        }
    }
}

