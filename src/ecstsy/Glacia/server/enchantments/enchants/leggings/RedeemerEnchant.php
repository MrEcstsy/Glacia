<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\leggings;

use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\player\Player;

final class RedeemerEnchant implements EntityDamageEnchantmentHandler
{
    public static function handle(EntityDamageEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) {
            return;
        }

        $player = $event->getEntity();
        if (!$player instanceof Player) {
            return;
        }

        $session = PlayerManager::getInstance()->getSession($player);
        $cooldownSeconds = EnchantTuningManager::cooldownSeconds("redeemer", $level, 9);
        $result = EnchantProcManager::attempt(
            $player,
            "redeemer",
            $level,
            EnchantTuningManager::chance("redeemer", $level, 9.0),
            $cooldownSeconds,
            static fn() => max(0, (int) ($session?->getCooldown("redeemer") ?? 0))
        );
        if (!$result->success) {
            return;
        }

        $durationSeconds = max(1, EnchantTuningManager::valueInt("redeemer", "buff-duration-seconds", $level, 1));
        $strengthAmplifier = max(0, EnchantTuningManager::valueInt("redeemer", "strength-amplifier", $level, 0));
        $player->getEffects()->add(new EffectInstance(VanillaEffects::STRENGTH(), $durationSeconds * 20, $strengthAmplifier));

        EnchantProcManager::applyCooldownIfNeeded($result, static function (int $seconds) use ($session): void {
            $session?->addCooldown("redeemer", $seconds);
        });
        EnchantProcManager::debugAppliedValues($player, $result, [
            "duration_seconds" => $durationSeconds,
            "strength_amplifier" => $strengthAmplifier,
        ]);
    }
}
