<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\mobs;

use ecstsy\Glacia\entity\SpawnerEntity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class SnowGolem extends SpawnerEntity
{

    protected function getInitialSizeInfo(): EntitySizeInfo
    {
        return new EntitySizeInfo(1.9, 0.7);
    }

    public static function getNetworkTypeId(): string
    {
        return EntityIds::SNOW_GOLEM;
    }

    public function getName(): string
    {
        return "Snow Golem";
    }

    public function getDrops(): array
    {
        return [
            VanillaItems::SNOWBALL()->setCount(mt_rand(2, 6)),
        ];
    }

    public function getXpDropAmount(): int
    {
        return 5;
    }
}
