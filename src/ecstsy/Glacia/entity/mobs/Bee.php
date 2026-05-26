<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\mobs;

use ecstsy\Glacia\entity\SpawnerEntity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class Bee extends SpawnerEntity
{
    protected function getInitialSizeInfo() : EntitySizeInfo
    {
        return new EntitySizeInfo(0.6, 0.7);
    }

    public static function getNetworkTypeId() : string
    {
        return EntityIds::BEE;
    }

    public function getName() : string
    {
        return "Bee";
    }

    public function getXpDropAmount() : int
    {
        return 5;
    }

    public function getDrops() : array
    {
        return [
            (StringToItemParser::getInstance()->parse("honeycomb") ?? VanillaItems::SLIMEBALL())->setCount(mt_rand(1, 2)),
        ];
    }
}
