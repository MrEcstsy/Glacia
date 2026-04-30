<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\mobs;

use ecstsy\Glacia\entity\SpawnerEntity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

class Ghast extends SpawnerEntity
{

    protected function getInitialSizeInfo() : EntitySizeInfo{
        return new EntitySizeInfo(2.2, 2.2);
    }

    public static function getNetworkTypeId() : string{
        return EntityIds::GHAST;
    }


    public function getName() : string{
        return "Ghast";
    }

    public function getDrops() : array{
        return [
            VanillaItems::GHAST_TEAR()->setCount(mt_rand(0, 1)),
        ];
    }

    public function getXpDropAmount() : int{
        return 5;
    }
}