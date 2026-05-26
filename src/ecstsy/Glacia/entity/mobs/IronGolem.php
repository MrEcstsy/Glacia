<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\mobs;

use ecstsy\Glacia\entity\SpawnerEntity;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

class IronGolem extends SpawnerEntity
{

    protected function getInitialSizeInfo() : EntitySizeInfo{
        return new EntitySizeInfo(2.9, 1.4);
    }

    public static function getNetworkTypeId() : string{
        return EntityIds::IRON_GOLEM;
    }

    public function getXpDropAmount() : int{
        return 8;
    }

    public function getName() : string{
        return "Iron Golem";
    }

    public function getDrops() : array{
        return [
            VanillaBlocks::IRON()->asItem()->setCount(mt_rand(1, 2)),
        ];
    }
}
