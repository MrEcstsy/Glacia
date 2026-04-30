<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\boss;

use ecstsy\Glacia\server\items\pets\PetManager;
use ecstsy\MartianUtilities\entity\BaseHostileEntity;
use ecstsy\MartianUtilities\utils\PlayerUtils;
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

    public function canCastAnyAbility(): bool {
        return $this->bossTick >= $this->globalAbilityCooldownUntil;
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
        return "&r&l" . $this->getBossName();
    }

    public function isOverloaded(): bool {
        return $this->bossTick < $this->overloadUntil;
    }

    public function triggerOverload(int $durationTicks): void {
        $this->overloadUntil = $this->bossTick + $durationTicks;
    }

    protected function updateAI(): void {
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
        return $this->getPosition()->distanceSquared($this->target->getPosition()) <= ($reach ** 2);
    }

    protected function getMeleeReach(): float {
        return 2.2;
    }

    protected function getCloseRangeOrbitBuffer(): float {
        return 0.35;
    }

    protected function getBossTargetRange(): float {
        return 30.0;
    }

    protected function getTargetLockTicks(): int {
        return 20;
    }

    protected function getMeleeDamage(): float {
        return match ($this->phase) {
            1 => 6.0,
            2 => 7.5,
            3 => 9.0,
            default => 6.0
        };
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

        $event = new EntityDamageByEntityEvent(
            $this,
            $this->target,
            EntityDamageByEntityEvent::CAUSE_ENTITY_ATTACK,
            $damage
        );

        $this->target->attack($event);

        if (!$event->isCancelled()) {
            $this->attackCooldown = 14;
        }
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

    public function attack(EntityDamageEvent $source): void {
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

        $this->setNameTag(C::colorize(
            "&r&l{$baseName}\n&r{$color}❤ &r&f{$health}/{$maxHealth}"
        ));
    }

    protected function updateHealthNameTag(): void {
        $this->updateNameTag();
    }

    public function getBossName(): string {
        return $this->getName();
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

    protected function getSeparationStrength(): float {
        return 0.08;
    }

    protected function isValidBossTarget(Player $player): bool {
        return $player->isOnline()
            && $player->isAlive()
            && !$player->isClosed()
            && !$player->isSpectator()
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
                $player->isSpectator()
            ) {
                continue;
            }

            if ($player->getPosition()->distanceSquared($this->getPosition()) <= $radiusSq) {
                $players[] = $player;
            }
        }

        return $players;
    }

    abstract protected function initAbilities(): void;
}
