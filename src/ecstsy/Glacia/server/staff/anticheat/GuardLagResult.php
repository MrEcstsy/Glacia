<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\staff\anticheat;

/** Outcome of Glacia lag compensation (widens reach allowance when connection is unstable). */
final class GuardLagResult
{
    public const TIER_OK = 0;
    public const TIER_SOFT = 1;
    public const TIER_HARD = 2;

    /** Bit flags for audits / debugging (human-readable masks). */
    public const CAUSE_NONE = 0;
    public const CAUSE_PING_SOFT = 1;
    public const CAUSE_PING_HARD = 2;
    public const CAUSE_JITTER_SOFT = 4;
    public const CAUSE_JITTER_HARD = 8;
    public const CAUSE_SPIKE_SOFT = 16;
    public const CAUSE_SPIKE_HARD = 32;
    public const CAUSE_STALL_SOFT = 64;
    public const CAUSE_STALL_HARD = 128;

    public function __construct(
        public readonly int $tier,
        public readonly float $multiplier,
        public readonly int $causeMask,
        public readonly int $pingMs,
        public readonly float $jitterMs,
        public readonly float $pingEwmaMs,
        public readonly float $pingSpikeMs,
        public readonly float $lastAuthIntervalMs
    ) {
    }
}
