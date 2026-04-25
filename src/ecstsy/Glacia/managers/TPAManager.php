<?php

declare(strict_types=1);

namespace ecstsy\Glacia\managers;

use ecstsy\Glacia\server\zones\ZoneManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class TPAManager {

    /** @var array<string, array{type: string, sender: string, target: string, time: int}> */
    private static array $requests = [];

    private const REQUEST_TIMEOUT = 30; 

    private static function isRequestExpired(array $req): bool {
        return (time() - $req['time']) > self::REQUEST_TIMEOUT;
    }

    public static function sendTPA(Player $sender, Player $target, string $type = "tpa"): bool {
        $targetKey = strtolower($target->getName());
        $activeRequest = self::getActiveRequestForTarget($target);
        if ($activeRequest !== null && strtolower($activeRequest["sender"]) !== strtolower($sender->getName())) {
            $sender->sendMessage(C::colorize("&r&cThat player already has a pending teleport request."));
            return false;
        }

        self::$requests[$targetKey] = [
            "type" => $type,
            "sender" => $sender->getName(),
            "target" => $target->getName(),
            "time" => time()
        ];
        $target->sendMessage(C::colorize("&r&b&l[TPA] &r&f{$sender->getName()} has requested to teleport " . ($type === "tpahere" ? 'you to them,' : 'to you.') . " Use &b/tpaccept {$sender->getName()} &for &b/tpdeny {$sender->getName()}&f."));
        $sender->sendMessage(C::colorize("&r&b&l[TPA] &r&fTeleport request sent to {$target->getName()}."));
        return true;
    }

    public static function acceptTPA(Player $target, Player $sender): void {
        $key = strtolower($target->getName());

        if (!isset(self::$requests[$key]) || strtolower(self::$requests[$key]['sender']) !== strtolower($sender->getName())) {
            $target->sendMessage(C::colorize("&r&cNo teleport request from &f{$sender->getName()}&r&c was found."));
            return;
        }

        $req = self::$requests[$key];

        if (self::isRequestExpired($req)) {
            unset(self::$requests[$key]);
            $target->sendMessage(C::colorize("&r&cThe teleport request from &f{$sender->getName()}&r&c has expired."));
            $sender->sendMessage(C::colorize("&r&cYour teleport request to &f{$target->getName()}&r&c has expired."));
            return;
        }

        unset(self::$requests[$key]);

        if ($req["type"] === "tpahere") {
            ZoneManager::allowNextBoundaryCrossing($target, 15);
            GlaciaUtils::timedTeleport($target, $sender->getPosition()->asVector3());
            $sender->sendMessage(C::colorize("&r&b&l[/TPAHERE] &r&f{$target->getName()} has accepted your teleport request."));
            $target->sendMessage(C::colorize("&r&b&l[TPA] &r&fYou accepted {$sender->getName()}'s teleport request."));
        } else {
            ZoneManager::allowNextBoundaryCrossing($sender, 15);
            GlaciaUtils::timedTeleport($sender, $target->getPosition()->asVector3());
            $sender->sendMessage(C::colorize("&r&b&l[/TPA] &r&f{$target->getName()} has accepted your teleport request."));
            $target->sendMessage(C::colorize("&r&b&l[TPA] &r&fYou accepted {$sender->getName()}'s teleport request."));
        }
    }

    public static function denyTPA(Player $target, Player $sender): void {
        $key = strtolower($target->getName());

        if (!isset(self::$requests[$key]) || strtolower(self::$requests[$key]['sender']) !== strtolower($sender->getName())) {
            $target->sendMessage(C::colorize("&r&cNo teleport request from &f{$sender->getName()}&r&c was found."));
            return;
        }

        $req = self::$requests[$key];
        if (self::isRequestExpired($req)) {
            unset(self::$requests[$key]);
            $target->sendMessage(C::colorize("&r&cThe teleport request from &f{$sender->getName()}&r&c has expired."));
            $sender->sendMessage(C::colorize("&r&cYour teleport request to &f{$target->getName()}&r&c has expired."));
            return;
        }

        unset(self::$requests[$key]);

        $target->sendMessage(C::colorize("&r&b&l[TPA] &r&fYou have denied the teleport request from {$sender->getName()}."));
        $sender->sendMessage(C::colorize("&r&b&l[TPA] &r&fYour teleport request to {$target->getName()} has been denied."));
    }

    public static function hasPendingRequest(Player $target, Player $sender): bool {
        $request = self::getActiveRequestForTarget($target);
        if ($request === null || strtolower($request["sender"]) !== strtolower($sender->getName())) {
            return false;
        }

        return true;
    }

    /**
     * @return array{type: string, sender: string, target: string, time: int}|null
     */
    private static function getActiveRequestForTarget(Player $target): ?array
    {
        $key = strtolower($target->getName());
        $request = self::$requests[$key] ?? null;
        if ($request === null) {
            return null;
        }

        if (self::isRequestExpired($request)) {
            unset(self::$requests[$key]);
            return null;
        }

        return $request;
    }
}
