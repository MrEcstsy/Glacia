<?php

declare(strict_types=1);

namespace ecstsy\Glacia\listeners;

use ecstsy\Glacia\entity\GlaciaSandFallingBlock;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\cannon\CannonRulesConfig;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\Sand;
use pocketmine\event\block\BlockExplodeEvent;
use pocketmine\entity\object\FallingBlock;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\entity\EntityExplodeEvent;
use pocketmine\event\entity\EntitySpawnEvent;
use pocketmine\event\Listener;
use pocketmine\item\Item;
use pocketmine\math\AxisAlignedBB;
use pocketmine\world\World;
use function abs;
use function floor;
use function is_array;
use function max;

/**
 * FallableTrait spawns {@see FallingBlock} directly (not via EntityFactory). Swap sand to {@see GlaciaSandFallingBlock}.
 */
final class SandFallingEntityListener implements Listener {
    private const BLOCK_DATA_ROOT = "glacia_sand_stack";
    private const BLOCK_DATA_COUNT = "count";

    public function onEntitySpawn(EntitySpawnEvent $event): void {
        $entity = $event->getEntity();
        if ($entity instanceof GlaciaSandFallingBlock) {
            return;
        }
        if (!$entity instanceof FallingBlock) {
            return;
        }
        if (!$entity->getBlock() instanceof Sand) {
            return;
        }

        $world = $entity->getWorld();

        $cx = ((int) floor($entity->getLocation()->x)) >> 4;
        $cz = ((int) floor($entity->getLocation()->z)) >> 4;
        if (CannonRulesConfig::enabled()) {
            $cap = CannonRulesConfig::getMaxFallingSandPerChunk();
            $n = 0;
            foreach ($world->getChunkEntities($cx, $cz) as $e) {
                if ($e instanceof GlaciaSandFallingBlock && !$e->isFlaggedForDespawn()) {
                    $n++;
                }
            }
            if ($n >= $cap) {
                return;
            }
        }

        $loc = $entity->getLocation();
        $block = $entity->getBlock();
        $motion = $entity->getMotion();
        $fallDistance = $entity->getFallDistance();
        $nbt = $entity->saveNBT();

        $entity->flagForDespawn();

        $replacement = new GlaciaSandFallingBlock($loc, $block, $nbt);
        $originX = (int) floor($loc->x);
        $originY = (int) floor($loc->y);
        $originZ = (int) floor($loc->z);
        $replacement->setStackedCount(self::takeStackedCountAt($world, $originX, $originY, $originZ));
        $replacement->setMotion($motion);
        $replacement->setFallDistance($fallDistance);
        $replacement->spawnToAll();
    }

    public function onBlockBreak(BlockBreakEvent $event): void {
        $block = $event->getBlock();
        $world = $block->getPosition()->getWorld();
        $x = $block->getPosition()->getFloorX();
        $y = $block->getPosition()->getFloorY();
        $z = $block->getPosition()->getFloorZ();

        if ($block->getTypeId() === BlockTypeIds::COBWEB) {
            $this->compressCobwebColumn($world, $x, $y, $z);
            return;
        }

        if ($block instanceof Sand) {
            $count = self::takeStackedCountAt($world, $x, $y, $z);
            if ($count <= 1) {
                return;
            }
            $event->setDrops(self::splitItemIntoStacks($block->asItem(), $count));
        }
    }

