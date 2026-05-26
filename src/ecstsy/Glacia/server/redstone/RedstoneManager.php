<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\redstone;

use diamondgold\DummyItemsBlocks\block\PistonExtensionHelper;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\tasks\RedstoneUpdateTask;
use ecstsy\Glacia\utils\SplitConfigHelper;
use pocketmine\block\Block;
use pocketmine\plugin\Plugin;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\block\Button;
use pocketmine\block\Lever;
use pocketmine\block\PressurePlate;
use pocketmine\block\Redstone;
use pocketmine\block\RedstoneLamp;
use pocketmine\block\RedstoneRepeater;
use pocketmine\block\RedstoneWire;
use pocketmine\block\WoodenButton;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\format\Chunk;
use pocketmine\world\Position;
use pocketmine\world\World;
use function array_keys;
use function array_pop;
use function max;

final class RedstoneManager
{
    use SingletonTrait;

    /** @var array<string, array<int, array{int, int, int}>> */
    private array $queuedUpdates = [];
    /** @var array<string, array<int, int>> */
    private array $powerStates = [];
    /** @var array<string, array<int, int>> */
    private array $buttonPowerExpiries = [];
    /** @var array<string, array<int, array{token: int, desired: bool}>> */
    private array $repeaterUpdates = [];
    /** @var array<string, RedstoneSignalSource> */
    private array $registeredSignalSources = [];
    /** @var array<string, RedstoneReceiver> */
    private array $registeredReceivers = [];

    private bool $flushScheduled = false;
    private int $nextRepeaterToken = 0;

    private int $maxWireDistance = 16;
    private int $maxUpdatesPerFlush = 1024;
    /** @see redstone.yml chunk-load-dispenser-warmup-delay-ticks */
    private int $chunkLoadDispenserWarmupDelayTicks = 8;
    /** 0 = skip one-shot sweep */
    private int $postBootDispenserWarmupDelayTicks = 80;
    private int $stoneButtonPowerTicks = 20;
    private int $woodenButtonPowerTicks = 30;
    private int $pressurePlateRefreshTicks = 2;
    private int $dispenserCooldownTicks = 4;
    private int $tntFuseTicks = 80;
    private float $tntLaunchHorizontal = 0.35;
    private float $tntLaunchVerticalBoost = 0.06;
    private float $tntLaunchVertical = 0.35;
    private float $tntSpawnOffset = 0.72;
    /** Axis impulse for dispenser primed TNT along facing, before stack multiplier. */
    private float $primedDispenserLaunchBase = 0.09;
    /** 0 = stack depth does not scale launch; each +0.1 adds to the per-stack multiplier. Primed TNT only. */
    private float $primedDispenserStackVelocityPerExtraPiece = 0.0;
    /** If true (default), dispenser-primed TNT uses world gravity; if false, setHasGravity(false). */
    private bool $primedDispenserTntGravityEnabled = true;
    private bool $tntWorksUnderwater = false;
    /**
     * When false (legacy): dispenser primed TNT ignores water/lava flow nudges — straighter underwater shots.
     * When true: flowing liquids apply PocketMine's small current impulse like other entities.
     */
    private bool $primedTntLiquidCurrents = false;
    /** When true and the shot uses cannon physics, applies gravity/drag multipliers below. */
    private bool $cannonTntEnabled = false;
    private float $cannonTntWaterDragMultiplier = 1.0;
    private float $cannonTntGravityMultiplier = 1.0;
    private bool $cannonTntPropulsionEnabled = true;
    private float $cannonTntPropulsionRadius = 5.0;
    private float $cannonTntPropulsionPower = 1.15;
    private float $cannonTntPropulsionVerticalBoost = 0.08;
    /** Floor for linear falloff after pad; optional (see falloff-radius-pad). */
    private float $cannonTntPropulsionMinFalloff = 0.0;
    /** Added to radius only in falloff denominator: at dist≈r, (1−dist/(r+pad))&gt;0. Default &gt;0 fixes 2-gap+r=3 rim with no YAML edit. Set 0 for old strict rim. */
    private float $cannonTntPropulsionFalloffRadiusPad = 0.4;
    /** When false: upward additive scales by max(0.35, falloff). When true: full vertical-boost every hit (less distance variance). */
    private bool $cannonTntPropulsionVerticalBoostFull = false;
    /**
     * When true: additive vertical-boost is multiplied by min(1, strength / sourcePower) so weak glancing hits do not get full pop.
     * Good with low power + moderate vertical-boost (avoids “sky shot” when radial shove is tiny).
     */
    private bool $cannonTntPropulsionVerticalBoostScalesWithHitStrength = false;
    /** Extra +Y chunk (vertical-boost); off = only radial direction.y × strength (flat setups ≈ no Glacia pop). */
    private bool $cannonTntPropulsionAdditiveVerticalBoost = true;
    private float $cannonTntPropulsionWaterMultiplier = 1.35;
    /** Soft cap from YAML {@code max-motion} (blocks/tick velocity magnitude). */
    private float $cannonTntPropulsionMaxMotion = 2.2;
    /** Hard ceiling — effective cap is {@code min(max-motion, absolute-max-motion)}. */
    private float $cannonTntPropulsionAbsoluteMaxMotion = 128.0;
    /**
     * When true: only dispenser-fired primed TNT is pushed (sand still is). Manual flint-lit chains behave like vanilla craters.
     * @see redstone.yml cannon-tnt-propulsion.only-dispenser-primed-targets
     */
    private bool $cannonTntPropulsionOnlyDispenserPrimedTargets = false;
    private float $sandCobwebHorizontalDamp = 0.25;
    private float $sandCobwebVerticalDamp = 0.05;
    private float $sandCobwebMaxFallPerTick = 0.004;
    private bool $sandCobwebNoDragWhileInside = true;

