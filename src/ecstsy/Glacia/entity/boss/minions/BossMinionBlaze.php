<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\boss\minions;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class BossMinionBlaze extends BossMinion
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
    }
}
