<?php

declare(strict_types=1);

namespace ecstsy\Glacia\listeners;

use ecstsy\Glacia\server\staff\anticheat\AntiCheatManager;
use pocketmine\event\Listener;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\AnimatePacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\SetActorMotionPacket;
use pocketmine\player\Player;

final class AntiCheatListener implements Listener
{
    public function onJoin(PlayerJoinEvent $event): void
    {
        AntiCheatManager::getInstance()->handleJoin($event->getPlayer());
    }

    public function onQuit(PlayerQuitEvent $event): void
    {
        AntiCheatManager::getInstance()->handleQuit($event->getPlayer());
    }

    public function onTeleport(EntityTeleportEvent $event): void
    {
        $entity = $event->getEntity();
        if ($entity instanceof Player) {
            AntiCheatManager::getInstance()->handleTeleport($entity);
        }
    }

    public function onRespawn(PlayerRespawnEvent $event): void
    {
        AntiCheatManager::getInstance()->handleTeleport($event->getPlayer());
    }

    public function onDataPacketReceive(DataPacketReceiveEvent $event): void
    {
        $player = $event->getOrigin()->getPlayer();
        if (!$player instanceof Player) {
            return;
        }

        $packet = $event->getPacket();
        AntiCheatManager::getInstance()->observeInboundPacket($player);

        if ($packet instanceof AnimatePacket
            && $packet->actorRuntimeId === $player->getId()
            && ($packet->action === AnimatePacket::ACTION_SWING_ARM)
        ) {
            AntiCheatManager::getInstance()->recordArmSwing($player);
        }

        if ($packet instanceof PlayerAuthInputPacket) {
            AntiCheatManager::getInstance()->handleAuthInput($player, $packet);
        }
    }

    public function onDataPacketSend(DataPacketSendEvent $event): void
    {
        foreach ($event->getTargets() as $target) {
            $player = $target->getPlayer();
            if (!$player instanceof Player) {
                continue;
            }

            foreach ($event->getPackets() as $packet) {
                if ($packet instanceof SetActorMotionPacket && $packet->actorRuntimeId === $player->getId()) {
                    AntiCheatManager::getInstance()->handleMotionSent($player, $packet->motion);
                }
            }
        }
    }
}
