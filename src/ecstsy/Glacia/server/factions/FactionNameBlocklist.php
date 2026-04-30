<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\factions;

use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\utils\TextFormat as C;

/**
 * Config-driven profanity / impersonation checks for faction names and descriptions.
 * Leet-style folding catches common character substitutions (inspired by typical faction-plugin validation).
 */
final class FactionNameBlocklist
{
    /** @var list<string> */
    private static array $blockedSubstrings = [];
    /** @var array<string, true> */
    private static array $blockedFullNames = [];
    /** @var array<string, true> */
    private static array $reservedNames = [];
    private static int $maxDescriptionLength = 200;

    public static function load(): void
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "faction_name_blocklist.yml");
        $all = $config?->getAll() ?? [];

        self::$blockedSubstrings = [];
        foreach ((array) ($all["blocked-substrings"] ?? []) as $entry) {
            if (!is_string($entry)) {
                continue;
            }
            $t = strtolower(trim($entry));
            if ($t !== "") {
                self::$blockedSubstrings[] = $t;
            }
        }

        self::$blockedFullNames = self::stringListToSet((array) ($all["blocked-full-names"] ?? []));
        self::$reservedNames = self::stringListToSet((array) ($all["reserved-names"] ?? []));
        self::$maxDescriptionLength = max(32, min(2000, (int) ($all["max-description-length"] ?? 200)));
    }

    /**
     * @param list<string>|array<int, mixed> $list
     * @return array<string, true>
     */
    private static function stringListToSet(array $list): array
    {
        $out = [];
        foreach ($list as $entry) {
            if (!is_string($entry)) {
                continue;
            }
            $t = strtolower(trim($entry));
            if ($t !== "") {
                $out[$t] = true;
            }
        }

        return $out;
    }

    public static function getMaxDescriptionLength(): int
    {
        return self::$maxDescriptionLength;
    }

    /**
     * Collapse formatting, apply simple leet substitutions, strip non-letters for substring checks.
     */
    public static function foldString(string $input): string
    {
        $lower = strtolower(C::clean($input));
        $t = strtr($lower, [
            "0" => "o",
            "1" => "i",
            "3" => "e",
            "4" => "a",
            "5" => "s",
            "7" => "t",
            "8" => "b",
            "@" => "a",
            "$" => "s",
            "!" => "i",
            "|" => "l",
        ]);

        return preg_replace("/[^a-z0-9]/", "", $t) ?? "";
    }

    public static function firstFactionNameViolation(string $originalInput, string $sanitizedName): ?string
    {
        $sn = strtolower($sanitizedName);
        if (isset(self::$reservedNames[$sn])) {
            return "That name is reserved.";
        }

        if (isset(self::$blockedFullNames[$sn])) {
            return "That name is not allowed.";
        }

        $haystacks = [
            strtolower($originalInput),
            $sn,
            self::foldString($originalInput),
            self::foldString($sanitizedName),
        ];

        foreach (self::$blockedSubstrings as $word) {
            foreach ($haystacks as $hay) {
                if ($hay !== "" && str_contains($hay, $word)) {
                    return "That name contains a forbidden word.";
                }
            }
        }

        return null;
    }

    public static function firstDescriptionViolation(string $text): ?string
    {
        if (strlen($text) > self::$maxDescriptionLength) {
            return "Description is too long (max " . self::$maxDescriptionLength . " characters).";
        }

        $haystacks = [
            strtolower(C::clean($text)),
            self::foldString($text),
        ];

        foreach (self::$blockedSubstrings as $word) {
            foreach ($haystacks as $hay) {
                if ($hay !== "" && str_contains($hay, $word)) {
                    return "That description contains a forbidden word.";
                }
            }
        }

        foreach (array_keys(self::$blockedFullNames) as $word) {
            foreach ($haystacks as $hay) {
                if ($hay !== "" && str_contains($hay, $word)) {
                    return "That description contains a forbidden word.";
                }
            }
        }

        return null;
    }
}
