<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\boss\ability;

use ecstsy\Glacia\entity\boss\BossAbility;
use ecstsy\Glacia\entity\boss\BossEntity;
use ecstsy\Glacia\entity\boss\BossParticles;
use ecstsy\Glacia\entity\boss\bosses\GlacialHydra;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\CustomParticleEffectManager;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\TextFormat as C;
use pocketmine\world\particle\BlockBreakParticle;

final class HydraCrystalSpikes extends BossAbility
{
    public function __construct()
    {
        parent::__construct(210);
    }

    protected function checkConditions(BossEntity $boss): bool
    {
        return $boss instanceof GlacialHydra &&
            $boss->getPhase() >= 2 &&
            $boss->getTarget() !== null &&
            ($boss->hasHeadActive(GlacialHydra::HEAD_FROST) || $boss->hasHeadActive(GlacialHydra::HEAD_VOID));
    }

    protected function onCast(BossEntity $boss): void
    {
        if (!$boss instanceof GlacialHydra) {
            return;
        }

        $spikePoints = $this->collectSpikePoints($boss);
        if ($spikePoints === []) {
            return;
        }

        $world = $boss->getWorld();
        $telegraphParticle = new BlockBreakParticle(VanillaBlocks::PACKED_ICE());
        $impactParticle = new BlockBreakParticle(VanillaBlocks::BLUE_ICE());
        $damage = match ($boss->getPhase()) {
            2 => 12.0,
            3 => 16.0,
            default => 9.0,
        } * $boss->getPressureDamageMultiplier();
        $radius = 1.9;

        $boss->broadcastEntityAnimation("crystal_spikes", "", "", 0.14);
        $boss->broadcastAbility(C::colorize("&r&dVoidfrost crystals begin rupturing from the floor!"), 36);
        $boss->playAbilitySound("random.glass", 1.15, 0.8, 36);

        for ($tick = 2; $tick <= 18; $tick += 4) {
            Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(
                static function () use ($world, $spikePoints, $telegraphParticle): void {
                    foreach ($spikePoints as $point) {
                        BossParticles::ring(
                            $world,
                            $point,
                            1.6,
                            $telegraphParticle,
                            8,
                            0.08
                        );
                        BossParticles::burst(
                            $world,
                            $point,
                            BossParticles::portalParticle(),
                            4,
                            0.5,
                            0.5
                        );
                        CustomParticleEffectManager::stackedRing(
                            $world,
                            $point,
                            1.7,
                            CustomParticleEffectManager::HYDRA_WARNING,
                            2,
                            14,
                            0.18,
                            48.0
                        );
                        CustomParticleEffectManager::column(
                            $world,
                            $point->add(0, 0.05, 0),
                            CustomParticleEffectManager::HYDRA_FROST,
                            1.5,
                            6,
                            48.0
                        );
                    }
                }
            ), $tick);
        }

        Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(
            static function () use ($boss, $world, $spikePoints, $impactParticle, $damage, $radius): void {
                if ($boss->isClosed() || !$boss->isAlive() || $boss->getWorld() !== $world) {
                    return;
                }

                $radiusSq = $radius * $radius;

                foreach ($spikePoints as $point) {
                    BossParticles::burst(
                        $world,
                        $point,
                        $impactParticle,
                        12,
                        0.75,
                        1.2
                    );
                    BossParticles::burst(
                        $world,
                        $point->add(0, 0.3, 0),
                        BossParticles::portalParticle(),
                        6,
                        0.6,
                        0.8
                    );
                    CustomParticleEffectManager::burst(
                        $world,
                        $point->add(0, 0.18, 0),
                        CustomParticleEffectManager::HYDRA_FROST,
                        18,
                        0.5,
                        0.75,
                        56.0
                    );
                    CustomParticleEffectManager::column(
                        $world,
                        $point->add(0, 0.08, 0),
                        CustomParticleEffectManager::HYDRA_VOID,
                        1.9,
                        7,
                        56.0
                    );
                }

                foreach ($world->getPlayers() as $player) {
                    if (!$player->isConnected() || !$player->isAlive() || $player->isSpectator()) {
                        continue;
                    }

                    foreach ($spikePoints as $point) {
                        if ($player->getPosition()->distanceSquared($point) > $radiusSq) {
                            continue;
                        }

                        $boss->dealBossTrueDamage($player, $damage);
                        $player->getEffects()->add(new EffectInstance(VanillaEffects::SLOWNESS(), 80, 1));
                        $boss->applyBossMotion($player, new Vector3(0, 0.34, 0));
                        break;
                    }
                }

                $boss->playAbilitySound("random.glass", 1.25, 0.55, 36);
            }
        ), 22);
    }

    /**
     * @return Vector3[]
     */
    private function collectSpikePoints(GlacialHydra $boss): array
    {
        $points = [];
        $seen = [];
        $limit = $boss->getEngagedPlayerCount(12.0) >= 4 ? 6 : 4;

        $target = $boss->getTarget();
        if ($target !== null) {
            $point = $target->getPosition()->asVector3();
            $points[] = $point;
            $seen[$this->pointKey($point)] = true;
        }

        foreach ($boss->getPlayersInRange(12) as $player) {
            $point = $player->getPosition()->asVector3();
            $key = $this->pointKey($point);
            if (isset($seen[$key])) {
                continue;
            }

            $points[] = $point;
            $seen[$key] = true;
            if (count($points) >= $limit) {
                break;
            }
        }

        if ($boss->getPhase() >= 3 && $points !== []) {
            $extra = [];
            foreach ($points as $point) {
                $offsetA = $point->add(1.8, 0, 0.9);
                $offsetB = $point->add(-1.4, 0, -1.2);

                foreach ([$offsetA, $offsetB] as $candidate) {
                    $key = $this->pointKey($candidate);
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $extra[] = $candidate;
                    $seen[$key] = true;
                }
            }

            $points = array_merge($points, array_slice($extra, 0, 4));
        }

        return $points;
    }

    private function pointKey(Vector3 $point): string
    {
        return ((int) round($point->x)) . ':' . ((int) round($point->z));
    }
}
