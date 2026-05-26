<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\commands;

use pocketmine\player\Player;
use pocketmine\utils\Config;

/**
 * Shared cooldown between GUI / menu-like commands ({@see misc.yml} command-rate-limit).
 *
 * Tracks time since the last allowed use of any listed command for that player (anti-dupe / InvMenu overlap).
 * Optional {@code command-interval-ms} sets a longer cooldown for specific roots (e.g. shop, pv).
 */
final class CommandRateLimiter
{

    private static bool $enabled = false;

    private static int $intervalMs = 500;

    /** @var array<string, int> lowercase command label => min gap in ms since last tracked command */
    private static array $intervalByLabel = [];

    private static string $bypassPermission = "glacia.command.ratelimit.bypass";

    /** @var array<string, true> */
    private static array $labels = [];

    /** @var array<string, float> player's uuid string => microtime(true) timestamp of last allowed use */
    private static array $lastUse = [];

    public static function loadFromMisc(?Config $misc): void
    {
        if ($misc === null) {
            self::$enabled = false;
            self::$labels = [];
            self::$intervalByLabel = [];
            return;
        }

        $cfg = $misc->get("command-rate-limit");
        if (!is_array($cfg)) {
            self::$enabled = false;
            self::$labels = [];
            self::$intervalByLabel = [];
            return;
        }

        self::$intervalMs = max(0, (int) ($cfg["interval-ms"] ?? 500));

        /** @var array<string, int> */
        $byLabel = [];
        $overrides = $cfg["command-interval-ms"] ?? [];
        if (is_array($overrides)) {
            foreach ($overrides as $cmdName => $ms) {
                if (!is_string($cmdName) || $cmdName === "") {
                    continue;
                }
                $byLabel[strtolower(trim($cmdName))] = max(0, (int) $ms);
            }
        }
        self::$intervalByLabel = $byLabel;

        self::$bypassPermission = (string) ($cfg["bypass-permission"] ?? "glacia.command.ratelimit.bypass");

        $list = $cfg["commands"] ?? [];
        $labels = [];
        if (is_array($list)) {
            foreach ($list as $entry) {
                if (!is_string($entry) || $entry === "") {
                    continue;
                }
                $labels[strtolower($entry)] = true;
            }
        }
        self::$labels = $labels;

        self::$enabled = (bool) ($cfg["enabled"] ?? false)
            && self::$intervalMs > 0
            && $labels !== [];
    }

    public static function forgetPlayer(Player $player): void
    {
        unset(self::$lastUse[$player->getUniqueId()->toString()]);
    }

    /**
     * @return non-empty-string|false command label lowercase, or false if not a simple chat command
     */
    private static function parseLabel(string $message): string|false
    {
        $message = trim($message);
        if ($message === "") {
            return false;
        }
        /** @see Server::dispatchCommand() — Bedrock/chat uses no leading slash */
        $stripped = str_starts_with($message, "/")
            ? ltrim(substr($message, 1), '/')
            : $message;
        $stripped = trim($stripped);
        if ($stripped === "") {
            return false;
        }

        foreach (preg_split('/\s+/', $stripped, 2) ?: [] as $token) {
            $token = trim($token);
            if ($token === "") {
                continue;
            }
            if (str_starts_with($token, "./")) {
                $token = substr($token, 2);
            }
            if (($p = strpos($token, ":")) !== false) {
                $token = substr($token, $p + 1);
            }

            return strtolower($token);
        }

        return false;
    }

    /** Human-readable remainder for chat (sub-second precision when under 1s). */
    public static function formatRemaining(int $remainingMs): string
    {
        if ($remainingMs < 1000) {
            return max(1, $remainingMs) . "ms";
        }
        return rtrim(rtrim(number_format(max(100, $remainingMs) / 1000, 2), "0"), ".") . "s";
    }

    /**
     * If tracked and too soon since any listed command was used: returns remaining throttle time (ms).
     * If allowed (or irrelevant): registers this use and returns null.
     *
     * @return int|null remaining milliseconds until next allowed tracked command when blocked
     */
    public static function check(Player $player, string $chatMessage): ?int
    {
        if (!self::$enabled || $player->hasPermission(self::$bypassPermission)) {
            return null;
        }

        $label = self::parseLabel($chatMessage);
        if ($label === false || !isset(self::$labels[$label])) {
            return null;
        }

        $requiredMs = self::$intervalByLabel[$label] ?? self::$intervalMs;
        if ($requiredMs <= 0) {
            return null;
        }

        $uuid = $player->getUniqueId()->toString();
        $now = microtime(true);
        $last = self::$lastUse[$uuid] ?? null;

        if ($last !== null && (($now - $last) * 1000.0) < $requiredMs) {
            $remaining = max(1, $requiredMs - (int) (($now - $last) * 1000));

            return $remaining;
        }

        self::$lastUse[$uuid] = $now;

        return null;
    }
}
