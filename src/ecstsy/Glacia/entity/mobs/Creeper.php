<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\mobs;

use ecstsy\Glacia\entity\SpawnerEntity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

class Creeper extends SpawnerEntity
{

    protected function getInitialSizeInfo() : EntitySizeInfo{
        return new EntitySizeInfo(1.8, 0.6);
    }

    public static function getNetworkTypeId() : string{
        return EntityIds::CREEPER;
    }


    public function getName() : string{
        return "Creeper";
    }

    public function getDrops() : array{
        return [
            VanillaItems::GUNPOWDER()->setCount(mt_rand(0, 2)),
        ];
    }

    public function getXpDropAmount() : int{
        return 5;
    }
}