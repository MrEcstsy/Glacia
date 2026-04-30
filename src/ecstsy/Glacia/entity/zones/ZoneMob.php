<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\zones;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\zones\ZoneMobManager;
use ecstsy\MartianUtilities\entity\BaseHostileEntity;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\projectile\Arrow;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Item;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\TextFormat as C;
use pocketmine\world\Position;
use pocketmine\world\sound\ExplodeSound;

class ZoneMob extends BaseHostileEntity {

    private string $zoneId = "";
    private string $mobId = "";
    private string $displayName = "Zone Mob";
    private float $configuredDamage = 4.0;
    private float $configuredSpeed = 0.19;
    private float $configuredTargetRange = 15.0;
    private int $configuredXp = 20;
    private bool $elite = false;
    private string $eliteType = "";
    private float $lootMultiplier = 1.0;
    private string $combatStyle = "melee";
    private string $weapon = "none";
    private int $rangedCooldownTicks = 26;
    private int $meleeCooldownTicks = 13;
    private int $attackWindupTicks = 6;
    private float $attackReach = 2.0;
    private float $idealRangeMin = 6.0;
    private float $idealRangeMax = 13.0;
    private int $strafeChance = 35;
    private bool $retreatWhenTooClose = true;
    private bool $allowMeleeWhenRanged = false;
    private float $hordeSeparationStrength = 0.26;
    private ?Player $pendingAttackTarget = null;
    private int $pendingAttackTicks = 0;
    private int $nextChargedBurstTick = 0;
    private int $nextGraviticPulseTick = 0;

    protected int $aiInterval = 1;

    protected function initEntity(CompoundTag $nbt): void
    {
        parent::initEntity($nbt);

        $this->zoneId = strtolower($nbt->getString("zone_id", ""));
        $this->mobId = strtolower($nbt->getString("zone_mob_id", ""));
        $this->displayName = $nbt->getString("zone_mob_name", "Zone Mob");
        $this->configuredDamage = max(1.0, $nbt->getFloat("zone_mob_damage", 4.0));
        $this->configuredSpeed = max(0.08, min(0.34, $nbt->getFloat("zone_mob_speed", 0.19)));
        $this->configuredTargetRange = max(8.0, min(28.0, $nbt->getFloat("zone_mob_target_range", 15.0)));
        $this->configuredXp = max(1, $nbt->getInt("zone_mob_xp", 20));
        $this->elite = $nbt->getByte("zone_mob_elite", 0) === 1;
        $this->eliteType = strtolower($nbt->getString("zone_mob_elite_type", ""));
        $this->lootMultiplier = max(1.0, $nbt->getFloat("zone_mob_loot_multiplier", 1.0));
        $this->combatStyle = strtolower($nbt->getString("zone_mob_combat_style", "melee"));
        $this->weapon = strtolower($nbt->getString("zone_mob_weapon", "none"));
        $this->rangedCooldownTicks = max(8, $nbt->getInt("zone_mob_ranged_cooldown", 26));
        $this->meleeCooldownTicks = max(6, $nbt->getInt("zone_mob_melee_cooldown", 13));
        $this->attackWindupTicks = max(0, min(10, $nbt->getInt("zone_mob_attack_windup", 6)));
        $this->attackReach = max(1.4, min(3.6, $nbt->getFloat("zone_mob_attack_reach", 2.0)));
        $this->idealRangeMin = max(4.0, min(18.0, $nbt->getFloat("zone_mob_ideal_range_min", 6.0)));
        $this->idealRangeMax = max($this->idealRangeMin + 1.0, min(28.0, $nbt->getFloat("zone_mob_ideal_range_max", 13.0)));
        $this->strafeChance = max(0, min(100, $nbt->getInt("zone_mob_strafe_chance", 35)));
        $this->retreatWhenTooClose = $nbt->getByte("zone_mob_retreat_when_too_close", 1) === 1;
        $this->allowMeleeWhenRanged = $nbt->getByte("zone_mob_allow_melee_when_ranged", 0) === 1;
        $this->hordeSeparationStrength = max(0.05, min(0.75, $nbt->getFloat("zone_mob_horde_separation", 0.26)));

        $health = max(10, $nbt->getInt("zone_mob_health", 30));
        $this->setMaxHealth($health);
        $this->setHealth($health);
        $this->setNameTagAlwaysVisible();
        $this->setNameTagVisible();
        $this->nextChargedBurstTick = $this->ticksLived + mt_rand(22, 42);
        $this->nextGraviticPulseTick = $this->ticksLived + mt_rand(10, 18);
        $this->updateHealthNameTag();
    }

