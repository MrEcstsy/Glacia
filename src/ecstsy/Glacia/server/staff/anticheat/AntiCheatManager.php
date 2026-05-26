<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\staff\anticheat;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\staff\anticheat\modules\AimAssistGuardModule;
use ecstsy\Glacia\server\staff\anticheat\modules\CollisionAnomalyGuardModule;
use ecstsy\Glacia\server\staff\anticheat\modules\KillAuraGuardModule;
use ecstsy\Glacia\server\staff\anticheat\modules\MultiAuraGuardModule;
use ecstsy\Glacia\server\staff\anticheat\modules\NoSwingGuardModule;
use ecstsy\Glacia\server\staff\anticheat\GuardDetectionModule;
use ecstsy\Glacia\server\staff\anticheat\GuardFlag;
use ecstsy\Glacia\server\staff\anticheat\GuardFingerprintEngine;
use ecstsy\Glacia\server\staff\anticheat\GuardLagDetector;
use ecstsy\Glacia\server\staff\anticheat\GuardViolationHistoryEngine;
use ecstsy\Glacia\server\staff\anticheat\modules\PacketStormGuardModule;
use ecstsy\Glacia\utils\AuditFileLogger;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\utils\TextFormat as C;

final class AntiCheatManager
{
    private static ?self $instance = null;

    /** @var array<string, AntiCheatProfile> */
    private array $profiles = [];
    /** @var array<string, true> */
    private array $alertSubscribers = [];

    private bool $enabled = true;
    private string $alertPermission = "glacia.anticheat.alerts";
    private int $violationDecayPerSecond = 1;

    private bool $reachEnabled = true;
    private float $reachThreshold = 3.15;
    private int $reachAlertVl = 6;

    private bool $autoClickerEnabled = true;
    private int $autoClickerCpsThreshold = 16;
    private float $autoClickerVarianceThresholdMs = 12.0;
    private int $autoClickerAlertVl = 6;
    private float $autoClickerAlertCooldownSecs = 30.0;

    private bool $timerEnabled = true;
    private int $timerMinSamples = 12;
    private float $timerAverageThresholdMs = 44.0;
    private float $timerVarianceThresholdMs = 6.5;
    private int $timerAlertVl = 8;

    private bool $speedEnabled = true;
    private float $speedGroundMax = 0.70;
    private float $speedAirMax = 0.42;
    private float $speedSprintBonus = 0.08;
    private int $speedMaxPing = 260;
    private int $speedAlertVl = 8;

    private bool $badPacketsEnabled = true;
    private float $badPacketsMaxPitch = 90.0;
    private int $badPacketsAlertVl = 4;

    private bool $velocityEnabled = true;
    private float $velocityMinHorizontal = 0.12;
    private float $velocityMinRatio = 0.55;
    private int $velocityMaxPing = 220;
    private int $velocityAlertVl = 6;

    private bool $guardEnabled = true;
    private bool $guardFingerprintEnabled = true;
    private bool $guardRiskHistoryEnabled = true;
    private int $guardRiskHistoryResetDays = 7;
    private float $guardRiskHistoryBlendWeight = 0.35;
    private float $guardRiskHistoryMaxScore = 100.0;
    private float $guardLogMinConfidence = 82.0;
    private string $guardBypassPermission = "";
    private int $guardPingSoftMs = 180;
    private int $guardPingHardMs = 340;
    private float $guardJitterSoftMs = 38.0;
    private float $guardJitterHardMs = 85.0;
    private float $guardLagMultSoft = 1.12;
    private float $guardLagMultHard = 1.28;
    private bool $guardLagAdvancedSignals = true;
    private int $guardLagSpikeSoftAboveEwmaMs = 72;
    private int $guardLagSpikeHardAboveEwmaMs = 140;
    private float $guardLagStallIntervalSoftMs = 92.0;
    private float $guardLagStallIntervalHardMs = 175.0;
    private int $guardLagMinPingEwmaSamples = 10;
    private int $guardPacketStormMaxPerSecond = 220;
    private int $guardCollisionBoxThreshold = 12;
    private float $guardModuleBroadcastCooldownSecs = 18.0;
    /** @var array<string, bool> */
    private array $guardModuleEnabled = [];

    /** @var list<GuardDetectionModule> */
    private array $guardModules = [];

    private ?GuardFingerprintEngine $guardFingerprints = null;
    private ?GuardRiskHistoryEngine $guardRiskHistory = null;
    private ?GuardViolationHistoryEngine $guardViolationHistory = null;

    /** @var array<string, float> keyed by "{$player}|{$moduleKey}" */
    private array $guardLastLoggedAt = [];

    private GuardLagDetector $guardLagDetector;

