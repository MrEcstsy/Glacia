<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\watchdogs;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class ChunkLlamaWatchdogEntity extends ChunkWatchdogEntity
{
    public static function getNetworkTypeId(): string
    {
        return EntityIds::LLAMA;
    }

    protected function getDisplayName(): string
    {
        return "&dChunk Llama";
    }
}
