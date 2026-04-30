<?php

declare(strict_types=1);

namespace ecstsy\Glacia\managers;

use ecstsy\Glacia\server\zones\ZoneManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\Glacia\utils\lang\MessageTemplates;
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
            $sender->sendMessage(C::colorize(MessageTemplates::render("tpa.error.target_busy")));
            return false;
        }

        self::$requests[$targetKey] = [
            "type" => $type,
            "sender" => $sender->getName(),
            "target" => $target->getName(),
            "time" => time()
        ];
        $target->sendMessage(C::colorize(MessageTemplates::render(
            $type === "tpahere" ? "tpa.request.received.here" : "tpa.request.received.to_you",
            ["player" => $sender->getName()]
        )));
        $sender->sendMessage(C::colorize(MessageTemplates::render("tpa.request.sent", ["player" => $target->getName()])));
        return true;
    }

    public static function acceptTPA(Player $target, Player $sender): void {
        $key = strtolower($target->getName());

        if (!isset(self::$requests[$key]) || strtolower(self::$requests[$key]['sender']) !== strtolower($sender->getName())) {
            $target->sendMessage(C::colorize(MessageTemplates::render("tpa.error.not_found", ["player" => $sender->getName()])));
            return;
        }

        $req = self::$requests[$key];

        if (self::isRequestExpired($req)) {
            unset(self::$requests[$key]);
            $target->sendMessage(C::colorize(MessageTemplates::render("tpa.error.expired.incoming", ["player" => $sender->getName()])));
            $sender->sendMessage(C::colorize(MessageTemplates::render("tpa.error.expired.outgoing", ["player" => $target->getName()])));
            return;
        }

        unset(self::$requests[$key]);

        if ($req["type"] === "tpahere") {
            ZoneManager::allowNextBoundaryCrossing($target, 15);
            GlaciaUtils::timedTeleport($target, $sender->getPosition()->asVector3());
            $sender->sendMessage(C::colorize(MessageTemplates::render("tpa.accepted.sender.here", ["player" => $target->getName()])));
            $target->sendMessage(C::colorize(MessageTemplates::render("tpa.accepted.target", ["player" => $sender->getName()])));
        } else {
            ZoneManager::allowNextBoundaryCrossing($sender, 15);
            GlaciaUtils::timedTeleport($sender, $target->getPosition()->asVector3());
            $sender->sendMessage(C::colorize(MessageTemplates::render("tpa.accepted.sender", ["player" => $target->getName()])));
            $target->sendMessage(C::colorize(MessageTemplates::render("tpa.accepted.target", ["player" => $sender->getName()])));
        }
    }

    public static function denyTPA(Player $target, Player $sender): void {
        $key = strtolower($target->getName());

        if (!isset(self::$requests[$key]) || strtolower(self::$requests[$key]['sender']) !== strtolower($sender->getName())) {
            $target->sendMessage(C::colorize(MessageTemplates::render("tpa.error.not_found", ["player" => $sender->getName()])));
            return;
        }

        $req = self::$requests[$key];
        if (self::isRequestExpired($req)) {
            unset(self::$requests[$key]);
            $target->sendMessage(C::colorize(MessageTemplates::render("tpa.error.expired.incoming", ["player" => $sender->getName()])));
            $sender->sendMessage(C::colorize(MessageTemplates::render("tpa.error.expired.outgoing", ["player" => $target->getName()])));
            return;
        }

        unset(self::$requests[$key]);

        $target->sendMessage(C::colorize(MessageTemplates::render("tpa.denied.target", ["player" => $sender->getName()])));
        $sender->sendMessage(C::colorize(MessageTemplates::render("tpa.denied.sender", ["player" => $target->getName()])));
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