    private function __construct()
    {
        $this->guardModules = [
            new PacketStormGuardModule(),
            new MultiAuraGuardModule(),
            new NoSwingGuardModule(),
            new KillAuraGuardModule(),
            new AimAssistGuardModule(),
            new CollisionAnomalyGuardModule(),
        ];

        $this->loadFromConfig();

        Loader::getInstance()->getScheduler()->scheduleRepeatingTask(new ClosureTask(function(): void {
            $this->tick();
        }), 20);

        foreach (Server::getInstance()->getOnlinePlayers() as $player) {
            $this->handleJoin($player);
        }
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public function loadFromConfig(): void
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "misc.yml");
        $section = (array) ($config?->get("anticheat", []) ?? []);

        $this->enabled = (bool) ($section["enabled"] ?? true);
        $this->alertPermission = trim((string) ($section["alert-permission"] ?? "glacia.anticheat.alerts"));
        if ($this->alertPermission === "") {
            $this->alertPermission = "glacia.anticheat.alerts";
        }
        $this->violationDecayPerSecond = max(0, (int) ($section["violation-decay-per-second"] ?? 1));

        $reach = (array) ($section["reach"] ?? []);
        $this->reachEnabled = (bool) ($reach["enabled"] ?? true);
        $this->reachThreshold = max(2.8, (float) ($reach["threshold"] ?? 3.15));
        $this->reachAlertVl = max(1, (int) ($reach["alert-vl"] ?? 6));

        $autoClicker = (array) ($section["autoclicker"] ?? []);
        $this->autoClickerEnabled = (bool) ($autoClicker["enabled"] ?? true);
        $this->autoClickerCpsThreshold = max(8, (int) ($autoClicker["cps-threshold"] ?? 12));
        $this->autoClickerVarianceThresholdMs = max(1.0, (float) ($autoClicker["low-variance-threshold-ms"] ?? 12.0));
        $this->autoClickerAlertVl = max(1, (int) ($autoClicker["alert-vl"] ?? 8));
        $this->autoClickerAlertCooldownSecs = max(5.0, (float) ($autoClicker["alert-cooldown-seconds"] ?? 30.0));

        $timer = (array) ($section["timer"] ?? []);
        $this->timerEnabled = (bool) ($timer["enabled"] ?? true);
        $this->timerMinSamples = max(6, (int) ($timer["min-samples"] ?? 12));
        $this->timerAverageThresholdMs = max(20.0, (float) ($timer["average-threshold-ms"] ?? 44.0));
        $this->timerVarianceThresholdMs = max(1.0, (float) ($timer["variance-threshold-ms"] ?? 6.5));
        $this->timerAlertVl = max(1, (int) ($timer["alert-vl"] ?? 8));

        $speed = (array) ($section["speed"] ?? []);
        $this->speedEnabled = (bool) ($speed["enabled"] ?? true);
        $this->speedGroundMax = max(0.2, (float) ($speed["ground-max"] ?? 0.70));
        $this->speedAirMax = max(0.2, (float) ($speed["air-max"] ?? 0.42));
        $this->speedSprintBonus = max(0.0, (float) ($speed["sprint-bonus"] ?? 0.08));
        $this->speedMaxPing = max(50, (int) ($speed["max-ping"] ?? 260));
        $this->speedAlertVl = max(1, (int) ($speed["alert-vl"] ?? 8));

        $badPackets = (array) ($section["badpackets"] ?? []);
        $this->badPacketsEnabled = (bool) ($badPackets["enabled"] ?? true);
        $this->badPacketsMaxPitch = max(45.0, min(91.0, (float) ($badPackets["max-pitch"] ?? 90.0)));
        $this->badPacketsAlertVl = max(1, (int) ($badPackets["alert-vl"] ?? 4));

        $velocity = (array) ($section["velocity"] ?? []);
        $this->velocityEnabled = (bool) ($velocity["enabled"] ?? true);
        $this->velocityMinHorizontal = max(0.01, (float) ($velocity["min-horizontal-motion"] ?? 0.12));
        $this->velocityMinRatio = max(0.1, min(1.0, (float) ($velocity["min-ratio"] ?? 0.55)));
        $this->velocityMaxPing = max(50, (int) ($velocity["max-ping"] ?? 220));
        $this->velocityAlertVl = max(1, (int) ($velocity["alert-vl"] ?? 6));

        foreach (Server::getInstance()->getOnlinePlayers() as $player) {
            if ($player->hasPermission($this->alertPermission)) {
                $this->alertSubscribers[$player->getName()] = true;
            }
        }

        $guard = (array) ($section["guard"] ?? []);
        $this->guardEnabled = (bool) ($guard["enabled"] ?? true);
        $this->guardFingerprintEnabled = (bool) ($guard["fingerprint-enabled"] ?? true);
        $riskHistory = (array) ($guard["risk-history"] ?? []);
        $this->guardRiskHistoryEnabled = (bool) ($riskHistory["enabled"] ?? true);
        $this->guardRiskHistoryResetDays = max(1, (int) ($riskHistory["reset-days"] ?? 7));
        $this->guardRiskHistoryBlendWeight = max(0.0, min(1.0, (float) ($riskHistory["blend-weight"] ?? 0.35)));
        $this->guardRiskHistoryMaxScore = max(10.0, (float) ($riskHistory["max-score"] ?? 100.0));
        $this->guardLogMinConfidence = max(1.0, min(100.0, (float) ($guard["log-min-confidence"] ?? 82.0)));
        $this->guardBypassPermission = trim((string) ($guard["bypass-permission"] ?? ""));
        $this->guardModuleBroadcastCooldownSecs = max(4.0, (float) ($guard["module-broadcast-cooldown-seconds"] ?? 18.0));

