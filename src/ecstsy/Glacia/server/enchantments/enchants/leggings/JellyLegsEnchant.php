<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\leggings;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantDebugManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageEvent;

final class JellyLegsEnchant implements EntityDamageEnchantmentHandler {

    public static function handle(EntityDamageEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) {
            return;
        }

        if ($event->getCause() !== EntityDamageEvent::CAUSE_FALL) {
            return;
        }

        $entity = $event->getEntity();
        if (!$entity instanceof Living) {
            return;
        }

        $chance = EnchantTuningManager::chance("jellylegs", $level, 40.0);
        $result = EnchantProcManager::attempt($entity, "jellylegs", $level, $chance, EnchantTuningManager::cooldownSeconds("jellylegs", $level, 0));
        if (!$result->success) {
            return;
        }

        $event->cancel();
        EnchantDebugManager::logAppliedValues($entity, $result, [
            "cause" => "fall",
            "cancelled" => true,
        ]);
    }
}
