<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\staff\anticheat;

use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\Position;

/**
 * Movement + combat telemetry for Glacia Guard (log-only supplementary checks).
 *
 * @phpstan-type FingerprintMetric array{mean: float, var: float, n: int}
 * @phpstan-type RiskHistory array{
 *   period_started_at:int,
 *   updated_at:int,
 *   score:float,
 *   violation_alerts:int,
 *   heuristic_signals:int,
 *   last_reason:string
 * }
 */
final class AntiCheatProfile
{
    private Vector3 $lastPosition;
    private float $lastAuthAt = 0.0;
    private readonly string $xuid;
    private readonly string $offlineLookup;
    private float $joinWallTime;

    /** Jitter estimate from recent auth/movement samples (milliseconds). */
    private float $jitterMs = 0.0;

    private float $lastYaw = 0.0;
    private float $lastYawDelta = 0.0;
    private float $lastYawAccel = 0.0;
    /** @var float[] */
    private array $yawJerks = [];

    private float $lastSwingWallTime = 0.0;
    private float $lastHitWallTime = 0.0;
    private ?int $lastHitVictimEntityRuntimeId = null;
    private float $lastEyeToVictimDistance = 0.0;
    private int $swingCount = 0;
    private int $hitCount = 0;

    private float $pingEwmaMs = 0.0;
    /** Samples recorded for EWMA stabilization (advanced lag spike detection). */
    private int $lagPingSamples = 0;

    /** @var float[] microtime timestamps, trimmed to ~1s window */
    private array $packetSampleTimes = [];

    /** @var array<int, int[]> victim runtime ids keyed by tick */
    private array $hitsByCombatTick = [];

    private float $expectedKnockbackScalar = 0.0;
    private float $receivedKnockbackScalar = 0.0;
    private int $knockbackSamples = 0;

    /** Last known grounded position when collision looked empty (observe-only). */
    private ?Position $lastProbablySafeGround = null;

    /** @var array<string, FingerprintMetric> */
    private array $fingerprintMetrics = [];
    /** @var RiskHistory */
    private array $riskHistory = [
        "period_started_at" => 0,
        "updated_at" => 0,
        "score" => 0.0,
        "violation_alerts" => 0,
        "heuristic_signals" => 0,
        "last_reason" => "",
    ];
    /** @var list<float> */
    private array $authIntervalsMs = [];
    /** @var list<float> */
    private array $attackTimestamps = [];
    /** @var list<float> */
    private array $attackIntervalsMs = [];
    /** @var list<float> */
    private array $velocityRatios = [];
    /** @var array<string, int> */
    private array $violations = [];
    /** @var array<string, int> */
    private array $buffers = [];
    /** @var array<string, float> */
    private array $lastAlertAt = [];
    private ?Vector3 $expectedMotion = null;
    private int $velocitySamplesRemaining = 0;
    private int $teleportGraceTicks = 0;

    private const LAG_PING_EWMA_ALPHA = 0.125;

    public function __construct(Player $player)
    {
        $this->lastPosition = $player->getLocation()->asVector3();
        $this->xuid = $player->getXuid();
        $this->offlineLookup = strtolower($player->getName());
        $this->joinWallTime = microtime(true);
        $this->lastYaw = $player->getLocation()->getYaw();
    }

    public function getStablePlayerId(): string
    {
        return $this->xuid !== "" ? $this->xuid : $this->offlineLookup;
    }

    public function getJoinWallTime(): float
    {
        return $this->joinWallTime;
    }

    public function getJitterMs(): float
    {
        return $this->jitterMs;
    }

    public function setJitterMs(float $jitterMs): void
    {
        $this->jitterMs = max(0.0, $jitterMs);
    }

    public function getLastYawDelta(): float
    {
        return $this->lastYawDelta;
    }

    public function getLastSwingWallTime(): float
    {
        return $this->lastSwingWallTime;
    }

    public function getLastHitWallTime(): float
    {
        return $this->lastHitWallTime;
    }

    public function getLastHitVictimEntityRuntimeId(): ?int
    {
        return $this->lastHitVictimEntityRuntimeId;
    }

    public function getLastEyeToVictimDistance(): float
    {
        return $this->lastEyeToVictimDistance;
    }

    public function setLastEyeToVictimDistance(float $distance): void
    {
        $this->lastEyeToVictimDistance = max(0.0, $distance);
    }

    public function getSwingCount(): int
    {
        return $this->swingCount;
    }

