<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\mobs;

use ecstsy\Glacia\entity\SpawnerEntity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

class MagmaCube extends SpawnerEntity
{

    protected function getInitialSizeInfo() : EntitySizeInfo{
        return new EntitySizeInfo(0.51, 0.51);
    }

    public static function getNetworkTypeId() : string{
        return EntityIds::MAGMA_CUBE;
    }


    public function getName() : string{
        return "Magma Cube";
    }

    public function getDrops() : array{
        return [
            VanillaItems::MAGMA_CREAM()->setCount(mt_rand(0, 1)),
        ];
    }

    public function getXpDropAmount() : int{
        return 1;
    }
}