    /**
     * gravity_eff ∝ gravity-multiplier × {@see getDispenserPrimedGravityOnePlusVelocityMultiplier}.
     * Default curve: inverse — faster shots reduce effective gravity ("float" more).
     */
    private bool $dispenserPrimedGravityOnePlusVelocityEnabled = false;
    private float $dispenserPrimedGravityVelocityCoeff = 1.0;
    /** Speed metric: xz (horizontal) | xyz | total — see resolveTntFormulaSpeed(). */
    private string $dispenserPrimedGravityVelocityMode = "xz";
    /** inverse | legacy — see primed-tnt-gravity-times-one-plus-velocity.curve */
    private string $dispenserPrimedGravityVelocityCurve = "inverse";

    /** @see redstone.yml primed-tnt-water-suppresses-explosion-block-break */
    private bool $primedTntWaterSuppressesExplosionBlockBreak = true;

    /** Propulsion impulse ∝ strength × (1 + coeff × |target motion|); see hit-strength-times-one-plus-velocity. */
    private bool $propulsionOnePlusVelocityEnabled = false;
    private float $propulsionOnePlusVelocityCoeff = 0.0;
    private string $propulsionOnePlusVelocityMode = "xz";

    protected static function make(): self
    {
        $instance = new self();
        $instance->reloadConfig();
        if (class_exists(\diamondgold\DummyItemsBlocks\block\Piston::class)) {
            $instance->registerReceiver(\diamondgold\DummyItemsBlocks\block\Piston::class, new PistonRedstoneHandler());
        }
        return $instance;
    }

    public static function init(): void
    {
        self::getInstance();
    }

    public function reloadConfig(): void
    {
        // Always sync from disk — getConfiguration() alone returns a stale in-memory cache unless reloadConfiguration() runs.
        $redstone = SplitConfigHelper::loadRootSection(Loader::getInstance(), "redstone.yml", "redstone");
        $button = (array) ($redstone["button-power-ticks"] ?? []);
        $pressurePlate = (array) ($redstone["pressure-plate"] ?? []);
        $dispenser = (array) ($redstone["dispenser"] ?? []);
        $propulsion = (array) ($redstone["cannon-tnt-propulsion"] ?? []);
        $sandCobweb = (array) ($redstone["sand-cobweb"] ?? []);
        $velocity = (array) ($dispenser["tnt-velocity"] ?? []);

        $this->maxWireDistance = max(1, (int) ($redstone["max-wire-distance"] ?? 16));
        $this->maxUpdatesPerFlush = max(16, (int) ($redstone["max-updates-per-flush"] ?? 1024));
        $this->chunkLoadDispenserWarmupDelayTicks = max(1, min(600, (int) ($redstone["chunk-load-dispenser-warmup-delay-ticks"] ?? 8)));
        $this->postBootDispenserWarmupDelayTicks = max(0, min(12000, (int) ($redstone["post-boot-dispenser-warmup-delay-ticks"] ?? 80)));
        $this->stoneButtonPowerTicks = max(1, (int) ($button["stone"] ?? 20));
        $this->woodenButtonPowerTicks = max(1, (int) ($button["wooden"] ?? 30));
        $this->pressurePlateRefreshTicks = max(1, (int) ($pressurePlate["refresh-ticks"] ?? 2));
        $this->dispenserCooldownTicks = max(1, (int) ($dispenser["cooldown-ticks"] ?? 4));
        $this->tntFuseTicks = max(1, (int) ($dispenser["tnt-fuse-ticks"] ?? 80));
        $this->tntLaunchHorizontal = (float) ($velocity["horizontal"] ?? 0.35);
        $this->tntLaunchVerticalBoost = (float) ($velocity["vertical-boost"] ?? 0.06);
        $this->tntLaunchVertical = (float) ($velocity["vertical"] ?? 0.35);
        $this->tntSpawnOffset = (float) ($dispenser["spawn-offset"] ?? 0.72);
        $this->primedDispenserLaunchBase = (float) ($dispenser["primed-tnt-launch-base"] ?? 0.09);
        $this->primedDispenserStackVelocityPerExtraPiece = (float) ($dispenser["primed-tnt-stack-velocity-per-extra-piece"] ?? 0.0);
        $this->primedDispenserTntGravityEnabled = (bool) ($dispenser["primed-tnt-dispenser-gravity"] ?? true);
        $this->tntWorksUnderwater = (bool) ($dispenser["works-underwater"] ?? false);
        $this->primedTntLiquidCurrents = (bool) ($dispenser["primed-tnt-liquid-currents"] ?? false);
        $this->cannonTntEnabled = (bool) ($dispenser["cannon-mode"] ?? false);
        $this->cannonTntWaterDragMultiplier = max(0.0, (float) ($dispenser["water-drag-multiplier"] ?? 1.0));
        $this->cannonTntGravityMultiplier = max(0.0, (float) ($dispenser["gravity-multiplier"] ?? 1.0));
        $this->cannonTntPropulsionEnabled = (bool) ($propulsion["enabled"] ?? true);
        $this->cannonTntPropulsionRadius = max(0.5, (float) ($propulsion["radius"] ?? 5.0));
        $this->cannonTntPropulsionMinFalloff = max(0.0, min(1.0, (float) ($propulsion["minimum-falloff"] ?? 0.0)));
        $this->cannonTntPropulsionFalloffRadiusPad = max(0.0, (float) ($propulsion["falloff-radius-pad"] ?? 0.4));
        $this->cannonTntPropulsionPower = max(0.0, (float) ($propulsion["power"] ?? 1.15));
        $this->cannonTntPropulsionVerticalBoost = (float) ($propulsion["vertical-boost"] ?? 0.08);
        $this->cannonTntPropulsionVerticalBoostFull = (bool) ($propulsion["vertical-boost-full"] ?? false);
        $this->cannonTntPropulsionVerticalBoostScalesWithHitStrength = (bool) ($propulsion["vertical-boost-scales-with-hit-strength"] ?? false);
        $this->cannonTntPropulsionAdditiveVerticalBoost = (bool) ($propulsion["additive-vertical-boost"] ?? true);
        $this->cannonTntPropulsionWaterMultiplier = max(0.0, (float) ($propulsion["water-multiplier"] ?? 1.35));
        $this->cannonTntPropulsionMaxMotion = (float) ($propulsion["max-motion"] ?? 2.2);
        $this->cannonTntPropulsionAbsoluteMaxMotion = max(1.0, (float) ($propulsion["absolute-max-motion"] ?? 128.0));
        $this->cannonTntPropulsionOnlyDispenserPrimedTargets = (bool) ($propulsion["only-dispenser-primed-targets"] ?? false);
        $this->sandCobwebHorizontalDamp = max(0.0, min(1.0, (float) ($sandCobweb["horizontal-damp"] ?? 0.25)));
        $this->sandCobwebVerticalDamp = max(0.0, min(1.0, (float) ($sandCobweb["vertical-damp"] ?? 0.05)));
        $this->sandCobwebMaxFallPerTick = max(0.001, (float) ($sandCobweb["max-fall-per-tick"] ?? 0.004));
        $this->sandCobwebNoDragWhileInside = (bool) ($sandCobweb["no-drag-while-inside"] ?? true);

        // Consumed by GlaciaPrimedTNT (dispenser-launched ticks + explosion propulsion). Keys mirror resources/redstone.yml.
        $gravOnePlus = (array) ($dispenser["primed-tnt-gravity-times-one-plus-velocity"] ?? []);
        $this->dispenserPrimedGravityOnePlusVelocityEnabled = (bool) ($gravOnePlus["enabled"] ?? false);
        $this->dispenserPrimedGravityVelocityCoeff = max(0.0, (float) ($gravOnePlus["velocity-coefficient"] ?? 1.0));
        $this->dispenserPrimedGravityVelocityMode = strtolower(trim((string) ($gravOnePlus["velocity-mode"] ?? "xz")));
        $curve = strtolower(trim((string) ($gravOnePlus["curve"] ?? "inverse")));
        $this->dispenserPrimedGravityVelocityCurve = in_array($curve, ["legacy", "multiply", "boost", "old"], true)
            ? "legacy"
            : "inverse";

        $hitOnePlus = (array) ($propulsion["hit-strength-times-one-plus-velocity"] ?? []);
        $this->propulsionOnePlusVelocityEnabled = (bool) ($hitOnePlus["enabled"] ?? false);
        $this->propulsionOnePlusVelocityCoeff = (float) ($hitOnePlus["velocity-coefficient"] ?? 0.0);
        $this->propulsionOnePlusVelocityMode = strtolower(trim((string) ($hitOnePlus["velocity-mode"] ?? "xz")));

        $this->primedTntWaterSuppressesExplosionBlockBreak = (bool) ($redstone["primed-tnt-water-suppresses-explosion-block-break"] ?? true);
    }

