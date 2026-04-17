<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\mobs;

use ecstsy\Glacia\entity\SpawnerEntity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

class Spider extends SpawnerEntity
{

    protected function getInitialSizeInfo() : EntitySizeInfo{
        return new EntitySizeInfo(0.9, 0.9);
    }

    public static function getNetworkTypeId() : string{
        return EntityIds::SPIDER;
    }


    public function getName() : string{
        return "Spider";
    }

    public function getDrops() : array{
        return [
            VanillaItems::SPIDER_EYE()->setCount(mt_rand(1, 2)),
        ];
    }

    public function getXpDropAmount() : int{
        return 4;
    }
}