    public function saveNBT(): CompoundTag
    {
        $nbt = parent::saveNBT();
        $nbt->setString("zone_id", $this->zoneId);
        $nbt->setString("zone_mob_id", $this->mobId);
        $nbt->setString("zone_mob_name", $this->displayName);
        $nbt->setFloat("zone_mob_damage", $this->configuredDamage);
        $nbt->setFloat("zone_mob_speed", $this->configuredSpeed);
        $nbt->setFloat("zone_mob_target_range", $this->configuredTargetRange);
        $nbt->setInt("zone_mob_xp", $this->configuredXp);
        $nbt->setInt("zone_mob_health", (int) $this->getMaxHealth());
        $nbt->setByte("zone_mob_elite", $this->elite ? 1 : 0);
        $nbt->setString("zone_mob_elite_type", $this->eliteType);
        $nbt->setFloat("zone_mob_loot_multiplier", $this->lootMultiplier);
        $nbt->setString("zone_mob_combat_style", $this->combatStyle);
        $nbt->setString("zone_mob_weapon", $this->weapon);
        $nbt->setInt("zone_mob_ranged_cooldown", $this->rangedCooldownTicks);
        $nbt->setInt("zone_mob_melee_cooldown", $this->meleeCooldownTicks);
        $nbt->setInt("zone_mob_attack_windup", $this->attackWindupTicks);
        $nbt->setFloat("zone_mob_attack_reach", $this->attackReach);
        $nbt->setFloat("zone_mob_ideal_range_min", $this->idealRangeMin);
        $nbt->setFloat("zone_mob_ideal_range_max", $this->idealRangeMax);
        $nbt->setInt("zone_mob_strafe_chance", $this->strafeChance);
        $nbt->setByte("zone_mob_retreat_when_too_close", $this->retreatWhenTooClose ? 1 : 0);
        $nbt->setByte("zone_mob_allow_melee_when_ranged", $this->allowMeleeWhenRanged ? 1 : 0);
        $nbt->setFloat("zone_mob_horde_separation", $this->hordeSeparationStrength);
        return $nbt;
    }

    protected function entityBaseTick(int $tickDiff = 1): bool
    {
        if (
            $this->ticksLived >= 5 &&
            ($this->zoneId === "" || $this->mobId === "" || C::clean($this->displayName) === "Zone Mob")
        ) {
            $this->flagForDespawn();
            return parent::entityBaseTick($tickDiff);
        }

        $hasUpdate = parent::entityBaseTick($tickDiff);

        $this->tickPendingAttack($tickDiff);
        if (($this->ticksLived % 5) === 0) {
            $this->tickEliteAmbientAbilities();
        }

        if (($this->ticksLived % 10) === 0) {
            $this->updateHealthNameTag();
        }

        return $hasUpdate;
    }

    protected function updateAI(): void
    {
        if ($this->usesRangedCombat()) {
            $this->handleRangedBehavior();
            return;
        }

        $this->handleMeleeBehavior();
    }

    protected function tickMovement(): void
    {
        parent::tickMovement();

        if (!$this->isAlive()) {
            return;
        }

        if ($this->pendingAttackTarget !== null) {
            $this->motion->x *= 0.72;
            $this->motion->z *= 0.72;
        }

        if ($this->target !== null && !$this->usesRangedCombat()) {
            $distanceSq = $this->getPosition()->distanceSquared($this->target->getPosition());
            if (
                $distanceSq >= 6.25 &&
                $distanceSq <= 72.25 &&
                ($this->ticksLived % 6) === 0 &&
                mt_rand(1, 100) <= $this->getAmbientStrafeChance()
            ) {
                $forward = $this->buildMovementToward($this->target->getPosition(), $this->getMoveSpeed());
                $this->applyRandomStrafeImpulse($forward);
            }
        }

        if ($this->target !== null && $this->stuckTicks >= 6 && ($this->ticksLived % 6) === 0) {
            $this->performZoneSoftRecovery();
        }
    }

    protected function getInitialSizeInfo(): EntitySizeInfo
    {
        return new EntitySizeInfo(1.8, 0.6);
    }

