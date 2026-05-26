<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\zones;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class ZoneMobBlaze extends ZoneMob
{
    public static function getNetworkTypeId(): string
    {
        return EntityIds::BLAZE;
    }

    protected function tickMovement(): void
    {
        parent::tickMovement();

        if (!$this->isAlive() || !$this->target instanceof \pocketmine\player\Player) {
            return;
        }

        $desiredY = $this->target->getPosition()->y + 1.15;
        $deltaY = $desiredY - $this->location->y;

        if ($deltaY > 1.1) {
            $this->motion->y = min(0.22, $this->motion->y + 0.07);
        } elseif ($deltaY > 0.35) {
            $this->motion->y = min(0.16, $this->motion->y + 0.04);
        } elseif ($deltaY < -1.4) {
            $this->motion->y = max(-0.10, $this->motion->y - 0.04);
        } else {
            $this->motion->y *= 0.84;
        }

        if ($this->isGroundedForStepHop() && ($this->shouldJumpAhead() || $this->requiresJumpTowardElevatedNearbyTarget())) {
            $this->motion->y = max($this->motion->y, $this->getAdaptiveJumpVelocity() * 0.78);
        }
    }
}