    public function isPrimedTntWaterSuppressesExplosionBlockBreak(): bool
    {
        return $this->primedTntWaterSuppressesExplosionBlockBreak;
    }

    public function getChunkLoadDispenserWarmupDelayTicks(): int
    {
        return $this->chunkLoadDispenserWarmupDelayTicks;
    }

    /**
     * Enqueue propagation from dispensing blocks that have tiles in this chunk (cheap).
     */
    public static function enqueueDispenserWarmupForChunk(World $world, Chunk $chunk): void
    {
        $manager = self::getInstance();
        foreach ($chunk->getTiles() as $tile) {
            $pos = $tile->getPosition();
            $holder = $world->getBlockAt($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
            if (!RedstoneDispenserManager::isDispensingBlock($holder)) {
                continue;
            }

            $manager->queuePositionUpdate($holder->getPosition(), true);
        }
    }

    /** After worlds + BlockData exist: second pass catches race where ChunkLoad warmup ran too early. */
    public static function schedulePostBootDispenserWarmup(Plugin $plugin): void
    {
        $delay = self::getInstance()->postBootDispenserWarmupDelayTicks;
        if ($delay <= 0) {
            return;
        }

        $plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(static function (): void {
            $wm = Server::getInstance()->getWorldManager();
            foreach ($wm->getWorlds() as $world) {
                if (!$world->isLoaded()) {
                    continue;
                }

                foreach ($world->getLoadedChunks() as $chunk) {
                    self::enqueueDispenserWarmupForChunk($world, $chunk);
                }
            }
        }), $delay);
    }

    public function registerSignalSource(string $blockClass, RedstoneSignalSource $signalSource): void
    {
        $this->registeredSignalSources[$blockClass] = $signalSource;
    }

    public function registerReceiver(string $blockClass, RedstoneReceiver $receiver): void
    {
        $this->registeredReceivers[$blockClass] = $receiver;
    }

    public function isRelevantBlock(Block $block): bool
    {
        return $block instanceof Lever
            || $block instanceof Button
            || $block instanceof PressurePlate
            || $block instanceof Redstone
            || $block instanceof RedstoneRepeater
            || $block instanceof RedstoneWire
            || $block instanceof RedstoneLamp
            || RedstoneDispenserManager::isDispensingBlock($block)
            || $this->resolveSignalSource($block) !== null
            || $this->resolveReceiver($block) !== null;
    }

    public function activateButton(Button $button): void
    {
        $position = $button->getPosition();
        $worldKey = self::worldKey($position->getWorld());
        $hash = self::hashPosition($position);
        $duration = $button instanceof WoodenButton ? $this->woodenButtonPowerTicks : $this->stoneButtonPowerTicks;
        $this->buttonPowerExpiries[$worldKey][$hash] = Loader::getInstance()->getServer()->getTick() + $duration;

        $this->queuePositionUpdate($position, true);
        Loader::getInstance()->getScheduler()->scheduleDelayedTask(
            new \pocketmine\scheduler\ClosureTask(function () use ($worldKey, $position): void {
                $world = Loader::getInstance()->getServer()->getWorldManager()->getWorldByName($worldKey);
                if ($world === null) {
                    return;
                }

                $this->queueCoordinates($world, $position->getFloorX(), $position->getFloorY(), $position->getFloorZ(), true);
            }),
            $duration + 1
        );
    }

    public function schedulePressurePlateRefresh(Position $position): void
    {
        $worldKey = self::worldKey($position->getWorld());
        Loader::getInstance()->getScheduler()->scheduleDelayedTask(
            new \pocketmine\scheduler\ClosureTask(function () use ($worldKey, $position): void {
                $world = Loader::getInstance()->getServer()->getWorldManager()->getWorldByName($worldKey);
                if ($world === null) {
                    return;
                }

                $this->queueCoordinates($world, $position->getFloorX(), $position->getFloorY(), $position->getFloorZ(), true);
            }),
            $this->pressurePlateRefreshTicks
        );
    }

