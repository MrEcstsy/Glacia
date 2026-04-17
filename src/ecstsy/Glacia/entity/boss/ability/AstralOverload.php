<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\boss\ability;

use ecstsy\Glacia\entity\boss\BossAbility;
use ecstsy\Glacia\entity\boss\BossEntity;

final class AstralOverload extends BossAbility {

    public function __construct() {
        parent::__construct(999999); 
    }

    protected function checkConditions(BossEntity $boss): bool {
        return $boss->getPhase() === 3;
    }

    protected function onCast(BossEntity $boss): void {
        $boss->triggerOverload(100);
        $boss->playAbilitySound("mob.enderdragon.growl", 1.5, 0.6);
    }
}
