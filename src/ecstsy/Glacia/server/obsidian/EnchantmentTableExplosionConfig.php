<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\obsidian;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\customtnt\CustomTNTIds;
use ecstsy\MartianUtilities\utils\GeneralUtils;

/**
 * misc.yml enchantment-table-explosions — same fractional-wear scheme as obsidian.
 */
final class EnchantmentTableExplosionConfig {

    public static function subExplosionEventsFor(string $profileKey): int {
        return match ($profileKey) {
            CustomTNTIds::GIGANTIC, CustomTNTIds::LETHAL => 5,
            default => 1,
        };
    }

    public static function displayLayers(): int {
        $cfg = GeneralUtils::getConfiguration(Loader::getInstance(), "misc.yml");
        $v = (int) ($cfg?->getNested("enchantment-table-explosions.display-layers", 6) ?? 6);
        return max(1, min(99, $v));
    }

    public static function hitsToBreakFor(string $profileKey): int {
        $cfg = GeneralUtils::getConfiguration(Loader::getInstance(), "misc.yml");
        /** @var array<string, mixed> $map */
        $map = (array) ($cfg?->getNested("enchantment-table-explosions.hits-to-break", []) ?? []);

        $canonical = ObsidianExplosionConfig::canonicalProfileKey($profileKey);
        /** @var list<string> $lookups */
        $lookups = $canonical === CustomTNTIds::PROFILE_NORMAL ? [
            $canonical,
            "normal-tnt",
            "normal_tnt",
            "vanilla-tnt",
            "vanilla",
        ] : [
            $canonical,
            $profileKey,
        ];

        foreach ($lookups as $key) {
            if (isset($map[$key]) && is_numeric($map[$key])) {
                return max(1, (int) $map[$key]);
            }
        }

        return 6;
    }

    public static function fractionalWearPerExplodeEvent(string $profileKey): float {
        $hits = self::hitsToBreakFor($profileKey);
        $subs = max(1, self::subExplosionEventsFor($profileKey));
        $denom = max(1, $hits * $subs);

        return 1.0 / $denom;
    }
}
