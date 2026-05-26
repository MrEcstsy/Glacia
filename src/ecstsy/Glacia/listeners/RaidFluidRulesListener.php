<?php

declare(strict_types=1);

namespace ecstsy\Glacia\listeners;

use pocketmine\block\Lava;
use pocketmine\event\block\BlockExplodeEvent;
use pocketmine\event\entity\EntityExplodeEvent;
use pocketmine\event\Listener;
use pocketmine\world\World;

/**
 * Raid/cannon fluid conventions: lava survives TNT blasts.
 * Water/lava spread behavior is left vanilla.
 */
final class RaidFluidRulesListener implements Listener {

    public function onEntityExplode(EntityExplodeEvent $event): void {
        if ($event->isCancelled()) {
            return;
        }

        $out = [];
        foreach ($event->getBlockList() as $block) {
            if ($block instanceof Lava) {
                continue;
            }
            $p = $block->getPosition();
            $out[World::blockHash($p->getFloorX(), $p->getFloorY(), $p->getFloorZ())] = $block;
        }
        $event->setBlockList(array_values($out));
    }

    public function onBlockExplode(BlockExplodeEvent $event): void {
        if ($event->isCancelled()) {
            return;
        }
        $out = [];
        foreach ($event->getAffectedBlocks() as $block) {
            if ($block instanceof Lava) {
                continue;
            }
            $p = $block->getPosition();
            $out[World::blockHash($p->getFloorX(), $p->getFloorY(), $p->getFloorZ())] = $block;
        }
        $event->setAffectedBlocks(array_values($out));
    }
}
