<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\boots;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityEffectAddEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantDebugManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\entity\EntityEffectAddEvent;
use pocketmine\player\Player;

final class MetaphysicalEnchant implements EntityEffectAddEnchantmentHandler {

    public static function handle(EntityEffectAddEvent $event, CustomEnchantment $enchant, int $level): void {
        if ($event->isCancelled()) {
            return;
        }

        $entity = $event->getEntity();
        if (!$entity instanceof Player) {
            return;
        }

        $effect = $event->getEffect()->getType();
        if ($effect !== VanillaEffects::SLOWNESS()) {
            return;
        }

        $chance = EnchantTuningManager::chance("metaphysical", $level, 40.0);
        $result = EnchantProcManager::attempt($entity, "metaphysical", $level, $chance, EnchantTuningManager::cooldownSeconds("metaphysical", $level, 0));
        if (!$result->success) {
            return;
        }

        $event->cancel();
        EnchantDebugManager::logAppliedValues($entity, $result, [
            "negated_effect" => "slowness",
            "cancelled" => true,
        ]);
    }
}
