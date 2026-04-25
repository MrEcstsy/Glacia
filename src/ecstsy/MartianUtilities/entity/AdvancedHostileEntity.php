<?php

declare(strict_types=1);

namespace ecstsy\MartianUtilities\entity;

use pocketmine\math\Vector3;
use pocketmine\player\Player;

abstract class AdvancedHostileEntity extends BaseHostileEntity
{
    protected int $aiInterval = 1;

    protected function updateAI(): void
    {
        parent::updateAI();

        if ($this->target === null || !$this->target->isAlive()) {
            return;
        }

        if ($this->target->getPosition()->distanceSquared($this->getPosition()) <= 16.0) {
            $this->pathCooldown = min($this->pathCooldown, 2);
        }
    }

    protected function tickMovement(): void
    {
        parent::tickMovement();

        if ($this->target === null || !$this->isAlive()) {
            return;
        }

        $distanceSq = $this->getPosition()->distanceSquared($this->target->getPosition());
        $dir = $this->buildMovementToward($this->target->getPosition(), $this->getMoveSpeed());

        if ($distanceSq <= 25.0 && $distanceSq >= 4.0 && mt_rand(1, 100) <= 35) {
            $this->strafe($dir, $this->getMoveSpeed());
        }

        if (($this->target->getPosition()->y - $this->getPosition()->y) > 0.8 && $this->onGround) {
            $this->jump();
            $this->pathCooldown = 0;
        }

        if ($this->stuckTicks >= 4 && $this->onGround) {
            $this->jump();
            $this->pathCooldown = 0;
        }
    }

    protected function getTargetRange(): float
    {
        return 22.0;
    }

    protected function getMoveSpeed(): float
    {
        return 0.25;
    }
}
