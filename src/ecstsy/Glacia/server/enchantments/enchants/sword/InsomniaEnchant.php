<?php 

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\sword;

use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class InsomniaEnchant implements EntityDamageByEntityEnchantmentHandler {
    
    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) {
            return;
        }

        $damager = $event->getDamager();
        $victim  = $event->getEntity();

        if (!$damager instanceof Player) return;
        if (!$victim instanceof Living) return;

        $session = PlayerManager::getInstance()->getSession($damager);

        $item = $damager->getInventory()->getItemInHand();

        if (!CustomEnchantmentManager::hasEnchantment($item, "insomnia")) {
            return;
        }

        $chance = EnchantTuningManager::chance("insomnia", $level, 10.0);
        $cooldownSeconds = EnchantTuningManager::cooldownSeconds("insomnia", $level, 10);
        $slowTicks = max(1, EnchantTuningManager::valueInt("insomnia", "slow-duration-ticks", $level, 40));
        $fatigueTicks = max(1, EnchantTuningManager::valueInt("insomnia", "fatigue-duration-ticks", $level, 60));
        $nauseaTicks = max(1, EnchantTuningManager::valueInt("insomnia", "nausea-duration-ticks", $level, 40));
        $result = EnchantProcManager::attempt(
            $damager,
            "insomnia",
            $level,
            $chance,
            $cooldownSeconds,
            static fn() => $session->getCooldown("insomnia")
        );
        if (!$result->success) {
            return;
        }

        $victim->getEffects()->add(new EffectInstance(
            VanillaEffects::SLOWNESS(),
            $slowTicks,
            0
        ));

        $victim->getEffects()->add(new EffectInstance(
            VanillaEffects::MINING_FATIGUE(),
            $fatigueTicks,
            0
        ));

        $victim->getEffects()->add(new EffectInstance(
            VanillaEffects::NAUSEA(),
            $nauseaTicks,
            0
        ));
        EnchantProcManager::applyCooldownIfNeeded($result, static fn(int $seconds) => $session->addCooldown("insomnia", $seconds));
        EnchantProcManager::debugAppliedValues($damager, $result, [
            "slow_ticks" => $slowTicks,
            "fatigue_ticks" => $fatigueTicks,
            "nausea_ticks" => $nauseaTicks,
        ]);
    }
}
