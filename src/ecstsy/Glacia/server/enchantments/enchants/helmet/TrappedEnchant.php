<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\helmet;

use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\enchantments\manager\ProcLuckManager;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class TrappedEnchant implements EntityDamageByEntityEnchantmentHandler
{
    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) return;

        $victim = $event->getEntity();
        $damager = $event->getDamager();
        if (!$victim instanceof Player || !$damager instanceof Player) return;

        $chance = EnchantTuningManager::getLevelInt("trapped", "chance-by-level", $level, 4 + ($level * 3));
        if (!ProcLuckManager::roll($victim, "trapped", $chance, 2, 10)) return;

        $session = PlayerManager::getInstance()->getSession($victim);
        $cooldown = max(0, EnchantTuningManager::getInt("trapped", "cooldown", 7));
        if ($session !== null && $cooldown > 0 && $session->isOnCooldown("trapped")) return;

        $seconds = max(1, EnchantTuningManager::getLevelInt("trapped", "root-duration-seconds-by-level", $level, min(5, $level)));
        $amp = max(0, EnchantTuningManager::getInt("trapped", "slowness-amplifier", 6));
        $damager->getEffects()->add(new EffectInstance(VanillaEffects::SLOWNESS(), $seconds * 20, $amp));

        if ($session !== null && $cooldown > 0) {
            $session->addCooldown("trapped", $cooldown);
        }
    }
}

