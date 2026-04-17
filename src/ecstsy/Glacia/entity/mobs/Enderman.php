<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\mobs;

use ecstsy\Glacia\entity\SpawnerEntity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

class Enderman extends SpawnerEntity
{

    protected function getInitialSizeInfo() : EntitySizeInfo{
        return new EntitySizeInfo(2.9, 0.6);
    }

    public static function getNetworkTypeId() : string{
        return EntityIds::ENDERMAN;
    }


    public function getName() : string{
        return "Enderman";
    }

    public function getDrops() : array{
        return [
            VanillaItems::ENDER_PEARL()->setCount(mt_rand(0, 1)),
        ];
    }

    public function getXpDropAmount() : int{
        return 5;
    }
}