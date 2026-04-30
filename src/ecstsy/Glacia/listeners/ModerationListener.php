<?php

declare(strict_types=1);

namespace ecstsy\Glacia\listeners;

use ecstsy\Glacia\server\staff\ModerationManager;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\server\CommandEvent;
use pocketmine\utils\TextFormat as C;

final class ModerationListener implements Listener {
    public function onMove(PlayerMoveEvent $event): void {
        $player = $event->getPlayer();
        $mgr = ModerationManager::getInstance();
        $entry = $mgr->getFreezeEntry($player->getUniqueId()->toString());
        if ($entry === null) {
            return;
        }
        $from = $event->getFrom();
        $to = $event->getTo();
        if ($to === null) {
            return;
        }
        if ($from->getFloorX() === $to->getFloorX() && $from->getFloorY() === $to->getFloorY() && $from->getFloorZ() === $to->getFloorZ()) {
            return;
        }
        $event->cancel();
        $until = isset($entry["until"]) && is_int($entry["until"]) ? (int) $entry["until"] : 0;
        $timeText = $until > 0 ? (" until " . date("g:i A", $until)) : "";
        $player->sendPopup(C::colorize("&r&cYou are frozen{$timeText}."));
    }

    public function onCommand(CommandEvent $event): void {
        $sender = $event->getSender();
        if (!$sender instanceof \pocketmine\player\Player) {
            return;
        }
        $player = $sender;
        $mgr = ModerationManager::getInstance();
        if ($mgr->getFreezeEntry($player->getUniqueId()->toString()) === null) {
            return;
        }
        $raw = strtolower(trim($event->getCommand()));
        $msg = ltrim($raw, "/");
        if (str_starts_with($msg, "msg ") || str_starts_with($msg, "r ") || str_starts_with($msg, "reply ") || str_starts_with($msg, "report ")) {
            return;
        }
        if ($msg === "help" || str_starts_with($msg, "rules")) {
            return;
        }
        $event->cancel();
        $player->sendMessage(C::colorize("&r&cYou are frozen. Only messaging/report commands are allowed."));
    }
}

