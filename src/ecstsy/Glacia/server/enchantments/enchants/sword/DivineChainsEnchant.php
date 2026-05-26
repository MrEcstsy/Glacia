<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\sword;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantDebugManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use pocketmine\entity\Living;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;

final class DivineChainsEnchant implements EntityDamageByEntityEnchantmentHandler
{
    /** @var array<string, float> */
    private static array $activeUntil = [];

    /** @var array<string, int> */
    private static array $stunTokens = [];

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) {
            return;
        }

        $damager = $event->getDamager();
        $victim = $event->getEntity();
        if (!$damager instanceof Living || !$victim instanceof Living) {
            return;
        }

        $key = self::pairKey($damager, $victim);
        if ((self::$activeUntil[$key] ?? 0.0) >= microtime(true)) {
            $bonusDamage = max(0.0, EnchantTuningManager::value("divinechains", "bonus-damage-per-hit", $level, 3.0));
            if ($bonusDamage > 0.0) {
                $before = $event->getBaseDamage();
                $event->setBaseDamage($before + $bonusDamage);
                EnchantDebugManager::logPassiveValues($damager, "divinechains", $level, [
                    "bonus_damage" => $bonusDamage,
                    "base_damage" => $before,
                    "final_damage" => $event->getBaseDamage(),
                ]);
            }
        }

        $result = EnchantProcManager::attempt(
            $damager,
            "divinechains",
            $level,
            EnchantTuningManager::chance("divinechains", $level, 1.0),
            EnchantTuningManager::cooldownSeconds("divinechains", $level, 0)
        );
        if (!$result->success) {
            return;
        }

        $stunSeconds = max(0.1, EnchantTuningManager::value("divinechains", "stun-seconds", $level, 1.5));
        $slowAmplifier = min(4, max(0, EnchantTuningManager::valueInt("divinechains", "slowness-amplifier", $level, 4)));
        $durationTicks = max(1, (int) round($stunSeconds * 20));
        self::$activeUntil[$key] = microtime(true) + $stunSeconds;
        self::applyStun($victim, $durationTicks, $slowAmplifier);

        EnchantProcManager::debugAppliedValues($damager, $result, [
            "stun_seconds" => $stunSeconds,
            "slowness_amplifier" => $slowAmplifier,
        ]);
    }

    private static function applyStun(Living $victim, int $durationTicks, int $slowAmplifier): void
    {
        $victim->setMotion(new Vector3(0.0, 0.0, 0.0));
        $victim->getEffects()->add(new EffectInstance(VanillaEffects::SLOWNESS(), $durationTicks, $slowAmplifier, false));

        if (!$victim instanceof Player) {
            return;
        }

        $playerId = (string) $victim->getId();
        $token = (self::$stunTokens[$playerId] ?? 0) + 1;
        self::$stunTokens[$playerId] = $token;

        for ($tick = 1; $tick <= $durationTicks; $tick++) {
            Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(
                static function() use ($victim, $playerId, $token): void {
                    if (!$victim->isAlive() || !$victim->isConnected()) {
                        return;
                    }
                    if ((self::$stunTokens[$playerId] ?? 0) !== $token) {
                        return;
                    }
                    $victim->setMotion(new Vector3(0.0, 0.0, 0.0));
                }
            ), $tick);
        }
    }

    private static function pairKey(Living $damager, Living $victim): string
    {
        return spl_object_id($damager) . ":" . spl_object_id($victim);
    }
}
