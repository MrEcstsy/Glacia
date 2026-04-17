<?php

declare(strict_types=1);

namespace ecstsy\Glacia\managers;

use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class TPAManager {

    /** @var array<string, array> */
    private static array $requests = [];

    private const REQUEST_TIMEOUT = 30; 

    private static function isRequestExpired(array $req): bool {
        return (time() - $req['time']) > self::REQUEST_TIMEOUT;
    }

    public static function sendTPA(Player $sender, Player $target, string $type = "tpa"): void {
        self::$requests[strtolower($target->getName())] = [
            "type" => $type,
            "sender" => $sender->getName(),
            "target" => $target->getName(),
            "time" => time()
        ];
        $target->sendMessage(C::colorize("&r&b&l[TPA] &r&f{$sender->getName()} has requested to teleport " . ($type === "tpahere" ? 'you to them,' : 'to you.') . " Use &b/tpaccept {$sender->getName()} &for &b/tpdeny {$sender->getName()}&f."));
        $sender->sendMessage(C::colorize("&r&b&l[TPA] &r&fTeleport request sent to {$target->getName()}."));
    }

    public static function acceptTPA(Player $target, Player $sender): void {
        $key = strtolower($target->getName());

        if (!isset(self::$requests[$key]) || strtolower(self::$requests[$key]['sender']) !== strtolower($sender->getName())) {
            $target->sendMessage(C::colorize("&r&cError: &4No teleport request found from {$sender->getName()}."));
            return;
        }

        $req = self::$requests[$key];

        if (self::isRequestExpired($req)) {
            unset(self::$requests[$key]);
            $target->sendMessage(C::colorize("&r&cError: &4Teleport request from {$sender->getName()} has expired."));
            $sender->sendMessage(C::colorize("&r&cError: &4Your teleport request to {$target->getName()} has expired."));
            return;
        }

        unset(self::$requests[$key]);

        if ($req["type"] === "tpahere") {
            GlaciaUtils::timedTeleport($target, $sender->getPosition()->asVector3());
            $sender->sendMessage(C::colorize("&r&b&l[/TPAHERE] &r&f{$target->getName()} has accepted your teleport request."));
        } else {
            GlaciaUtils::timedTeleport($sender, $target->getPosition()->asVector3());
            $sender->sendMessage(C::colorize("&r&b&l[/TPA] &r&f{$target->getName()} has accepted your teleport request."));
        }
    }

    public static function denyTPA(Player $target, Player $sender): void {
        $key = strtolower($target->getName());

        if (!isset(self::$requests[$key]) || strtolower(self::$requests[$key]['sender']) !== strtolower($sender->getName())) {
            $target->sendMessage(C::colorize("&r&cError: &4No teleport request found from {$sender->getName()}."));
            return;
        }

        $req = self::$requests[$key];
        if (self::isRequestExpired($req)) {
            unset(self::$requests[$key]);
            $target->sendMessage(C::colorize("&r&cError: &4Teleport request from {$sender->getName()} has expired."));
            $sender->sendMessage(C::colorize("&r&cError: &4Your teleport request to {$target->getName()} has expired."));
            return;
        }

        unset(self::$requests[$key]);

        $target->sendMessage(C::colorize("&r&b&l[TPA] &r&fYou have denied the teleport request from {$sender->getName()}."));
        $sender->sendMessage(C::colorize("&r&b&l[TPA] &r&fYour teleport request to {$target->getName()} has been denied."));
    }

    public static function hasPendingRequest(Player $target, Player $sender): bool {
        $key = strtolower($target->getName());
        if (!isset(self::$requests[$key]) || strtolower(self::$requests[$key]["sender"]) !== strtolower($sender->getName())) {
            return false;
        }
        if (self::isRequestExpired(self::$requests[$key])) {
            unset(self::$requests[$key]);
            return false;
        }
        return true;
    }
}