<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\mobs;

use ecstsy\Glacia\entity\SpawnerEntity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class Warden extends SpawnerEntity
{
    protected function getInitialSizeInfo() : EntitySizeInfo
    {
        return new EntitySizeInfo(2.9, 0.9);
    }

    public static function getNetworkTypeId() : string
    {
        return EntityIds::WARDEN;
    }

    public function getName() : string
    {
        return "Warden";
    }

    public function getXpDropAmount() : int
    {
        return 25;
    }

    public function getDrops() : array
    {
        return [
            (StringToItemParser::getInstance()->parse("echo_shard") ?? VanillaItems::ENDER_PEARL())->setCount(mt_rand(1, 2)),
        ];
    }
}
