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

    /**
     * Lowercase, strip formatting, optionally leet-fold, and preserve word boundaries as spaces.
     */
    private static function normalizePhraseText(string $input, bool $leetFold = false): string
    {
        $text = strtolower(C::clean($input));
        if ($leetFold) {
            $text = strtr($text, [
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
        }

        $text = preg_replace("/[^a-z0-9]+/", " ", $text) ?? "";
        return trim(preg_replace("/\\s+/", " ", $text) ?? "");
    }

    private static function containsBlockedPhrase(string $haystack, string $needle): bool
    {
        if ($haystack === "" || $needle === "") {
            return false;
        }

        return preg_match('/(?:^| )' . preg_quote($needle, '/') . '(?: |$)/', $haystack) === 1;
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

    public static function firstGenericNameViolation(string $originalInput, string $sanitizedName, string $label = "name"): ?string
    {
        $sn = strtolower(trim($sanitizedName));
        if ($sn === "") {
            return null;
        }

        if (isset(self::$reservedNames[$sn])) {
            return "That {$label} is reserved.";
        }

        if (isset(self::$blockedFullNames[$sn])) {
            return "That {$label} is not allowed.";
        }

        $haystacks = [
            strtolower(C::clean($originalInput)),
            $sn,
            self::foldString($originalInput),
            self::foldString($sanitizedName),
        ];

        foreach (self::$blockedSubstrings as $word) {
            foreach ($haystacks as $hay) {
                if ($hay !== "" && str_contains($hay, $word)) {
                    return "That {$label} contains prohibited language.";
                }
            }
        }

        return null;
    }

    public static function firstGenericTextViolation(string $text, string $label = "message", ?int $maxLength = null): ?string
    {
        if ($maxLength !== null && strlen($text) > $maxLength) {
            return ucfirst($label) . " is too long (max {$maxLength} characters).";
        }

        $haystacks = [
            self::normalizePhraseText($text, false),
            self::normalizePhraseText($text, true),
        ];

        foreach (self::$blockedSubstrings as $word) {
            $needle = self::normalizePhraseText($word, true);
            foreach ($haystacks as $hay) {
                if (self::containsBlockedPhrase($hay, $needle)) {
                    return "That {$label} contains prohibited language.";
                }
            }
        }

        foreach (array_keys(self::$blockedFullNames) as $word) {
            $needle = self::normalizePhraseText($word, true);
            foreach ($haystacks as $hay) {
                if (self::containsBlockedPhrase($hay, $needle)) {
                    return "That {$label} contains prohibited language.";
                }
            }
        }

        return null;
    }
}
