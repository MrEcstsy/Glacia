<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\boss\ability;

use ecstsy\Glacia\entity\boss\BossAbility;
use ecstsy\Glacia\entity\boss\BossEntity;
use ecstsy\Glacia\entity\boss\bosses\GlacialHydra;
use ecstsy\Glacia\entity\boss\projectile\HydraRiftFireball as HydraRiftFireballProjectile;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\CustomParticleEffectManager;
use pocketmine\entity\Location;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\TextFormat as C;
use pocketmine\world\sound\GhastShootSound;

final class HydraRiftFireball extends BossAbility
{
    public function __construct()
    {
        parent::__construct(150);
    }

    protected function checkConditions(BossEntity $boss): bool
    {
        if (!$boss instanceof GlacialHydra || !$boss->hasHeadActive(GlacialHydra::HEAD_VOID)) {
            return false;
        }

        $target = $boss->getTarget();
        if ($target === null) {
            return false;
        }

        $distanceSq = $boss->getPosition()->distanceSquared($target->getPosition());
        return $distanceSq >= (4.5 ** 2) && $distanceSq <= (24.0 ** 2);
    }

    protected function onCast(BossEntity $boss): void
    {
        if (!$boss instanceof GlacialHydra) {
            return;
        }

        $boss->broadcastEntityAnimation("void_cast", "", "", 0.12);
        $boss->broadcastAbility(C::colorize("&r&5The Hydra condenses a rift ember. &r&fHit it back!"), 36);
        $boss->playAbilitySound("mob.endermen.portal", 1.2, 0.7, 36);

        Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(
            static function () use ($boss): void {
                if ($boss->isClosed() || !$boss->isAlive()) {
                    return;
                }

                $target = $boss->getTarget();
                if ($target === null || !$target->isAlive() || !$target->isConnected()) {
                    return;
                }

                $origin = $boss->getPosition()->asVector3()->add(0, 1.65, 0);
                CustomParticleEffectManager::columnVariants(
                    $boss->getWorld(),
                    $origin->add(0, -0.2, 0),
                    [
                        CustomParticleEffectManager::HYDRA_VOID_CORE,
                        CustomParticleEffectManager::HYDRA_VOID,
                        CustomParticleEffectManager::HYDRA_VOID_SHARD,
                    ],
                    2.3,
                    8,
                    48.0
                );
                CustomParticleEffectManager::burstVariants(
                    $boss->getWorld(),
                    $origin,
                    [
                        CustomParticleEffectManager::HYDRA_VOID,
                        CustomParticleEffectManager::HYDRA_VOID_CORE,
                        CustomParticleEffectManager::HYDRA_VOID_SHARD,
                    ],
                    18,
                    0.65,
                    0.55,
                    48.0
                );
                $aim = $target->getPosition()->asVector3()->add(0, $target->getEyeHeight() * 0.85, 0);
                $delta = $aim->subtractVector($origin);
                if ($delta->lengthSquared() <= 0.0001) {
                    return;
                }

                $baseDirection = $delta->normalize();
                $volley = match ($boss->getPhase()) {
                    1 => [0.0],
                    2 => [-9.0, 9.0],
                    3 => [-14.0, 0.0, 14.0],
                    default => [0.0],
                };
                $damage = match ($boss->getPhase()) {
                    1 => 7.5,
                    2 => 10.0,
                    3 => 13.0,
                    default => 7.5,
                } * $boss->getPressureDamageMultiplier();
                $speed = match ($boss->getPhase()) {
                    1 => 0.92,
                    2 => 1.05,
                    3 => 1.18,
                    default => 0.92,
                };

                $launchPlans = [[$target, $volley, $damage]];
                $extraTargets = [];
                foreach ($boss->getPlayersInRange(18) as $candidate) {
                    if ($candidate->getId() === $target->getId()) {
                        continue;
                    }

                    $extraTargets[] = $candidate;
                    if (count($extraTargets) >= 2) {
                        break;
                    }
                }

                if ($boss->getEngagedPlayerCount(18.0) >= 3 && isset($extraTargets[0])) {
                    $launchPlans[] = [$extraTargets[0], [0.0], $damage * 0.9];
                }

                if (($boss->getPhase() >= 3 || $boss->getEngagedPlayerCount(18.0) >= 4) && isset($extraTargets[1])) {
                    $launchPlans[] = [$extraTargets[1], [0.0], $damage * 0.82];
                }

                foreach ($launchPlans as [$launchTarget, $spreads, $shotDamage]) {
                    if (!$launchTarget instanceof \pocketmine\player\Player || !$launchTarget->isAlive() || !$launchTarget->isConnected()) {
                        continue;
                    }

                    $aim = $launchTarget->getPosition()->asVector3()->add(0, $launchTarget->getEyeHeight() * 0.85, 0);
                    $shotDelta = $aim->subtractVector($origin);
                    if ($shotDelta->lengthSquared() <= 0.0001) {
                        continue;
                    }

                    $shotDirection = $shotDelta->normalize();

                    foreach ($spreads as $spread) {
                        $direction = self::rotateDirection($shotDirection, $spread);
                        $spawn = $origin->addVector($direction->multiply(2.35));

                        $projectile = new HydraRiftFireballProjectile(
                            Location::fromObject($spawn, $boss->getWorld(), $boss->getLocation()->yaw, $boss->getLocation()->pitch),
                            $boss,
                            CompoundTag::create()
                        );
                        $projectile->setBaseDamage($shotDamage);
                        $projectile->setMotion($direction->multiply($speed));

                        $ev = new ProjectileLaunchEvent($projectile);
                        $ev->call();
                        if ($ev->isCancelled()) {
                            $projectile->flagForDespawn();
                            continue;
                        }

                        $projectile->spawnToAll();
                    }
                }

                $boss->getWorld()->addSound($origin, new GhastShootSound());
            }
        ), 10);
    }

    private static function rotateDirection(\pocketmine\math\Vector3 $direction, float $degrees): \pocketmine\math\Vector3
    {
        if (abs($degrees) < 0.001) {
            return $direction;
        }

        $radians = deg2rad($degrees);
        $cos = cos($radians);
        $sin = sin($radians);

        return new \pocketmine\math\Vector3(
            ($direction->x * $cos) - ($direction->z * $sin),
            $direction->y,
            ($direction->x * $sin) + ($direction->z * $cos)
        );
    }
}