    public static function getNetworkTypeId(): string
    {
        return EntityIds::ZOMBIE;
    }

    public function getName(): string
    {
        return C::clean($this->displayName);
    }

    public function getZoneId(): string
    {
        return $this->zoneId;
    }

    public function getMobId(): string
    {
        return $this->mobId;
    }

    public function isElite(): bool
    {
        return $this->elite;
    }

    public function getEliteType(): string
    {
        return $this->eliteType;
    }

    /**
     * @return Item[]
     */
    public function getDrops(): array
    {
        return ZoneMobManager::rollDrops($this->zoneId, $this->mobId, $this->elite, $this->lootMultiplier);
    }

    public function getLevelXpDropAmount(): int
    {
        return $this->configuredXp;
    }

    public function getXpDropAmount(): int
    {
        return max(1, (int) floor($this->configuredXp / 3));
    }

    protected function getTargetRange(): float
    {
        return $this->configuredTargetRange;
    }

    protected function getMoveSpeed(): float
    {
        return $this->configuredSpeed;
    }

    protected function getAttackDamage(): float
    {
        return $this->configuredDamage;
    }

    protected function getAttackCooldownTicks(): int
    {
        return $this->meleeCooldownTicks;
    }

    protected function getAttackReach(): float
    {
        return $this->attackReach;
    }

    private function usesRangedCombat(): bool
    {
        if ($this->combatStyle === "ranged") {
            return true;
        }

        return $this->weapon === "bow";
    }

    private function handleMeleeBehavior(): void
    {
        parent::updateAI();
        $this->applyHordeSteering();
    }

    private function handleRangedBehavior(): void
    {
        $this->validateTarget();
        if ($this->target === null) {
            $this->target = $this->findClosestPlayer();
            if ($this->target === null) {
                $this->path = [];
                $this->pathIndex = 0;
                return;
            }
            $this->recalculatePath();
        }

        if (!$this->target->isAlive()) {
            $this->clearTarget();
            return;
        }

        $targetPos = $this->target->getPosition();
        $this->lookAt($targetPos->add(0, $this->target->getEyeHeight(), 0));
        $distanceSq = $this->getPosition()->distanceSquared($targetPos);
        $idealMinSq = $this->idealRangeMin * $this->idealRangeMin;
        $idealMaxSq = $this->idealRangeMax * $this->idealRangeMax;
        $speed = $this->getMoveSpeed();

        if ($distanceSq >= $idealMinSq && $distanceSq <= $idealMaxSq && $this->attackCooldown <= 0) {
            $this->shootArrowAt($this->target);
        }

        if ($distanceSq < $idealMinSq && $this->retreatWhenTooClose) {
            $away = $this->buildMovementToward($targetPos, $speed);
            $this->motion->x = (-$away->x * 0.9) + ($this->buildSeparationVector()->x * 0.5);
            $this->motion->z = (-$away->z * 0.9) + ($this->buildSeparationVector()->z * 0.5);
        } elseif ($distanceSq > $idealMaxSq) {
            if ($this->shouldRecalculatePath()) {
                $this->recalculatePath();
            }

            $nextNode = $this->getCurrentPathNode();
            $goal = $nextNode ?? $targetPos;
            $move = $this->buildMovementToward($goal, $speed)->addVector($this->buildSeparationVector());
            $this->motion->x = $move->x;
            $this->motion->z = $move->z;
        } else {
            $forward = $this->buildMovementToward($targetPos, $speed);
            if (($this->ticksLived % 5) === 0 && mt_rand(1, 100) <= $this->getAmbientStrafeChance()) {
                $this->applyRandomStrafeImpulse($forward, 0.42);
            } else {
                $this->motion->x *= 0.78;
                $this->motion->z *= 0.78;
            }
        }

        if ($this->allowMeleeWhenRanged && $this->canAttack($this->target)) {
            $this->attackPlayer($this->target);
        }

        if ($this->target !== null && $this->stuckTicks >= 6 && ($this->ticksLived % 6) === 0) {
            $this->performZoneSoftRecovery();
        }

        if ($this->shouldJumpAhead() || $this->requiresJumpTowardElevatedNearbyTarget()) {
            $this->motion->y = max($this->motion->y, 0.42);
            $this->pathCooldown = 0;
        }

        $this->applyHordeSteering();
    }

