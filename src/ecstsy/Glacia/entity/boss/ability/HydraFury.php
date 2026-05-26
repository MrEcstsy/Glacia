<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\boss\ability;

use ecstsy\Glacia\entity\boss\BossAbility;
use ecstsy\Glacia\entity\boss\BossEntity;
use ecstsy\Glacia\entity\boss\bosses\GlacialHydra;
use ecstsy\Glacia\utils\CustomParticleEffectManager;
use pocketmine\utils\TextFormat as C;

final class HydraFury extends BossAbility
{
    public function __construct()
    {
        parent::__construct(260);
    }

    protected function checkConditions(BossEntity $boss): bool
    {
        return $boss instanceof GlacialHydra &&
            $boss->hasHeadActive(GlacialHydra::HEAD_RAGE) &&
            $boss->getPhase() >= 2 &&
            !$boss->isOverloaded();
    }

    protected function onCast(BossEntity $boss): void
    {
        if (!$boss instanceof GlacialHydra) {
            return;
        }

        $duration = match ($boss->getPhase()) {
            2 => 100,
            3 => 140,
            default => 80,
        };
        $duration += min(40, max(0, $boss->getEngagedPlayerCount(14.0) - 1) * 10);

        $boss->triggerOverload($duration);
        $boss->broadcastEntityAnimation("rage_cast", "", "", 0.15);
        $boss->broadcastAbility(C::colorize("&r&cThe Hydra's rage head erupts into a frenzy!"), 36);
        $boss->playAbilitySound("mob.enderdragon.growl", 1.5, 0.7, 36);
        CustomParticleEffectManager::stackedRing(
            $boss->getWorld(),
            $boss->getPosition()->asVector3()->add(0, 0.15, 0),
            2.2,
            CustomParticleEffectManager::HYDRA_WARNING,
            3,
            18,
            0.18,
            48.0
        );
        CustomParticleEffectManager::column(
            $boss->getWorld(),
            $boss->getPosition()->asVector3()->add(0, 0.35, 0),
            CustomParticleEffectManager::HYDRA_FROST,
            2.1,
            8,
            48.0
        );
    }
}
