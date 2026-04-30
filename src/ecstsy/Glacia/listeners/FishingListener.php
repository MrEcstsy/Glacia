<?php

declare(strict_types=1);

namespace ecstsy\Glacia\listeners;

use ecstsy\Glacia\server\fishing\FishingManager;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\item\ItemTypeIds;

final class FishingListener implements Listener{

    public function onItemUse(PlayerItemUseEvent $event): void{
        $item = $event->getItem();
        if($item->getTypeId() !== ItemTypeIds::FISHING_ROD){
            return;
        }

        FishingManager::handleRodUse($event->getPlayer());
    }

    public function onItemHeld(PlayerItemHeldEvent $event): void{
        $player = $event->getPlayer();
        $newItem = $player->getInventory()->getItem($event->getSlot());
        if($newItem->getTypeId() === ItemTypeIds::FISHING_ROD){
            return;
        }

        FishingManager::despawnPlayerHook($player);
    }

    public function onInteract(PlayerInteractEvent $event): void{
        $item = $event->getItem();
        if($item->getTypeId() !== ItemTypeIds::FISHING_ROD){
            return;
        }

        FishingManager::handleRodUse($event->getPlayer());
    }

    public function onQuit(PlayerQuitEvent $event): void{
        FishingManager::clearPlayer($event->getPlayer());
    }
}

