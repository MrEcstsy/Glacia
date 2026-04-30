<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\trade;

use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class TradeManager {

    private const REQUEST_TIMEOUT = 30;

    /** @var array<string, array<string, int>> */
    private static array $pending = [];
    /** @var array<string, TradeSession> */
    private static array $sessions = [];

    public static function handleTradeCommand(Player $sender, Player $target): void
    {
        self::cleanupExpiredRequests();
        if (self::getSession($sender) !== null) {
            $sender->sendMessage(C::colorize("&r&cYou are already in a trade."));
            return;
        }
        if (self::getSession($target) !== null) {
            $sender->sendMessage(C::colorize("&r&cThat player is already in a trade."));
            return;
        }

        $senderKey = $sender->getUniqueId()->toString();
        $targetKey = $target->getUniqueId()->toString();

        if (isset(self::$pending[$senderKey][$targetKey])) {
            unset(self::$pending[$senderKey][$targetKey]);
            if ((self::$pending[$senderKey] ?? []) === []) {
                unset(self::$pending[$senderKey]);
            }
            self::startSession($sender, $target);
            return;
        }

        if (isset(self::$pending[$targetKey][$senderKey])) {
            $sender->sendMessage(C::colorize("&r&eYou already sent a trade request to &f{$target->getName()}&e."));
            return;
        }

        self::$pending[$targetKey][$senderKey] = time() + self::REQUEST_TIMEOUT;
        $sender->sendMessage(C::colorize("&r&aTrade request sent to &f{$target->getName()}&a."));
        $target->sendMessage(C::colorize("&r&e{$sender->getName()} wants to trade. Run &f/trade {$sender->getName()} &eto accept."));
    }

    public static function startSession(Player $a, Player $b): void
    {
        $session = new TradeSession($a, $b);
        self::$sessions[$a->getUniqueId()->toString()] = $session;
        self::$sessions[$b->getUniqueId()->toString()] = $session;
        $session->open();
    }

    public static function unregisterSession(TradeSession $session): void
    {
        unset(self::$sessions[$session->getPlayerA()->getUniqueId()->toString()]);
        unset(self::$sessions[$session->getPlayerB()->getUniqueId()->toString()]);
    }

    public static function getSession(Player $player): ?TradeSession
    {
        return self::$sessions[$player->getUniqueId()->toString()] ?? null;
    }

    public static function handleDisconnect(Player $player): void
    {
        $key = $player->getUniqueId()->toString();
        unset(self::$pending[$key]);
        foreach (self::$pending as $targetKey => $sources) {
            unset(self::$pending[$targetKey][$key]);
            if ((self::$pending[$targetKey] ?? []) === []) {
                unset(self::$pending[$targetKey]);
            }
        }

        $session = self::getSession($player);
        if ($session !== null) {
            $session->cancel($player->getName() . " left the server.");
        }
    }

    public static function shutdown(): void
    {
        foreach (self::$sessions as $session) {
            $session->cancel("Trade cancelled due to server shutdown.");
        }
        self::$sessions = [];
        self::$pending = [];
    }

    private static function cleanupExpiredRequests(): void
    {
        $now = time();
        foreach (self::$pending as $targetKey => $sources) {
            foreach ($sources as $sourceKey => $expiresAt) {
                if ($expiresAt <= $now) {
                    unset(self::$pending[$targetKey][$sourceKey]);
                }
            }
            if ((self::$pending[$targetKey] ?? []) === []) {
                unset(self::$pending[$targetKey]);
            }
        }
    }
}

