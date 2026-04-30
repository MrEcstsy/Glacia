<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\axe;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\enchantments\manager\ProcLuckManager;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class PhantomEnchant implements EntityDamageByEntityEnchantmentHandler
{
    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) return;
        $damager = $event->getDamager();
        if (!$damager instanceof Player) return;
        if (!$damager->getWorld()->isNight()) return;

        $chance = EnchantTuningManager::getLevelInt("phantom", "chance-by-level", $level, $level);
        if (!ProcLuckManager::roll($damager, "phantom", $chance, 1, 6)) return;

        $multiplier = max(1.1, EnchantTuningManager::getFloat("phantom", "night-damage-multiplier", 2.0));
        $event->setBaseDamage($event->getBaseDamage() * $multiplier);
    }
}
