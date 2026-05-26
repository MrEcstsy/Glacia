<?php

declare(strict_types=1);

namespace ecstsy\Glacia\listeners;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\collectionchests\CollectionChestManager;
use ecstsy\Glacia\server\collectionchests\HarvesterCropRegistry;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\entity\ItemSpawnEvent;
use pocketmine\event\Listener;
use pocketmine\item\Item;

final class HarvesterChestCollectorListener implements Listener {

    /**
     * After other plugins adjust drops / XP (fortune etc.), pull farm output into the chunk harvester chest.
     *
     * @priority LOW
     */
    public function onBlockBreak(BlockBreakEvent $event): void
    {
        if ($event->isCancelled()) {
            return;
        }

        $block = $event->getBlock();
        if (!HarvesterCropRegistry::isHarvestFarmBlock($block)) {
            return;
        }

        $drops = $event->getDrops();
        $xp = $event->getXpDropAmount();
        if ($drops === [] && $xp <= 0) {
            return;
        }

        $pos = $block->getPosition();
        if (!Loader::getCollectionChestManager()->getChunkChestAt($pos, CollectionChestManager::CHEST_TYPE_HARVESTER)) {
            return;
        }

        $toStore = [];
        foreach ($drops as $drop) {
            if ($drop instanceof Item && !$drop->isNull() && $drop->getCount() > 0) {
                $toStore[] = clone $drop;
            }
        }

        if ($toStore === [] && $xp <= 0) {
            return;
        }

        if (!Loader::getCollectionChestManager()->addDropsToChunk($pos, $toStore, $xp, CollectionChestManager::CHEST_TYPE_HARVESTER)) {
            return;
        }

        $event->setDrops([]);
        $event->setXpDropAmount(0);
    }

    /**
     * Breaks with no player (e.g. sugar cane losing water) do not fire {@see BlockBreakEvent}; vacuum loose farm items instead.
     */
    public function onItemSpawn(ItemSpawnEvent $event): void
    {
        $entity = $event->getEntity();
        $item = $entity->getItem();
        if (!HarvesterCropRegistry::shouldVacuumLooseFarmItem($entity, $item)) {
            return;
        }

        $pos = $entity->getPosition();
        if (!Loader::getCollectionChestManager()->getChunkChestAt($pos, CollectionChestManager::CHEST_TYPE_HARVESTER)) {
            return;
        }

        if (!Loader::getCollectionChestManager()->addDropsToChunk($pos, [clone $item], 0, CollectionChestManager::CHEST_TYPE_HARVESTER)) {
            return;
        }

        $entity->flagForDespawn();
    }
}
