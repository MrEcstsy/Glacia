<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\axe;

use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\entity\Living;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class PummelEnchant implements EntityDamageByEntityEnchantmentHandler {

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) {
            return;
        }

        $damager = $event->getDamager();
        $victim  = $event->getEntity();
        if (!$damager instanceof Player || !$victim instanceof Player) {
            return;
        }

        $session = PlayerManager::getInstance()->getSession($damager);
        $item = $damager->getInventory()->getItemInHand();
        if (!CustomEnchantmentManager::hasEnchantment($item, "pummel")) {
            return;
        }

        $cooldownSeconds = EnchantTuningManager::cooldownSeconds("pummel", $level, 60);
        $result = EnchantProcManager::attempt(
            $damager,
            "pummel",
            $level,
            EnchantTuningManager::chance("pummel", $level, 5.0),
            $cooldownSeconds,
            static fn() => max(0, (int) ($session?->getCooldown("pummel") ?? 0))
        );
        if (!$result->success) {
            return;
        }

        $radius = max(1.0, EnchantTuningManager::value("pummel", "aoe-radius-blocks", $level, 5.0));
        $slowSeconds = max(1, EnchantTuningManager::valueInt("pummel", "slowness-duration-seconds", $level, 3));
        $amplifier = max(0, EnchantTuningManager::valueInt("pummel", "slowness-amplifier", $level, max(0, $level - 1)));
        $affected = 0;

        $entities = $damager->getWorld()->getNearbyEntities($damager->getBoundingBox()->expandedCopy($radius, $radius, $radius), $damager);
        foreach ($entities as $entity) {
            if (!$entity instanceof Living) {
                continue;
            }
            if ($entity instanceof Player && GlaciaUtils::isAlly($damager, $entity)) {
                continue;
            }

            $entity->getEffects()->add(new EffectInstance(VanillaEffects::SLOWNESS(), 20 * $slowSeconds, $amplifier));
            $affected++;
        }

        EnchantProcManager::applyCooldownIfNeeded($result, static function (int $seconds) use ($session): void {
            $session?->addCooldown("pummel", $seconds);
        });
        EnchantProcManager::debugAppliedValues($damager, $result, [
            "aoe_radius" => $radius,
            "slowness_seconds" => $slowSeconds,
            "slowness_amplifier" => $amplifier,
            "affected_entities" => $affected,
        ]);
    }
}
