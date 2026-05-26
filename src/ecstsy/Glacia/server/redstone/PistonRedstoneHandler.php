<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\redstone;

use diamondgold\DummyItemsBlocks\block\Piston;
use diamondgold\DummyItemsBlocks\block\PistonExtensionHelper;
use diamondgold\DummyItemsBlocks\tile\DummyTile;
use diamondgold\DummyItemsBlocks\tile\TileNbtTagNames;
use pocketmine\block\Block;

/**
 * Drives {@link Piston} (DummyItemsBlocks) from Glacia redstone: extended while powered, retracted when not.
 * Matches the tile {@link TileNbtTagNames::State} values used by the block’s onInteract (0 = retracted, 2 = extended).
 */
final class PistonRedstoneHandler implements RedstoneReceiver {

    public function onRedstonePowerChange(Block $block, int $power): void {
        if (!$block instanceof Piston) {
            return;
        }

        $world = $block->getPosition()->getWorld();
        $pos = $block->getPosition();
        $block = $world->getBlockAt($pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
        if (!$block instanceof Piston) {
            return;
        }

        $powered = $power > 0;
        $tile = $world->getTile($pos);
        if (!$tile instanceof DummyTile) {
            return;
        }

        $nbt = $tile->saveNBT();
        $target = $powered ? 2 : 0;
        if ($nbt->getByte(TileNbtTagNames::State, 0) === $target) {
            return;
        }

        if ($powered) {
            if (!PistonExtensionHelper::applyExtend($block)) {
                return;
            }
        } else {
            PistonExtensionHelper::applyRetract($block);
        }

        $nbt->setByte(TileNbtTagNames::State, $target);
        $tile->readSaveData($nbt);
        $world->setBlock($block->getPosition(), $block, false);
        $world->notifyNeighbourBlockUpdate($pos);
    }
}
