<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\axe;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\BleedManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\enchantments\manager\MirrorbrandProcManager;
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

        $result = EnchantProcManager::attempt(
            $damager,
            "bleed",
            $level,
            EnchantTuningManager::chance("bleed", $level, 5.0),
            EnchantTuningManager::cooldownSeconds("bleed", $level, 0)
        );
        if (!$result->success) {
            return;
        }

        $slowTicks = max(0, EnchantTuningManager::valueInt("bleed", "slow-tick-marker-ticks", $level, 0));
        $slowAmplifier = min(4, max(0, EnchantTuningManager::valueInt("bleed", "slow-tick-marker-amplifier", $level, 4)));
        if ($slowTicks > 0) {
            $victim->getEffects()->add(new EffectInstance(VanillaEffects::SLOWNESS(), $slowTicks, $slowAmplifier));
        }
        BleedManager::apply($victim, $level);
        MirrorbrandProcManager::record($damager, "bleed", static function (Player $reflector, Player $attacker) use ($level, $slowTicks, $slowAmplifier): bool {
            if ($slowTicks > 0) {
                $attacker->getEffects()->add(new EffectInstance(VanillaEffects::SLOWNESS(), $slowTicks, $slowAmplifier));
            }
            BleedManager::apply($attacker, $level);
            return true;
        });
        EnchantProcManager::debugAppliedValues($damager, $result, [
            "slow_ticks" => $slowTicks,
            "slow_amplifier" => $slowAmplifier,
        ]);
    }
}