    public function queuePositionUpdate(Position $position, bool $includeNeighbors = true): void
    {
        $this->queueCoordinates($position->getWorld(), $position->getFloorX(), $position->getFloorY(), $position->getFloorZ(), $includeNeighbors);
    }

    public function cleanupBlock(Block|Position $block): void
    {
        $position = $block instanceof Position ? $block : $block->getPosition();
        $worldKey = self::worldKey($position->getWorld());
        $hash = self::hashPosition($position);

        unset($this->powerStates[$worldKey][$hash], $this->buttonPowerExpiries[$worldKey][$hash]);
        if (($this->powerStates[$worldKey] ?? []) === []) {
            unset($this->powerStates[$worldKey]);
        }
        if (($this->buttonPowerExpiries[$worldKey] ?? []) === []) {
            unset($this->buttonPowerExpiries[$worldKey]);
        }
    }

    public function cleanupWorld(World|string $world): void
    {
        $worldKey = $world instanceof World ? self::worldKey($world) : $world;
        unset($this->queuedUpdates[$worldKey], $this->powerStates[$worldKey], $this->buttonPowerExpiries[$worldKey], $this->repeaterUpdates[$worldKey]);
        RedstoneDispenserManager::clearLogicalPoweredWorld($worldKey);
    }

    public function flushQueuedUpdates(): void
    {
        $this->flushScheduled = false;
        $processed = 0;

        foreach (array_keys($this->queuedUpdates) as $worldKey) {
            $world = Loader::getInstance()->getServer()->getWorldManager()->getWorldByName($worldKey);
            if ($world === null) {
                $this->cleanupWorld($worldKey);
                continue;
            }

            while (($entry = array_pop($this->queuedUpdates[$worldKey])) !== null) {
                [$x, $y, $z] = $entry;
                $this->rebuildNetworkAt($world, $x, $y, $z);
                $processed++;

                if ($processed >= $this->maxUpdatesPerFlush) {
                    $this->scheduleFlush();
                    return;
                }
            }

            unset($this->queuedUpdates[$worldKey]);
        }
    }

    public function getDispenserCooldownTicks(): int
    {
        return $this->dispenserCooldownTicks;
    }

    public function getTntFuseTicks(): int
    {
        return $this->tntFuseTicks;
    }

    public function getTntLaunchHorizontal(): float
    {
        return $this->tntLaunchHorizontal;
    }

    public function getTntLaunchVerticalBoost(): float
    {
        return $this->tntLaunchVerticalBoost;
    }

    public function getTntLaunchVertical(): float
    {
        return $this->tntLaunchVertical;
    }

    public function getTntSpawnOffset(): float
    {
        return $this->tntSpawnOffset;
    }

    /**
     * Scales dispenser primed-TNT launch from how many TNT were in the chosen slot (before one is removed).
     * Uncapped: 1 + (pieces - 1) * primed-tnt-stack-velocity-per-extra-piece.
     */
    public function getPrimedDispenserStackVelocityMultiplier(int $stackPieceCount): float
    {
        $n = max(1, $stackPieceCount);

        return 1.0 + ($n - 1) * $this->primedDispenserStackVelocityPerExtraPiece;
    }

    /** @see redstone.dispenser.primed-tnt-launch-base — per-axis impulse before stack multiplier */
    public function getPrimedDispenserLaunchBase(): float
    {
        return $this->primedDispenserLaunchBase;
    }

    /** @see redstone.yml dispenser.primed-tnt-dispenser-gravity */
    public function isPrimedDispenserTntGravityEnabled(): bool
    {
        return $this->primedDispenserTntGravityEnabled;
    }

    public function tntWorksUnderwater(): bool
    {
        return $this->tntWorksUnderwater;
    }

    /** @see redstone.yml dispenser.primed-tnt-liquid-currents */
    public function isPrimedTntLiquidCurrentsEnabled(): bool
    {
        return $this->primedTntLiquidCurrents;
    }

    public function isCannonTntEnabled(): bool
    {
        return $this->cannonTntEnabled;
    }

    public function getCannonTntWaterDragMultiplier(): float
    {
        return $this->cannonTntWaterDragMultiplier;
    }

    public function getCannonTntGravityMultiplier(): float
    {
        return $this->cannonTntGravityMultiplier;
    }

    public function isCannonTntPropulsionEnabled(): bool
    {
        return $this->cannonTntPropulsionEnabled;
    }

    public function getCannonTntPropulsionRadius(): float
    {
        return $this->cannonTntPropulsionRadius;
    }

    /** Minimum linear falloff (0–1); stops “2-block gap + radius=3 ⇒ center dist≈3 ⇒ zero strength” rim dead zone. */
    public function getCannonTntPropulsionMinFalloff(): float
    {
        return $this->cannonTntPropulsionMinFalloff;
    }

    public function getCannonTntPropulsionFalloffRadiusPad(): float
    {
        return $this->cannonTntPropulsionFalloffRadiusPad;
    }

    public function getCannonTntPropulsionPower(): float
    {
        return $this->cannonTntPropulsionPower;
    }

    public function getCannonTntPropulsionVerticalBoost(): float
    {
        return $this->cannonTntPropulsionVerticalBoost;
    }

    /** @see redstone.yml cannon-tnt-propulsion.vertical-boost-full */
    public function isCannonTntPropulsionVerticalBoostFull(): bool
    {
        return $this->cannonTntPropulsionVerticalBoostFull;
    }

    public function isCannonTntPropulsionVerticalBoostScalesWithHitStrength(): bool
    {
        return $this->cannonTntPropulsionVerticalBoostScalesWithHitStrength;
    }

    /** @see redstone.yml cannon-tnt-propulsion.additive-vertical-boost */
    public function isCannonTntPropulsionAdditiveVerticalBoostEnabled(): bool
    {
        return $this->cannonTntPropulsionAdditiveVerticalBoost;
    }

