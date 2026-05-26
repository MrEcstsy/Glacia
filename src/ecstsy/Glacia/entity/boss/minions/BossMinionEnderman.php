<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\boss\minions;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class BossMinionEnderman extends BossMinion
{
    public static function getNetworkTypeId(): string
    {
        return EntityIds::ENDERMAN;
    }
}
