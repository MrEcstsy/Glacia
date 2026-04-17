<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\axe;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\BleedManager;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class BleedEnchant implements EntityDamageByEntityEnchantmentHandler {

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void {

        if ($event->isCancelled()) {
            return;
        }

        $damager = $event->getDamager();
        $victim  = $event->getEntity();

        if (!$damager instanceof Player || !$victim instanceof Player) {
            return;
        }

        $item = $damager->getInventory()->getItemInHand();
        if (!CustomEnchantmentManager::hasEnchantment($item, "bleed")) {
            return;
        }

        $chance = $level * 5;

        if (mt_rand(1, 100) <= $chance) {
            $victim->getEffects()->add(new EffectInstance(VanillaEffects::SLOWNESS(), 0, 100));
            BleedManager::apply($victim, $level);
        }
    }
}