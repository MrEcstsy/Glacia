<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\staff\anticheat;

use pocketmine\player\Player;

/**
 * Client-side-ish lag proxies: smoothed ping, RTT spikes vs EWMA, input interval jitter/stutter.
 *
 * Ping from MCPE networking is heuristic; EWMA spike catches sudden RTT climbs better than thresholds alone.
 */
final class GuardLagDetector
{
    public function __construct(
        private readonly int $pingSoftMs,
        private readonly int $pingHardMs,
        private readonly float $jitterSoftMs,
        private readonly float $jitterHardMs,
        private readonly float $multSoft,
        private readonly float $multHard,
        private readonly bool $advancedLagSignalsEnabled,
        private readonly int $spikeSoftAboveEwmaMs,
        private readonly int $spikeHardAboveEwmaMs,
        private readonly float $stallIntervalSoftMs,
        private readonly float $stallIntervalHardMs,
        private readonly int $minEwmaPingSamplesBeforeSpike
    ) {
    }

    public function evaluate(Player $player, AntiCheatProfile $profile): GuardLagResult
    {
        $pingMs = max(0, min(8000, $player->getNetworkSession()->getPing()));
        $jitterMs = $profile->getJitterMs();
        $intervalMs = $profile->getLastAuthIntervalMs();
        $ewma = $profile->getPingEwmaMs();
        $spikeAboveEwma = 0.0;
        $stallWarnMs = $intervalMs;

        if ($this->advancedLagSignalsEnabled) {
            if (
                $this->minEwmaPingSamplesBeforeSpike > 0
                && $profile->lagPingSampleCount() >= $this->minEwmaPingSamplesBeforeSpike
                && $ewma > 1.0
            ) {
                $spikeAboveEwma = max(0.0, $pingMs - $ewma);
            }
        } else {
            $stallWarnMs = 0.0;
        }

        if ($profile->getTeleportGraceTicks() > 0) {
            return new GuardLagResult(
                GuardLagResult::TIER_OK,
                1.0,
                GuardLagResult::CAUSE_NONE,
                $pingMs,
                $jitterMs,
                $ewma,
                $spikeAboveEwma,
                $stallWarnMs
            );
        }

        $mask = GuardLagResult::CAUSE_NONE;
        $hard = false;
        $soft = false;

        if ($pingMs >= $this->pingHardMs) {
            $mask |= GuardLagResult::CAUSE_PING_HARD;
            $hard = true;
        } elseif ($pingMs >= $this->pingSoftMs) {
            $mask |= GuardLagResult::CAUSE_PING_SOFT;
            $soft = true;
        }

        if ($jitterMs >= $this->jitterHardMs) {
            $mask |= GuardLagResult::CAUSE_JITTER_HARD;
            $hard = true;
        } elseif ($jitterMs >= $this->jitterSoftMs) {
            $mask |= GuardLagResult::CAUSE_JITTER_SOFT;
            $soft = true;
        }

        if ($this->advancedLagSignalsEnabled) {
            if ($spikeAboveEwma >= $this->spikeHardAboveEwmaMs) {
                $mask |= GuardLagResult::CAUSE_SPIKE_HARD;
                $hard = true;
            } elseif ($spikeAboveEwma >= $this->spikeSoftAboveEwmaMs) {
                $mask |= GuardLagResult::CAUSE_SPIKE_SOFT;
                $soft = true;
            }
        }

        if ($this->advancedLagSignalsEnabled) {
            if ($stallWarnMs >= $this->stallIntervalHardMs) {
                $mask |= GuardLagResult::CAUSE_STALL_HARD;
                $hard = true;
            } elseif ($stallWarnMs >= $this->stallIntervalSoftMs) {
                $mask |= GuardLagResult::CAUSE_STALL_SOFT;
                $soft = true;
            }
        }

        $tier = GuardLagResult::TIER_OK;
        $multiplier = 1.0;
        if ($hard) {
            $tier = GuardLagResult::TIER_HARD;
            $multiplier = $this->multHard;
        } elseif ($soft) {
            $tier = GuardLagResult::TIER_SOFT;
            $multiplier = $this->multSoft;
        }

        return new GuardLagResult(
            $tier,
            $multiplier,
            $mask,
            $pingMs,
            $jitterMs,
            $ewma,
            $spikeAboveEwma,
            $stallWarnMs
        );
    }
}