    public function getCannonTntPropulsionWaterMultiplier(): float
    {
        return $this->cannonTntPropulsionWaterMultiplier;
    }

    public function getCannonTntPropulsionMaxMotion(): float
    {
        return $this->cannonTntPropulsionMaxMotion;
    }

    public function getCannonTntPropulsionAbsoluteMaxMotion(): float
    {
        return $this->cannonTntPropulsionAbsoluteMaxMotion;
    }

    public function isCannonTntPropulsionOnlyDispenserPrimedTargets(): bool
    {
        return $this->cannonTntPropulsionOnlyDispenserPrimedTargets;
    }

    /**
     * Velocity magnitude cap (blocks/tick) for primed-TNT motion clamp + chain-merge + explosion propulsion.
     * max-motion &gt; 0: min(max-motion, absolute-max-motion). max-motion &lt;= 0: soft cap off → use absolute-max only (not zero).
     */
    public function getPrimedTntEffectiveMotionCap(): float
    {
        $abs = max(1.0, $this->cannonTntPropulsionAbsoluteMaxMotion);
        $max = $this->cannonTntPropulsionMaxMotion;
        $effective = $max <= 0.0 ? $abs : min($max, $abs);
        if (!is_finite($effective) || $effective <= 0.0) {
            return max(4.5, min(128.0, $abs));
        }

        return $effective;
    }

    public function getSandCobwebHorizontalDamp(): float
    {
        return $this->sandCobwebHorizontalDamp;
    }

    public function getSandCobwebVerticalDamp(): float
    {
        return $this->sandCobwebVerticalDamp;
    }

    public function getSandCobwebMaxFallPerTick(): float
    {
        return $this->sandCobwebMaxFallPerTick;
    }

    public function isSandCobwebNoDragWhileInsideEnabled(): bool
    {
        return $this->sandCobwebNoDragWhileInside;
    }

    public function isDispenserPrimedGravityOnePlusVelocityEnabled(): bool
    {
        return $this->dispenserPrimedGravityOnePlusVelocityEnabled;
    }

    public function getDispenserPrimedGravityVelocityCoeff(): float
    {
        return $this->dispenserPrimedGravityVelocityCoeff;
    }

    public function getDispenserPrimedGravityVelocityMode(): string
    {
        return $this->dispenserPrimedGravityVelocityMode;
    }

    /** inverse | legacy */
    public function getDispenserPrimedGravityVelocityCurve(): string
    {
        return $this->dispenserPrimedGravityVelocityCurve;
    }

    /**
     * Per-tick scale on gravity for dispenser-fired primed TNT when one-plus is enabled.
     * - inverse (default): 1/(1 + k×v) — faster → lower effective gravity (cannon shots "float").
     * - legacy: 1 + k×v — faster → stronger pull (old behavior).
     * Speed uses velocity-mode under primed-tnt-gravity-times-one-plus-velocity.
     */
    public function getDispenserPrimedGravityOnePlusVelocityMultiplier(Vector3 $motion): float
    {
        if (!$this->dispenserPrimedGravityOnePlusVelocityEnabled) {
            return 1.0;
        }
        $v = self::resolveTntFormulaSpeed($motion, $this->dispenserPrimedGravityVelocityMode);
        $k = $this->dispenserPrimedGravityVelocityCoeff;

        if ($this->dispenserPrimedGravityVelocityCurve === "legacy") {
            return 1.0 + $k * $v;
        }

        return 1.0 / max(1e-6, 1.0 + $k * $v);
    }

    public function isPropulsionStrengthOnePlusVelocityEnabled(): bool
    {
        return $this->propulsionOnePlusVelocityEnabled;
    }

    /**
     * Multiplies explosion propulsion base strength toward a target entity: (1 + velocity-coefficient × target speed).
     */
    public function getPropulsionStrengthOnePlusVelocityMultiplier(Vector3 $targetMotion): float
    {
        if (!$this->propulsionOnePlusVelocityEnabled) {
            return 1.0;
        }
        $v = self::resolveTntFormulaSpeed($targetMotion, $this->propulsionOnePlusVelocityMode);

        return 1.0 + $this->propulsionOnePlusVelocityCoeff * $v;
    }

    /**
     * @param non-empty-string $mode xz | horizontal — speed on XZ; xyz | total | 3d — |v|
     */
    public static function resolveTntFormulaSpeed(Vector3 $motion, string $mode): float
    {
        $mode = strtolower(trim($mode));

        return match ($mode) {
            "xz", "horizontal", "2d", "flat" => sqrt(($motion->x ** 2) + ($motion->z ** 2)),
            "xyz", "total", "3d", "length" => $motion->length(),
            default => sqrt(($motion->x ** 2) + ($motion->z ** 2)),
        };
    }

    private function queueCoordinates(World $world, int $x, int $y, int $z, bool $includeNeighbors): void
    {
        $worldKey = self::worldKey($world);
        $coordinates = [[$x, $y, $z]];
        if ($includeNeighbors) {
            foreach ($this->getRelevantNeighborCoordinates($x, $y, $z) as $neighbor) {
                $coordinates[] = $neighbor;
            }
        }

        foreach ($coordinates as [$nx, $ny, $nz]) {
            $hash = World::blockHash($nx, $ny, $nz);
            $this->queuedUpdates[$worldKey][$hash] = [$nx, $ny, $nz];
        }

        $this->scheduleFlush();
    }

    private function scheduleFlush(): void
    {
        if ($this->flushScheduled) {
            return;
        }

        $this->flushScheduled = true;
        Loader::getInstance()->getScheduler()->scheduleDelayedTask(new RedstoneUpdateTask(), 1);
    }

