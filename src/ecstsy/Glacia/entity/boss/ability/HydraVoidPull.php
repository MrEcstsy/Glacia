<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\boss\ability;

use ecstsy\Glacia\entity\boss\BossAbility;
use ecstsy\Glacia\entity\boss\BossEntity;
use ecstsy\Glacia\entity\boss\BossParticles;
use ecstsy\Glacia\entity\boss\bosses\GlacialHydra;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\CustomParticleEffectManager;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\TextFormat as C;

final class HydraVoidPull extends BossAbility
{
    public function __construct()
    {
        parent::__construct(180);
    }

    protected function checkConditions(BossEntity $boss): bool
    {
        return $boss instanceof GlacialHydra && $boss->hasHeadActive(GlacialHydra::HEAD_VOID);
    }

    protected function onCast(BossEntity $boss): void
    {
        if (!$boss instanceof GlacialHydra) {
            return;
        }

        $center = $boss->getPosition()->asVector3()->add(0, 1.4, 0);
        $world = $boss->getWorld();

        $boss->broadcastEntityAnimation("void_cast", "", "", 0.14);
        $boss->broadcastAbility(C::colorize("&r&5The Hydra's void head distorts space!"), 36);
        $boss->playAbilitySound("mob.endermen.portal", 1.3, 0.75, 36);

        for ($tick = 2; $tick <= 12; $tick += 2) {
            Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(
                static function () use ($world, $center): void {
                    BossParticles::ring(
                        $world,
                        $center,
                        2.4,
                        BossParticles::portalParticle(),
                        8,
                        0.0
                    );
                    BossParticles::ring(
                        $world,
                        $center,
                        4.0,
                        BossParticles::portalParticle(),
                        10,
                        0.2
                    );
                    CustomParticleEffectManager::stackedRing(
                        $world,
                        $center,
                        4.0,
                        CustomParticleEffectManager::HYDRA_WARNING,
                        2,
                        20,
                        0.22,
                        48.0
                    );
                    CustomParticleEffectManager::ringVariants(
                        $world,
                        $center,
                        2.7,
                        [
                            CustomParticleEffectManager::HYDRA_WARNING,
                            CustomParticleEffectManager::HYDRA_WARNING_ARC,
                        ],
                        16,
                        0.14,
                        48.0
                    );
                    CustomParticleEffectManager::columnVariants(
                        $world,
                        $center->add(0, 0.1, 0),
                        [
                            CustomParticleEffectManager::HYDRA_VOID_CORE,
                            CustomParticleEffectManager::HYDRA_VOID,
                            CustomParticleEffectManager::HYDRA_VOID_SHARD,
                        ],
                        2.1,
                        8,
                        48.0
                    );
                }
            ), $tick);
        }

        Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(
            static function () use ($boss, $world, $center): void {
                if ($boss->isClosed() || !$boss->isAlive() || $boss->getWorld() !== $world) {
                    return;
                }

                BossParticles::burst(
                    $world,
                    $center,
                    BossParticles::portalParticle(),
                    16,
                    1.7,
                    0.8
                );
                CustomParticleEffectManager::burstVariants(
                    $world,
                    $center->add(0, 0.15, 0),
                    [
                        CustomParticleEffectManager::HYDRA_VOID,
                        CustomParticleEffectManager::HYDRA_VOID_CORE,
                        CustomParticleEffectManager::HYDRA_VOID_SHARD,
                    ],
                    24,
                    0.95,
                    0.75,
                    56.0
                );
                CustomParticleEffectManager::stackedRing(
                    $world,
                    $center,
                    4.2,
                    CustomParticleEffectManager::HYDRA_WARNING,
                    3,
                    22,
                    0.22,
                    56.0
                );

                foreach ($boss->getPlayersInRange(11) as $player) {
                    $delta = $boss->getPosition()->subtractVector($player->getPosition());
                    $length = sqrt(($delta->x * $delta->x) + ($delta->z * $delta->z));
                    if ($length <= 0.001) {
                        continue;
                    }

                    $force = match ($boss->getPhase()) {
                        1 => 0.48,
                        2 => 0.76,
                        3 => 1.02,
                        default => 0.48,
                    };

                    $boss->applyBossMotion($player, new Vector3(
                        ($delta->x / $length) * $force,
                        0.30,
                        ($delta->z / $length) * $force
                    ));
                    CustomParticleEffectManager::lineVariants(
                        $world,
                        $player->getPosition()->add(0, 0.9, 0),
                        $center,
                        [
                            CustomParticleEffectManager::HYDRA_VOID_CORE,
                            CustomParticleEffectManager::HYDRA_VOID_SHARD,
                        ],
                        8,
                        48.0
                    );
                    $boss->dealBossTrueDamage(
                        $player,
                        match ($boss->getPhase()) {
                            1 => 5.0,
                            2 => 7.5,
                            3 => 10.5,
                            default => 5.0,
                        } * $boss->getPressureDamageMultiplier()
                    );
                }

                $boss->playAbilitySound("mob.endermen.portal", 1.4, 0.55, 36);

                if ($boss->getPhase() < 2) {
                    return;
                }

                foreach ([4, 8] as $tick) {
                    Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(
                        static function () use ($world, $center): void {
                            BossParticles::ring($world, $center, 4.2, BossParticles::warningParticle(), 20, 0.05);
                            CustomParticleEffectManager::stackedRing(
                                $world,
                                $center,
                                4.2,
                                CustomParticleEffectManager::HYDRA_WARNING,
                                2,
                                22,
                                0.22,
                                56.0
                            );
                        }
                    ), $tick);
                }

                Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(
                    static function () use ($boss, $world, $center): void {
                        if ($boss->isClosed() || !$boss->isAlive() || $boss->getWorld() !== $world) {
                            return;
                        }

                        BossParticles::burst($world, $center, BossParticles::portalParticle(), 10, 1.4, 0.8);
                        CustomParticleEffectManager::columnVariants(
                            $world,
                            $center->add(0, 0.10, 0),
                            [
                                CustomParticleEffectManager::HYDRA_VOID_CORE,
                                CustomParticleEffectManager::HYDRA_VOID,
                                CustomParticleEffectManager::HYDRA_VOID_SHARD,
                            ],
                            2.7,
                            10,
                            56.0
                        );
                        CustomParticleEffectManager::burstVariants(
                            $world,
                            $center->add(0, 0.18, 0),
                            [
                                CustomParticleEffectManager::HYDRA_VOID,
                                CustomParticleEffectManager::HYDRA_VOID_CORE,
                                CustomParticleEffectManager::HYDRA_VOID_SHARD,
                            ],
                            28,
                            1.25,
                            1.0,
                            56.0
                        );
                        foreach ($world->getPlayers() as $player) {
                            if (!$player->isAlive() || !$player->isConnected()) {
                                continue;
                            }
                            if ($player->getPosition()->distanceSquared($center) > (4.2 ** 2)) {
                                continue;
                            }

                            $boss->dealBossTrueDamage(
                                $player,
                                ($boss->getPhase() >= 3 ? 13.0 : 9.0) * $boss->getPressureDamageMultiplier()
                            );
                        }

                        $boss->broadcastAbility(C::colorize("&r&5The rift ruptures where the Hydra dragged you!"), 36);
                    }
                ), 10);
            }
        ), 14);
    }
}
