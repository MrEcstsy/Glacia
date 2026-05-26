<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\boss\projectile;

use ecstsy\Glacia\entity\boss\BossParticles;
use ecstsy\Glacia\entity\boss\bosses\GlacialHydra;
use ecstsy\Glacia\utils\CustomParticleEffectManager;
use pocketmine\color\Color;
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
use pocketmine\utils\TextFormat as C;
use pocketmine\world\particle\DustParticle;
use pocketmine\world\particle\FlameParticle;
use pocketmine\world\particle\PortalParticle;
use pocketmine\world\sound\BlazeShootSound;
use pocketmine\world\sound\ExplodeSound;
use function ceil;
use function max;

final class HydraRiftFireball extends Projectile implements NeverSavedWithChunkEntity
{
    private bool $deflected = false;
    private ?int $ignoredEntityId = null;
    private int $ignoreUntilTick = 0;

    public static function getNetworkTypeId(): string
    {
        return "minecraft:ice_bomb";
    }

    protected function getInitialSizeInfo(): EntitySizeInfo
    {
        return new EntitySizeInfo(0.55, 0.55);
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
    }

    public function getResultDamage(): int
    {
        return (int) ceil(max(1.0, $this->motion->length() * $this->getBaseDamage()));
    }

    public function canBeCollidedWith(): bool
    {
        return true;
    }

    public function canCollideWith(Entity $entity): bool
    {
        if ($this->ignoredEntityId !== null && $this->ticksLived <= $this->ignoreUntilTick && $entity->getId() === $this->ignoredEntityId) {
            return false;
        }

        $owner = $this->getOwningEntity();
        if ($owner !== null && $entity->getId() === $owner->getId()) {
            return false;
        }

        return parent::canCollideWith($entity);
    }

    public function attack(EntityDamageEvent $source): void
    {
        if ($source instanceof EntityDamageByEntityEvent && ($damager = $source->getDamager()) instanceof Player) {
            $source->cancel();
            $this->deflectFrom($damager);
            return;
        }

        parent::attack($source);
    }

    protected function entityBaseTick(int $tickDiff = 1): bool
    {
        $hasUpdate = parent::entityBaseTick($tickDiff);

        if (($this->ticksLived % 2) === 0) {
            $this->spawnTrail();
        }

        if ($this->ticksLived > 160) {
            $this->flagForDespawn();
            $hasUpdate = true;
        }

        return $hasUpdate;
    }

    protected function onHit(ProjectileHitEvent $event): void
    {
        $world = $this->getWorld();
        $pos = $this->getPosition();

        $world->addSound($pos, new ExplodeSound());
        BossParticles::burst($world, $pos, new PortalParticle(), 8, 0.7, 0.45);
        BossParticles::burst(
            $world,
            $pos,
            new DustParticle($this->deflected ? new Color(255, 120, 170) : new Color(156, 92, 255)),
            10,
            0.6,
            0.45
        );
        CustomParticleEffectManager::stackedRing(
            $world,
            $pos,
            1.35,
            CustomParticleEffectManager::HYDRA_WARNING,
            2,
            14,
            0.20,
            48.0
        );
        CustomParticleEffectManager::ringVariants(
            $world,
            $pos,
            1.05,
            [
                CustomParticleEffectManager::HYDRA_WARNING,
                CustomParticleEffectManager::HYDRA_WARNING_ARC,
            ],
            12,
            0.18,
            48.0
        );
        CustomParticleEffectManager::burstVariants(
            $world,
            $pos->add(0, 0.16, 0),
            [
                CustomParticleEffectManager::HYDRA_VOID,
                CustomParticleEffectManager::HYDRA_VOID_CORE,
                CustomParticleEffectManager::HYDRA_VOID_SHARD,
            ],
            18,
            0.52,
            0.55,
            48.0
        );
    }

    protected function onHitEntity(Entity $entityHit, RayTraceResult $hitResult): void
    {
        parent::onHitEntity($entityHit, $hitResult);

        if ($this->deflected && $entityHit instanceof GlacialHydra) {
            $entityHit->broadcastAbility(C::colorize("&r&bThe Hydra recoils as its own rift ember crashes back into it!"), 36);
            $entityHit->triggerGlobalAbilityCooldown(24);
        }
    }

    private function deflectFrom(Player $player): void
    {
        if ($this->isClosed() || !$this->isAlive()) {
            return;
        }

        $this->deflected = true;
        $this->setOwningEntity($player);
        $this->setBaseDamage(max($this->getBaseDamage() * 2.35, 18.0));
        $this->ignoredEntityId = $player->getId();
        $this->ignoreUntilTick = $this->ticksLived + 10;

        $direction = $player->getDirectionVector()->normalize();
        $speed = max(1.15, $this->motion->length() + 0.18);
        $origin = $player->getEyePos()->addVector($direction->multiply(1.1));

        $this->setPosition($origin);
        $this->setRotation($player->getLocation()->yaw, $player->getLocation()->pitch);
        $this->motion = $direction->multiply($speed)->add(0, 0.08, 0);

        $player->sendActionBarMessage(C::colorize("&r&dYou deflected the rift ember!"));
        $this->getWorld()->addSound($origin, new BlazeShootSound());
        BossParticles::burst($this->getWorld(), $origin, new DustParticle(new Color(255, 125, 170)), 14, 0.7, 0.45);
        CustomParticleEffectManager::burstVariants(
            $this->getWorld(),
            $origin,
            [
                CustomParticleEffectManager::HYDRA_FROST,
                CustomParticleEffectManager::HYDRA_FROST_SHARD,
                CustomParticleEffectManager::HYDRA_FROST_MIST,
            ],
            14,
            0.42,
            0.42,
            48.0
        );
    }

    private function spawnTrail(): void
    {
        $world = $this->getWorld();
        $pos = $this->getPosition();

        $world->addParticle(
            $pos,
            new DustParticle($this->deflected ? new Color(255, 120, 170) : new Color(140, 92, 255))
        );
        $world->addParticle($pos->add(0, 0.12, 0), new PortalParticle());
        CustomParticleEffectManager::burstVariants(
            $world,
            $pos->add(0, 0.06, 0),
            $this->deflected
                ? [
                    CustomParticleEffectManager::HYDRA_FROST,
                    CustomParticleEffectManager::HYDRA_FROST_SHARD,
                    CustomParticleEffectManager::HYDRA_FROST_MIST,
                ]
                : [
                    CustomParticleEffectManager::HYDRA_VOID,
                    CustomParticleEffectManager::HYDRA_VOID_CORE,
                    CustomParticleEffectManager::HYDRA_VOID_SHARD,
                ],
            4,
            0.16,
            0.18,
            40.0
        );
        if (($this->ticksLived % 4) === 0) {
            $world->addParticle($pos, new FlameParticle());
        }
    }
}
