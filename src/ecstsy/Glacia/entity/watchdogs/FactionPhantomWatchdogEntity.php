<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\watchdogs;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class FactionPhantomWatchdogEntity extends ChunkWatchdogEntity
{
    public static function getNetworkTypeId(): string
    {
        return EntityIds::PHANTOM;
    }

    protected function getDisplayName(): string
    {
        return "&5Faction Phantom";
    }
}
