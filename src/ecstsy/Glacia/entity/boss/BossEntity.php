<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\boss;

use ecstsy\MartianUtilities\entity\BaseHostileEntity;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

abstract class BossEntity extends BaseHostileEntity {

    /** @var BossAbility[] */
    protected array $abilities = [];

    protected array $damageLog = [];
    protected int $phase = 1;
    protected int $bossTick = 0;
    protected array $marks = [];

    protected int $overloadUntil = 0;
    protected int $globalAbilityCooldownUntil = 0;

    public function canCastAnyAbility(): bool {
        return $this->bossTick >= $this->globalAbilityCooldownUntil;
    }

    public function triggerGlobalAbilityCooldown(int $ticks): void {
        $this->globalAbilityCooldownUntil = $this->bossTick + $ticks;
    }

    protected function initEntity(CompoundTag $nbt): void {
        parent::initEntity($nbt);
        $this->initAbilities();
    }

    public function isOverloaded(): bool {
        return $this->bossTick < $this->overloadUntil;
    }

    public function triggerOverload(int $durationTicks): void {
        $this->overloadUntil = $this->bossTick + $durationTicks;
    }

    protected function updateAI(): void {
        $this->updatePhase();
        $this->pickTarget();

        if ($this->target !== null) {
            $distance = $this->getPosition()->distance($this->target->getPosition());

            if ($distance > 30 || !$this->target->isAlive() || !$this->target->isOnline() || $this->target->isSpectator()) {
                $this->clearTarget();
                return;
            }

            $this->lookAt($this->target->getPosition()->add(0, $this->target->getEyeHeight(), 0));
        }

        $this->tickAbilities();

        if ($this->canMeleeAttack()) {
            $this->doMeleeAttack();
        }

        $this->moveTowardTarget();
    }

    protected function pickTarget(): void {
        $closest = $this->findClosestPlayer();
        if ($closest !== null) {
            $this->target = $closest;
        }
    }

    protected function canMeleeAttack(): bool {
        if (!$this->hasTarget() || $this->attackCooldown > 0) {
            return false;
        }

        return $this->getPosition()->distanceSquared($this->target->getPosition()) <= (2.2 ** 2);
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

        $baseSpeed = $this->isOverloaded() ? 0.42 : 0.30;

        $phaseMultiplier = match ($this->phase) {
            1 => 1.00,
            2 => 1.20,
            3 => 1.42,
            default => 1.00
        };

        $speed = $baseSpeed * $phaseMultiplier;

        $nextNode = $this->getCurrentPathNode();
        $goal = $nextNode ?? $this->target->getPosition();

        $move = $this->buildMovementToward($goal, $speed);
        $move = $move->addVector($this->buildSeparationVector());

        $horizontalLenSq = ($move->x * $move->x) + ($move->z * $move->z);
        if ($horizontalLenSq > 0.0001) {
            $len = sqrt($horizontalLenSq);
            $targetVX = ($move->x / $len) * $speed;
            $targetVZ = ($move->z / $len) * $speed;

            $this->motion->x += ($targetVX - $this->motion->x) * 0.50;
            $this->motion->z += ($targetVZ - $this->motion->z) * 0.50;
        }

        if ($this->shouldJumpAhead()) {
            $this->jump();
        }

        if ($this->shouldRecalculatePath()) {
            $this->recalculatePath();
        }
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

        foreach ($this->abilities as $ability) {
            if ($ability->canCast($this)) {
                $ability->cast($this);
                $this->triggerGlobalAbilityCooldown(40);
                break;
            }
        }
    }

    public function logDamage(Player $player, float $amount): void {
        $id = $player->getName();
        $this->damageLog[$id] = ($this->damageLog[$id] ?? 0) + $amount;
    }

    protected function onDeath(): void {
        $this->rewardPlayers();
    }

    protected function rewardPlayers(): void {}

    protected function onPhaseChange(int $phase): void {}

    protected function entityBaseTick(int $tickDiff = 1): bool {
        $this->bossTick += $tickDiff;

        if (($this->bossTick % 10) === 0) {
            $this->updateNameTag();
            $this->cleanupMarks();
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

    public function getBossName(): string {
        return $this->getName();
    }

    protected function getTopDamagers(int $limit = 5): array {
        arsort($this->damageLog);
        return array_slice($this->damageLog, 0, $limit, true);
    }

    protected function getSeparationStrength(): float {
        return 0.08;
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