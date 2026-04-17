<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\boss\ability;

use ecstsy\Glacia\entity\boss\BossAbility;
use ecstsy\Glacia\entity\boss\BossEntity;
use pocketmine\utils\TextFormat as C;

final class AstralMark extends BossAbility {

    public function __construct() {
        parent::__construct(180); 
    }

    protected function onCast(BossEntity $boss): void {
        $target = $boss->getTarget();
        if ($target === null) return;

        $boss->markPlayer($target, 80); 
        $boss->playAbilitySound("random.anvil_land", 0.8, 1.4);
        $target->sendActionBarMessage("§c§lYou are marked by Astral energy!");
        $boss->broadcastAbility(C::colorize("&r&l&5Astral Devourer &r&7has marked &d" . $target->getName() . "&r&7!"));
    }
}