<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\mobs;

use ecstsy\Glacia\entity\SpawnerEntity;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class Sheep extends SpawnerEntity
{

    protected function getInitialSizeInfo() : EntitySizeInfo{
        return new EntitySizeInfo(1.3, 0.9);
    }

    public static function getNetworkTypeId() : string{
        return EntityIds::SHEEP;
    }


    public function getName() : string{
        return "Sheep";
    }

    public function getXpDropAmount() : int{
        return 4;
    }

    public function getDrops() : array{
        return [
            VanillaBlocks::WOOL()->asItem()->setCount(mt_rand(1, 2)),
            VanillaItems::RAW_MUTTON()->setCount(mt_rand(0, 1)),
        ];
    }
}