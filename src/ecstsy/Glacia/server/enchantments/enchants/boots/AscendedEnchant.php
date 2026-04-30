<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\boots;

use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\enchantments\manager\ProcLuckManager;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\player\Player;

final class AscendedEnchant implements EntityDamageEnchantmentHandler
{
    /** @var array<string, int> */
    private static array $fallImmunityUntil = [];

    /** @see Living::getInitialGravity() */
    private const LIVING_GRAVITY = 0.08;
    /** 1 - Living::getInitialDragMultiplier() */
    private const LIVING_FRICTION = 0.98;

    public static function handle(EntityDamageEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) return;

        $player = $event->getEntity();
        if (!$player instanceof Player) return;

        if ($event->getCause() === EntityDamageEvent::CAUSE_FALL) {
            $key = strtolower($player->getName());
            $until = self::$fallImmunityUntil[$key] ?? 0;
            if ($until > time()) {
                $event->cancel();
                $player->resetFallDistance();
                return;
            }

            unset(self::$fallImmunityUntil[$key]);
            return;
        }

        $threshold = max(0.05, min(1.0, EnchantTuningManager::getFloat("ascended", "trigger-health-percent", 0.3)));
        if (($player->getHealth() / max(1.0, $player->getMaxHealth())) > $threshold) return;

        $chance = EnchantTuningManager::getLevelInt("ascended", "chance-by-level", $level, 4 + ($level * 3));
        if (!ProcLuckManager::roll($player, "ascended", $chance, 2, 10)) return;

        $session = PlayerManager::getInstance()->getSession($player);
        $cooldown = max(0, EnchantTuningManager::getInt("ascended", "cooldown", 10));
        if ($session !== null && $cooldown > 0 && $session->isOnCooldown("ascended")) return;

        $peakOpt = EnchantTuningManager::getValue("ascended", "target-peak-blocks", null);
        if (is_numeric($peakOpt) && (float) $peakOpt > 0) {
            $boostY = self::launchVelocityForPeakHeight((float) $peakOpt);
        } else {
            $boostY = EnchantTuningManager::getFloat("ascended", "launch-velocity", 1.0);
        }
        $motion = $player->getMotion();
        $motion->y = max($motion->y, $boostY);
        $player->setMotion($motion);
        $player->resetFallDistance();

        $fallImmunitySeconds = max(1, EnchantTuningManager::getInt("ascended", "fall-immunity-seconds", 10));
        self::$fallImmunityUntil[strtolower($player->getName())] = time() + $fallImmunitySeconds;

        if ($session !== null && $cooldown > 0) {
            $session->addCooldown("ascended", $cooldown);
        }
    }

    /**
     * Simulates player (Living) vertical motion: gravity 0.08, drag 0.02 applied after gravity (PM default).
     */
    private static function maxVerticalDisplacementFromInitialYVelocity(float $v0): float
    {
        $v = $v0;
        $h = 0.0;
        $maxH = 0.0;
        for ($i = 0; $i < 8000; $i++) {
            $v = ($v - self::LIVING_GRAVITY) * self::LIVING_FRICTION;
            $h += $v;
            if ($h > $maxH) {
                $maxH = $h;
            }
            if ($h < $maxH - 0.0001) {
                break;
            }
        }
        return $maxH;
    }

    private static function launchVelocityForPeakHeight(float $targetBlocks): float
    {
        $targetBlocks = max(0.1, $targetBlocks);
        if (self::maxVerticalDisplacementFromInitialYVelocity(25.0) < $targetBlocks) {
            return 25.0;
        }
        $lo = 0.1;
        $hi = 25.0;
        for ($i = 0; $i < 48; $i++) {
            $mid = ($lo + $hi) / 2.0;
            if (self::maxVerticalDisplacementFromInitialYVelocity($mid) >= $targetBlocks) {
                $hi = $mid;
            } else {
                $lo = $mid;
            }
        }
        return $hi;
    }
}

