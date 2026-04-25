<?php

declare(strict_types=1);

namespace ecstsy\Glacia\listeners;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\zones\ZoneManager;
use ecstsy\Glacia\server\zones\ZoneMobManager;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\item\ItemTypeIds;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class ZoneListener implements Listener {

    /** @var array<string, string|null> */
    private array $playerZones = [];
    /** @var array<string, int> */
    private array $denyMessageCooldowns = [];

    public function onPlayerMove(PlayerMoveEvent $event) : void
    {
        $from = $event->getFrom();
        $to = $event->getTo();
        if (
            $to === null ||
            (
                $from->getFloorX() === $to->getFloorX() &&
                $from->getFloorY() === $to->getFloorY() &&
                $from->getFloorZ() === $to->getFloorZ() &&
                $from->getWorld()->getFolderName() === $to->getWorld()->getFolderName()
            )
        ) {
            return;
        }

        $player = $event->getPlayer();
        $fromZone = ZoneManager::getZoneAt($from);
        $toZone = ZoneManager::getZoneAt($to);

        if ($fromZone !== null && ($toZone["id"] ?? null) !== ($fromZone["id"] ?? null)) {
            $event->cancel();
            $this->sendBoundaryMessage($player, $fromZone);
            return;
        }

        if ($toZone !== null && ($fromZone["id"] ?? null) !== ($toZone["id"] ?? null) && !ZoneManager::canPlayerAccess($player, $toZone)) {
            $event->cancel();
            $this->sendLockedMessage($player, $toZone);
            return;
        }

        $this->syncZoneState($player, $toZone);
    }

    public function onTeleport(EntityTeleportEvent $event) : void
    {
        $entity = $event->getEntity();
        if (!$entity instanceof Player) {
            return;
        }

        if ($entity->hasPermission("glacia.staff")) {
            $this->syncZoneState($entity, ZoneManager::getZoneAt($event->getTo()));
            return;
        }

        $fromZone = ZoneManager::getZoneAt($event->getFrom());
        $toZone = ZoneManager::getZoneAt($event->getTo());
        if ($fromZone !== null && ($toZone["id"] ?? null) !== ($fromZone["id"] ?? null) && !ZoneManager::consumeBoundaryCrossingBypass($entity)) {
            $event->cancel();
            $this->sendBoundaryMessage($entity, $fromZone);
            return;
        }

        if ($toZone !== null && !ZoneManager::canPlayerAccess($entity, $toZone)) {
            $event->cancel();
            $this->sendLockedMessage($entity, $toZone);
            return;
        }

        $this->syncZoneState($entity, $toZone);
    }

    public function onItemUse(PlayerItemUseEvent $event) : void
    {
        $player = $event->getPlayer();
        $zone = ZoneManager::getZoneAt($player->getPosition());
        if ($zone === null || $event->getItem()->getTypeId() !== ItemTypeIds::ENDER_PEARL) {
            return;
        }

        $event->cancel();
        $this->sendBoundaryMessage($player, $zone, "&r&cEnder pearls are disabled inside zones.");
    }

    public function onQuit(PlayerQuitEvent $event) : void
    {
        $key = $event->getPlayer()->getUniqueId()->toString();
        unset($this->playerZones[$key], $this->denyMessageCooldowns[$key]);
        ZoneMobManager::clearPlayerState($event->getPlayer());
    }

    private function syncZoneState(Player $player, ?array $zone) : void
    {
        $key = $player->getUniqueId()->toString();
        $zoneId = $zone["id"] ?? null;
        if (($this->playerZones[$key] ?? null) === $zoneId) {
            return;
        }

        $this->playerZones[$key] = $zoneId;
        ZoneMobManager::handleZoneState($player, $zoneId);
        if ($zone !== null) {
            $player->sendActionBarMessage(C::colorize("&r&a>> Entered &f" . (string) $zone["display"]));
        }
    }

    private function sendLockedMessage(Player $player, array $zone) : void
    {
        $key = $player->getUniqueId()->toString();
        $now = time();
        if (($this->denyMessageCooldowns[$key] ?? 0) > $now) {
            return;
        }

        $this->denyMessageCooldowns[$key] = $now + 2;
        $session = Loader::getPlayerManager()->getSession($player);
        $level = $session?->getLevel() ?? 0;
        $player->sendMessage(C::colorize(
            "&r&c" . (string) $zone["display"] . " &cis locked. Required level: &f" .
            number_format(ZoneManager::getUnlockRequirement($zone)) . "&c. Your level: &f" . number_format($level) . "&c."
        ));
        PlayerUtils::playSound($player, "note.bass");
    }

    private function sendBoundaryMessage(Player $player, array $zone, ?string $message = null) : void
    {
        $key = $player->getUniqueId()->toString() . ":boundary";
        $now = time();
        if (($this->denyMessageCooldowns[$key] ?? 0) > $now) {
            return;
        }

        $this->denyMessageCooldowns[$key] = $now + 2;
        $player->sendActionBarMessage(C::colorize($message ?? "&r&cYou cannot leave &f" . (string) $zone["display"] . "&r&c from here."));
        PlayerUtils::playSound($player, "note.bass");
    }
}
