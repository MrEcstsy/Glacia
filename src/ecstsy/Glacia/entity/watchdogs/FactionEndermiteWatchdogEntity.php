<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\watchdogs;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class FactionEndermiteWatchdogEntity extends ChunkWatchdogEntity
{
    public static function getNetworkTypeId(): string
    {
        return EntityIds::ENDERMITE;
    }

    protected function getDisplayName(): string
    {
        return "&5Faction Endermite";
    }
}
