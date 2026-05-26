<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\boss;

use ecstsy\Glacia\entity\boss\minions\BossMinion;
use ecstsy\Glacia\entity\boss\minions\BossMinionEnderman;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\boss\BossConfigManager;
use ecstsy\Glacia\server\items\pets\PetManager;
use ecstsy\Glacia\server\staff\HiddenStaffTargeting;
use ecstsy\Glacia\utils\RewardResolver;
use ecstsy\MartianUtilities\entity\BaseHostileEntity;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\block\Block;
use pocketmine\entity\Location;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\network\mcpe\protocol\AnimateEntityPacket;
use pocketmine\network\mcpe\protocol\types\BossBarColor;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use ReflectionMethod;
use Throwable;
use xenialdan\apibossbar\BossBar;
use function array_filter;
use function implode;
use function is_array;
use function is_numeric;
use function number_format;
use function preg_replace;
use function strtolower;
use function strrpos;
use function substr;

abstract class BossEntity extends BaseHostileEntity {

    /** @var BossAbility[] */
    protected array $abilities = [];

    protected array $damageLog = [];
    protected int $phase = 1;
    protected int $bossTick = 0;
    protected array $marks = [];
    /** @var array<int, array{value: float, tick: int}> */
    protected array $recentThreat = [];

    protected int $overloadUntil = 0;
    protected int $globalAbilityCooldownUntil = 0;
    protected int $targetLockUntil = 0;
    protected int $lastAbilityIndex = -1;

    protected ?BossBar $bossBar = null;
    protected float $bossBarRange = 64.0;

    protected float $maxYawTurnPerTick = 12.0;
    protected float $maxPitchTurnPerTick = 8.0;
    protected float $velocityLerp = 0.22;
    protected float $meleeDeadzone = 1.9;
    protected int $closeStrafeDirection = 1;
    protected int $nextCloseStrafeSwapTick = 0;
    /** @var array<int, true> */
    protected array $activeMinionIds = [];
    /** @var array<int, true> */
    protected array $triggeredIntermissionPhases = [];
    protected bool $minionIntermissionActive = false;
    protected int $minionIntermissionStartedAt = 0;
    protected int $minionIntermissionTimeoutAt = 0;
    protected int $spawnedIntermissionMinionCount = 0;
    protected bool $clearingIntermissionMinions = false;

    public function canCastAnyAbility(): bool {
        return !$this->minionIntermissionActive && $this->bossTick >= $this->globalAbilityCooldownUntil;
    }

    public function triggerGlobalAbilityCooldown(int $ticks): void {
        $this->globalAbilityCooldownUntil = $this->bossTick + $ticks;
    }

    protected function initEntity(CompoundTag $nbt): void {
        parent::initEntity($nbt);
        $this->initAbilities();
        $this->initBossBar();
    }

    protected function initBossBar(): void {
        $this->bossBar = new BossBar();
        $this->bossBar->setColor($this->getBossBarColor());
        $this->bossBar->setTitle(C::colorize($this->getBossBarTitle()));
        $this->bossBar->setPercentage(1.0);
    }

    protected function getBossBarColor(): int {
        return BossBarColor::PURPLE;
    }

    protected function getBossBarTitle(): string {
        return $this->decorateBossBarTitle("&r&l" . $this->getBossName());
    }

    public function isOverloaded(): bool {
        return $this->bossTick < $this->overloadUntil;
    }

    public function triggerOverload(int $durationTicks): void {
        $this->overloadUntil = $this->bossTick + $durationTicks;
    }

    protected function updateAI(): void {
        if ($this->minionIntermissionActive) {
            $this->clearTarget();
            $this->motion->x *= 0.35;
            $this->motion->z *= 0.35;
            return;
        }

        $this->updatePhase();

        if ($this->target instanceof Player && !$this->isValidBossTarget($this->target)) {
            $this->clearTarget();
        }

        $this->pickTarget();

        if ($this->target !== null) {
            $distance = $this->getPosition()->distance($this->target->getPosition());

            if ($distance > $this->getBossTargetRange() || !$this->target->isAlive() || !$this->target->isOnline() || $this->target->isSpectator()) {
                $this->clearTarget();
                $this->pickTarget();
                if ($this->target === null) {
                    return;
                }
            }

            $this->smoothFaceTarget($this->target->getPosition()->add(0, $this->target->getEyeHeight(), 0));
        }

        $this->tickAbilities();

        if ($this->canMeleeAttack()) {
            $this->doMeleeAttack();
        }

        $this->moveTowardTarget();
    }

    protected function pickTarget(): void {
        $current = $this->target instanceof Player && $this->isValidBossTarget($this->target) ? $this->target : null;
        $candidates = $this->getPlayersInRange($this->getBossTargetRange());
        if ($candidates === []) {
            $this->clearTarget();
            return;
        }

        $best = null;
        $bestScore = -INF;
        foreach ($candidates as $candidate) {
            $score = $this->getTargetPriorityScore($candidate);
            if ($current !== null && $candidate->getId() === $current->getId()) {
                $score += 2.5;
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $candidate;
            }
        }

        if (!$best instanceof Player) {
            $this->clearTarget();
            return;
        }

        if ($current instanceof Player) {
            $currentScore = $this->getTargetPriorityScore($current) + 2.5;
            if ($this->bossTick < $this->targetLockUntil && ($currentScore + 3.75) >= $bestScore) {
                $this->target = $current;
                return;
            }
        }

        if (!$current instanceof Player || $current->getId() !== $best->getId()) {
            $this->target = $best;
            $this->targetLockUntil = $this->bossTick + $this->getTargetLockTicks();
            $this->pathCooldown = 0;
            $this->recalculatePath();
            return;
        }

        $this->target = $best;
    }