    public function attackPlayer(Player $player): void
    {
        if ($this->pendingAttackTarget !== null || $this->attackCooldown > 0) {
            return;
        }

        if ($this->attackWindupTicks <= 0) {
            $this->deliverAttackTo($player);
            return;
        }

        $this->pendingAttackTarget = $player;
        $this->pendingAttackTicks = $this->attackWindupTicks;
        $this->attackCooldown = $this->attackWindupTicks + $this->getAttackCooldownTicks();
    }

    public function attack(EntityDamageEvent $source): void
    {
        if ($this->elite && $this->eliteType === "reinforced") {
            $source->setBaseDamage(max(0.0, $source->getBaseDamage() * 0.78));
        }

        parent::attack($source);
    }

    protected function onDeath(): void
    {
        parent::onDeath();
        $this->triggerEliteDeathEffect();
    }

    private function applyHordeSteering(): void
    {
        if ($this->target === null) {
            return;
        }

        $pushX = 0.0;
        $pushZ = 0.0;
        $radius = 2.6;
        $bb = $this->getBoundingBox()->expandedCopy($radius, 0.6, $radius);
        foreach ($this->getWorld()->getNearbyEntities($bb) as $entity) {
            if (!$entity instanceof self || $entity === $this || $entity->isClosed() || !$entity->isAlive()) {
                continue;
            }

            $dx = $this->location->x - $entity->location->x;
            $dz = $this->location->z - $entity->location->z;
            $distSq = ($dx * $dx) + ($dz * $dz);
            if ($distSq <= 0.0001 || $distSq > ($radius * $radius)) {
                continue;
            }

            $dist = sqrt($distSq);
            $strength = (1.0 - ($dist / $radius)) * $this->hordeSeparationStrength;
            $pushX += ($dx / $dist) * $strength;
            $pushZ += ($dz / $dist) * $strength;
        }

        $this->motion->x += $pushX;
        $this->motion->z += $pushZ;
    }

    private function tickPendingAttack(int $tickDiff): void
    {
        if ($this->pendingAttackTarget === null) {
            return;
        }

        $target = $this->pendingAttackTarget;
        if (
            !$target->isAlive() ||
            !$target->isOnline() ||
            $target->isClosed() ||
            $target->getWorld()->getFolderName() !== $this->getWorld()->getFolderName()
        ) {
            $this->clearPendingAttack();
            return;
        }

        $this->lookAt($target->getPosition()->add(0, $target->getEyeHeight(), 0));
        $this->motion->x *= 0.72;
        $this->motion->z *= 0.72;

        $this->pendingAttackTicks -= $tickDiff;
        if ($this->pendingAttackTicks > 0) {
            return;
        }

        if ($this->getPosition()->distanceSquared($target->getPosition()) <= (($this->getAttackReach() + 0.45) ** 2)) {
            $this->deliverAttackTo($target);
        }

        $this->clearPendingAttack();
    }

    private function clearPendingAttack(): void
    {
        $this->pendingAttackTarget = null;
        $this->pendingAttackTicks = 0;
    }

    private function deliverAttackTo(Player $player): void
    {
        $event = new EntityDamageByEntityEvent(
            $this,
            $player,
            EntityDamageByEntityEvent::CAUSE_ENTITY_ATTACK,
            $this->getAttackDamage()
        );
        $player->attack($event);

        if ($event->isCancelled()) {
            return;
        }

        $dx = $player->getPosition()->x - $this->location->x;
        $dz = $player->getPosition()->z - $this->location->z;
        $player->knockBack($dx, $dz);
        $this->applyEliteHitEffect($player);
    }

    private function applyEliteHitEffect(Player $player): void
    {
        if (!$this->elite) {
            return;
        }

        switch ($this->eliteType) {
            case "molten":
                $player->setOnFire(4);
                break;

            case "frost":
                $player->getEffects()->add(new EffectInstance(VanillaEffects::SLOWNESS(), 60, 1, false));
                break;

            case "leeching":
                $this->setHealth(min($this->getMaxHealth(), $this->getHealth() + max(1.0, $this->configuredDamage * 0.35)));
                break;

            case "corrupted":
                $this->applyRandomCorruptedDebuff($player);
                break;

            case "gravitic":
                self::pullLivingToward($player, $this->getPosition(), 0.26, 0.08);
                break;
        }
    }

