<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\boss\ability;

use ecstsy\Glacia\entity\boss\BossAbility;
use ecstsy\Glacia\entity\boss\BossParticles;
use ecstsy\Glacia\entity\boss\BossEntity;
use ecstsy\Glacia\entity\boss\bosses\GlacialHydra;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\CustomParticleEffectManager;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\TextFormat as C;
use pocketmine\world\particle\BlockBreakParticle;

final class HydraIceStorm extends BossAbility
{
    public function __construct()
    {
        parent::__construct(200);
    }

    protected function checkConditions(BossEntity $boss): bool
    {
        return $boss instanceof GlacialHydra &&
            $boss->hasHeadActive(GlacialHydra::HEAD_FROST) &&
            $boss->getTarget() !== null;
    }

    protected function onCast(BossEntity $boss): void
    {
        if (!$boss instanceof GlacialHydra) {
            return;
        }

        $target = $boss->getTarget();
        if ($target === null) {
            return;
        }

        $center = $target->getPosition()->asVector3();
        $world = $boss->getWorld();
        $radius = match ($boss->getPhase()) {
            1 => 3.5,
            2 => 4.2,
            3 => 5.0,
            default => 3.5,
        } + min(0.8, max(0, $boss->getEngagedPlayerCount(14.0) - 1) * 0.2);
        $damage = match ($boss->getPhase()) {
            1 => 9.0,
            2 => 13.0,
            3 => 17.0,
            default => 9.0,
        } * $boss->getPressureDamageMultiplier();

        $boss->broadcastEntityAnimation("frost_cast", "", "", 0.12);
        $boss->broadcastAbility(C::colorize("&r&bThe Hydra's frost head calls down an ice storm!"), 36);
        $boss->playAbilitySound("ambient.weather.rain", 1.2, 0.8, 36);

        for ($t = 2; $t <= 22; $t += 4) {
            Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(
                static function () use ($world, $center, $radius): void {
                    BossParticles::ring(
                        $world,
                        $center,
                        $radius,
                        new BlockBreakParticle(VanillaBlocks::PACKED_ICE()),
                        max(10, (int) ($radius * 3.2)),
                        0.12
                    );
                    CustomParticleEffectManager::stackedRing(
                        $world,
                        $center,
                        $radius,
                        CustomParticleEffectManager::HYDRA_WARNING,
                        2,
                        max(18, (int) ($radius * 4.6)),
                        0.24,
                        48.0
                    );
                    CustomParticleEffectManager::column(
                        $world,
                        $center->add(0, 0.10, 0),
                        CustomParticleEffectManager::HYDRA_FROST,
                        1.9,
                        7,
                        48.0
                    );
                }
            ), $t);
        }

        Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(
            static function () use ($boss, $world, $center, $radius, $damage): void {
                if ($boss->isClosed() || !$boss->isAlive() || $boss->getWorld() !== $world) {
                    return;
                }

                BossParticles::burst(
                    $world,
                    $center,
                    new BlockBreakParticle(VanillaBlocks::ICE()),
                    20,
                    $radius * 0.45,
                    1.05
                );
                CustomParticleEffectManager::stackedRing(
                    $world,
                    $center,
                    $radius * 0.9,
                    CustomParticleEffectManager::HYDRA_WARNING,
                    3,
                    max(22, (int) ($radius * 5.2)),
                    0.22,
                    56.0
                );
                CustomParticleEffectManager::burst(
                    $world,
                    $center->add(0, 0.18, 0),
                    CustomParticleEffectManager::HYDRA_FROST,
                    26,
                    $radius * 0.32,
                    0.85,
                    56.0
                );
                CustomParticleEffectManager::column(
                    $world,
                    $center->add(0, 0.12, 0),
                    CustomParticleEffectManager::HYDRA_FROST,
                    2.4,
                    9,
                    56.0
                );

                $radiusSq = $radius * $radius;
                foreach ($world->getPlayers() as $player) {
                    if (!$player->isAlive() || !$player->isConnected()) {
                        continue;
                    }
                    if ($player->getPosition()->distanceSquared($center) > $radiusSq) {
                        continue;
                    }

                    $boss->dealBossTrueDamage($player, $damage);
                    $player->getEffects()->add(new EffectInstance(VanillaEffects::SLOWNESS(), 80, 2));
                }

                $boss->playAbilitySound("random.glass", 1.2, 0.6, 36);
            }
        ), 24);
    }
}