    protected function canMeleeAttack(): bool {
        if (!$this->hasTarget() || $this->attackCooldown > 0) {
            return false;
        }

        $reach = $this->getMeleeReach();
        if ($this->getPosition()->distanceSquared($this->target->getPosition()) > ($reach ** 2)) {
            return false;
        }

        return $this->isTargetInsideMeleeArc($this->target);
    }

    protected function getMeleeReach(): float {
        return $this->getConfiguredMeleeReach(2.2);
    }

    protected function getCloseRangeOrbitBuffer(): float {
        return 0.35;
    }

    protected function getBossTargetRange(): float {
        return $this->getConfiguredTargetRange(30.0);
    }

    protected function getTargetLockTicks(): int {
        return 20;
    }

    protected function getMeleeDamage(): float {
        return $this->getConfiguredMeleeDamage([
            1 => 12.0,
            2 => 17.0,
            3 => 23.0,
        ]);
    }

    protected function getMeleeFacingDotThreshold(): float
    {
        return 0.60;
    }

    protected function doMeleeAttack(): void {
        if (!$this->target instanceof Player) {
            return;
        }

        $damage = $this->getMeleeDamage();

        if ($this->isPlayerMarked($this->target)) {
            $damage *= 1.35;
        }

        if ($this->isOverloaded()) {
            $damage *= 1.25;
        }

        $event = $this->dealBossTrueDamage($this->target, $damage, EntityDamageEvent::CAUSE_ENTITY_ATTACK);

        if (!$event->isCancelled()) {
            $this->attackCooldown = 14;
        }
    }

    public function dealBossTrueDamage(Player $player, float $damage, int $cause = EntityDamageEvent::CAUSE_MAGIC): EntityDamageByEntityEvent
    {
        $damage = max(0.0, $damage);
        $event = new EntityDamageByEntityEvent(
            $this,
            $player,
            $cause,
            $damage,
            [],
            0.0,
            0.0
        );
        if ($this->minionIntermissionActive) {
            $event->cancel();
            return $event;
        }
        $player->attack($event);

        if (!$event->isCancelled()) {
            $minimumDamage = $this->getMinimumUnmitigatedDamage($damage, $cause);
            if ($minimumDamage > 0.0) {
                $extraDamage = max(0.0, $minimumDamage - $event->getFinalDamage());
                if ($extraDamage > 0.0) {
                    $this->applyGuaranteedBossDamage($player, $extraDamage);
                }
            }
        }

        return $event;
    }

    protected function getMinimumUnmitigatedDamage(float $baseDamage, int $cause): float
    {
        return 0.0;
    }

    private function applyGuaranteedBossDamage(Player $player, float $extraDamage): void
    {
        if ($extraDamage <= 0.0 || !$player->isAlive() || $player->isClosed()) {
            return;
        }

        $newHealth = max(0.0, $player->getHealth() - $extraDamage);
        $player->setHealth($newHealth);
    }

    protected function isTargetInsideMeleeArc(Player $player): bool
    {
        $direction = $this->getDirectionVector();
        $forwardLenSq = ($direction->x * $direction->x) + ($direction->z * $direction->z);
        if ($forwardLenSq <= 0.0001) {
            return true;
        }

        $deltaX = $player->getPosition()->x - $this->location->x;
        $deltaZ = $player->getPosition()->z - $this->location->z;
        $targetLenSq = ($deltaX * $deltaX) + ($deltaZ * $deltaZ);
        if ($targetLenSq <= 0.0001) {
            return true;
        }

        $forwardLen = sqrt($forwardLenSq);
        $targetLen = sqrt($targetLenSq);
        $dot = (($direction->x / $forwardLen) * ($deltaX / $targetLen)) + (($direction->z / $forwardLen) * ($deltaZ / $targetLen));

        return $dot >= $this->getMeleeFacingDotThreshold();
    }

    protected function moveTowardTarget(): void {
        if (!$this->hasTarget()) {
            $this->motion->x *= 0.8;
            $this->motion->z *= 0.8;
            return;
        }

        $distSq = $this->getPosition()->distanceSquared($this->target->getPosition());
        if ($this->handleCloseRangeMovement($distSq)) {
            return;
        }

        $baseSpeed = $this->isOverloaded() ? 0.42 : 0.30;

        $phaseMultiplier = match ($this->phase) {
            1 => 1.00,
            2 => 1.20,
            3 => 1.42,
            default => 1.00
        };

        $speed = $baseSpeed * $phaseMultiplier * $this->getMovementAggressionMultiplier();

        $nextNode = $this->getCurrentPathNode();
        $goal = $nextNode ?? $this->target->getPosition();

        $move = $this->buildMovementToward($goal, $speed);
        $move = $move->addVector($this->buildSeparationVector());

        $horizontalLenSq = ($move->x * $move->x) + ($move->z * $move->z);
        if ($horizontalLenSq > 0.0001) {
            $len = sqrt($horizontalLenSq);
            $targetVX = ($move->x / $len) * $speed;
            $targetVZ = ($move->z / $len) * $speed;

            $this->motion->x += ($targetVX - $this->motion->x) * $this->velocityLerp;
            $this->motion->z += ($targetVZ - $this->motion->z) * $this->velocityLerp;
        }

        if ($this->shouldJumpAhead()) {
            $this->jump();
        }

        if ($this->shouldRecalculatePath()) {
            $this->recalculatePath();
        }
    }

