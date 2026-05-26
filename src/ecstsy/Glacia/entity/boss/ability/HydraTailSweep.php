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
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\TextFormat as C;
use pocketmine\world\particle\BlockBreakParticle;

final class HydraTailSweep extends BossAbility
{
    public function __construct()
    {
        parent::__construct(170);
    }

    protected function checkConditions(BossEntity $boss): bool
    {
        return $boss instanceof GlacialHydra &&
            $boss->getPhase() >= 2 &&
            $boss->hasHeadActive(GlacialHydra::HEAD_RAGE) &&
            count($boss->getPlayersInRange(6.5)) > 0;
    }

    protected function onCast(BossEntity $boss): void
    {
        if (!$boss instanceof GlacialHydra) {
            return;
        }

        $world = $boss->getWorld();
        $center = $boss->getPosition()->asVector3()->add(0, 1.0, 0);
        $radius = 6.5 + min(1.0, max(0, $boss->getEngagedPlayerCount(10.0) - 1) * 0.25);
        $damage = match ($boss->getPhase()) {
            2 => 10.5,
            3 => 14.5,
            default => 8.5,
        } * $boss->getPressureDamageMultiplier();

        $boss->broadcastEntityAnimation("tail_sweep", "", "", 0.12);
        $boss->broadcastAbility(C::colorize("&r&cThe Hydra coils its tail for a crushing sweep!"), 36);
        $boss->playAbilitySound("mob.enderdragon.growl", 1.2, 0.85, 36);
        CustomParticleEffectManager::stackedRing(
            $world,
            $center,
            $radius * 0.92,
            CustomParticleEffectManager::HYDRA_WARNING,
            2,
            max(22, (int) ($radius * 3.5)),
            0.18,
            48.0
        );
        CustomParticleEffectManager::ringVariants(
            $world,
            $center,
            $radius * 0.74,
            [
                CustomParticleEffectManager::HYDRA_WARNING,
                CustomParticleEffectManager::HYDRA_WARNING_ARC,
            ],
            max(16, (int) ($radius * 2.4)),
            0.12,
            48.0
        );

        Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(
            static function () use ($boss, $world, $center, $damage, $radius): void {
                if ($boss->isClosed() || !$boss->isAlive() || $boss->getWorld() !== $world) {
                    return;
                }

                BossParticles::burst(
                    $world,
                    $center,
                    new BlockBreakParticle(VanillaBlocks::OBSIDIAN()),
                    14,
                    2.4,
                    0.8
                );
                CustomParticleEffectManager::burst(
                    $world,
                    $center->add(0, 0.18, 0),
                    CustomParticleEffectManager::HYDRA_FROST,
                    24,
                    1.9,
                    0.9,
                    56.0
                );
                CustomParticleEffectManager::stackedRing(
                    $world,
                    $center,
                    $radius,
                    CustomParticleEffectManager::HYDRA_WARNING,
                    2,
                    max(24, (int) ($radius * 3.6)),
                    0.20,
                    56.0
                );

                foreach ($boss->getPlayersInRange($radius) as $player) {
                    $delta = $player->getPosition()->subtractVector($boss->getPosition());
                    $length = sqrt(($delta->x * $delta->x) + ($delta->z * $delta->z));
                    if ($length <= 0.001) {
                        continue;
                    }

                    $boss->dealBossTrueDamage($player, $damage);
                    $boss->applyBossMotion($player, new Vector3(
                        ($delta->x / $length) * 0.95,
                        0.36,
                        ($delta->z / $length) * 0.95
                    ));
                }

                $boss->playAbilitySound("mob.enderdragon.hit", 1.3, 0.6, 36);
            }
        ), 10);
    }
}
