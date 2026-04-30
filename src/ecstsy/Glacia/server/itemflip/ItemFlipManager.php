<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\itemflip;

use ecstsy\Glacia\Loader;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use pocketmine\world\Position;

final class ItemFlipManager {

    private const REQUEST_TIMEOUT_SECONDS = 30;

    /** @var array<string, array<string, array{expires:int}>> */
    private static array $pending = [];
    /** @var array<string, ItemFlipSession> */
    private static array $activeSessions = [];

    public static function sendChallenge(Player $sender, Player $target): void
    {
        self::cleanupExpiredRequests();
        if (self::isInSession($sender) || self::isInSession($target)) {
            $sender->sendMessage(C::colorize("&r&cYou or that player is already in an item flip."));
            return;
        }

        if (!self::isInSafeZone($sender->getPosition()) || !self::isInSafeZone($target->getPosition())) {
            $sender->sendMessage(C::colorize("&r&cBoth players must be in a safe zone to item flip."));
            return;
        }

        if ($sender->getWorld()->getFolderName() !== $target->getWorld()->getFolderName() || $sender->getPosition()->distance($target->getPosition()) > 10) {
            $sender->sendMessage(C::colorize("&r&cYou must be within &f10 blocks &cof that player."));
            return;
        }

        self::createRequest($sender, $target);
    }

    public static function acceptChallenge(Player $acceptor, Player $requester): void
    {
        self::cleanupExpiredRequests();
        if (self::isInSession($acceptor) || self::isInSession($requester)) {
            $acceptor->sendMessage(C::colorize("&r&cYou or that player is already in an item flip."));
            return;
        }
        if (!self::isInSafeZone($acceptor->getPosition()) || !self::isInSafeZone($requester->getPosition())) {
            $acceptor->sendMessage(C::colorize("&r&cBoth players must be in a safe zone to item flip."));
            return;
        }
        if ($acceptor->getWorld()->getFolderName() !== $requester->getWorld()->getFolderName() || $acceptor->getPosition()->distance($requester->getPosition()) > 10) {
            $acceptor->sendMessage(C::colorize("&r&cYou must be within &f10 blocks &cof that player."));
            return;
        }
        self::acceptRequest($acceptor, $requester);
    }

    public static function denyChallenge(Player $target, Player $requester): void
    {
        self::cleanupExpiredRequests();
        $targetKey = $target->getUniqueId()->toString();
        $requesterKey = $requester->getUniqueId()->toString();
        $payload = self::$pending[$targetKey][$requesterKey] ?? null;
        if ($payload === null) {
            $target->sendMessage(C::colorize("&r&cNo pending item flip request from that player."));
            return;
        }

        unset(self::$pending[$targetKey][$requesterKey]);
        if ((self::$pending[$targetKey] ?? []) === []) {
            unset(self::$pending[$targetKey]);
        }

        $target->sendMessage(C::colorize("&r&eDenied &f{$requester->getName()}'s &eItem Flip request."));
        if ($requester->isOnline()) {
            $requester->sendMessage(C::colorize("&r&c{$target->getName()} denied your Item Flip request."));
        }
    }

    /**
     * @return array<int, array{player: string, credits: int, expires_in: int}>
     */
    public static function getPendingFor(Player $target): array
    {
        self::cleanupExpiredRequests();
        $targetKey = $target->getUniqueId()->toString();
        $rows = [];
        foreach (self::$pending[$targetKey] ?? [] as $sourceKey => $payload) {
            $expiresIn = max(0, ((int) ($payload["expires"] ?? 0)) - time());
            $sourcePlayer = $target->getServer()->getPlayerByUUID(\Ramsey\Uuid\Uuid::fromString($sourceKey));
            $rows[] = [
                "player" => $sourcePlayer?->getName() ?? $sourceKey,
                "credits" => 1,
                "expires_in" => $expiresIn
            ];
        }
        return $rows;
    }

    public static function hasPendingFrom(Player $target, Player $requester): bool
    {
        self::cleanupExpiredRequests();
        return isset(self::$pending[$target->getUniqueId()->toString()][$requester->getUniqueId()->toString()]);
    }

