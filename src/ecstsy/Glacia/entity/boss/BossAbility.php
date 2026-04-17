<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\boss;

abstract class BossAbility {

    protected int $cooldownTicks;
    protected int $lastCast = 0;

    public function __construct(int $cooldownTicks)
    {
        $this->cooldownTicks = $cooldownTicks;
    }

    public function canCast(BossEntity $boss): bool {
        $tick = $boss->getBossTick();

        return $tick - $this->lastCast >= $this->cooldownTicks && $this->checkConditions($boss);
    }

    public function cast(BossEntity $boss): void {
        $this->lastCast = $boss->getBossTick();
        $this->onCast($boss);
    }

    abstract protected function onCast(BossEntity $boss): void;

    protected function checkConditions(BossEntity $boss): bool {
        return true; 
    }
}