    protected function handleCloseRangeMovement(float $distSq): bool
    {
        if (!$this->hasTarget()) {
            return false;
        }

        $combatRadius = max($this->meleeDeadzone, $this->getMeleeReach() + $this->getCloseRangeOrbitBuffer());
        $deadzoneSq = $combatRadius * $combatRadius;
        if ($distSq > $deadzoneSq) {
            return false;
        }

        $targetPos = $this->target->getPosition();
        $dx = $targetPos->x - $this->location->x;
        $dz = $targetPos->z - $this->location->z;
        $lenSq = ($dx * $dx) + ($dz * $dz);

        if ($lenSq <= 0.0001) {
            $this->motion->x *= 0.75;
            $this->motion->z *= 0.75;
            return true;
        }

        if ($this->bossTick >= $this->nextCloseStrafeSwapTick || $this->stuckTicks >= 3) {
            $this->closeStrafeDirection = mt_rand(0, 1) === 0 ? -1 : 1;
            $this->nextCloseStrafeSwapTick = $this->bossTick + mt_rand(18, 34);
        }

        $distance = sqrt($lenSq);
        $forwardX = $dx / $distance;
        $forwardZ = $dz / $distance;
        $orbitX = -$forwardZ * $this->closeStrafeDirection;
        $orbitZ = $forwardX * $this->closeStrafeDirection;

        $innerRadius = max(0.9, $combatRadius - 0.7);
        $pressure = 0.08;
        if ($distance < $innerRadius) {
            $pressure = -0.38;
        } elseif ($distance > ($combatRadius * 0.96)) {
            $pressure = 0.24;
        }

        $phaseMultiplier = match ($this->phase) {
            1 => 1.00,
            2 => 1.10,
            3 => 1.22,
            default => 1.00,
        };
        $speed = ($this->isOverloaded() ? 0.30 : 0.24) * $phaseMultiplier * $this->getMovementAggressionMultiplier();
        $targetVX = (($orbitX * 0.84) + ($forwardX * $pressure)) * $speed;
        $targetVZ = (($orbitZ * 0.84) + ($forwardZ * $pressure)) * $speed;

        $this->motion->x += ($targetVX - $this->motion->x) * 0.32;
        $this->motion->z += ($targetVZ - $this->motion->z) * 0.32;

        if ($this->shouldJumpAhead()) {
            $this->jump();
        }

        if ($this->stuckTicks >= 5 || $this->shouldRecalculatePath()) {
            $this->recalculatePath();
        }

        return true;
    }

    /**
     * Rotates yaw/pitch toward the target by at most maxYawTurnPerTick / maxPitchTurnPerTick
     * degrees per tick. Avoids the wild snapping that pocketmine's instant lookAt() causes
     * when targets jitter or strafe close.
     */
    protected function smoothFaceTarget(\pocketmine\math\Vector3 $target): void {
        $bossPos = $this->location;

        $dx = $target->x - $bossPos->x;
        $dz = $target->z - $bossPos->z;
        $dy = $target->y - ($bossPos->y + $this->getEyeHeight());

        $horizontalSq = ($dx * $dx) + ($dz * $dz);
        if ($horizontalSq <= 0.0001) {
            return;
        }
        $horizontal = sqrt($horizontalSq);

        $desiredYaw = -atan2($dx, $dz) / M_PI * 180.0;
        $desiredPitch = -atan2($dy, $horizontal) / M_PI * 180.0;

        $yawDelta = $desiredYaw - $bossPos->yaw;
        while ($yawDelta > 180.0) { $yawDelta -= 360.0; }
        while ($yawDelta < -180.0) { $yawDelta += 360.0; }

        $maxYaw = $this->maxYawTurnPerTick;
        if ($yawDelta > $maxYaw) { $yawDelta = $maxYaw; }
        elseif ($yawDelta < -$maxYaw) { $yawDelta = -$maxYaw; }

        $pitchDelta = $desiredPitch - $bossPos->pitch;
        $maxPitch = $this->maxPitchTurnPerTick;
        if ($pitchDelta > $maxPitch) { $pitchDelta = $maxPitch; }
        elseif ($pitchDelta < -$maxPitch) { $pitchDelta = -$maxPitch; }

        $newYaw = $bossPos->yaw + $yawDelta;
        $newPitch = $bossPos->pitch + $pitchDelta;
        if ($newPitch > 89.9) { $newPitch = 89.9; }
        elseif ($newPitch < -89.9) { $newPitch = -89.9; }

        $this->setRotation($newYaw, $newPitch);
    }

    protected function updatePhase(): void {
        $hp = $this->getHealth();
        $max = $this->getMaxHealth();

        $newPhase = match (true) {
            $hp <= $max * 0.25 => 3,
            $hp <= $max * 0.50 => 2,
            default => 1,
        };

        if ($newPhase !== $this->phase) {
            $this->phase = $newPhase;
            $this->onPhaseChange($newPhase);
            $this->tryStartMinionIntermission($newPhase);
        }
    }

    protected function tickAbilities(): void {
        if (!$this->canCastAnyAbility()) {
            return;
        }

        $count = count($this->abilities);
        if ($count === 0) {
            return;
        }

        $start = $this->lastAbilityIndex >= 0 ? (($this->lastAbilityIndex + 1) % $count) : 0;
        for ($offset = 0; $offset < $count; $offset++) {
            $index = ($start + $offset) % $count;
            $ability = $this->abilities[$index];
            if ($ability->canCast($this)) {
                $ability->cast($this);
                $this->lastAbilityIndex = $index;
                $this->triggerGlobalAbilityCooldown($this->getAbilityCadenceTicks());
                break;
            }
        }
    }

    public function logDamage(Player $player, float $amount): void {
        $id = $player->getName();
        $this->damageLog[$id] = ($this->damageLog[$id] ?? 0) + $amount;
    }

    protected function onDeath(): void {
        $this->disposeBossBar();
        $this->rewardPlayers();
    }

    protected function onDispose(): void {
        $this->disposeBossBar();
        parent::onDispose();
    }

    protected function disposeBossBar(): void {
        if ($this->bossBar !== null) {
            $this->bossBar->removeAllPlayers();
            $this->bossBar = null;
        }
    }

