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
}
