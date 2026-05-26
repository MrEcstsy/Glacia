<?php 

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\sword;

use ecstsy\Glacia\entity\MummifiedRemnant;
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

final class FeatherweightEnchant implements EntityDamageByEntityEnchantmentHandler {
    
    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) return;

        $damager = $event->getDamager();
        if (!$damager instanceof Living) return;

        $session = $damager instanceof Player ? PlayerManager::getInstance()->getSession($damager) : null;

        $item = $damager instanceof Player ? $damager->getInventory()->getItemInHand() : ($damager instanceof MummifiedRemnant ? $damager->getCombatItem() : null);
        if ($item === null) return;
        if (!CustomEnchantmentManager::hasEnchantment($item, "featherweight")) return;

        $chance = EnchantTuningManager::chance("featherweight", $level, 35.0);
        $cooldownSeconds = EnchantTuningManager::cooldownSeconds("featherweight", $level, 2);
        $durationTicks = max(1, EnchantTuningManager::valueInt("featherweight", "duration-ticks", $level, 60));
        $effectLevel = max(0, EnchantTuningManager::valueInt("featherweight", "amplifier", $level, 0));
        $result = EnchantProcManager::attempt(
            $damager,
            "featherweight",
            $level,
            $chance,
            $cooldownSeconds,
            static fn() => $session?->getCooldown("featherweight") ?? 0
        );
        if (!$result->success) return;

        $damager->getEffects()->add(new EffectInstance(
            VanillaEffects::HASTE(),
            $durationTicks,
            $effectLevel
        ));
        EnchantProcManager::applyCooldownIfNeeded($result, static fn(int $seconds) => $session?->addCooldown("featherweight", $seconds));
        EnchantProcManager::debugAppliedValues($damager, $result, [
            "duration_ticks" => $durationTicks,
            "amplifier" => $effectLevel,
        ]);
    }
}
