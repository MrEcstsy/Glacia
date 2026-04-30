<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\leggings;

use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\enchantments\manager\ProcLuckManager;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\player\Player;

final class RedeemerEnchant implements EntityDamageEnchantmentHandler
{
    public static function handle(EntityDamageEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) return;

        $player = $event->getEntity();
        if (!$player instanceof Player) return;

        $chance = EnchantTuningManager::getLevelInt("redeemer", "chance-by-level", $level, 5 + ($level * 4));
        if (!ProcLuckManager::roll($player, "redeemer", $chance, 2, 12)) return;

        $session = PlayerManager::getInstance()->getSession($player);
        $cooldown = max(0, EnchantTuningManager::getInt("redeemer", "cooldown", 9));
        if ($session !== null && $cooldown > 0 && $session->isOnCooldown("redeemer")) return;

        $seconds = max(1, EnchantTuningManager::getLevelInt("redeemer", "buff-duration-seconds-by-level", $level, min(3, $level)));
        $amp = max(0, EnchantTuningManager::getLevelInt("redeemer", "strength-amplifier-by-level", $level, 0));
        $player->getEffects()->add(new EffectInstance(VanillaEffects::STRENGTH(), $seconds * 20, $amp));

        if ($session !== null && $cooldown > 0) {
            $session->addCooldown("redeemer", $cooldown);
        }
    }
}

