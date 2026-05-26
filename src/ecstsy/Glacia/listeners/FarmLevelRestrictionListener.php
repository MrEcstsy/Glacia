<?php

declare(strict_types=1);

namespace ecstsy\Glacia\listeners;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\farming\FarmItemLoreHelper;
use ecstsy\Glacia\server\farming\FarmLevelRequirementManager;
use ecstsy\Glacia\server\collectionchests\HarvesterCropRegistry;
use pocketmine\block\CaveVines;
use pocketmine\block\SweetBerryBush;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityItemPickupEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\item\Fertilizer;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;

final class FarmLevelRestrictionListener implements Listener {

    /**
     * @priority HIGH
     */
    public function onFarmBlockBreak(BlockBreakEvent $event): void
    {
        if (!$this->restrictionEnabled()) {
            return;
        }

        $player = $event->getPlayer();
        $block = $event->getBlock();
        $family = HarvesterCropRegistry::getFarmFamilyForBlock($block);
        if ($family === null) {
            return;
        }

        if (FarmLevelRequirementManager::meetsRequirement($player, $family)) {
            return;
        }

        $event->cancel();
        FarmLevelRequirementManager::notifyRequirementFailed($player, $family);
    }

    /**
     * @priority NORMAL
     */
    public function onFarmBlockBreakLore(BlockBreakEvent $event): void
    {
        if (!$this->restrictionEnabled()) {
            return;
        }
        if ($event->isCancelled()) {
            return;
        }

        $block = $event->getBlock();
        if (!HarvesterCropRegistry::isHarvestFarmBlock($block)) {
            return;
        }

        $event->setDrops(FarmItemLoreHelper::applyToDrops($event->getDrops()));
    }

    /**
     * @priority HIGH
     */
    public function onFarmBlockPlace(BlockPlaceEvent $event): void
    {
        if (!$this->restrictionEnabled()) {
            return;
        }

        $player = $event->getPlayer();
        foreach ($event->getTransaction()->getBlocks() as [$x, $y, $z, $placed]) {
            $family = HarvesterCropRegistry::getFarmFamilyForBlock($placed);
            if ($family === null) {
                continue;
            }
            if (FarmLevelRequirementManager::meetsRequirement($player, $family)) {
                continue;
            }

            $event->cancel();
            FarmLevelRequirementManager::notifyRequirementFailed($player, $family);
            return;
        }
    }

    public function onBerryOrVinePick(PlayerInteractEvent $event): void
    {
        if (!$this->restrictionEnabled()) {
            return;
        }
        if ($event->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK) {
            return;
        }

        $player = $event->getPlayer();
        $item = $event->getItem();
        if ($item instanceof Fertilizer) {
            return;
        }

        $block = $event->getBlock();
        $family = null;
        if ($block instanceof SweetBerryBush) {
            if ($block->getBerryDropAmount() <= 0) {
                return;
            }
            $family = "sweet_berries";
        } elseif ($block instanceof CaveVines && $block->hasBerries()) {
            $family = "cave_vines";
        }

        if ($family === null) {
            return;
        }

        if (FarmLevelRequirementManager::meetsRequirement($player, $family)) {
            return;
        }

        $event->setUseBlock(false);
        FarmLevelRequirementManager::notifyRequirementFailed($player, $family);
    }

    public function onPickupFarmItem(EntityItemPickupEvent $event): void
    {
        if (!$this->restrictionEnabled()) {
            return;
        }

        $collector = $event->getEntity();
        if (!$collector instanceof Player) {
            return;
        }

        $event->setItem(FarmItemLoreHelper::applyToItem($event->getItem()));
    }

    public function onJoinRefreshLore(PlayerJoinEvent $event): void
    {
        if (!FarmLevelRequirementManager::isEnabled()) {
            return;
        }

        $player = $event->getPlayer();
        Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(static function() use ($player): void {
            if (!$player->isConnected()) {
                return;
            }

            $inv = $player->getInventory();
            foreach ($inv->getContents() as $slot => $stack) {
                if ($stack->isNull()) {
                    continue;
                }
                $updated = FarmItemLoreHelper::applyToItem($stack);
                if (!$updated->equalsExact($stack)) {
                    $inv->setItem($slot, $updated);
                }
            }
        }), 1);
    }

    private function restrictionEnabled(): bool
    {
        return FarmLevelRequirementManager::isEnabled();
    }

}