    public function getHitCount(): int
    {
        return $this->hitCount;
    }

    public function recordArmSwing(float $wallTime): void
    {
        $this->lastSwingWallTime = $wallTime;
        ++$this->swingCount;
    }

    /**
     * @param int $combatTick Server tick when damage was applied
     */
    public function recordHitSample(float $wallTime, int $combatTick, int $victimRuntimeId): void
    {
        $this->lastHitWallTime = $wallTime;
        $this->lastHitVictimEntityRuntimeId = $victimRuntimeId;
        ++$this->hitCount;

        $this->hitsByCombatTick[$combatTick] ??= [];
        $this->hitsByCombatTick[$combatTick][] = $victimRuntimeId;
        while (count($this->hitsByCombatTick) > 80) {
            $ticks = array_keys($this->hitsByCombatTick);
            if ($ticks === []) {
                break;
            }
            unset($this->hitsByCombatTick[(int) $ticks[0]]);
        }
    }

    public function recordPacketSample(float $wallTime): void
    {
        $this->packetSampleTimes[] = $wallTime;
        $this->trimPacketWindow($wallTime - 1.0);
    }

    public function getPacketRatePerSecond(): int
    {
        $this->trimPacketWindow(microtime(true) - 1.0);

        return count($this->packetSampleTimes);
    }

    private function trimPacketWindow(float $minTime): void
    {
        while ($this->packetSampleTimes !== [] && $this->packetSampleTimes[0] < $minTime) {
            array_shift($this->packetSampleTimes);
        }
    }

    /**
     * @return array<int, int[]>
     */
    public function getHitsByCombatTick(): array
    {
        return $this->hitsByCombatTick;
    }

    /**
     * @return float[]
     */
    public function getYawJerks(): array
    {
        return $this->yawJerks;
    }

    public function recordLagPingSample(int $pingMs): void
    {
        $pingMs = max(0, min(8000, $pingMs));
        ++$this->lagPingSamples;
        if ($this->lagPingSamples <= 1) {
            $this->pingEwmaMs = (float) $pingMs;

            return;
        }
        $this->pingEwmaMs = self::LAG_PING_EWMA_ALPHA * $pingMs + (1.0 - self::LAG_PING_EWMA_ALPHA) * $this->pingEwmaMs;
    }

    public function getPingEwmaMs(): float
    {
        return $this->pingEwmaMs;
    }

    public function lagPingSampleCount(): int
    {
        return $this->lagPingSamples;
    }

    public function getLastAuthIntervalMs(): float
    {
        if ($this->authIntervalsMs === []) {
            return 0.0;
        }

        return (float) $this->authIntervalsMs[array_key_last($this->authIntervalsMs)];
    }

    public function refreshJitterFromRecentAuthIntervals(): void
    {
        $samples = array_slice($this->authIntervalsMs, -20);
        if (count($samples) < 2) {
            $this->jitterMs = 0.0;

            return;
        }
        $this->jitterMs = self::standardDeviationSample($samples);
    }

    public function swingHitRatio(): float
    {
        if ($this->hitCount <= 0) {
            return 1.0;
        }

        return $this->swingCount / max(1, $this->hitCount);
    }

    public function updateFacingKinematics(Player $player): void
    {
        $yaw = $player->getLocation()->getYaw();
        $delta = abs(self::angleDiffDegrees($yaw, $this->lastYaw));
        $accel = $delta - $this->lastYawDelta;
        $jerk = $accel - $this->lastYawAccel;
        $this->yawJerks[] = $jerk;
        if (count($this->yawJerks) > 60) {
            array_shift($this->yawJerks);
        }
        $this->lastYaw = $yaw;
        $this->lastYawAccel = $accel;
        $this->lastYawDelta = $delta;
    }

    public static function angleDiffDegrees(float $a, float $b): float
    {
        return fmod(($a - $b + 540.0), 360.0) - 180.0;
    }

    public function recordExpectedKnockback(float $amount): void
    {
        if ($amount <= 0.0) {
            return;
        }
        $this->expectedKnockbackScalar += $amount;
        ++$this->knockbackSamples;
    }

    public function recordReceivedKnockbackDelta(Vector3 $delta): void
    {
        $this->receivedKnockbackScalar += $delta->length();
    }

    public function getKnockbackSamples(): int
    {
        return $this->knockbackSamples;
    }

    public function knockbackRatio(): float
    {
        if ($this->expectedKnockbackScalar <= 0.0) {
            return 1.0;
        }

        return $this->receivedKnockbackScalar / $this->expectedKnockbackScalar;
    }

