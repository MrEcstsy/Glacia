<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\obsidian;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\customtnt\CustomTNTIds;
use ecstsy\MartianUtilities\utils\GeneralUtils;

/**
 * misc.yml obsidian-explosions — per-pulse wear is divided by sub-explosion count so total wear per placement stays 1/hits-to-break.
 */
final class ObsidianExplosionConfig
{
    /** How many PocketMine explosions one placed charge fires (primary + explodeExtra offsets). */
    public static function subExplosionEventsFor(string $profileKey): int
    {
        return match ($profileKey) {
            CustomTNTIds::GIGANTIC, CustomTNTIds::LETHAL => 5,
            default => 1,
        };
    }

    public static function waterBlocksDamage(): bool
    {
        $cfg = GeneralUtils::getConfiguration(Loader::getInstance(), "misc.yml");
        return (bool) ($cfg?->getNested("obsidian-explosions.water-blocks-damage", true) ?? true);
    }

    public static function displayLayers(): int
    {
        $cfg = GeneralUtils::getConfiguration(Loader::getInstance(), "misc.yml");
        $v = (int) ($cfg?->getNested("obsidian-explosions.display-layers", 3) ?? 3);
        return max(1, min(99, $v));
    }

    /**
     * Placements (whole charges) to fully erase one obsidian from fresh.
     * Lethal/gigantic fire multiple PocketMine explosions per charge, but fractional wear is tuned so their total per placement is still 1/hits.
     */
    public static function hitsToBreakFor(string $profileKey): int
    {
        $cfg = GeneralUtils::getConfiguration(Loader::getInstance(), "misc.yml");
        /** @var array<string, mixed> $map */
        $map = (array) ($cfg?->getNested("obsidian-explosions.hits-to-break", []) ?? []);

        $canonical = self::canonicalProfileKey($profileKey);
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

        return 3;
    }

    /**
     * Fraction of durability removed per EntityExplodeEvent for this blast profile (uniform across sub-explosions).
     */
    public static function fractionalWearPerExplodeEvent(string $profileKey): float
    {
        $hits = self::hitsToBreakFor($profileKey);
        $subs = max(1, self::subExplosionEventsFor($profileKey));
        $denom = max(1, $hits * $subs);

        return 1.0 / $denom;
    }

    public static function canonicalProfileKey(string $yamlKey): string
    {
        return match (strtolower(trim($yamlKey))) {
            "normal-tnt", "vanilla-tnt", "vanilla", "minecraft:tnt", "primed_tnt" => CustomTNTIds::PROFILE_NORMAL,
            default => strtolower(trim($yamlKey)),
        };
    }
}
