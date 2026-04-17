<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\axe;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class BerserkEnchant implements EntityDamageByEntityEnchantmentHandler {

    private const COOLDOWN_KEY = 'enchant.berserk.cooldown';
    private const COOLDOWN = 20; 

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void {
        if ($event->isCancelled()) {
            return;
        }

        $damager = $event->getDamager();
        if (!$damager instanceof Player) {
            return;
        }
        $session = Loader::getPlayerManager()->getSession($damager);

        if ($session->isOnCooldown(self::COOLDOWN_KEY)) {
            return;
        }

        $chance = match ($level) {
            1 => 4,
            2 => 6,
            3 => 8,
            4 => 10,
            5 => 12,
            default => 0
        };

        if (mt_rand(1, 100) > $chance) {
            return;
        }

        $session->addCooldown(self::COOLDOWN_KEY, self::COOLDOWN);

        $strengthDuration = 20 * (3 + $level); 
        $strengthAmplifier = $level >= 4 ? 1 : 0; 

        $fatigueDuration = 20 * (4 + $level); 
        $fatigueAmplifier = 0;

        $damager->getEffects()->add(new EffectInstance(
            VanillaEffects::STRENGTH(),
            $strengthDuration,
            $strengthAmplifier,
            false
        ));

        $damager->getEffects()->add(new EffectInstance(
            VanillaEffects::MINING_FATIGUE(),
            $fatigueDuration,
            $fatigueAmplifier,
            false
        ));
    }
}