<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments;

use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\GeneralUtils;

/**
 * Applies {@code enchant_descriptions.yml}: keys are enchant ids (same as CE definitions).
 * Supports string values or nested {@code description: "..."}.
 */
final class EnchantDescriptionOverrides {

    public static function reload(): void {
        foreach (CustomEnchantments::getAll() as $ce) {
            $ce->applyDescriptionOverride(null);
        }

        $cfg = GeneralUtils::getConfiguration(Loader::getInstance(), "enchant_descriptions.yml");
        if ($cfg === null) {
            return;
        }

        foreach ($cfg->getAll() as $key => $entry) {
            $id = trim((string) $key);
            if ($id === "") {
                continue;
            }

            $text = self::normalizeEntryText($entry);
            if ($text === "") {
                continue;
            }

            $ench = CustomEnchantments::getEnchantmentByName($id);
            if ($ench === null) {
                continue;
            }

            $ench->applyDescriptionOverride(self::newlineUnescape($text));
        }
    }

    /** @param mixed $entry */
    private static function normalizeEntryText(mixed $entry): string {
        if (is_string($entry)) {
            return trim($entry);
        }
        if (!is_array($entry)) {
            return "";
        }

        foreach (["description", "text", "lore"] as $k) {
            if (isset($entry[$k]) && is_string($entry[$k])) {
                return trim((string) $entry[$k]);
            }
        }

        $lines = [];
        foreach ($entry as $v) {
            if (is_string($v)) {
                $lines[] = trim($v);
            }
        }

        return trim(implode("\n", array_filter($lines, static fn(string $s): bool => $s !== "")));
    }

    /** Allows literal `\n` in YAML when you want embedded newlines in one quoted line. */
    private static function newlineUnescape(string $s): string {
        return str_replace(["\\r\\n", '\\n'], ["\r\n", "\n"], $s);
    }
}
