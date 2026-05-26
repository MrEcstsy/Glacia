<?php

declare(strict_types=1);

namespace ecstsy\Glacia\listeners;

use ecstsy\Glacia\server\staff\StaffModeManager;
use ecstsy\Glacia\server\staff\VanishManager;
use ecstsy\Glacia\server\staff\HiddenStaffTargeting;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityItemPickupEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\QueryRegenerateEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\Inventory;
use pocketmine\inventory\PlayerCraftingInventory;
use pocketmine\inventory\PlayerCursorInventory;
use pocketmine\inventory\PlayerInventory;
use pocketmine\inventory\PlayerOffHandInventory;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\MobArmorEquipmentPacket;
use pocketmine\network\mcpe\protocol\MobEquipmentPacket;
use pocketmine\network\mcpe\protocol\PlayerListPacket;
use pocketmine\network\mcpe\protocol\SetActorDataPacket;
use pocketmine\player\Player;
use pocketmine\Server;

final class StaffModeListener implements Listener {

    public function onJoin(PlayerJoinEvent $event) : void {
        StaffModeManager::onPlayerJoinVisible($event->getPlayer());
        VanishManager::syncJoin($event->getPlayer());
    }

    public function onQuit(PlayerQuitEvent $event) : void {
        $player = $event->getPlayer();
        if (StaffModeManager::isInStaffMode($player)) {
            StaffModeManager::restoreSnapshotForDisconnect($player);
        }
        VanishManager::handleQuit($player);
    }

    public function onItemUse(PlayerItemUseEvent $event) : void {
        $player = $event->getPlayer();
        if (!StaffModeManager::isInStaffMode($player)) {
            return;
        }
        if (StaffModeManager::readToolId($event->getItem()) === null) {
            return;
        }
        StaffModeManager::handleToolUse($player, $event->getItem());
        $event->cancel();
    }

    public function onDrop(PlayerDropItemEvent $event) : void {
        if (StaffModeManager::isInStaffMode($event->getPlayer())) {
            $event->cancel();
        }
    }

    public function onTransaction(InventoryTransactionEvent $event) : void {
        $player = $event->getTransaction()->getSource();
        if (!StaffModeManager::isInStaffMode($player)) {
            return;
        }
        foreach ($event->getTransaction()->getInventories() as $inventory) {
            if (self::inventoryHeldByPlayer($inventory, $player)) {
                $event->cancel();
                return;
            }
        }
    }

    public function onBreak(BlockBreakEvent $event) : void {
        if (StaffModeManager::isInStaffMode($event->getPlayer())) {
            $event->cancel();
        }
    }

    public function onPlace(BlockPlaceEvent $event) : void {
        if (StaffModeManager::isInStaffMode($event->getPlayer())) {
            $event->cancel();
        }
    }

    public function onDamage(EntityDamageEvent $event) : void {
        $entity = $event->getEntity();
        if ($entity instanceof Player && HiddenStaffTargeting::isUntargetable($entity)) {
            $event->cancel();
            return;
        }
        if ($event instanceof EntityDamageByEntityEvent) {
            $damager = $event->getDamager();
            if ($damager instanceof Player && StaffModeManager::isInStaffMode($damager)) {
                $event->cancel();
            }
        }
    }

    public function onPickup(EntityItemPickupEvent $event) : void {
        $collector = $event->getEntity();
        if ($collector instanceof Player && (StaffModeManager::isInStaffMode($collector) || VanishManager::isVanished($collector))) {
            $event->cancel();
        }
    }

    public function onQueryRegenerate(QueryRegenerateEvent $event) : void {
        $visibleNames = [];
        $visibleCount = 0;
        foreach (Server::getInstance()->getOnlinePlayers() as $player) {
            if (VanishManager::isVanished($player)) {
                continue;
            }
            $visibleNames[] = $player->getName();
            ++$visibleCount;
        }
        $event->getQueryInfo()->setPlayerList($visibleNames);
        $event->getQueryInfo()->setPlayerCount($visibleCount);
    }

    public function onDataPacketSend(DataPacketSendEvent $event) : void {
        $targets = $event->getTargets();
        $out = [];
        foreach ($event->getPackets() as $packet) {
            if ($packet instanceof MobEquipmentPacket || $packet instanceof MobArmorEquipmentPacket || $packet instanceof SetActorDataPacket) {
                $entity = Server::getInstance()->getWorldManager()->findEntity($packet->actorRuntimeId);
                if ($entity instanceof Player && VanishManager::isVanished($entity)) {
                    $shouldSuppress = false;
                    foreach ($targets as $session) {
                        if (!$session instanceof NetworkSession) {
                            continue;
                        }
                        $viewer = $session->getPlayer();
                        if ($viewer !== $entity) {
                            $shouldSuppress = true;
                            break;
                        }
                    }
                    if ($shouldSuppress) {
                        continue;
                    }
                }
            }
            if ($packet instanceof PlayerListPacket && $packet->type === PlayerListPacket::TYPE_ADD && $packet->entries !== []) {
                $filtered = $packet->entries;
                foreach ($targets as $session) {
                    if (!$session instanceof NetworkSession) {
                        continue;
                    }
                    $viewer = $session->getPlayer();
                    $filtered = VanishManager::filterPlayerListAddEntries($filtered, $viewer);
                    if ($filtered === []) {
                        break;
                    }
                }
                if ($filtered === []) {
                    continue;
                }
                if (count($filtered) !== count($packet->entries)) {
                    $packet = PlayerListPacket::add($filtered);
                }
            }
            $out[] = $packet;
        }
        $event->setPackets($out);
    }

    private static function inventoryHeldByPlayer(Inventory $inventory, Player $player) : bool {
        if ($inventory instanceof PlayerInventory || $inventory instanceof PlayerCursorInventory || $inventory instanceof PlayerOffHandInventory || $inventory instanceof PlayerCraftingInventory) {
            return $inventory->getHolder() === $player;
        }
        if ($inventory instanceof ArmorInventory) {
            return $inventory->getHolder() === $player;
        }
        return false;
    }
}
