<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils;

final class EmojiManager {

    /** @var array<string, string> */
    private const TOKENS = [
        ":skull:" => "\u{E900}",
        ":joy:" => "\u{E901}",
        ":heart:" => "\u{E902}",
        ":star:" => "\u{E903}",
        ":fire:" => "\u{E904}",
        ":sparkles:" => "\u{E905}",
        ":check:" => "\u{E906}",
        ":x:" => "\u{E907}",
        ":xp:" => "\u{E908}",
        ":cooldown:" => "\u{E909}",
    ];

    public static function replaceTokens(string $message): string
    {
        return str_ireplace(array_keys(self::TOKENS), array_values(self::TOKENS), $message);
    }
}
