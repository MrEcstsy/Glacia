<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\boss\ability;

use ecstsy\Glacia\entity\boss\BossAbility;
use ecstsy\Glacia\entity\boss\BossEntity;

final class PermafrostAura extends BossAbility {

    public function __construct() {
        parent::__construct(); 
    }

    protected function checkConditions(BossEntity $boss): bool {
        return $boss->getPhase() === 2;
    }

    protected function onCast(BossEntity $boss): void {
        $boss->playAbilitySound("ambient.weather.rain", 1.0, 0.8);
    }
}