    protected function syncBossBar(): void {
        if ($this->bossBar === null) {
            return;
        }

        $maxHealth = $this->getMaxHealth();
        $percentage = $maxHealth > 0
            ? max(0.0, min(1.0, $this->getHealth() / $maxHealth))
            : 0.0;

        $this->bossBar->setPercentage($percentage);
        $this->bossBar->setTitle(C::colorize($this->getBossBarTitle()));

        $rangeSq = $this->bossBarRange * $this->bossBarRange;
        $bossPos = $this->getPosition();
        $world = $this->getWorld();

        $eligible = [];
        foreach ($world->getPlayers() as $player) {
            if (!$player->isConnected() || $player->isSpectator() || !$player->isAlive()) {
                continue;
            }
            if ($player->getPosition()->distanceSquared($bossPos) > $rangeSq) {
                continue;
            }
            $eligible[$player->getId()] = $player;
        }

        $current = $this->bossBar->getPlayers();

        foreach ($current as $id => $player) {
            if (!isset($eligible[$id]) || !$player->isConnected() || $player->getWorld() !== $world) {
                $this->bossBar->removePlayer($player);
            }
        }

        foreach ($eligible as $id => $player) {
            if (!isset($current[$id])) {
                $this->bossBar->addPlayer($player);
            }
        }
    }

    protected function rewardPlayers(): void {}

    protected function onPhaseChange(int $phase): void {}

    protected function entityBaseTick(int $tickDiff = 1): bool {
        $this->bossTick += $tickDiff;
        $this->tickMinionIntermission();

        if ($this->minionIntermissionActive) {
            $this->motion->x = 0.0;
            $this->motion->z = 0.0;
            if ($this->onGround) {
                $this->motion->y = min($this->motion->y, 0.0);
            }
        }

        if (($this->bossTick % 10) === 0) {
            $this->updateNameTag();
            $this->cleanupMarks();
            $this->cleanupThreat();
            $this->syncBossBar();
        }

        return parent::entityBaseTick($tickDiff);
    }

    public function getBossTick(): int {
        return $this->bossTick;
    }