    private function rebuildNetworkAt(World $world, int $originX, int $originY, int $originZ): void
    {
        $cluster = $this->collectNetworkCluster($world, $originX, $originY, $originZ);
        if ($cluster === []) {
            return;
        }

        $wirePowers = [];
        $wireQueue = [];
        $poweredBlocks = [];
        $newStates = [];

        foreach ($cluster as $hash => $block) {
            if (!$this->isSourceBlock($block)) {
                continue;
            }

            $power = $this->getSourcePower($block);
            $newStates[$hash] = $power;
            if ($power <= 0) {
                continue;
            }

            if ($block instanceof RedstoneRepeater) {
                $front = $block->getPosition()->getSide(Facing::opposite($block->getFacing()));
                $this->seedWirePower($cluster, $wirePowers, $wireQueue, $front->getFloorX(), $front->getFloorY(), $front->getFloorZ(), $power);
                $this->seedPoweredBlock($world, $poweredBlocks, $front->getFloorX(), $front->getFloorY(), $front->getFloorZ(), $power);
            } else {
                foreach (Facing::ALL as $face) {
                    $side = $block->getPosition()->getSide($face);
                    $this->seedWirePower($cluster, $wirePowers, $wireQueue, $side->getFloorX(), $side->getFloorY(), $side->getFloorZ(), $power);
                }
            }

            foreach ($this->getPoweredBlockCoordinates($block) as [$px, $py, $pz]) {
                $this->seedPoweredBlock($world, $poweredBlocks, $px, $py, $pz, $power);
            }
        }

        foreach ($poweredBlocks as $hash => $power) {
            [$x, $y, $z] = self::decodeBlockHash($hash);
            foreach ($this->getPoweredBlockOutputCoordinates($x, $y, $z) as [$nx, $ny, $nz]) {
                $this->seedWirePower($cluster, $wirePowers, $wireQueue, $nx, $ny, $nz, $power);
            }
        }

        while (($entry = array_pop($wireQueue)) !== null) {
            [$x, $y, $z, $power] = $entry;
            if ($power <= 1) {
                continue;
            }

            foreach ($this->getWireNeighborCoordinates($x, $y, $z) as [$nx, $ny, $nz]) {
                $this->seedWirePower($cluster, $wirePowers, $wireQueue, $nx, $ny, $nz, $power - 1);
            }
        }

        $worldKey = self::worldKey($world);
        foreach ($cluster as $hash => $block) {
            if ($block instanceof RedstoneWire) {
                $power = (int) ($wirePowers[$hash] ?? 0);
                $newStates[$hash] = $power;
                $this->applyWirePower($block, $power);
                continue;
            }

            if ($block instanceof RedstoneRepeater) {
                $power = $this->getRepeaterInputPower($block, $cluster, $wirePowers, $poweredBlocks);
                $newStates[$hash] = $block->isPowered() ? 15 : 0;
                $this->updateRepeater($block, $power > 0);
                continue;
            }

            if ($this->isReceiverBlock($block)) {
                $power = $this->getReceiverPower($block, $cluster, $wirePowers, $poweredBlocks);
                $newStates[$hash] = $power;
                $this->applyReceiverPower($block, $power);
                continue;
            }

            $newStates[$hash] = $newStates[$hash] ?? 0;
        }

        foreach (array_keys($cluster) as $hash) {
            $this->powerStates[$worldKey][$hash] = (int) ($newStates[$hash] ?? 0);
        }
    }

    /**
     * @return array<string, Block>
     */
    private function collectNetworkCluster(World $world, int $originX, int $originY, int $originZ): array
    {
        $stack = [[$originX, $originY, $originZ]];
        $visited = [];
        $cluster = [];

        while (($entry = array_pop($stack)) !== null) {
            [$x, $y, $z] = $entry;
            if (max(abs($x - $originX), abs($y - $originY), abs($z - $originZ)) > $this->maxWireDistance) {
                continue;
            }

            $hash = World::blockHash($x, $y, $z);
            if (isset($visited[$hash])) {
                continue;
            }
            $visited[$hash] = true;

            $block = $world->getBlockAt($x, $y, $z);
            if (!$this->isRelevantBlock($block)) {
                continue;
            }

            $cluster[$hash] = $block;
            foreach ($this->getRelevantNeighborCoordinates($x, $y, $z) as $neighbor) {
                $stack[] = $neighbor;
            }
        }

        return $cluster;
    }

    private function isSourceBlock(Block $block): bool
    {
        return $block instanceof Lever
            || $block instanceof Button
            || $block instanceof PressurePlate
            || $block instanceof Redstone
            || $block instanceof RedstoneRepeater
            || $this->resolveSignalSource($block) !== null;
    }

    private function isReceiverBlock(Block $block): bool
    {
        return $block instanceof RedstoneLamp
            || RedstoneDispenserManager::isDispensingBlock($block)
            || $this->resolveReceiver($block) !== null;
    }

    private function getSourcePower(Block $block): int
    {
        if ($block instanceof Lever) {
            return $block->isActivated() ? 15 : 0;
        }

        if ($block instanceof Button) {
            $position = $block->getPosition();
            $worldKey = self::worldKey($position->getWorld());
            $hash = self::hashPosition($position);
            $expiresAt = $this->buttonPowerExpiries[$worldKey][$hash] ?? null;
            if ($expiresAt !== null) {
                if ($expiresAt > Loader::getInstance()->getServer()->getTick()) {
                    return 15;
                }

                unset($this->buttonPowerExpiries[$worldKey][$hash]);
            }

            return $block->isPressed() ? 15 : 0;
        }

        if ($block instanceof PressurePlate) {
            return method_exists($block, "getOutputSignalStrength") ? (int) $block->getOutputSignalStrength() : 0;
        }

        if ($block instanceof Redstone) {
            return 15;
        }

        if ($block instanceof RedstoneRepeater) {
            return $block->isPowered() ? 15 : 0;
        }

        $registered = $this->resolveSignalSource($block);
        return $registered?->getSignalPower($block) ?? 0;
    }

    /**
     * @param array<int, int> $poweredBlocks
     */
    private function seedPoweredBlock(World $world, array &$poweredBlocks, int $x, int $y, int $z, int $power): void
    {
        if ($power <= 0) {
            return;
        }

        $block = $world->getBlockAt($x, $y, $z);
        if (!$this->isConductiveBlock($block)) {
            return;
        }

        $hash = World::blockHash($x, $y, $z);
        if (($poweredBlocks[$hash] ?? -1) >= $power) {
            return;
        }

        $poweredBlocks[$hash] = $power;
    }

