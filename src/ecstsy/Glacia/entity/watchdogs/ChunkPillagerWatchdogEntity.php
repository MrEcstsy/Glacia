<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\watchdogs;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class ChunkPillagerWatchdogEntity extends ChunkWatchdogEntity
{
    public static function getNetworkTypeId(): string
    {
        return EntityIds::PILLAGER;
    }

    protected function getDisplayName(): string
    {
        return "&8Chunk Pillager";
    }
}
