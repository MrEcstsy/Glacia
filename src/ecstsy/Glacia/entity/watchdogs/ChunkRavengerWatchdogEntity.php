<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\watchdogs;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class ChunkRavengerWatchdogEntity extends ChunkWatchdogEntity
{
    public static function getNetworkTypeId(): string
    {
        return EntityIds::RAVAGER;
    }

    protected function getDisplayName(): string
    {
        return "&cChunk Ravenger";
    }
}
