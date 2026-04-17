<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\boss\ability;

use ecstsy\Glacia\entity\boss\BossAbility;
use ecstsy\Glacia\entity\boss\BossEntity;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\utils\TextFormat as C;

final class AstralFracture extends BossAbility {

    public function __construct()
    {
        parent::__construct(240);
    }

    protected function checkConditions(BossEntity $boss): bool
    {
        return $boss->hasTarget();
    }

    protected function onCast(BossEntity $boss): void
    {
        $boss->playAbilitySound("mob.wither.shoot", 1.0, 1.0);
        $boss->broadcastAbility(C::colorize("&r&5Astral energy fractures the ground!"));

        foreach ($boss->getPlayersInRange(5) as $player) {
            $damage = match ($boss->getPhase()) {
                1 => 4.0,
                2 => 6.5,
                3 => 9.0,
            };

            $player->attack(new EntityDamageEvent(
                $boss,
                EntityDamageEvent::CAUSE_MAGIC,
                $damage
            ));
        }
    }
}