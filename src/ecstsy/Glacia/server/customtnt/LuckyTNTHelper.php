<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\customtnt;

use ecstsy\Glacia\blocks\tiles\SpawnerTile;
use ecstsy\Glacia\server\items\GlaciaItemFactory;
use ecstsy\Glacia\server\items\GlaciaSpawnerItem;
use pocketmine\block\MonsterSpawner;
use pocketmine\data\bedrock\LegacyEntityIdToStringIdMap;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\math\Vector3;
use pocketmine\world\Position;
use pocketmine\world\World;

final class LuckyTNTHelper {

    public static function dropSpawnersInRadius(Position $center, float $radius): void {
        $world = $center->getWorld();
        $seen = [];

        $minX = (int) floor($center->x - $radius);
        $maxX = (int) ceil($center->x + $radius);
        $minY = (int) floor($center->y - $radius);
        $maxY = (int) ceil($center->y + $radius);
        $minZ = (int) floor($center->z - $radius);
        $maxZ = (int) ceil($center->z + $radius);

        for ($x = $minX; $x <= $maxX; $x++) {
            for ($y = $minY; $y <= $maxY; $y++) {
                for ($z = $minZ; $z <= $maxZ; $z++) {
                    $key = $x . ":" . $y . ":" . $z;
                    if (isset($seen[$key])) {
                        continue;
                    }

                    $pos = new Position($x, $y, $z, $world);

                    if ($pos->distance($center) > $radius + 0.5) {
                        continue;
                    }

                    $block = $world->getBlock($pos);
                    if (!$block instanceof MonsterSpawner) {
                        continue;
                    }

                    $tile = $world->getTile($pos);
                    if (!$tile instanceof SpawnerTile) {
                        continue;
                    }

                    self::dropSpawnerStack($world, $pos, $tile);

                    $seen[$key] = true;
                }
            }
        }
    }

    private static function createSpawnerDrop(SpawnerTile $tile): Item {
        if ($tile->isZoneSpawner()) {
            return GlaciaSpawnerItem::createZoneSpawner($tile->getZoneId());
        }

        $legacyId = $tile->getLegacyEntityId();

        $entityString = LegacyEntityIdToStringIdMap::getInstance()->legacyToString($legacyId);

        if ($entityString === null || $entityString === ":") {
            return GlaciaSpawnerItem::MONSTER_SPAWNER()->asItem();
        }

        $entityString = str_replace("minecraft:", "", $entityString);

        $itemId = $entityString . "_spawner";

        $item = StringToItemParser::getInstance()->parse($itemId);

        return $item ?? GlaciaSpawnerItem::MONSTER_SPAWNER()->asItem();
    }

    private static function dropSpawnerStack(World $world, Position $pos, SpawnerTile $tile): void {
        $prototype = self::createSpawnerDrop($tile);
        $total = max(1, $tile->getStackCount());
        $maxStackSize = $prototype->getMaxStackSize();

        while ($total > 0) {
            $drop = clone $prototype;
            $drop->setCount(min($total, $maxStackSize));
            $world->dropItem($pos->add(0.5, 0.5, 0.5), $drop);
            $total -= $drop->getCount();
        }

        if ($tile->getAugmentCount() > 0) {
            $augment = GlaciaItemFactory::create("spawner_augment");
            $augment->setCount($tile->getAugmentCount());
            $world->dropItem($pos->add(0.5, 0.5, 0.5), $augment);
        }
    }
}