    /**
     * @param array<string, Block> $cluster
     * @param array<int, int> $wirePowers
     * @param list<array{int, int, int, int}> $wireQueue
     */
    private function seedWirePower(array $cluster, array &$wirePowers, array &$wireQueue, int $x, int $y, int $z, int $power): void
    {
        if ($power <= 0) {
            return;
        }

        $hash = World::blockHash($x, $y, $z);
        $block = $cluster[$hash] ?? null;
        if (!$block instanceof RedstoneWire) {
            return;
        }

        if (($wirePowers[$hash] ?? -1) >= $power) {
            return;
        }

        $wirePowers[$hash] = $power;
        $wireQueue[] = [$x, $y, $z, $power];
    }

    /**
     * @param array<string, Block> $cluster
     * @param array<int, int> $wirePowers
     * @param array<int, int> $poweredBlocks
     */
    private function getReceiverPower(Block $block, array $cluster, array $wirePowers, array $poweredBlocks): int
    {
        $maxPower = 0;
        $world = $block->getPosition()->getWorld();
        $pistonFrontFace = null;
        if (
            class_exists(\diamondgold\DummyItemsBlocks\block\Piston::class)
            && $block instanceof \diamondgold\DummyItemsBlocks\block\Piston
        ) {
            // Vanilla pistons do not accept direct power from their front (head/output) side — use extension axis.
            $pistonFrontFace = PistonExtensionHelper::extensionFacing($block);
        }

        foreach (Facing::ALL as $face) {
            if ($pistonFrontFace !== null && $face === $pistonFrontFace) {
                continue;
            }
            $side = $block->getPosition()->getSide($face);
            $hash = World::blockHash($side->getFloorX(), $side->getFloorY(), $side->getFloorZ());
            $neighbor = $cluster[$hash] ?? $world->getBlock($side);

            if ($neighbor instanceof RedstoneWire) {
                $maxPower = max($maxPower, (int) ($wirePowers[$hash] ?? 0));
                continue;
            }

            if (isset($poweredBlocks[$hash])) {
                $maxPower = max($maxPower, (int) $poweredBlocks[$hash]);
                continue;
            }

            if ($this->isSourceBlock($neighbor)) {
                if ($neighbor instanceof RedstoneRepeater) {
                    if ($this->doesRepeaterOutputTo($neighbor, $block)) {
                        $maxPower = max($maxPower, $this->getSourcePower($neighbor));
                    }
                    continue;
                }

                $maxPower = max($maxPower, $this->getSourcePower($neighbor));
            }
        }

        return $maxPower;
    }

    /**
     * @param array<string, Block> $cluster
     * @param array<int, int> $wirePowers
     * @param array<int, int> $poweredBlocks
     */
    private function getRepeaterInputPower(RedstoneRepeater $repeater, array $cluster, array $wirePowers, array $poweredBlocks): int
    {
        $back = $repeater->getPosition()->getSide($repeater->getFacing());
        $hash = World::blockHash($back->getFloorX(), $back->getFloorY(), $back->getFloorZ());
        $neighbor = $cluster[$hash] ?? $repeater->getPosition()->getWorld()->getBlock($back);

        if ($neighbor instanceof RedstoneWire) {
            return (int) ($wirePowers[$hash] ?? $neighbor->getOutputSignalStrength());
        }

        if (isset($poweredBlocks[$hash])) {
            return (int) $poweredBlocks[$hash];
        }

        if ($neighbor instanceof RedstoneRepeater) {
            return $this->doesRepeaterOutputTo($neighbor, $repeater) ? $this->getSourcePower($neighbor) : 0;
        }

        if ($this->isSourceBlock($neighbor)) {
            return $this->getSourcePower($neighbor);
        }

        return 0;
    }

    private function updateRepeater(RedstoneRepeater $repeater, bool $desiredPowered): void
    {
        $position = $repeater->getPosition();
        $worldKey = self::worldKey($position->getWorld());
        $hash = self::hashPosition($position);

        if ($repeater->isPowered() === $desiredPowered) {
            unset($this->repeaterUpdates[$worldKey][$hash]);
            if (($this->repeaterUpdates[$worldKey] ?? []) === []) {
                unset($this->repeaterUpdates[$worldKey]);
            }
            return;
        }

        $existing = $this->repeaterUpdates[$worldKey][$hash] ?? null;
        if ($existing !== null && $existing["desired"] === $desiredPowered) {
            return;
        }

        $token = ++$this->nextRepeaterToken;
        $this->repeaterUpdates[$worldKey][$hash] = [
            "token" => $token,
            "desired" => $desiredPowered
        ];

        Loader::getInstance()->getScheduler()->scheduleDelayedTask(
            new \pocketmine\scheduler\ClosureTask(function () use ($worldKey, $hash, $token, $desiredPowered, $position): void {
                $update = $this->repeaterUpdates[$worldKey][$hash] ?? null;
                if ($update === null || $update["token"] !== $token || $update["desired"] !== $desiredPowered) {
                    return;
                }

                $world = Loader::getInstance()->getServer()->getWorldManager()->getWorldByName($worldKey);
                if ($world === null) {
                    unset($this->repeaterUpdates[$worldKey][$hash]);
                    return;
                }

                $current = $world->getBlockAt($position->getFloorX(), $position->getFloorY(), $position->getFloorZ());
                if (!$current instanceof RedstoneRepeater) {
                    unset($this->repeaterUpdates[$worldKey][$hash]);
                    return;
                }

                if ($current->isPowered() !== $desiredPowered) {
                    $updated = clone $current;
                    $updated->setPowered($desiredPowered);
                    $world->setBlock($position, $updated);
                }

                unset($this->repeaterUpdates[$worldKey][$hash]);
                if (($this->repeaterUpdates[$worldKey] ?? []) === []) {
                    unset($this->repeaterUpdates[$worldKey]);
                }

                $this->queuePositionUpdate($position, true);
            }),
            $repeater->getDelay() * 2
        );
    }