    private function applyRandomCorruptedDebuff(Player $player): void
    {
        $roll = mt_rand(1, 4);
        switch ($roll) {
            case 1:
                $player->getEffects()->add(new EffectInstance(VanillaEffects::WEAKNESS(), 70, 0, false));
                break;
            case 2:
                $player->getEffects()->add(new EffectInstance(VanillaEffects::SLOWNESS(), 50, 0, false));
                break;
            case 3:
                $player->getEffects()->add(new EffectInstance(VanillaEffects::NAUSEA(), 60, 0, false));
                break;
            default:
                $player->getEffects()->add(new EffectInstance(VanillaEffects::BLINDNESS(), 32, 0, false));
                break;
        }
    }

    private function tickEliteAmbientAbilities(): void
    {
        if (!$this->elite || $this->target === null || !$this->target->isAlive()) {
            return;
        }

        switch ($this->eliteType) {
            case "charged":
                if ($this->ticksLived >= $this->nextChargedBurstTick) {
                    $boostSpeed = min(0.34, $this->getMoveSpeed() * 1.45);
                    $burst = $this->buildMovementToward($this->target->getPosition(), $boostSpeed);
                    $this->motion->x = $burst->x;
                    $this->motion->z = $burst->z;
                    if ($this->onGround && (($this->target->getPosition()->y - $this->getPosition()->y) > 0.5 || $this->stuckTicks >= 4)) {
                        $this->motion->y = max($this->motion->y, 0.42);
                    }
                    $this->nextChargedBurstTick = $this->ticksLived + mt_rand(28, 50);
                }
                break;

            case "gravitic":
                if ($this->ticksLived >= $this->nextGraviticPulseTick) {
                    self::pullNearbyTargets($this->getPosition(), 4.8, 0.10, 0.04);
                    $this->nextGraviticPulseTick = $this->ticksLived + mt_rand(10, 16);
                }
                break;
        }
    }

    private function triggerEliteDeathEffect(): void
    {
        if (!$this->elite || $this->eliteType === "") {
            return;
        }

        $center = Position::fromObject(
            $this->getPosition()->add(0, $this->getSize()->getHeight() / 2, 0),
            $this->getWorld()
        );
        $burstDamage = max(3.0, $this->configuredDamage * 1.35);

        switch ($this->eliteType) {
            case "exploding":
                self::detonateAt($center, 4.2, $burstDamage);
                break;

            case "imploding":
                self::pullNearbyTargets($center, 5.2, 0.22, 0.08);
                Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($center, $burstDamage): void {
                    self::detonateAt($center, 4.4, $burstDamage);
                }), 8);
                break;