    public static function handleDisconnect(Player $player): void
    {
        $key = $player->getUniqueId()->toString();
        unset(self::$pending[$key]);
        foreach (self::$pending as $targetKey => $requests) {
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
        foreach (self::$activeSessions as $session) {
            $session->cancel("Item Flip cancelled due to server shutdown.");
        }
        self::$activeSessions = [];
        self::$pending = [];
    }

    public static function registerSession(ItemFlipSession $session): void
    {
        self::$activeSessions[$session->getPlayerA()->getUniqueId()->toString()] = $session;
        self::$activeSessions[$session->getPlayerB()->getUniqueId()->toString()] = $session;
    }

    public static function unregisterSession(ItemFlipSession $session): void
    {
        unset(self::$activeSessions[$session->getPlayerA()->getUniqueId()->toString()]);
        unset(self::$activeSessions[$session->getPlayerB()->getUniqueId()->toString()]);
    }

    public static function getSession(Player $player): ?ItemFlipSession
    {
        return self::$activeSessions[$player->getUniqueId()->toString()] ?? null;
    }

    public static function isInSession(Player $player): bool
    {
        return self::getSession($player) !== null;
    }

    private static function createRequest(Player $sender, Player $target): void
    {
        $targetKey = $target->getUniqueId()->toString();
        $senderKey = $sender->getUniqueId()->toString();
        self::$pending[$targetKey][$senderKey] = [
            "expires" => time() + self::REQUEST_TIMEOUT_SECONDS
        ];

        $sender->sendMessage(C::colorize("&r&aItem Flip request sent to &f{$target->getName()}&a."));
        $target->sendMessage(C::colorize("&r&e{$sender->getName()} challenged you to an Item Flip."));
        $target->sendMessage(C::colorize("&r&7Type &f/if {$sender->getName()} &7to accept."));
    }

    private static function acceptRequest(Player $acceptor, Player $requester): void
    {
        $acceptorKey = $acceptor->getUniqueId()->toString();
        $requesterKey = $requester->getUniqueId()->toString();
        $payload = self::$pending[$acceptorKey][$requesterKey] ?? null;
        if ($payload === null) {
            $acceptor->sendMessage(C::colorize("&r&cNo pending item flip request from that player."));
            return;
        }

        unset(self::$pending[$acceptorKey][$requesterKey]);
        if ((self::$pending[$acceptorKey] ?? []) === []) {
            unset(self::$pending[$acceptorKey]);
        }

        $aSession = Loader::getPlayerManager()->getSession($requester);
        $bSession = Loader::getPlayerManager()->getSession($acceptor);
        if ($aSession === null || $bSession === null) {
            return;
        }

        if ($aSession->getItemFlipCredits() < 1) {
            $acceptor->sendMessage(C::colorize("&r&c{$requester->getName()} does not have an Item Flip Credit."));
            $requester->sendMessage(C::colorize("&r&cItem Flip failed: you need an Item Flip Credit."));
            return;
        }
        if ($bSession->getItemFlipCredits() < 1) {
            $acceptor->sendMessage(C::colorize("&r&cYou need an Item Flip Credit to accept."));
            return;
        }

        $aSession->removeItemFlipCredits(1);
        $bSession->removeItemFlipCredits(1);

        $session = new ItemFlipSession($requester, $acceptor);
        self::registerSession($session);
        $session->open();
    }

    private static function isInSafeZone(Position $position): bool
    {
        $region = Loader::getRegionManager()->getRegionAt($position);
        if ($region === null) {
            return false;
        }

        return in_array(strtolower($region->getName()), ["spawn", "safezone", "safe_zone"], true);
    }

    private static function cleanupExpiredRequests(): void
    {
        $now = time();
        foreach (self::$pending as $targetKey => $requests) {
            foreach ($requests as $sourceKey => $payload) {
                if (($payload["expires"] ?? 0) <= $now) {
                    unset(self::$pending[$targetKey][$sourceKey]);
                }
            }
            if ((self::$pending[$targetKey] ?? []) === []) {
                unset(self::$pending[$targetKey]);
            }
        }
    }
}