        $lag = (array) ($guard["lag"] ?? []);
        $this->guardPingSoftMs = max(0, (int) ($lag["ping-soft"] ?? 180));
        $this->guardPingHardMs = max(0, (int) ($lag["ping-hard"] ?? 340));
        $this->guardJitterSoftMs = max(0.0, (float) ($lag["jitter-soft-ms"] ?? 38.0));
        $this->guardJitterHardMs = max(0.0, (float) ($lag["jitter-hard-ms"] ?? 85.0));
        $this->guardLagMultSoft = max(1.0, (float) ($lag["multiplier-soft"] ?? 1.12));
        $this->guardLagMultHard = max(1.0, (float) ($lag["multiplier-hard"] ?? 1.28));

        $this->guardLagAdvancedSignals = (bool) ($lag["advanced-lag-detection"] ?? true);
        $this->guardLagSpikeSoftAboveEwmaMs = max(0, (int) ($lag["spike-above-ewma-soft-ms"] ?? 72));
        $this->guardLagSpikeHardAboveEwmaMs = max(0, (int) ($lag["spike-above-ewma-hard-ms"] ?? 140));
        $this->guardLagStallIntervalSoftMs = max(0.0, (float) ($lag["stall-interval-soft-ms"] ?? 92.0));
        $this->guardLagStallIntervalHardMs = max(0.0, (float) ($lag["stall-interval-hard-ms"] ?? 175.0));
        $this->guardLagMinPingEwmaSamples = max(0, (int) ($lag["min-ping-ewma-samples"] ?? 10));

        $this->guardLagDetector = new GuardLagDetector(
            $this->guardPingSoftMs,
            $this->guardPingHardMs,
            $this->guardJitterSoftMs,
            $this->guardJitterHardMs,
            $this->guardLagMultSoft,
            $this->guardLagMultHard,
            $this->guardLagAdvancedSignals,
            $this->guardLagSpikeSoftAboveEwmaMs,
            $this->guardLagSpikeHardAboveEwmaMs,
            $this->guardLagStallIntervalSoftMs,
            $this->guardLagStallIntervalHardMs,
            $this->guardLagMinPingEwmaSamples
        );

        $mods = (array) ($guard["modules"] ?? []);
        $this->guardModuleEnabled = [
            "packet_storm" => (bool) ($mods["packet-storm"] ?? true),
            "multi_aura" => (bool) ($mods["multi-aura"] ?? true),
            "no_swing" => (bool) ($mods["no-swing"] ?? true),
            "kill_aura" => (bool) ($mods["kill-aura"] ?? true),
            "aim_assist" => (bool) ($mods["aim-assist"] ?? true),
            "collision_anomaly" => (bool) ($mods["collision-anomaly"] ?? true),
        ];
        $this->guardPacketStormMaxPerSecond = max(120, (int) ($mods["packet-storm-max-per-second"] ?? 220));
        $this->guardCollisionBoxThreshold = max(4, (int) ($mods["collision-box-flag-threshold"] ?? 12));

        $fingerDir = Loader::getInstance()->getDataFolder() . "guard" . DIRECTORY_SEPARATOR . "fingerprints" . DIRECTORY_SEPARATOR;
        $this->guardFingerprints = $this->guardFingerprintEnabled
            ? new GuardFingerprintEngine($fingerDir)
            : null;
        $historyDir = Loader::getInstance()->getDataFolder() . "guard" . DIRECTORY_SEPARATOR . "risk_history" . DIRECTORY_SEPARATOR;
        $this->guardRiskHistory = $this->guardRiskHistoryEnabled
            ? new GuardRiskHistoryEngine($historyDir, $this->guardRiskHistoryResetDays, $this->guardRiskHistoryMaxScore)
            : null;
        $violationDir = Loader::getInstance()->getDataFolder() . "guard" . DIRECTORY_SEPARATOR . "violations" . DIRECTORY_SEPARATOR;
        $this->guardViolationHistory = new GuardViolationHistoryEngine($violationDir);