    protected function getBossConfigId(): string
    {
        $short = substr(static::class, (int) strrpos(static::class, "\\") + 1);
        $normalized = preg_replace('/(?<!^)[A-Z]/', '_$0', $short);
        return strtolower($normalized ?? $short);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getBossDefaultConfig(): array
    {
        return [
            "stats" => [
                "max-health" => 1000.0,
                "melee-reach" => 2.2,
                "target-range" => 30.0,
                "follow-range" => 30.0,
                "knockback-resistance" => 0.8,
                "movement-speed" => [
                    "phase-1" => 0.30,
                    "phase-2" => 0.36,
                    "phase-3" => 0.42,
                ],
                "melee-damage" => [
                    "phase-1" => 12.0,
                    "phase-2" => 17.0,
                    "phase-3" => 23.0,
                ],
            ],
            "minions" => [
                "phase-2" => [
                    "name" => "&r&dAstral Riftspawn",
                    "health" => 88,
                    "damage" => 8.0,
                    "speed" => 0.27,
                    "target-range" => 26.0,
                    "attack-reach" => 2.5,
                    "attack-cooldown" => 11,
                ],
                "phase-3" => [
                    "name" => "&r&5Astral Riftspawn",
                    "health" => 115,
                    "damage" => 10.0,
                    "speed" => 0.30,
                    "target-range" => 26.0,
                    "attack-reach" => 2.5,
                    "attack-cooldown" => 9,
                ],
            ],
            "rewards" => [
                "top-damagers" => 5,
                "ranks" => [
                    1 => ["money" => 0, "items" => []],
                    2 => ["money" => 0, "items" => []],
                    3 => ["money" => 0, "items" => []],
                    4 => ["money" => 0, "items" => []],
                    5 => ["money" => 0, "items" => []],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getBossConfig(): array
    {
        return BossConfigManager::getBossConfig($this->getBossConfigId(), $this->getBossDefaultConfig());
    }

    protected function getConfiguredMaxHealth(float $default): float
    {
        return $this->bossConfigFloat(["stats", "max-health"], $default, 1.0, 500000.0);
    }

    protected function getConfiguredMovementSpeed(int $phase, float $default): float
    {
        return $this->bossConfigFloat(["stats", "movement-speed", "phase-" . $phase], $default, 0.01, 2.0);
    }

    protected function getConfiguredFollowRange(float $default): float
    {
        return $this->bossConfigFloat(["stats", "follow-range"], $default, 4.0, 256.0);
    }

    protected function getConfiguredKnockbackResistance(float $default): float
    {
        return $this->bossConfigFloat(["stats", "knockback-resistance"], $default, 0.0, 1.0);
    }

    protected function getConfiguredMeleeReach(float $default): float
    {
        return $this->bossConfigFloat(["stats", "melee-reach"], $default, 1.0, 12.0);
    }

    protected function getConfiguredTargetRange(float $default): float
    {
        return $this->bossConfigFloat(["stats", "target-range"], $default, 4.0, 128.0);
    }

    /**
     * @param array<int, float> $defaults
     */
    protected function getConfiguredMeleeDamage(array $defaults): float
    {
        $default = $defaults[$this->phase] ?? ($defaults[1] ?? 1.0);
        return $this->bossConfigFloat(["stats", "melee-damage", "phase-" . $this->phase], $default, 0.0, 100000.0);
    }

    /**
     * @param array<string, mixed> $default
     * @return array<string, mixed>
     */
    protected function getConfiguredMinionSpec(int $phase, array $default): array
    {
        $phaseKey = "phase-" . $phase;
        $configured = $this->bossConfigArray(["minions", $phaseKey]);

        return [
            "class" => $default["class"] ?? BossMinionEnderman::class,
            "name" => (string) ($configured["name"] ?? $default["name"] ?? "&r&cBoss Minion"),
            "health" => (int) $this->normalizeFloat($configured["health"] ?? ($default["health"] ?? 40), (float) ($default["health"] ?? 40), 8.0, 50000.0),
            "damage" => $this->normalizeFloat($configured["damage"] ?? ($default["damage"] ?? 6.0), (float) ($default["damage"] ?? 6.0), 0.0, 10000.0),
            "speed" => $this->normalizeFloat($configured["speed"] ?? ($default["speed"] ?? 0.24), (float) ($default["speed"] ?? 0.24), 0.01, 2.0),
            "target_range" => $this->normalizeFloat($configured["target-range"] ?? ($configured["target_range"] ?? ($default["target_range"] ?? 24.0)), (float) ($default["target_range"] ?? 24.0), 4.0, 128.0),
            "attack_reach" => $this->normalizeFloat($configured["attack-reach"] ?? ($configured["attack_reach"] ?? ($default["attack_reach"] ?? 2.2)), (float) ($default["attack_reach"] ?? 2.2), 1.0, 12.0),
            "attack_cooldown" => (int) $this->normalizeFloat($configured["attack-cooldown"] ?? ($configured["attack_cooldown"] ?? ($default["attack_cooldown"] ?? 10)), (float) ($default["attack_cooldown"] ?? 10), 1.0, 1200.0),
        ];
    }

    protected function rewardConfiguredTopDamagers(string $victoryHeader, string $accentColor, ?string $bossLabel = null, array $questContext = []): void
    {
        $top = $this->getTopDamagers($this->getConfiguredTopDamagerLimit(5));
        if ($top === []) {
            return;
        }

        $lines = [];
        $rank = 1;
        $label = $bossLabel ?? C::clean($this->getName());

        foreach ($top as $playerId => $damage) {
            $player = $this->getWorld()->getServer()->getPlayerExact($playerId);
            if (!$player instanceof Player) {
                continue;
            }

            if ($rank === 1) {
                Loader::getQuestBoardManager()->recordProgress($player, "boss_top_damage", 1, $questContext);
            }

            $money = $this->getConfiguredRankMoney($rank);
            if ($money > 0) {
                Loader::getPlayerManager()->getSession($player)?->addBalance($money);
            }

            $rewardNames = $this->grantConfiguredRankItems($player, $rank);
            $message = $money > 0
                ? "&r&l&a[Boss] &r&aYou placed #{$rank} on the &f{$label} &aand earned &f$" . number_format($money)
                : "&r&l&a[Boss] &r&aYou placed #{$rank} on the &f{$label}&a.";
            $player->sendMessage(C::colorize($message));

            if ($rewardNames !== []) {
                $player->sendMessage(C::colorize("&r&7Bonus rewards: &f" . implode("&7, &f", $rewardNames)));
            }

            PlayerUtils::playSound($player, "random.levelup");
            $lines[] = C::colorize("&r&7#{$rank} &f{$player->getName()} &7- {$accentColor}" . number_format((int) $damage) . " dmg");
            $rank++;
        }

        if ($lines === []) {
            return;
        }

        foreach ($this->getWorld()->getPlayers() as $player) {
            $player->sendMessage(C::colorize($victoryHeader));
            foreach ($lines as $line) {
                $player->sendMessage($line);
            }
        }
    }

    public function attack(EntityDamageEvent $source): void {
        if ($this->minionIntermissionActive) {
            $source->cancel();
            return;
        }

        parent::attack($source);

        if (
            $source instanceof EntityDamageByEntityEvent &&
            ($damager = $source->getDamager()) instanceof Player
        ) {
            $this->logDamage($damager, $source->getFinalDamage());
            $this->logThreat($damager, $source->getFinalDamage());
        }
    }

    protected function hasCustomMovement(): bool {
        return true;
    }

    public function getPhase(): int {
        return $this->phase;
    }

    public function markPlayer(Player $player, int $durationTicks): void {
        $this->marks[$player->getId()] = $this->bossTick + $durationTicks;
    }

    public function isPlayerMarked(Player $player): bool {
        return isset($this->marks[$player->getId()]) && $this->marks[$player->getId()] > $this->bossTick;
    }

    protected function cleanupMarks(): void {
        foreach ($this->marks as $id => $expire) {
            if ($expire <= $this->bossTick) {
                unset($this->marks[$id]);
            }
        }
    }

    public function getTarget(): ?Player {
        return $this->target instanceof Player ? $this->target : null;
    }

    public function getEngagedPlayerCount(float $radius = 14.0): int {
        return count($this->getPlayersInRange($radius));
    }

    public function applyBossMotion(Player $player, Vector3 $motion): void {
        if ($this->minionIntermissionActive) {
            return;
        }
        $player->setMotion(PetManager::scaleBossMotion($player, $motion));
    }

    public function applyBossKnockback(Player $player, float $dx, float $dz, float $horizontal, float $vertical = 0.36): void {
        $lengthSq = ($dx * $dx) + ($dz * $dz);
        if ($lengthSq <= 0.0001) {
            return;
        }

        $length = sqrt($lengthSq);
        $this->applyBossMotion($player, new Vector3(
            ($dx / $length) * $horizontal,
            $vertical,
            ($dz / $length) * $horizontal
        ));
    }

    public function broadcastAbility(string $message, int $radius = 30): void {
        foreach ($this->getWorld()->getPlayers() as $player) {
            if ($player->getPosition()->distanceSquared($this->getPosition()) <= $radius ** 2) {
                $player->sendMessage($message);
            }
        }
    }

    public function playAbilitySound(string $sound, float $volume = 1.0, float $pitch = 1.0, int $radius = 30): void {
        foreach ($this->getWorld()->getPlayers() as $player) {
            if ($player->getPosition()->distanceSquared($this->getPosition()) <= $radius ** 2) {
                PlayerUtils::playSound($player, $sound, $volume, $pitch, $radius);
            }
        }
    }

    protected function updateNameTag(): void {
        $health = max(0, (int) ceil($this->getHealth()));
        $maxHealth = (int) ceil($this->getMaxHealth());

        $percent = $health / max($maxHealth, 1);

        if ($percent <= 0.25) {
            $color = "&c";
        } elseif ($percent <= 0.5) {
            $color = "&e";
        } else {
            $color = "&b";
        }

        $baseName = $this->getBossName();
        $statusLine = $this->minionIntermissionActive
            ? "\n&r&6Shielded &7| &f" . $this->getRemainingMinionCount() . "&7 minions left"
            : "";

        $this->setNameTag(C::colorize("&r&l{$baseName}\n&r{$color}❤ &r&f{$health}/{$maxHealth}{$statusLine}"));
    }

    protected function updateHealthNameTag(): void {
        $this->updateNameTag();
    }

    public function getBossName(): string {
        return $this->getName();
    }

    public function isMinionIntermissionActive(): bool
    {
        return $this->minionIntermissionActive;
    }

    public function handleMinionRemoved(BossMinion $minion): void
    {
        unset($this->activeMinionIds[$minion->getId()]);
        if ($this->minionIntermissionActive) {
            $this->syncBossBar();
        }
    }

    protected function getTopDamagers(int $limit = 5): array {
        arsort($this->damageLog);
        return array_slice($this->damageLog, 0, $limit, true);
    }

    protected function getAbilityCadenceTicks(): int {
        $extraPlayers = max(0, $this->getEngagedPlayerCount(14.0) - 1);
        $ticks = 40 - ($extraPlayers * 4) - (($this->phase - 1) * 3);
        if ($this->isOverloaded()) {
            $ticks -= 4;
        }

        return max(16, $ticks);
    }

    protected function getMovementAggressionMultiplier(): float {
        $extraPlayers = max(0, $this->getEngagedPlayerCount(14.0) - 1);
        $multiplier = 1.0 + min(0.24, $extraPlayers * 0.06);
        $multiplier += ($this->phase - 1) * 0.05;
        if ($this->isOverloaded()) {
            $multiplier += 0.08;
        }

        return $multiplier;
    }

    protected function getMinionSpawnRadiusBase(): float
    {
        return 3.5;
    }

    protected function getSeparationStrength(): float {
        return 0.08;
    }

    protected function isValidBossTarget(Player $player): bool {
        return $player->isOnline()
            && $player->isAlive()
            && !$player->isClosed()
            && !$player->isSpectator()
            && !HiddenStaffTargeting::isUntargetable($player)
            && $player->getWorld() === $this->getWorld()
            && $player->getPosition()->distanceSquared($this->getPosition()) <= ($this->getBossTargetRange() ** 2);
    }

    protected function getTargetPriorityScore(Player $player): float {
        $distance = sqrt($player->getPosition()->distanceSquared($this->getPosition()));
        $distanceScore = max(0.0, $this->getBossTargetRange() - $distance) * 0.65;
        $threatScore = $this->getThreatFor($player) * 1.35;
        $score = $distanceScore + $threatScore;

        if ($this->isPlayerMarked($player)) {
            $score += 6.0;
        }

        if ($player->getHealth() <= ($player->getMaxHealth() * 0.5)) {
            $score += 1.5;
        }

        return $score;
    }

    protected function logThreat(Player $player, float $amount): void {
        $key = $player->getId();
        $current = $this->recentThreat[$key]["value"] ?? 0.0;
        $this->recentThreat[$key] = [
            "value" => min(140.0, $current + max(0.0, $amount) * 1.2),
            "tick" => $this->bossTick,
        ];
    }

    protected function getThreatFor(Player $player): float {
        $key = $player->getId();
        if (!isset($this->recentThreat[$key])) {
            return 0.0;
        }

        $entry = $this->recentThreat[$key];
        $decayed = max(0.0, $entry["value"] - (($this->bossTick - $entry["tick"]) * 0.45));
        if ($decayed <= 0.0) {
            unset($this->recentThreat[$key]);
            return 0.0;
        }

        return $decayed;
    }

    protected function cleanupThreat(): void {
        foreach (array_keys($this->recentThreat) as $key) {
            $entry = $this->recentThreat[$key];
            $decayed = max(0.0, $entry["value"] - (($this->bossTick - $entry["tick"]) * 0.45));
            if ($decayed <= 0.0) {
                unset($this->recentThreat[$key]);
                continue;
            }

            $this->recentThreat[$key] = [
                "value" => $decayed,
                "tick" => $this->bossTick,
            ];
        }
    }

    /**
     * Pushes a one-shot animation onto the entity's animation controller via
     * AnimateEntityPacket. The packet's signature changed in newer PMMP-MP
     * 5.x releases (a stopExpressionVersion field was inserted), so we
     * resolve it through reflection once and cache the parameter count.
     *
     * @param string $animation     short name registered in the entity's `animations` map (e.g. "attack")
     * @param string $controller    short name of the controller in the same map (e.g. "ctrl_action"), or "" to play standalone
     * @param string $nextState     state to return to after the animation ends (controller short name resolves it)
     * @param float  $blendOutTime  blend duration when leaving the pushed animation
     */
    public function broadcastEntityAnimation(
        string $animation,
        string $controller = "",
        string $nextState = "default",
        float $blendOutTime = 0.0
    ): void {
        $viewers = $this->getViewers();
        if ($viewers === []) {
            return;
        }

        static $reflection = null;
        static $paramCount = -1;
        if ($reflection === null) {
            try {
                $reflection = new ReflectionMethod(AnimateEntityPacket::class, 'create');
                $paramCount = $reflection->getNumberOfParameters();
            } catch (Throwable) {
                $reflection = false;
            }
        }
        if ($reflection === false) {
            return;
        }

        $runtimeIds = [$this->getId()];

        if ($paramCount === 7) {
            $args = [$animation, $nextState, "", 0, $controller, $blendOutTime, $runtimeIds];
        } elseif ($paramCount === 6) {
            $args = [$animation, $nextState, "", $controller, $blendOutTime, $runtimeIds];
        } else {
            return;
        }

        try {
            /** @var AnimateEntityPacket $pk */
            $pk = $reflection->invoke(null, ...$args);
        } catch (Throwable) {
            return;
        }

        NetworkBroadcastUtils::broadcastPackets($viewers, [$pk]);
    }

    /**
     * @return Player[]
     */
    public function getPlayersInRange(float $radius): array {
        $players = [];
        $radiusSq = $radius * $radius;

        foreach ($this->getWorld()->getPlayers() as $player) {
            if (
                !$player->isOnline() ||
                !$player->isAlive() ||
                $player->isSpectator() ||
                HiddenStaffTargeting::isUntargetable($player)
            ) {
                continue;
            }

            if ($player->getPosition()->distanceSquared($this->getPosition()) <= $radiusSq) {
                $players[] = $player;
            }
        }

        return $players;
    }

    /**
     * @return array{class: class-string<BossMinion>, name: string, health: int, damage: float, speed: float, target_range: float, attack_reach: float, attack_cooldown: int}
     */
    protected function getMinionIntermissionSpec(int $phase): array
    {
        return $this->getConfiguredMinionSpec($phase, [
            "class" => BossMinionEnderman::class,
            "name" => $phase >= 3 ? "&r&5Astral Riftspawn" : "&r&dAstral Riftspawn",
            "health" => $phase >= 3 ? 115 : 88,
            "damage" => $phase >= 3 ? 10.0 : 8.0,
            "speed" => $phase >= 3 ? 0.30 : 0.27,
            "target_range" => 26.0,
            "attack_reach" => 2.5,
            "attack_cooldown" => $phase >= 3 ? 9 : 11,
        ]);
    }

    protected function shouldStartMinionIntermissionForPhase(int $phase): bool
    {
        return $phase >= 2;
    }

    protected function getMinionIntermissionCount(int $phase): int
    {
        $engaged = max(1, $this->getEngagedPlayerCount(18.0));
        return min(8, 4 + max(0, $phase - 2) + intdiv(max(0, $engaged - 1), 2));
    }

    protected function getMinionIntermissionTimeoutTicks(int $count): int
    {
        return 20 * (45 + ($count * 6));
    }

    protected function decorateBossBarTitle(string $baseTitle): string
    {
        if (!$this->minionIntermissionActive) {
            return $baseTitle;
        }

        return $baseTitle . " &r&8| &6Minions Left: &f" . $this->getRemainingMinionCount();
    }

    private function tryStartMinionIntermission(int $phase): void
    {
        if (
            $this->minionIntermissionActive ||
            !$this->shouldStartMinionIntermissionForPhase($phase) ||
            isset($this->triggeredIntermissionPhases[$phase])
        ) {
            return;
        }

        $count = $this->getMinionIntermissionCount($phase);
        if ($count <= 0) {
            return;
        }

        $this->triggeredIntermissionPhases[$phase] = true;
        $this->startMinionIntermission($phase, $count);
    }

    private function startMinionIntermission(int $phase, int $count): void
    {
        $spec = $this->getMinionIntermissionSpec($phase);
        $class = $spec["class"] ?? BossMinionEnderman::class;
        if (!is_a($class, BossMinion::class, true)) {
            return;
        }

        $spawned = 0;
        for ($i = 0; $i < $count; $i++) {
            $location = $this->findMinionSpawnLocation($i, $count);
            if ($location === null) {
                continue;
            }

            $nbt = CompoundTag::create()
                ->setInt("boss_minion_owner_id", $this->getId())
                ->setString("boss_minion_name", (string) ($spec["name"] ?? "&r&cBoss Minion"))
                ->setInt("boss_minion_health", max(8, (int) ($spec["health"] ?? 40)))
                ->setFloat("boss_minion_damage", max(1.0, (float) ($spec["damage"] ?? 6.0)))
                ->setFloat("boss_minion_speed", max(0.08, (float) ($spec["speed"] ?? 0.24)))
                ->setFloat("boss_minion_target_range", max(8.0, (float) ($spec["target_range"] ?? 24.0)))
                ->setFloat("boss_minion_attack_reach", max(1.4, (float) ($spec["attack_reach"] ?? 2.2)))
                ->setInt("boss_minion_attack_cooldown", max(6, (int) ($spec["attack_cooldown"] ?? 10)));

            $minion = new $class($location, $nbt);
            $minion->spawnToAll();
            $this->activeMinionIds[$minion->getId()] = true;
            $spawned++;
        }

        if ($spawned <= 0) {
            return;
        }

        $this->spawnedIntermissionMinionCount = $spawned;
        $this->minionIntermissionActive = true;
        $this->minionIntermissionStartedAt = $this->bossTick;
        $this->minionIntermissionTimeoutAt = $this->bossTick + $this->getMinionIntermissionTimeoutTicks($spawned);
        $this->attackCooldown = max($this->attackCooldown, 30);
        $this->triggerGlobalAbilityCooldown($this->getMinionIntermissionTimeoutTicks($spawned));
        $this->clearTarget();
        $this->broadcastAbility(C::colorize("&r&6" . C::clean($this->getName()) . " &7summons reinforcements and becomes shielded!"), 40);
        $this->syncBossBar();
        $this->updateNameTag();
    }

    private function tickMinionIntermission(): void
    {
        if (!$this->minionIntermissionActive) {
            return;
        }

        foreach (array_keys($this->activeMinionIds) as $entityId) {
            $entity = $this->getWorld()->getEntity($entityId);
            if (!$entity instanceof BossMinion || $entity->isClosed() || !$entity->isAlive()) {
                unset($this->activeMinionIds[$entityId]);
            }
        }

        if ($this->activeMinionIds === []) {
            $this->finishMinionIntermission(false);
            return;
        }

        if ($this->bossTick >= $this->minionIntermissionTimeoutAt) {
            $this->finishMinionIntermission(true);
        }
    }

    private function finishMinionIntermission(bool $timedOut): void
    {
        if (!$this->minionIntermissionActive) {
            return;
        }

        $this->minionIntermissionActive = false;

        if ($timedOut) {
            $this->clearingIntermissionMinions = true;
            foreach (array_keys($this->activeMinionIds) as $entityId) {
                $entity = $this->getWorld()->getEntity($entityId);
                if ($entity instanceof BossMinion && !$entity->isClosed()) {
                    $entity->flagForDespawn();
                }
            }
            $this->clearingIntermissionMinions = false;
            $this->broadcastAbility(C::colorize("&r&6The boss tears through its own stuck minions and the fight resumes!"), 40);
        } else {
            $this->broadcastAbility(C::colorize("&r&aThe minion wave has been cleared. The boss is vulnerable again!"), 40);
        }

        $this->activeMinionIds = [];
        $this->spawnedIntermissionMinionCount = 0;
        $this->minionIntermissionStartedAt = 0;
        $this->minionIntermissionTimeoutAt = 0;
        $this->globalAbilityCooldownUntil = min($this->globalAbilityCooldownUntil, $this->bossTick + 20);
        $this->syncBossBar();
        $this->updateNameTag();
    }

    private function findMinionSpawnLocation(int $index, int $count): ?Location
    {
        $world = $this->getWorld();
        $center = $this->getPosition();
        $baseY = $center->getFloorY();
        $angle = ((2 * M_PI) / max(1, $count)) * $index;

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $radius = $this->getMinionSpawnRadiusBase() + $attempt;
            $x = (int) floor($center->x + cos($angle + ($attempt * 0.35)) * $radius);
            $z = (int) floor($center->z + sin($angle + ($attempt * 0.35)) * $radius);

            for ($y = $baseY - 2; $y <= $baseY + 3; $y++) {
                if (!$this->isValidMinionSpawnColumn($world->getBlockAt($x, $y, $z), $world->getBlockAt($x, $y + 1, $z), $world->getBlockAt($x, $y + 2, $z))) {
                    continue;
                }

                return new Location($x + 0.5, $y + 1, $z + 0.5, $world, $this->location->yaw, $this->location->pitch);
            }
        }

        return new Location($center->x + 1.5, $center->y, $center->z + 1.5, $world, $this->location->yaw, $this->location->pitch);
    }

    private function isValidMinionSpawnColumn(Block $ground, Block $feet, Block $head): bool
    {
        return $ground->isSolid() && !$feet->isSolid() && !$head->isSolid();
    }

    private function getRemainingMinionCount(): int
    {
        return count($this->activeMinionIds);
    }

    /**
     * @param list<string> $path
     */
    private function bossConfigFloat(array $path, float $default, float $minValue, float $maxValue): float
    {
        return $this->normalizeFloat($this->bossConfigValue($path, $default), $default, $minValue, $maxValue);
    }

    /**
     * @param list<string> $path
     * @return array<string, mixed>
     */
    private function bossConfigArray(array $path): array
    {
        $value = $this->bossConfigValue($path, []);
        return is_array($value) ? $value : [];
    }

    /**
     * @param list<string|int> $path
     */
    private function bossConfigValue(array $path, mixed $default): mixed
    {
        $value = $this->getBossConfig();
        foreach ($path as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    private function normalizeFloat(mixed $value, float $default, float $minValue, float $maxValue): float
    {
        if (!is_numeric($value)) {
            return $default;
        }

        return max($minValue, min($maxValue, (float) $value));
    }

    private function getConfiguredTopDamagerLimit(int $default): int
    {
        return (int) $this->normalizeFloat($this->bossConfigValue(["rewards", "top-damagers"], $default), $default, 1.0, 25.0);
    }

    private function getConfiguredRankMoney(int $rank): int
    {
        return (int) $this->normalizeFloat($this->bossConfigValue(["rewards", "ranks", $rank, "money"], 0), 0.0, 0.0, 2000000000.0);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getConfiguredRankItems(int $rank): array
    {
        $items = $this->bossConfigValue(["rewards", "ranks", $rank, "items"], []);
        if (!is_array($items)) {
            return [];
        }

        return array_values(array_filter($items, static fn(mixed $entry): bool => is_array($entry)));
    }

    /**
     * @return list<string>
     */
    private function grantConfiguredRankItems(Player $player, int $rank): array
    {
        $rewardNames = [];

        foreach ($this->getConfiguredRankItems($rank) as $entry) {
            $item = RewardResolver::resolveItem($entry, $player);
            if ($item === null) {
                continue;
            }

            $displayName = C::clean($item->getCustomName() !== "" ? $item->getCustomName() : $item->getName());
            $rewardNames[] = ($item->getCount() > 1 ? $item->getCount() . "x " : "") . $displayName;

            foreach ($player->getInventory()->addItem(clone $item) as $leftover) {
                $player->getWorld()->dropItem($player->getPosition(), $leftover);
            }
        }

        return $rewardNames;
    }

    abstract protected function initAbilities(): void;
}
