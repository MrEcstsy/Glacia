<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\zones;

use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\NeverSavedWithChunkEntity;
use pocketmine\entity\projectile\Projectile;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\math\RayTraceResult;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\world\particle\FlameParticle;
use pocketmine\world\sound\BlazeShootSound;
use pocketmine\world\sound\ExplodeSound;

use function ceil;
use function max;

final class ZoneMobFireball extends Projectile implements NeverSavedWithChunkEntity
{
    private int $fireSeconds = 4;

    public static function getNetworkTypeId(): string
    {
        return "minecraft:fireball";
    }

    protected function getInitialSizeInfo(): EntitySizeInfo
    {
        return new EntitySizeInfo(0.22, 0.22);
    }

    protected function getInitialDragMultiplier(): float
    {
        return 0.01;
    }

    protected function getInitialGravity(): float
    {
        return 0.0;
    }

    protected function initEntity(CompoundTag $nbt): void
    {
        parent::initEntity($nbt);
        $this->setMaxHealth(1);
        $this->setHealth(1);
        $this->setScale(0.28);
        $this->fireSeconds = max(0, $nbt->getInt("zone_fireball_fire_seconds", 4));
    }

    public function saveNBT(): CompoundTag
    {
        $nbt = parent::saveNBT();
        $nbt->setInt("zone_fireball_fire_seconds", $this->fireSeconds);
        return $nbt;
    }

    public function setFireSeconds(int $seconds): void
    {
        $this->fireSeconds = max(0, $seconds);
    }

    public function getResultDamage(): int
    {
        return (int) ceil(max(1.0, $this->motion->length() * $this->getBaseDamage()));
    }

    public function canCollideWith(Entity $entity): bool
    {
        $owner = $this->getOwningEntity();
        if ($owner !== null && $entity->getId() === $owner->getId()) {
            return false;
        }

        return $entity instanceof Player && parent::canCollideWith($entity);
    }

    protected function entityBaseTick(int $tickDiff = 1): bool
    {
        $hasUpdate = parent::entityBaseTick($tickDiff);

        if (($this->ticksLived % 2) === 0) {
            $this->getWorld()->addParticle($this->getPosition(), new FlameParticle());
        }

        if ($this->ticksLived === 1) {
            $this->getWorld()->addSound($this->getPosition(), new BlazeShootSound());
        }

        if ($this->ticksLived > 80) {
            $this->flagForDespawn();
            $hasUpdate = true;
        }

        return $hasUpdate;
    }

    protected function onHit(ProjectileHitEvent $event): void
    {
        $this->getWorld()->addSound($this->getPosition(), new ExplodeSound());
        for ($i = 0; $i < 6; $i++) {
            $this->getWorld()->addParticle($this->getPosition()->add(mt_rand(-4, 4) / 10, mt_rand(-2, 4) / 10, mt_rand(-4, 4) / 10), new FlameParticle());
        }
    }

    protected function onHitEntity(Entity $entityHit, RayTraceResult $hitResult): void
    {
        parent::onHitEntity($entityHit, $hitResult);

        if ($entityHit instanceof Player && $this->fireSeconds > 0) {
            $entityHit->setOnFire($this->fireSeconds);
        }
    }

    public function attack(EntityDamageEvent $source): void
    {
        if ($source instanceof EntityDamageByEntityEvent && $source->getDamager() instanceof Player) {
            $source->cancel();
            return;
        }

        parent::attack($source);
    }
}
