<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\leggings;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\enchantments\manager\ProcLuckManager;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class StaticEnchant implements EntityDamageByEntityEnchantmentHandler
{
    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) return;
        $victim = $event->getEntity();
        if (!$victim instanceof Player) return;

        $chance = EnchantTuningManager::getLevelInt("static", "chance-by-level", $level, $level * 4);
        if (!ProcLuckManager::roll($victim, "static", $chance, 1, 8)) return;

        $session = Loader::getPlayerManager()->getSession($victim);
        if ($session === null) return;

        $extra = max(1, EnchantTuningManager::getInt("static", "extra-gapple-cooldown-seconds", 3));
        $remaining = $session->getCooldown("golden_apple") ?? 0;
        $session->addCooldown("golden_apple", $remaining + $extra);
    }
}