            case "splitter":
                ZoneMobManager::spawnSplitterOffspring($this, 2);
                break;
        }
    }

    private static function detonateAt(Position $center, float $radius, float $damage): void
    {
        $world = $center->getWorld();
        $world->addSound($center, new ExplodeSound());

        $box = new AxisAlignedBB(
            $center->x - $radius,
            $center->y - $radius,
            $center->z - $radius,
            $center->x + $radius,
            $center->y + $radius,
            $center->z + $radius
        );

        foreach ($world->getNearbyEntities($box) as $entity) {
            if (!$entity instanceof Living || $entity instanceof self || $entity->isClosed() || !$entity->isAlive()) {
                continue;
            }

            $distance = $entity->getPosition()->distance($center);
            if ($distance > $radius) {
                continue;
            }

            $scaledDamage = max(1.0, $damage * (1.0 - min(0.7, $distance / max(0.1, $radius))));
            $entity->attack(new EntityDamageEvent($entity, EntityDamageEvent::CAUSE_ENTITY_EXPLOSION, $scaledDamage));

            $dx = $entity->getPosition()->x - $center->x;
            $dz = $entity->getPosition()->z - $center->z;
            if ($entity instanceof Player) {
                $entity->knockBack($dx, $dz, 0.55);
            }
        }
    }

    private static function pullNearbyTargets(Position $center, float $radius, float $horizontalForce, float $verticalBoost): void
    {
        $box = new AxisAlignedBB(
            $center->x - $radius,
            $center->y - $radius,
            $center->z - $radius,
            $center->x + $radius,
            $center->y + $radius,
            $center->z + $radius
        );

        foreach ($center->getWorld()->getNearbyEntities($box) as $entity) {
            if (!$entity instanceof Living || $entity instanceof self || $entity->isClosed() || !$entity->isAlive()) {
                continue;
            }

            if ($entity->getPosition()->distance($center) > $radius) {
                continue;
            }

            self::pullLivingToward($entity, $center, $horizontalForce, $verticalBoost);
        }
    }

    private static function pullLivingToward(Living $entity, Vector3 $center, float $horizontalForce, float $verticalBoost): void
    {
        $dx = $center->x - $entity->getPosition()->x;
        $dz = $center->z - $entity->getPosition()->z;
        $lenSq = ($dx * $dx) + ($dz * $dz);
        if ($lenSq <= 0.0001) {
            return;
        }

        $len = sqrt($lenSq);
        $motion = $entity->getMotion();
        $motion->x += ($dx / $len) * $horizontalForce;
        $motion->z += ($dz / $len) * $horizontalForce;
        $motion->y = max($motion->y, $verticalBoost);
        $entity->setMotion($motion);
    }

    private function getAmbientStrafeChance(): int
    {
        $bonus = static::getNetworkTypeId() === EntityIds::SPIDER ? 20 : 8;
        return min(85, $this->strafeChance + $bonus);
    }

    private function applyRandomStrafeImpulse(Vector3 $forward, float $forwardBias = 0.55): void
    {
        $side = mt_rand(0, 1) === 0 ? -1.0 : 1.0;
        $this->motion->x = ($forward->x * $forwardBias) + ((-$forward->z) * $side * 0.85);
        $this->motion->z = ($forward->z * $forwardBias) + (($forward->x) * $side * 0.85);
    }

    private function performZoneSoftRecovery(): void
    {
        if ($this->target === null) {
            return;
        }

        $forward = $this->buildMovementToward($this->target->getPosition(), max(0.12, $this->getMoveSpeed()));
        $this->applyRandomStrafeImpulse($forward, 0.35);
        $this->motion->y = max($this->motion->y, 0.42);
        $this->pathCooldown = 0;
        $this->recalculatePath();
    }

    private function shootArrowAt(Entity $target): void
    {
        $shooterPos = $this->getPosition()->add(0, $this->getEyeHeight(), 0);
        $targetPos = $target->getPosition()->add(0, $target->getEyeHeight() * 0.85, 0);
        $dx = $targetPos->x - $shooterPos->x;
        $dy = $targetPos->y - $shooterPos->y;
        $dz = $targetPos->z - $shooterPos->z;
        $len = sqrt(($dx * $dx) + ($dy * $dy) + ($dz * $dz));
        if ($len <= 0.0001) {
            return;
        }

        $arrow = $this->createArrowProjectile();
        if (!$arrow instanceof Arrow) {
            return;
        }

        $speed = 1.15;
        $arrow->setMotion($arrow->getMotion()->add(($dx / $len) * $speed, ($dy / $len) * $speed, ($dz / $len) * $speed));
        if (method_exists($arrow, "setBaseDamage")) {
            $arrow->setBaseDamage(max(1.0, $this->configuredDamage));
        }
        $arrow->spawnToAll();
        $this->attackCooldown = $this->rangedCooldownTicks;
    }

    private function createArrowProjectile(): ?Arrow
    {
        $location = Location::fromObject($this->getPosition()->add(0, $this->getEyeHeight(), 0), $this->getWorld(), $this->location->yaw, $this->location->pitch);
        $nbt = CompoundTag::create();
        $attempts = [
            [$location, $this],
            [$location, $this, $nbt],
            [$location, $this, false],
            [$location, $this, false, $nbt],
        ];

        foreach ($attempts as $args) {
            try {
                $projectile = (new \ReflectionClass(Arrow::class))->newInstanceArgs($args);
                if ($projectile instanceof Arrow) {
                    return $projectile;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    protected function updateHealthNameTag(): void
    {
        $health = max(0, (int) ceil($this->getHealth()));
        $max = max(1, (int) ceil($this->getMaxHealth()));
        $color = $health <= ($max * 0.3) ? "&c" : ($health <= ($max * 0.6) ? "&e" : "&a");

        $this->setNameTag(C::colorize(
            "&r" . $this->displayName . "\n&r{$color}HP &f{$health}&7/&f{$max}"
        ));
    }

    public function getTicksLived(): int
    {
        return $this->ticksLived;
    }
}