    public function onEntityExplode(EntityExplodeEvent $event): void {
        foreach ($event->getBlockList() as $block) {
            $pos = $block->getPosition();
            if (!$block instanceof Sand) {
                continue;
            }
            self::clearStoredStackedCountAt($pos->getWorld(), $pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
        }
    }

    public function onBlockExplode(BlockExplodeEvent $event): void {
        foreach ($event->getAffectedBlocks() as $block) {
            $pos = $block->getPosition();
            if (!$block instanceof Sand) {
                continue;
            }
            self::clearStoredStackedCountAt($pos->getWorld(), $pos->getFloorX(), $pos->getFloorY(), $pos->getFloorZ());
        }
    }

    private function compressCobwebColumn(World $world, int $x, int $y, int $z): void {
        $box = new AxisAlignedBB($x - 1.25, $y - 8, $z - 1.25, $x + 2.25, $y + 40, $z + 2.25);
        $cx = $x + 0.5;
        $cz = $z + 0.5;
        $keeper = null;
        $stackedTotal = 0;

        foreach ($world->getNearbyEntities($box) as $entity) {
            if (!$entity instanceof GlaciaSandFallingBlock) {
                continue;
            }
            $loc = $entity->getLocation();
            if (abs($loc->x - $cx) > 1.35 || abs($loc->z - $cz) > 1.35) {
                continue;
            }

            $stackedTotal += $entity->getStackedCount();
            if ($keeper === null) {
                $keeper = $entity;
                continue;
            }
            $entity->flagForDespawn();
        }

        // Also consume any persisted stacked counts from placed sand in this cobweb column.
        for ($yy = $y - 8; $yy <= $y + 40; ++$yy) {
            $block = $world->getBlockAt($x, $yy, $z);
            if (!$block instanceof Sand) {
                continue;
            }
            $stored = self::takeStoredStackedCountAt($world, $x, $yy, $z);
            if ($stored > 0) {
                $stackedTotal += $stored;
            }
        }

        if ($keeper !== null && $stackedTotal > 0) {
            $keeper->setStackedCount($stackedTotal);
        }
    }

    private static function takeStackedCountAt(World $world, int $x, int $y, int $z): int {
        $data = Loader::getBlockData()->getAt($world, $x, $y, $z);
        if (!is_array($data)) {
            return 1;
        }

        $root = $data[self::BLOCK_DATA_ROOT] ?? null;
        if (!is_array($root)) {
            return 1;
        }

        $count = max(1, (int) ($root[self::BLOCK_DATA_COUNT] ?? 1));
        unset($data[self::BLOCK_DATA_ROOT]);
        if ($data === []) {
            Loader::getBlockData()->removeAt($world, $x, $y, $z);
        } else {
            Loader::getBlockData()->setAt($world, $x, $y, $z, $data);
        }

        return $count;
    }

    private static function takeStoredStackedCountAt(World $world, int $x, int $y, int $z): int {
        $data = Loader::getBlockData()->getAt($world, $x, $y, $z);
        if (!is_array($data)) {
            return 0;
        }

        $root = $data[self::BLOCK_DATA_ROOT] ?? null;
        if (!is_array($root)) {
            return 0;
        }

        $count = max(1, (int) ($root[self::BLOCK_DATA_COUNT] ?? 1));
        unset($data[self::BLOCK_DATA_ROOT]);
        if ($data === []) {
            Loader::getBlockData()->removeAt($world, $x, $y, $z);
        } else {
            Loader::getBlockData()->setAt($world, $x, $y, $z, $data);
        }

        return $count;
    }

    private static function clearStoredStackedCountAt(World $world, int $x, int $y, int $z): void {
        $data = Loader::getBlockData()->getAt($world, $x, $y, $z);
        if (!is_array($data) || !isset($data[self::BLOCK_DATA_ROOT])) {
            return;
        }

        unset($data[self::BLOCK_DATA_ROOT]);
        if ($data === []) {
            Loader::getBlockData()->removeAt($world, $x, $y, $z);
        } else {
            Loader::getBlockData()->setAt($world, $x, $y, $z, $data);
        }
    }

    /**
     * @return list<Item>
     */
    private static function splitItemIntoStacks(Item $template, int $count): array {
        $remaining = max(1, $count);
        $maxStack = max(1, $template->getMaxStackSize());
        $drops = [];
        while ($remaining > 0) {
            $piece = clone $template;
            $piece->setCount(min($maxStack, $remaining));
            $drops[] = $piece;
            $remaining -= $piece->getCount();
        }
        return $drops;
    }
}
