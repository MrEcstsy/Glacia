<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\sword;

use ecstsy\Glacia\entity\gkits\ForsakenChampion;
use ecstsy\Glacia\entity\MummifiedRemnant;
use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcResult;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\enchantments\manager\ProcLuckManager;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class IceAspectEnchant implements EntityDamageByEntityEnchantmentHandler
{
    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) return;

        $damager = $event->getDamager();
        $victim = $event->getEntity();
        if (!$damager instanceof Living || !$victim instanceof Living) return;

        $item = $damager instanceof Player ? $damager->getInventory()->getItemInHand() : (($damager instanceof ForsakenChampion || $damager instanceof MummifiedRemnant) ? $damager->getCombatItem() : null);
        if ($item === null || !CustomEnchantmentManager::hasEnchantment($item, "iceaspect")) return;

        $session = $damager instanceof Player ? PlayerManager::getInstance()->getSession($damager) : null;
        $forcedProc = $damager instanceof Player && BrotherhoodEnchant::consumeForcedProc($damager, "iceaspect");
        $cooldownSeconds = EnchantTuningManager::cooldownSeconds("iceaspect", $level, 3);
        $durationTicks = max(1, EnchantTuningManager::valueInt("iceaspect", "duration-ticks", $level, 20));
        $amplifier = max(0, EnchantTuningManager::valueInt("iceaspect", "amplifier", $level, 4));
        $result = null;
        if (!$forcedProc) {
            $chance = EnchantTuningManager::chance("iceaspect", $level, 10.0);
            $result = EnchantProcManager::attempt(
                $damager,
                "iceaspect",
                $level,
                $chance,
                $cooldownSeconds,
                static fn() => $session?->getCooldown("iceaspect") ?? 0
            );
            if (!$result->success) return;
        } elseif ($damager instanceof Player) {
            ProcLuckManager::notifyProc($damager, "iceaspect");
            $result = new EnchantProcResult(
                enchantId: "iceaspect",
                level: $level,
                success: true,
                reason: "forced_proc",
                baseChance: 100.0,
                finalChance: 100.0,
                roll: 0.01,
                cooldownSeconds: $cooldownSeconds,
                cooldownRemainingSeconds: 0,
                missesBefore: 0,
                missesAfter: 0,
                chanceBreakdown: []
            );
        }

        $victim->getEffects()->add(new EffectInstance(VanillaEffects::SLOWNESS(), $durationTicks, $amplifier));

        if ($damager instanceof Player) {
            $brotherhoodLevel = CustomEnchantmentManager::getLevel($item, "brotherhood");
            BrotherhoodEnchant::tryDistributeFromMasteryProc($damager, $brotherhoodLevel, "iceaspect");
        }

        if (!$forcedProc) {
            EnchantProcManager::applyCooldownIfNeeded($result, static fn(int $seconds) => $session?->addCooldown("iceaspect", $seconds));
        }
        if ($result !== null) {
            EnchantProcManager::debugAppliedValues($damager, $result, [
                "duration_ticks" => $durationTicks,
                "amplifier" => $amplifier,
                "forced" => $forcedProc,
            ]);
        }
    }
}

