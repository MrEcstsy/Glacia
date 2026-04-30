<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\boss\ability;

use ecstsy\Glacia\entity\boss\BossAbility;
use ecstsy\Glacia\entity\boss\BossEntity;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\utils\TextFormat as C;

final class AstralPull extends BossAbility {

    public function __construct() {
        parent::__construct(200); 
    }

    protected function onCast(BossEntity $boss): void {
        foreach ($boss->getPlayersInRange(10) as $player) {
            $dir = $boss->getPosition()
                ->subtractVector($player->getPosition())
                ->normalize();

            $force = match ($boss->getPhase()) {
                1 => 0.35,
                2 => 0.6,
                3 => 0.9,
            };

            $boss->applyBossMotion($player, new Vector3(
                $dir->x * $force,
                0.25,
                $dir->z * $force
            ));

            $player->attack(new EntityDamageByEntityEvent(
                $boss,
                $player,
                EntityDamageEvent::CAUSE_MAGIC,
                match ($boss->getPhase()) {
                    1 => 3.0,
                    2 => 5.0,
                    3 => 7.0,
                },
                [],
                0.0,
                0.0
            ));

            $boss->playAbilitySound("mob.endermen.portal", 1.2, 0.7);
            $boss->broadcastAbility(C::colorize("&r&l&5Astral Devourer bends space around itself!"));
        }
    }

}