    public function getLastProbablySafeGround(): ?Position
    {
        return $this->lastProbablySafeGround;
    }

    public function setLastProbablySafeGround(?Position $position): void
    {
        $this->lastProbablySafeGround = $position;
    }

    /**
     * @return array<string, FingerprintMetric>
     */
    public function getFingerprintMetrics(): array
    {
        return $this->fingerprintMetrics;
    }

    /**
     * @return FingerprintMetric|null
     */
    public function getFingerprintMetric(string $metric): ?array
    {
        return $this->fingerprintMetrics[$metric] ?? null;
    }

    /**
     * @param FingerprintMetric $stats
     */
    public function setFingerprintMetric(string $metric, array $stats): void
    {
        $this->fingerprintMetrics[$metric] = $stats;
    }

    /**
     * @return RiskHistory
     */
    public function getRiskHistory(): array
    {
        return $this->riskHistory;
    }

    /**
     * @param RiskHistory $history
     */
    public function setRiskHistory(array $history): void
    {
        $this->riskHistory = [
            "period_started_at" => max(0, (int) ($history["period_started_at"] ?? 0)),
            "updated_at" => max(0, (int) ($history["updated_at"] ?? 0)),
            "score" => max(0.0, (float) ($history["score"] ?? 0.0)),
            "violation_alerts" => max(0, (int) ($history["violation_alerts"] ?? 0)),
            "heuristic_signals" => max(0, (int) ($history["heuristic_signals"] ?? 0)),
            "last_reason" => (string) ($history["last_reason"] ?? ""),
        ];
    }

    public function resetRiskHistory(): void
    {
        $this->setRiskHistory([
            "period_started_at" => 0,
            "updated_at" => 0,
            "score" => 0.0,
            "violation_alerts" => 0,
            "heuristic_signals" => 0,
            "last_reason" => "",
        ]);
    }

    public function getRiskHistoryScore(): float
    {
        return max(0.0, (float) ($this->riskHistory["score"] ?? 0.0));
    }

    public static function variance(array $values): float
    {
        $n = count($values);
        if ($n < 2) {
            return 0.0;
        }
        $mean = array_sum($values) / $n;
        $sum = 0.0;
        foreach ($values as $v) {
            $sum += ($v - $mean) ** 2;
        }

        return $sum / ($n - 1);
    }

    public static function standardDeviationSample(array $values): float
    {
        return sqrt(self::variance($values));
    }

    public function getLastPosition(): Vector3
    {
        return $this->lastPosition;
    }

    public function setLastPosition(Vector3 $position): void
    {
        $this->lastPosition = $position;
    }

    public function getLastAuthAt(): float
    {
        return $this->lastAuthAt;
    }

    public function setLastAuthAt(float $timestamp): void
    {
        $this->lastAuthAt = $timestamp;
    }

    /**
     * @return list<float>
     */
    public function getAuthIntervalsMs(): array
    {
        return $this->authIntervalsMs;
    }

    public function pushAuthInterval(float $intervalMs): void
    {
        $this->authIntervalsMs[] = $intervalMs;
        if (count($this->authIntervalsMs) > 30) {
            array_shift($this->authIntervalsMs);
        }
    }

    /**
     * @return list<float>
     */
    public function getAttackTimestamps(): array
    {
        return $this->attackTimestamps;
    }

    public function setAttackTimestamps(array $attackTimestamps): void
    {
        $this->attackTimestamps = array_values($attackTimestamps);
    }

    public function pushAttackTimestamp(float $timestamp): void
    {
        $this->attackTimestamps[] = $timestamp;
        if (count($this->attackTimestamps) > 30) {
            array_shift($this->attackTimestamps);
        }
    }

    /**
     * @return list<float>
     */
    public function getAttackIntervalsMs(): array
    {
        return $this->attackIntervalsMs;
    }

    public function pushAttackInterval(float $intervalMs): void
    {
        $this->attackIntervalsMs[] = $intervalMs;
        if (count($this->attackIntervalsMs) > 20) {
            array_shift($this->attackIntervalsMs);
        }
    }

    /**
     * @return list<float>
     */
    public function getVelocityRatios(): array
    {
        return $this->velocityRatios;
    }

    public function clearVelocityRatios(): void
    {
        $this->velocityRatios = [];
    }