    private function applyWirePower(RedstoneWire $wire, int $power): void
    {
        $power = max(0, min(15, $power));
        if ($wire->getOutputSignalStrength() === $power) {
            return;
        }

        $updated = clone $wire;
        $updated->setOutputSignalStrength($power);
        $wire->getPosition()->getWorld()->setBlock($wire->getPosition(), $updated);
    }

    private function applyReceiverPower(Block $block, int $power): void
    {
        $receiver = $this->resolveReceiver($block);
        if ($receiver !== null) {
            $receiver->onRedstonePowerChange($block, $power);
            return;
        }

        if ($block instanceof RedstoneLamp) {
            $powered = $power > 0;
            if ($block->isPowered() === $powered) {
                return;
            }

            $updated = clone $block;
            $updated->setPowered($powered);
            $block->getPosition()->getWorld()->setBlock($block->getPosition(), $updated);
            return;
        }

        if (RedstoneDispenserManager::isDispensingBlock($block)) {
            RedstoneDispenserManager::setPowered($block, $power > 0);
        }
    }

    /**
     * @return list<array{int, int, int}>
     */
    private function getRelevantNeighborCoordinates(int $x, int $y, int $z): array
    {
        $neighbors = [];
        foreach (Facing::ALL as $face) {
            $offset = $this->offsetForFace($face);
            $neighbors[] = [$x + $offset[0], $y + $offset[1], $z + $offset[2]];
        }

        foreach (Facing::HORIZONTAL as $face) {
            $offset = $this->offsetForFace($face);
            $neighbors[] = [$x + $offset[0], $y + 1, $z + $offset[2]];
            $neighbors[] = [$x + $offset[0], $y - 1, $z + $offset[2]];
        }

        return $neighbors;
    }

    /**
     * @return list<array{int, int, int}>
     */
    private function getWireNeighborCoordinates(int $x, int $y, int $z): array
    {
        $neighbors = [];
        foreach (Facing::HORIZONTAL as $face) {
            $offset = $this->offsetForFace($face);
            $neighbors[] = [$x + $offset[0], $y, $z + $offset[2]];
            $neighbors[] = [$x + $offset[0], $y + 1, $z + $offset[2]];
            $neighbors[] = [$x + $offset[0], $y - 1, $z + $offset[2]];
        }

        return $neighbors;
    }

    /**
     * @return list<array{int, int, int}>
     */
    private function getPoweredBlockCoordinates(Block $block): array
    {
        if ($block instanceof Button) {
            $support = $block->getPosition()->getSide(Facing::opposite($block->getFacing()));
            return [[$support->getFloorX(), $support->getFloorY(), $support->getFloorZ()]];
        }

        if ($block instanceof Lever) {
            $supportFace = Facing::opposite($block->getFacing()->getFacing());
            $support = $block->getPosition()->getSide($supportFace);
            return [[$support->getFloorX(), $support->getFloorY(), $support->getFloorZ()]];
        }

        if ($block instanceof PressurePlate) {
            $support = $block->getPosition()->getSide(Facing::DOWN);
            return [[$support->getFloorX(), $support->getFloorY(), $support->getFloorZ()]];
        }

        return [];
    }

    /**
     * @return list<array{int, int, int}>
     */
    private function getPoweredBlockOutputCoordinates(int $x, int $y, int $z): array
    {
        $neighbors = [];
        foreach (Facing::ALL as $face) {
            $offset = $this->offsetForFace($face);
            $neighbors[] = [$x + $offset[0], $y + $offset[1], $z + $offset[2]];
        }

        foreach (Facing::HORIZONTAL as $face) {
            $offset = $this->offsetForFace($face);
            $neighbors[] = [$x + $offset[0], $y + 1, $z + $offset[2]];
            $neighbors[] = [$x + $offset[0], $y - 1, $z + $offset[2]];
        }

        return $neighbors;
    }

    private function isConductiveBlock(Block $block): bool
    {
        if (
            class_exists(\diamondgold\DummyItemsBlocks\block\Piston::class)
            && $block instanceof \diamondgold\DummyItemsBlocks\block\Piston
        ) {
            return false;
        }

        return $block->isSolid()
            && !$block instanceof RedstoneWire
            && !$block instanceof Redstone
            && !$block instanceof Button
            && !$block instanceof Lever
            && !$block instanceof PressurePlate
            && !$block instanceof RedstoneRepeater;
    }

    private function doesRepeaterOutputTo(RedstoneRepeater $repeater, Block $target): bool
    {
        $front = $repeater->getPosition()->getSide(Facing::opposite($repeater->getFacing()));
        return $front->getFloorX() === $target->getPosition()->getFloorX()
            && $front->getFloorY() === $target->getPosition()->getFloorY()
            && $front->getFloorZ() === $target->getPosition()->getFloorZ();
    }

    /**
     * @return array{int, int, int}
     */
    private function offsetForFace(int $face): array
    {
        return match ($face) {
            Facing::DOWN => [0, -1, 0],
            Facing::UP => [0, 1, 0],
            Facing::NORTH => [0, 0, -1],
            Facing::SOUTH => [0, 0, 1],
            Facing::WEST => [-1, 0, 0],
            Facing::EAST => [1, 0, 0],
            default => [0, 0, 0],
        };
    }

    private function resolveSignalSource(Block $block): ?RedstoneSignalSource
    {
        foreach ($this->registeredSignalSources as $class => $signalSource) {
            if ($block instanceof $class) {
                return $signalSource;
            }
        }

        return null;
    }

    private function resolveReceiver(Block $block): ?RedstoneReceiver
    {
        foreach ($this->registeredReceivers as $class => $receiver) {
            if ($block instanceof $class) {
                return $receiver;
            }
        }

        return null;
    }

    private static function worldKey(World $world): string
    {
        return $world->getFolderName();
    }

    private static function hashPosition(Position $position): int
    {
        return World::blockHash($position->getFloorX(), $position->getFloorY(), $position->getFloorZ());
    }

    /**
     * @return array{int, int, int}
     */
    private static function decodeBlockHash(int $hash): array
    {
        $x = null;
        $y = null;
        $z = null;
        World::getBlockXYZ($hash, $x, $y, $z);

        return [$x ?? 0, $y ?? 0, $z ?? 0];
    }
}
