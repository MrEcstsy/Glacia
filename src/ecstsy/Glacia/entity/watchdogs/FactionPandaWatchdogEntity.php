<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\watchdogs;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class FactionPandaWatchdogEntity extends ChunkWatchdogEntity
{
    public static function getNetworkTypeId(): string
    {
        return EntityIds::PANDA;
    }

    protected function getDisplayName(): string
    {
        return "&aFaction Panda";
    }
}