        if ($this->guardFingerprints !== null || $this->guardRiskHistory !== null || $this->guardViolationHistory !== null) {
            foreach ($this->profiles as $storedName => $liveProfile) {
                $online = Server::getInstance()->getPlayerExact((string) $storedName);
                if ($online instanceof Player && $liveProfile instanceof AntiCheatProfile) {
                    $this->guardFingerprints?->loadIntoProfile($online, $liveProfile);
                    $this->guardRiskHistory?->loadIntoProfile($online, $liveProfile);
                    $this->guardViolationHistory?->loadIntoProfile($online, $liveProfile);
                }
            }
        }
    }

    public function handleJoin(Player $player): void
    {
        $profile = new AntiCheatProfile($player);
        $this->profiles[$player->getName()] = $profile;
        $this->guardFingerprints?->loadIntoProfile($player, $profile);
        $this->guardRiskHistory?->loadIntoProfile($player, $profile);
        $this->guardViolationHistory?->loadIntoProfile($player, $profile);
        if ($this->canReceiveAlerts($player)) {
            $this->alertSubscribers[$player->getName()] = true;
        }
    }

    public function handleQuit(Player $player): void
    {
        $name = $player->getName();
        $snapshot = $this->profiles[$name] ?? null;
        if ($snapshot instanceof AntiCheatProfile) {
            $this->guardFingerprints?->saveAsync($player, $snapshot);
            $this->guardRiskHistory?->saveAsync($player, $snapshot);
            $this->guardViolationHistory?->saveAsync($player, $snapshot);
        }
        unset($this->profiles[$name], $this->alertSubscribers[$name]);
        $pref = $name . "|";
        foreach (array_keys($this->guardLastLoggedAt) as $key) {
            if (!str_starts_with($key, $pref)) {
                continue;
            }
            unset($this->guardLastLoggedAt[$key]);
        }
    }

    public function toggleAlerts(Player $player): bool
    {
        $name = $player->getName();
        if (!$this->canReceiveAlerts($player)) {
            unset($this->alertSubscribers[$name]);
            return false;
        }

        if (isset($this->alertSubscribers[$name])) {
            unset($this->alertSubscribers[$name]);
            return false;
        }

        $this->alertSubscribers[$name] = true;
        return true;
    }

    /**
     * @return array<string, int>
     */
    public function getViolations(Player $player): array
    {
        return $this->getProfile($player)->getViolations();
    }

    public function reset(Player $player): void
    {
        $profile = $this->getProfile($player);
        $profile->reset();
        $profile->resetFingerprintMetrics();
        $profile->resetRiskHistory();

        $this->guardFingerprints?->delete($player, $profile);
        $this->guardRiskHistory?->delete($player, $profile);
        $this->guardViolationHistory?->delete($player, $profile);
    }

    public function resetSeasonState(): void
    {
        foreach (Server::getInstance()->getOnlinePlayers() as $player) {
            $this->reset($player);
            $this->handleTeleport($player);
        }

        $this->guardFingerprints?->resetAll();
        $this->guardRiskHistory?->resetAll();
        $this->guardViolationHistory?->resetAll();
    }

    public function handleAuthInput(Player $player, PlayerAuthInputPacket $packet): void
    {
        if (!$this->enabled || $this->shouldBypass($player)) {
            return;
        }

        $profile = $this->getProfile($player);
        $profile->decrementTeleportGraceTicks();
        $now = microtime(true);
        $lastAuthAt = $profile->getLastAuthAt();
        if ($lastAuthAt > 0.0) {
            $profile->pushAuthInterval(($now - $lastAuthAt) * 1000);
        }
        $profile->setLastAuthAt($now);

        $profile->refreshJitterFromRecentAuthIntervals();
        $profile->recordLagPingSample($player->getNetworkSession()->getPing());

        $profile->updateFacingKinematics($player);
        if ($this->guardFingerprints !== null && $this->guardEnabled && !$this->shouldBypass($player)) {
            $this->guardFingerprints->record($profile, "yaw_delta", $profile->getLastYawDelta());
        }

        $position = $packet->getPosition();
        $lastPosition = $profile->getLastPosition();
        $deltaX = $position->x - $lastPosition->x;
        $deltaY = $position->y - $lastPosition->y;
        $deltaZ = $position->z - $lastPosition->z;
        $horizontal = sqrt(($deltaX * $deltaX) + ($deltaZ * $deltaZ));

        $this->evaluateBadPackets($player, $profile);
        $this->evaluateTimer($player, $profile);
        $this->evaluateSpeed($player, $profile, $horizontal, $deltaY);
        $this->evaluateVelocity($player, $profile, $horizontal, $deltaY);

        $profile->setLastPosition($position);

        $this->dispatchGlaciaGuardModules($player, $profile, [
            "phase" => "movement",
            "tick" => Server::getInstance()->getTick(),
        ]);
    }

    public function handleMotionSent(Player $player, Vector3 $motion): void
    {
        if (!$this->enabled || $this->shouldBypass($player) || !$this->velocityEnabled) {
            return;
        }

        $horizontal = sqrt(($motion->x * $motion->x) + ($motion->z * $motion->z));
        if ($horizontal < $this->velocityMinHorizontal) {
            return;
        }

        $profile = $this->getProfile($player);
        $profile->setExpectedMotion(clone $motion);
        $profile->setVelocitySamplesRemaining(4);
        $profile->clearVelocityRatios();
        $profile->setTeleportGraceTicks(max($profile->getTeleportGraceTicks(), 2));
    }

    public function handleAttack(Player $attacker, Player $victim): void
    {
        if (!$this->enabled || $this->shouldBypass($attacker)) {
            return;
        }

        $profile = $this->getProfile($attacker);
        $tick = Server::getInstance()->getTick();
        $profile->recordHitSample(microtime(true), $tick, $victim->getId());

        $this->recordAttackCadence($attacker, $profile);
        $this->evaluateReach($attacker, $victim, $profile);

        if ($this->guardFingerprints !== null && $this->guardEnabled) {
            $this->guardFingerprints->record($profile, "swing_hit_ratio", $profile->swingHitRatio());
            $intervals = $profile->getAttackIntervalsMs();
            if (count($intervals) >= 2) {
                $this->guardFingerprints->record($profile, "click_variance", AntiCheatProfile::variance($intervals));
            }
        }

        $this->dispatchGlaciaGuardModules($attacker, $profile, [
            "phase" => "attack",
            "tick" => $tick,
            "combat_tick" => $tick,
        ]);
    }

    /**
     * Samples every inbound MCP packet window for heuristic rate checks only.
     */
    public function observeInboundPacket(Player $player): void
    {
        if (!$this->enabled || !$this->guardEnabled || $this->shouldBypass($player)) {
            return;
        }

        $profile = $this->getProfile($player);
        $profile->recordPacketSample(microtime(true));
        $this->dispatchGlaciaGuardModules($player, $profile, [
            "phase" => "packet",
            "tick" => Server::getInstance()->getTick(),
        ]);
    }

    public function recordArmSwing(Player $player): void
    {
        if (!$this->enabled || !$this->guardEnabled || $this->shouldBypass($player)) {
            return;
        }

        $this->getProfile($player)->recordArmSwing(microtime(true));
    }

    public function getFingerprintEngine(): ?GuardFingerprintEngine
    {
        return $this->guardFingerprints;
    }

    public function isGuardRiskHistoryEnabled(): bool
    {
        return $this->guardRiskHistory !== null;
    }

    public function getGuardRiskHistoryBlendWeight(): float
    {
        return $this->guardRiskHistory !== null ? $this->guardRiskHistoryBlendWeight : 0.0;
    }

    public function getGuardRiskHistoryResetDays(): int
    {
        return $this->guardRiskHistoryResetDays;
    }

    public function getGuardRiskHistorySecondsRemaining(AntiCheatProfile $profile): int
    {
        return $this->guardRiskHistory?->secondsRemaining($profile) ?? 0;
    }

    public function isGuardModuleEnabled(string $configKey): bool
    {
        return $this->guardModuleEnabled[$configKey] ?? false;
    }

    public function getGuardPacketStormMaxPerSecond(): int
    {
        return $this->guardPacketStormMaxPerSecond;
    }

    public function getGuardCollisionBoxThreshold(): int
    {
        return $this->guardCollisionBoxThreshold;
    }

    private function recordAttackCadence(Player $attacker, AntiCheatProfile $profile): void
    {
        if (!$this->autoClickerEnabled) {
            return;
        }

        $now = microtime(true);
        $timestamps = array_values(array_filter(
            $profile->getAttackTimestamps(),
            static fn(float $timestamp): bool => ($now - $timestamp) <= 1.2
        ));

        $lastTimestamp = $timestamps === [] ? null : $timestamps[array_key_last($timestamps)];
        $timestamps[] = $now;
        $profile->setAttackTimestamps($timestamps);

        if ($lastTimestamp !== null) {
            $profile->pushAttackInterval(($now - $lastTimestamp) * 1000);
        }

        $cps = count(array_filter(
            $timestamps,
            static fn(float $timestamp): bool => ($now - $timestamp) <= 1.0
        ));

        $intervals = array_slice($profile->getAttackIntervalsMs(), -8);
        if ($cps < $this->autoClickerCpsThreshold || count($intervals) < 6) {
            return;
        }

        $variance = self::standardDeviation($intervals);
        if ($variance > $this->autoClickerVarianceThresholdMs) {
            return;
        }

        $average = self::average($intervals);
        $this->flag(
            $attacker,
            $profile,
            "AutoClicker",
            "CPS {$cps}, avg " . number_format($average, 1) . "ms, var " . number_format($variance, 1) . "ms",
            2,
            $this->autoClickerAlertVl
        );
    }

    private function evaluateReach(Player $attacker, Player $victim, AntiCheatProfile $profile): void
    {
        if (!$this->reachEnabled) {
            return;
        }

        $eye = $attacker->getEyePos();
        $bb = $victim->getBoundingBox();
        $closestX = max($bb->minX, min($eye->x, $bb->maxX));
        $closestY = max($bb->minY, min($eye->y, $bb->maxY));
        $closestZ = max($bb->minZ, min($eye->z, $bb->maxZ));

        $dx = $eye->x - $closestX;
        $dy = $eye->y - $closestY;
        $dz = $eye->z - $closestZ;
        $reach = sqrt(($dx * $dx) + ($dy * $dy) + ($dz * $dz));
        $profile->setLastEyeToVictimDistance($reach);

        $pingAllowance = min(0.35, max($attacker->getNetworkSession()->getPing(), $victim->getNetworkSession()->getPing()) / 3000);
        $lagMultiplier = $this->guardLagMultiplierFromNetwork($attacker, $profile);
        $allowedReach = ($this->reachThreshold + $pingAllowance) * $lagMultiplier;

        if ($reach <= $allowedReach) {
            if ($this->guardFingerprints !== null && $this->guardEnabled) {
                $this->guardFingerprints->record($profile, "reach", $reach);
            }

            return;
        }

        $this->flag(
            $attacker,
            $profile,
            "Reach",
            number_format($reach, 2) . " blocks on " . $victim->getName(),
            2,
            $this->reachAlertVl
        );
    }

    private function evaluateTimer(Player $player, AntiCheatProfile $profile): void
    {
        if (!$this->timerEnabled || $profile->getTeleportGraceTicks() > 0) {
            return;
        }

        $intervals = array_slice($profile->getAuthIntervalsMs(), -$this->timerMinSamples);
        if (count($intervals) < $this->timerMinSamples) {
            return;
        }

        $average = self::average($intervals);
        $variance = self::standardDeviation($intervals);
        if ($average > $this->timerAverageThresholdMs || $variance > $this->timerVarianceThresholdMs) {
            return;
        }

        $this->flag(
            $player,
            $profile,
            "Timer",
            "avg " . number_format($average, 1) . "ms, var " . number_format($variance, 1) . "ms",
            1,
            $this->timerAlertVl
        );
    }

    private function evaluateBadPackets(Player $player, AntiCheatProfile $profile): void
    {
        if (!$this->badPacketsEnabled) {
            return;
        }

        $pitch = $player->getLocation()->getPitch();
        if (abs($pitch) <= $this->badPacketsMaxPitch) {
            $profile->reduceBuffer("BadPackets", 1);
            return;
        }

        $buffer = $profile->addBuffer("BadPackets", 2);
        if ($buffer < 2) {
            return;
        }

        $this->flag(
            $player,
            $profile,
            "BadPackets",
            "pitch " . number_format($pitch, 2),
            1,
            $this->badPacketsAlertVl
        );
    }

    private function evaluateSpeed(Player $player, AntiCheatProfile $profile, float $horizontalDelta, float $deltaY): void
    {
        if (
            !$this->speedEnabled ||
            $player->getNetworkSession()->getPing() > $this->speedMaxPing ||
            $profile->getTeleportGraceTicks() > 0 ||
            $profile->getVelocitySamplesRemaining() > 0 ||
            $player->isFlying() ||
            $player->getAllowFlight() ||
            $player->isGliding() ||
            !$player->isAlive()
        ) {
            $profile->resetBuffer("Speed");
            return;
        }

        if ($horizontalDelta <= 0.001) {
            $profile->reduceBuffer("Speed", 1);
            return;
        }

        $allowed = $player->isOnGround() ? $this->speedGroundMax : $this->speedAirMax;
        if ($player->isSprinting()) {
            $allowed += $this->speedSprintBonus;
        }

        $speedEffect = $player->getEffects()->get(VanillaEffects::SPEED());
        if ($speedEffect !== null) {
            $allowed += 0.055 * ($speedEffect->getAmplifier() + 1);
        }

        $slownessEffect = $player->getEffects()->get(VanillaEffects::SLOWNESS());
        if ($slownessEffect !== null) {
            $allowed -= 0.045 * ($slownessEffect->getAmplifier() + 1);
        }

        $allowed += min(0.10, $player->getNetworkSession()->getPing() / 4000);
        $allowed = max(0.18, $allowed);

        if ($horizontalDelta <= $allowed) {
            $profile->reduceBuffer("Speed", 1);
            return;
        }

        if (!$player->isOnGround() && $deltaY > 0.42) {
            return;
        }

        $buffer = $profile->addBuffer("Speed", $horizontalDelta > ($allowed + 0.12) ? 2 : 1);
        if ($buffer < 3) {
            return;
        }

        $this->flag(
            $player,
            $profile,
            "Speed",
            "move " . number_format($horizontalDelta, 3) . " > " . number_format($allowed, 3),
            $horizontalDelta > ($allowed + 0.18) ? 2 : 1,
            $this->speedAlertVl
        );
    }

    private function evaluateVelocity(Player $player, AntiCheatProfile $profile, float $horizontalDelta, float $verticalDelta): void
    {
        if (
            !$this->velocityEnabled ||
            $player->getNetworkSession()->getPing() > $this->velocityMaxPing ||
            $profile->getVelocitySamplesRemaining() <= 0
        ) {
            return;
        }

        $motion = $profile->getExpectedMotion();
        if ($motion === null) {
            return;
        }

        $expectedHorizontal = sqrt(($motion->x * $motion->x) + ($motion->z * $motion->z));
        if ($expectedHorizontal < $this->velocityMinHorizontal) {
            return;
        }

        $ratio = $horizontalDelta / max(0.001, $expectedHorizontal);
        $profile->pushVelocityRatio($ratio);
        $profile->decrementVelocitySamplesRemaining();

        $friction = $player->isOnGround() ? 0.546 : 0.91;
        $profile->setExpectedMotion(new Vector3(
            $motion->x * $friction,
            ($motion->y - 0.08) * 0.98,
            $motion->z * $friction
        ));

        if ($profile->getVelocitySamplesRemaining() > 0) {
            return;
        }

        $ratios = $profile->getVelocityRatios();
        $profile->clearVelocityRatios();
        $profile->setExpectedMotion(null);
        if (count($ratios) < 2) {
            return;
        }

        $averageRatio = self::average($ratios);
        $bestRatio = max($ratios);
        if ($averageRatio >= $this->velocityMinRatio || $bestRatio >= ($this->velocityMinRatio + 0.08)) {
            return;
        }

        $this->flag(
            $player,
            $profile,
            "Velocity",
            "ratio " . number_format($averageRatio, 2) . " (best " . number_format($bestRatio, 2) . "), dY " . number_format($verticalDelta, 2),
            2,
            $this->velocityAlertVl
        );
    }

    public function handleTeleport(Player $player): void
    {
        $profile = $this->getProfile($player);
        $profile->setLastPosition($player->getLocation()->asVector3());
        $profile->setLastAuthAt(0.0);
        $profile->setExpectedMotion(null);
        $profile->setVelocitySamplesRemaining(0);
        $profile->clearVelocityRatios();
        $profile->setTeleportGraceTicks(10);
        $profile->resetBuffer("Speed");
        $profile->resetBuffer("BadPackets");
    }

    private function flag(Player $player, AntiCheatProfile $profile, string $check, string $detail, int $amount, int $alertVl): void
    {
        $violation = $profile->addViolation($check, $amount);
        if ($violation < $alertVl) {
            return;
        }

        $now = microtime(true);
        $cooldown = $check === "AutoClicker"
            ? $this->autoClickerAlertCooldownSecs
            : 10.0;
        if (($now - $profile->getLastAlertAt($check)) < $cooldown) {
            return;
        }
        $profile->setLastAlertAt($check, $now);
        $this->guardRiskHistory?->recordViolationAlert($profile, $check, $violation);
        $this->guardRiskHistory?->saveAsync($player, $profile);
        $this->guardViolationHistory?->saveAsync($player, $profile);

        $world = $player->getWorld()->getFolderName();
        $ping = $player->getNetworkSession()->getPing();
        $message = C::colorize(
            "&8[&bGuard&8] &f{$player->getName()} &7failed &e{$check}&7: &f{$detail} &8| &cVL {$violation} &8| &b{$ping}ms &8| &7{$world}"
        );

        foreach (array_keys($this->alertSubscribers) as $subscriberName) {
            $subscriber = Server::getInstance()->getPlayerExact($subscriberName);
            if ($subscriber === null || !$this->canReceiveAlerts($subscriber)) {
                unset($this->alertSubscribers[$subscriberName]);
                continue;
            }
            $subscriber->sendMessage($message);
        }

        Loader::getInstance()->getLogger()->info(C::clean("[Glacia Guard] {$player->getName()} VL {$violation} {$check}: {$detail}"));
        $lagRow = [];
        if ($this->guardEnabled) {
            $lag = $this->guardLagDetector->evaluate($player, $profile);
            $lagRow = [
                "tier" => $lag->tier,
                "multiplier" => round($lag->multiplier, 4),
                "causeMask" => $lag->causeMask,
                "pingMs" => $lag->pingMs,
                "jitterMs" => round($lag->jitterMs, 3),
                "pingEwmaMs" => round($lag->pingEwmaMs, 3),
                "pingSpikeMs" => round($lag->pingSpikeMs, 3),
                "lastAuthIntervalMs" => round($lag->lastAuthIntervalMs, 3),
            ];
        }
        AuditFileLogger::staff("Glacia Guard", [
            "player" => $player->getName(),
            "check" => $check,
            "detail" => $detail,
            "vl" => $violation,
            "ping" => $ping,
            "world" => $world,
            "tier" => "violation_alert",
            "lag" => $lagRow,
        ]);
    }

    private function tick(): void
    {
        if (!$this->enabled || $this->violationDecayPerSecond <= 0) {
            return;
        }

        foreach ($this->profiles as $profile) {
            $profile->decayViolations($this->violationDecayPerSecond);
        }
    }

    private function shouldBypass(Player $player): bool
    {
        if ($player->hasPermission("glacia.staff") || $player->isCreative()) {
            return true;
        }

        return $this->guardBypassPermission !== "" && $player->hasPermission($this->guardBypassPermission);
    }

    private function canReceiveAlerts(Player $player): bool
    {
        if (!$player->hasPermission($this->alertPermission)) {
            return false;
        }

        return $player->hasPermission("glacia.staff")
            || $player->hasPermission("glacia.anticheat.command");
    }

    /**
     * Widens reach tolerance when connection looks unstable (ping/jitter + optional EWMA spike + input stalls).
     */
    private function guardLagMultiplierFromNetwork(Player $player, AntiCheatProfile $profile): float
    {
        if (!$this->guardEnabled) {
            return 1.0;
        }

        return $this->guardLagDetector->evaluate($player, $profile)->multiplier;
    }

    /**
     * @param array<string, mixed> $ctx
     */
    private function dispatchGlaciaGuardModules(Player $player, AntiCheatProfile $profile, array $ctx): void
    {
        if (!$this->enabled || !$this->guardEnabled || $this->shouldBypass($player)) {
            return;
        }

        foreach ($this->guardModules as $module) {
            if (!$this->isGuardModuleEnabled($module->configKey())) {
                continue;
            }

            $hit = $module->check($this, $player, $profile, $ctx);
            if ($hit === null) {
                continue;
            }

            if ($hit->confidence < $this->guardLogMinConfidence) {
                continue;
            }

            $coolKey = $player->getName() . "|" . $module->configKey();
            $now = microtime(true);
            if (($now - ($this->guardLastLoggedAt[$coolKey] ?? 0.0)) < $this->guardModuleBroadcastCooldownSecs) {
                return;
            }
            $this->guardLastLoggedAt[$coolKey] = $now;

            $this->broadcastGlaciaGuardSignal($player, $profile, $module->getName(), $hit);

            return;
        }
    }

    private function broadcastGlaciaGuardSignal(Player $player, AntiCheatProfile $profile, string $moduleLabel, GuardFlag $hit): void
    {
        $this->guardRiskHistory?->recordHeuristicSignal($profile, $moduleLabel, $hit->confidence);
        $this->guardRiskHistory?->saveAsync($player, $profile);

        $world = $player->getWorld()->getFolderName();
        $ping = $player->getNetworkSession()->getPing();
        $conf = number_format($hit->confidence, 1);
        $message = C::colorize(
            "&8[&bGuard·Sig&8] &f{$player->getName()} &7· &e{$moduleLabel}&7 · &f{$hit->detail} &8· &aconf {$conf}% &8| &b{$ping}ms &8| &7{$world}"
        );

        foreach (array_keys($this->alertSubscribers) as $subscriberName) {
            $subscriber = Server::getInstance()->getPlayerExact($subscriberName);
            if ($subscriber === null || !$this->canReceiveAlerts($subscriber)) {
                unset($this->alertSubscribers[$subscriberName]);
                continue;
            }
            $subscriber->sendMessage($message);
        }

        Loader::getInstance()->getLogger()->notice(C::clean("[Glacia Guard] {$moduleLabel} {$hit->confidence}% {$player->getName()}: {$hit->detail}"));
        $lag = $this->guardLagDetector->evaluate($player, $profile);
        AuditFileLogger::staff("Glacia Guard", [
            "tier" => "heuristic_signal",
            "module" => $moduleLabel,
            "player" => $player->getName(),
            "confidence" => $hit->confidence,
            "detail" => $hit->detail,
            "evidence" => $hit->evidenceSnapshot,
            "ping" => $ping,
            "jitterMs" => $profile->getJitterMs(),
            "world" => $world,
            "lag" => [
                "lagTier" => $lag->tier,
                "lagMultiplier" => round($lag->multiplier, 4),
                "causeMask" => $lag->causeMask,
                "pingEwmaMs" => round($lag->pingEwmaMs, 3),
                "pingSpikeMs" => round($lag->pingSpikeMs, 3),
                "lastAuthIntervalMs" => round($lag->lastAuthIntervalMs, 3),
            ],
        ]);
    }

    /**
     * Live telemetry profile for staff UI (online players only).
     */
    public function getLiveProfile(Player $player): AntiCheatProfile
    {
        return $this->getProfile($player);
    }

    public function isAnticheatEnabled(): bool
    {
        return $this->enabled;
    }

    public function assessLag(Player $player): GuardLagResult
    {
        return $this->guardLagDetector->evaluate($player, $this->getProfile($player));
    }

    private function getProfile(Player $player): AntiCheatProfile
    {
        return $this->profiles[$player->getName()] ??= new AntiCheatProfile($player);
    }

    /**
     * @param list<float> $values
     */
    private static function average(array $values): float
    {
        if ($values === []) {
            return 0.0;
        }

        return array_sum($values) / count($values);
    }

    /**
     * @param list<float> $values
     */
    private static function standardDeviation(array $values): float
    {
        $count = count($values);
        if ($count < 2) {
            return 0.0;
        }

        $average = self::average($values);
        $sum = 0.0;
        foreach ($values as $value) {
            $diff = $value - $average;
            $sum += $diff * $diff;
        }

        return sqrt($sum / $count);
    }
}
