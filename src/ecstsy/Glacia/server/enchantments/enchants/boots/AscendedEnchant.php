<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\boots;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantDebugManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\items\rings\RingManager;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;

final class AscendedEnchant implements EntityDamageEnchantmentHandler
{
    /** @var array<string, int> */
    private static array $fallImmunityUntil = [];
    /** @var array<string, int> */
    private static array $airControlUntilTick = [];

    /** @see Living::getInitialGravity() */
    private const LIVING_GRAVITY = 0.08;
    /** 1 - Living::getInitialDragMultiplier() */
    private const LIVING_FRICTION = 0.98;

    public static function handle(EntityDamageEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) {
            return;
        }

        $player = $event->getEntity();
        if (!$player instanceof Player || RingManager::playerBlocksEnchant($player, "ascended")) {
            return;
        }

        if ($event->getCause() === EntityDamageEvent::CAUSE_FALL) {
            $key = strtolower($player->getName());
            $until = self::$fallImmunityUntil[$key] ?? 0;
            if ($until > time()) {
                $event->cancel();
                $player->resetFallDistance();
                EnchantDebugManager::logPassiveValues($player, "ascended", $level, [
                    "cause" => "fall",
                    "fall_immunity" => true,
                ]);
                return;
            }

            unset(self::$fallImmunityUntil[$key]);
            return;
        }

        $threshold = max(0.05, min(1.0, EnchantTuningManager::value("ascended", "trigger-health-percent", $level, 0.3)));
        $healthPercent = $player->getHealth() / max(1.0, $player->getMaxHealth());
        if ($healthPercent > $threshold) {
            return;
        }

        $session = PlayerManager::getInstance()->getSession($player);
        $cooldownSeconds = EnchantTuningManager::cooldownSeconds("ascended", $level, 10);
        $result = EnchantProcManager::attempt(
            $player,
            "ascended",
            $level,
            EnchantTuningManager::chance("ascended", $level, 7.0),
            $cooldownSeconds,
            static fn() => max(0, (int) ($session?->getCooldown("ascended") ?? 0))
        );
        if (!$result->success) {
            return;
        }

        $peakBlocks = EnchantTuningManager::value("ascended", "target-peak-blocks", $level, 0.0);
        if ($peakBlocks > 0.0) {
            $boostY = self::launchVelocityForPeakHeight($peakBlocks);
        } else {
            $boostY = max(0.0, EnchantTuningManager::value("ascended", "launch-velocity", $level, 1.0));
        }

        $horizontalBoost = max(0.0, EnchantTuningManager::value("ascended", "horizontal-launch-speed", $level, 1.05));
        $motion = $player->getMotion();
        $launchMotion = self::buildHorizontalLaunchVector($player, $horizontalBoost);
        $motion->x = $launchMotion->x;
        $motion->z = $launchMotion->z;
        $motion->y = max($motion->y, $boostY);
        $player->setMotion($motion);
        $player->resetFallDistance();

        $fallImmunitySeconds = max(1, EnchantTuningManager::valueInt("ascended", "fall-immunity-seconds", $level, 10));
        self::$fallImmunityUntil[strtolower($player->getName())] = time() + $fallImmunitySeconds;
        self::startAirControl(
            $player,
            max(0, EnchantTuningManager::valueInt("ascended", "air-control-ticks", $level, 16)),
            max(0.0, EnchantTuningManager::value("ascended", "air-control-speed", $level, 1.1)),
            max(0.0, EnchantTuningManager::value("ascended", "air-control-blend", $level, 0.72))
        );

        EnchantProcManager::applyCooldownIfNeeded($result, static function (int $seconds) use ($session): void {
            $session?->addCooldown("ascended", $seconds);
        });
        EnchantProcManager::debugAppliedValues($player, $result, [
            "health_percent" => round($healthPercent, 4),
            "trigger_health_percent" => $threshold,
            "launch_velocity" => $boostY,
            "horizontal_launch_speed" => $horizontalBoost,
            "fall_immunity_seconds" => $fallImmunitySeconds,
        ]);
    }

    private static function startAirControl(Player $player, int $ticks, float $speed, float $blend): void
    {
        if ($ticks <= 0 || $speed <= 0.0 || $blend <= 0.0) {
            return;
        }

        $playerKey = strtolower($player->getName());
        $expireTick = $player->getServer()->getTick() + $ticks;
        self::$airControlUntilTick[$playerKey] = $expireTick;

        $runTick = null;
        $runTick = static function () use ($player, $playerKey, $expireTick, $speed, $blend, &$runTick): void {
            if (
                !$player->isOnline() ||
                !$player->isAlive() ||
                $player->isClosed() ||
                ($player->isOnGround() && $player->getMotion()->y <= 0.0)
            ) {
                unset(self::$airControlUntilTick[$playerKey]);
                return;
            }

            if ((self::$airControlUntilTick[$playerKey] ?? 0) !== $expireTick || $player->getServer()->getTick() > $expireTick) {
                unset(self::$airControlUntilTick[$playerKey]);
                return;
            }

            $motion = $player->getMotion();
            $desired = self::buildHorizontalLaunchVector($player, $speed);
            $motion->x = ($motion->x * max(0.0, 1.0 - $blend)) + ($desired->x * $blend);
            $motion->z = ($motion->z * max(0.0, 1.0 - $blend)) + ($desired->z * $blend);
            $player->setMotion($motion);
            $player->resetFallDistance();

            Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(
                static function () use (&$runTick): void {
                    if ($runTick !== null) {
                        $runTick();
                    }
                }
            ), 1);
        };

        Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(
            static function () use (&$runTick): void {
                if ($runTick !== null) {
                    $runTick();
                }
            }
        ), 1);
    }

    private static function buildHorizontalLaunchVector(Player $player, float $speed): Vector3
    {
        $direction = $player->getDirectionVector();
        $horizontal = new Vector3($direction->x, 0.0, $direction->z);
        $lengthSquared = ($horizontal->x * $horizontal->x) + ($horizontal->z * $horizontal->z);
        if ($lengthSquared <= 0.0001) {
            return new Vector3(0.0, 0.0, 0.0);
        }

        $length = sqrt($lengthSquared);
        return new Vector3(
            ($horizontal->x / $length) * $speed,
            0.0,
            ($horizontal->z / $length) * $speed
        );
    }

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
