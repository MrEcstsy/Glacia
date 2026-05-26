<?php

declare(strict_types=1);

namespace ecstsy\Glacia\listeners;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\redstone\RedstoneDispenserManager;
use ecstsy\Glacia\server\redstone\RedstoneManager;
use pocketmine\block\Button;
use pocketmine\block\Lever;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\BlockUpdateEvent;
use pocketmine\event\block\PressurePlateUpdateEvent;
use pocketmine\event\entity\EntityExplodeEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\world\ChunkLoadEvent;
use pocketmine\event\world\WorldUnloadEvent;
use pocketmine\math\Facing;
use pocketmine\scheduler\ClosureTask;

final class RedstoneListener implements Listener
{
    public function onPlace(BlockPlaceEvent $event): void
    {
        $manager = RedstoneManager::getInstance();
        foreach ($event->getTransaction()->getBlocks() as [$x, $y, $z, $block]) {
            if (!$manager->isRelevantBlock($block)) {
                continue;
            }

            $manager->queuePositionUpdate($block->getPosition(), true);
        }
    }

    public function onBreak(BlockBreakEvent $event): void
    {
        $block = $event->getBlock();
        $manager = RedstoneManager::getInstance();

        if (RedstoneDispenserManager::isDispensingBlock($block)) {
            RedstoneDispenserManager::clearLogicalPoweredBlock(
                $block->getPosition()->getWorld(),
                $block->getPosition()->getFloorX(),
                $block->getPosition()->getFloorY(),
                $block->getPosition()->getFloorZ()
            );
            RedstoneDispenserManager::queueDestroyCheck(
                $block->getPosition()->getWorld(),
                $block->getPosition()->getFloorX(),
                $block->getPosition()->getFloorY(),
                $block->getPosition()->getFloorZ(),
                1
            );
        }

        $manager->cleanupBlock($block);
        $manager->queuePositionUpdate($block->getPosition(), true);
    }

    public function onInteract(PlayerInteractEvent $event): void
    {
        if ($event->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK) {
            return;
        }

        $block = $event->getBlock();
        if (RedstoneDispenserManager::isDispensingBlock($block)) {
            $item = $event->getItem();
            if (
                ($event->getPlayer()->isSneaking() && $item->canBePlaced()) ||
                ($event->getFace() === Facing::UP && $item->canBePlaced())
            ) {
                $event->setUseBlock(false);
                return;
            }

            $event->cancel();
            RedstoneDispenserManager::open($event->getPlayer(), $block);
            return;
        }

        $manager = RedstoneManager::getInstance();
        if ($block instanceof Button) {
            $manager->activateButton($block);
            return;
        }

        if ($block instanceof Lever || $manager->isRelevantBlock($block)) {
            $manager->queuePositionUpdate($block->getPosition(), true);
        }
    }

    public function onPressurePlateUpdate(PressurePlateUpdateEvent $event): void
    {
        $manager = RedstoneManager::getInstance();
        $manager->queuePositionUpdate($event->getBlock()->getPosition(), true);
        $manager->schedulePressurePlateRefresh($event->getBlock()->getPosition());
    }

    public function onBlockUpdate(BlockUpdateEvent $event): void
    {
        $manager = RedstoneManager::getInstance();
        if (!$manager->isRelevantBlock($event->getBlock())) {
            return;
        }

        $manager->queuePositionUpdate($event->getBlock()->getPosition(), true);
    }

    public function onExplode(EntityExplodeEvent $event): void
    {
        $manager = RedstoneManager::getInstance();
        foreach ($event->getBlockList() as $block) {
            if (RedstoneDispenserManager::isDispensingBlock($block)) {
                RedstoneDispenserManager::clearLogicalPoweredBlock(
                    $block->getPosition()->getWorld(),
                    $block->getPosition()->getFloorX(),
                    $block->getPosition()->getFloorY(),
                    $block->getPosition()->getFloorZ()
                );
            }

            RedstoneDispenserManager::queueDestroyCheck(
                $block->getPosition()->getWorld(),
                $block->getPosition()->getFloorX(),
                $block->getPosition()->getFloorY(),
                $block->getPosition()->getFloorZ(),
                1
            );

            $manager->cleanupBlock($block);
            $manager->queuePositionUpdate($block->getPosition(), true);
        }
    }

    public function onWorldUnload(WorldUnloadEvent $event): void
    {
        RedstoneManager::getInstance()->cleanupWorld($event->getWorld());
    }

    /**
     * Wakes dispenser/dropper tiles only (cheap); wire/repeaters are reached via neighbour expansion from propagation.
     */
    public function onChunkLoad(ChunkLoadEvent $event): void
    {
        if ($event->isNewChunk()) {
            return;
        }

        $world = $event->getWorld();
        $chunkX = $event->getChunkX();
        $chunkZ = $event->getChunkZ();
        $delay = RedstoneManager::getInstance()->getChunkLoadDispenserWarmupDelayTicks();

        Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(
            static function () use ($world, $chunkX, $chunkZ): void {
                if (!$world->isLoaded() || !$world->isChunkLoaded($chunkX, $chunkZ)) {
                    return;
                }

                $chunk = $world->getChunk($chunkX, $chunkZ);
                if ($chunk === null) {
                    return;
                }

                RedstoneManager::enqueueDispenserWarmupForChunk($world, $chunk);
            }
        ), $delay);
    }
}