    public function pushVelocityRatio(float $ratio): void
    {
        $this->velocityRatios[] = $ratio;
        if (count($this->velocityRatios) > 6) {
            array_shift($this->velocityRatios);
        }
    }

    public function getExpectedMotion(): ?Vector3
    {
        return $this->expectedMotion;
    }

    public function setExpectedMotion(?Vector3 $motion): void
    {
        $this->expectedMotion = $motion;
    }

    public function getVelocitySamplesRemaining(): int
    {
        return $this->velocitySamplesRemaining;
    }

    public function setVelocitySamplesRemaining(int $samples): void
    {
        $this->velocitySamplesRemaining = max(0, $samples);
    }

    public function decrementVelocitySamplesRemaining(): void
    {
        if ($this->velocitySamplesRemaining > 0) {
            --$this->velocitySamplesRemaining;
        }
    }

    public function addViolation(string $check, int $amount = 1): int
    {
        $this->violations[$check] = ($this->violations[$check] ?? 0) + max(1, $amount);
        return $this->violations[$check];
    }

    /**
     * @param array<string, int|float|string> $violations
     */
    public function setViolations(array $violations): void
    {
        $sanitized = [];
        foreach ($violations as $check => $value) {
            if (!is_string($check) || trim($check) === "") {
                continue;
            }

            $amount = max(0, (int) $value);
            if ($amount <= 0) {
                continue;
            }

            $sanitized[$check] = $amount;
        }

        $this->violations = $sanitized;
    }

    public function addBuffer(string $key, int $amount = 1): int
    {
        $this->buffers[$key] = ($this->buffers[$key] ?? 0) + max(1, $amount);
        return $this->buffers[$key];
    }

    public function reduceBuffer(string $key, int $amount = 1): int
    {
        $next = max(0, ($this->buffers[$key] ?? 0) - max(1, $amount));
        if ($next === 0) {
            unset($this->buffers[$key]);
            return 0;
        }

        $this->buffers[$key] = $next;
        return $next;
    }

    public function resetBuffer(string $key): void
    {
        unset($this->buffers[$key]);
    }

    public function getBuffer(string $key): int
    {
        return $this->buffers[$key] ?? 0;
    }

    /**
     * @return array<string, int>
     */
    public function getViolations(): array
    {
        arsort($this->violations);
        return $this->violations;
    }

    public function getViolation(string $check): int
    {
        return $this->violations[$check] ?? 0;
    }

    public function decayViolations(int $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        foreach ($this->violations as $check => $value) {
            $next = max(0, $value - $amount);
            if ($next === 0) {
                unset($this->violations[$check]);
                continue;
            }
            $this->violations[$check] = $next;
        }
    }

    public function reset(): void
    {
        $this->authIntervalsMs = [];
        $this->attackTimestamps = [];
        $this->attackIntervalsMs = [];
        $this->velocityRatios = [];
        $this->violations = [];
        $this->buffers = [];
        $this->lastAlertAt = [];
        $this->expectedMotion = null;
        $this->velocitySamplesRemaining = 0;
        $this->lastAuthAt = 0.0;
        $this->teleportGraceTicks = 0;
        $this->jitterMs = 0.0;
        $this->yawJerks = [];
        $this->lastYawDelta = 0.0;
        $this->lastYawAccel = 0.0;
        $this->packetSampleTimes = [];
        $this->hitsByCombatTick = [];
        $this->knockbackSamples = 0;
        $this->expectedKnockbackScalar = 0.0;
        $this->receivedKnockbackScalar = 0.0;
        $this->lastProbablySafeGround = null;
        $this->pingEwmaMs = 0.0;
        $this->lagPingSamples = 0;
        // fingerprints intentionally preserved across staff resets (per-player calibration)
    }

    public function resetFingerprintMetrics(): void
    {
        $this->fingerprintMetrics = [];
    }

    public function getLastAlertAt(string $check): float
    {
        return $this->lastAlertAt[$check] ?? 0.0;
    }

    public function setLastAlertAt(string $check, float $timestamp): void
    {
        $this->lastAlertAt[$check] = $timestamp;
    }

    public function getTeleportGraceTicks(): int
    {
        return $this->teleportGraceTicks;
    }

    public function setTeleportGraceTicks(int $ticks): void
    {
        $this->teleportGraceTicks = max(0, $ticks);
    }

    public function decrementTeleportGraceTicks(): void
    {
        if ($this->teleportGraceTicks > 0) {
            --$this->teleportGraceTicks;
        }
    }
}
