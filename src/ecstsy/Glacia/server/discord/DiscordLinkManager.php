<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\discord;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\player\GlaciaPlayer;
use pocketmine\player\Player;

final class DiscordLinkManager{

    /** @var array<string, array{uuid: string, username: string, expires: int}> */
    private static array $pendingCodes = [];
    private const CODE_TTL = 600;

    public static function createLinkCode(Player $player): string{
        $session = Loader::getPlayerManager()->getSession($player);
        if ($session === null) {
            return "";
        }

        $code = self::generateCode();
        self::$pendingCodes[$code] = [
            "uuid" => $session->getUuid()->toString(),
            "username" => $session->getUsername(),
            "expires" => time() + self::CODE_TTL
        ];

        return $code;
    }

    public static function claim(string $discordId, string $code): array{
        self::cleanupExpired();
        $discordId = trim($discordId);
        $code = strtoupper(trim($code));
        if ($discordId === "" || $code === "") {
            return ["ok" => false, "message" => "missing_discord_or_code"];
        }

        $pending = self::$pendingCodes[$code] ?? null;
        if ($pending === null) {
            return ["ok" => false, "message" => "invalid_or_expired_code"];
        }

        $session = Loader::getPlayerManager()->getSessionByUuid(\Ramsey\Uuid\Uuid::fromString($pending["uuid"]));
        if ($session === null) {
            unset(self::$pendingCodes[$code]);
            return ["ok" => false, "message" => "player_session_not_found"];
        }

        $existing = self::findSessionByDiscordId($discordId);
        if ($existing !== null && $existing->getUuid()->toString() !== $session->getUuid()->toString()) {
            return ["ok" => false, "message" => "discord_already_linked"];
        }

        $session->setSetting("discord_link", [
            "discord_id" => $discordId,
            "linked_at" => time(),
            "username" => $session->getUsername(),
            "uuid" => $session->getUuid()->toString()
        ]);

        unset(self::$pendingCodes[$code]);

        return [
            "ok" => true,
            "username" => $session->getUsername(),
            "uuid" => $session->getUuid()->toString(),
            "rank" => $session->getRank()
        ];
    }

    public static function unlinkSession(GlaciaPlayer $session): bool{
        if (!self::isLinked($session)) {
            return false;
        }
        $session->setSetting("discord_link", null);
        return true;
    }

    public static function unlinkByDiscordId(string $discordId): ?GlaciaPlayer{
        $session = self::findSessionByDiscordId($discordId);
        if ($session === null) {
            return null;
        }

        self::unlinkSession($session);
        return $session;
    }

    public static function getLinkData(GlaciaPlayer $session): ?array{
        $data = $session->getSetting("discord_link");
        return is_array($data) ? $data : null;
    }

    public static function isLinked(GlaciaPlayer $session): bool{
        $data = self::getLinkData($session);
        return is_array($data) && trim((string) ($data["discord_id"] ?? "")) !== "";
    }

    public static function findSessionByDiscordId(string $discordId): ?GlaciaPlayer{
        $discordId = trim($discordId);
        if ($discordId === "") {
            return null;
        }

        foreach (Loader::getPlayerManager()->getSessions() as $session) {
            $data = self::getLinkData($session);
            if (is_array($data) && (string) ($data["discord_id"] ?? "") === $discordId) {
                return $session;
            }
        }

        return null;
    }

    public static function getStatusPayload(GlaciaPlayer $session): array{
        $data = self::getLinkData($session);
        $faction = $session->getFaction();
        return [
            "username" => $session->getUsername(),
            "uuid" => $session->getUuid()->toString(),
            "rank" => $session->getRank(),
            "faction_name" => $faction?->getName(),
            "faction_role" => $session->getFactionRole(),
            "linked" => self::isLinked($session),
            "discord_id" => $data["discord_id"] ?? null,
            "linked_at" => $data["linked_at"] ?? null
        ];
    }

    private static function generateCode(): string{
        return "GLA-" . mt_rand(100000, 999999);
    }

    private static function cleanupExpired(): void{
        $now = time();
        foreach (self::$pendingCodes as $code => $pending) {
            if (($pending["expires"] ?? 0) <= $now) {
                unset(self::$pendingCodes[$code]);
            }
        }
    }
}

