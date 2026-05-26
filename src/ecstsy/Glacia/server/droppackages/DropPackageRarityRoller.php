<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\droppackages;

use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\GeneralUtils;

/**
 * Rolls which sub-pool (simple / unique / elite / …) a drop-package pane uses.
 * Weights come from {@see self::defaultsForTier()} unless overridden per file in {@code resources/dps/<Tier>.yml}:
 *   settings.pane-rarity-weights: { simple: 50, unique: 30, ... }
 * Values may be ints or maps: {@code unique: { roll: 40 }}.
 * Only rarities that exist under {@code <tier>-loot.<rarity>} in that file can roll.
 */
final class DropPackageRarityRoller {

    public static function roll(string $tier): string {
        $tierKey = strtolower(trim($tier));
        $available = self::lootRarityKeysForTier($tierKey);
        $configured = self::weightsFromYaml($tierKey);
        $table = self::buildTable($tierKey, $configured, self::defaultsForTier($tierKey), $available);
        return self::rollWeightedTable($table);
    }

    /**
     * @return array<string, int> rarity => positive weight
     */
    private static function buildTable(string $tierKey, array $configured, array $defaults, array $available): array {
        $avail = [];
        foreach ($available as $r) {
            $avail[strtolower((string) $r)] = true;
        }
        if ($avail === []) {
            return ["simple" => 100];
        }

        $configuredSum = 0;
        $fromConfig = [];
        foreach ($configured as $rarity => $w) {
            $r = strtolower((string) $rarity);
            if (!isset($avail[$r])) {
                continue;
            }
            $w = max(0, (int) $w);
            $fromConfig[$r] = $w;
            $configuredSum += $w;
        }

        $out = [];
        if ($configuredSum > 0) {
            foreach ($fromConfig as $r => $w) {
                if ($w > 0 && isset($avail[$r])) {
                    $out[$r] = $w;
                }
            }
            if ($out !== []) {
                return $out;
            }
        }

        foreach ($defaults as $r => $w) {
            $r = strtolower((string) $r);
            if (isset($avail[$r]) && $w > 0) {
                $out[$r] = (int) $w;
            }
        }

        if ($out !== []) {
            return $out;
        }

        $fallback = strtolower((string) ($available[0] ?? "simple"));
        return [$fallback => 100];
    }

    /**
     * @param array<string, int> $table
     */
    private static function rollWeightedTable(array $table): string {
        $total = array_sum($table);
        if ($total <= 0) {
            return array_key_first($table) ?: "simple";
        }
        $roll = mt_rand(1, $total);
        $current = 0;
        foreach ($table as $rarity => $weight) {
            $current += $weight;
            if ($roll <= $current) {
                return $rarity;
            }
        }

        return (string) array_key_first($table);
    }

    /**
     * @return string[]
     */
    private static function lootRarityKeysForTier(string $tier): array {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "dps/" . ucfirst(strtolower($tier)) . ".yml");
        $lootRoot = strtolower($tier) . "-loot";
        $loot = $config?->getNested($lootRoot, []);
        if (!is_array($loot)) {
            return ["simple"];
        }

        $keys = [];
        foreach ($loot as $k => $v) {
            if (!is_array($v) || $v === []) {
                continue;
            }
            foreach ($v as $e) {
                if (is_array($e)) {
                    $keys[] = strtolower((string) $k);
                    break;
                }
            }
        }

        return $keys !== [] ? array_values(array_unique($keys)) : ["simple"];
    }

    /**
     * @return array<string, int>
     */
    private static function weightsFromYaml(string $tier): array {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "dps/" . ucfirst(strtolower($tier)) . ".yml");
        if ($config === null) {
            return [];
        }

        $raw = $config->getNested("settings.pane-rarity-weights", []);
        if (!is_array($raw) || $raw === []) {
            $top = $config->get("pane-rarity-weights", []);
            $raw = is_array($top) ? $top : [];
        }

        $out = [];
        foreach ($raw as $k => $v) {
            $w = self::normalizeConfiguredWeight($v);
            if ($w < 0) {
                continue;
            }
            $out[strtolower(trim((string) $k))] = $w;
        }

        return $out;
    }

    private static function normalizeConfiguredWeight(mixed $v): int {
        if (is_int($v) || is_float($v)) {
            return max(0, (int) $v);
        }
        if (is_array($v)) {
            foreach (["roll", "weight", "chance", "percent"] as $key) {
                if (isset($v[$key]) && is_numeric($v[$key])) {
                    return max(0, (int) $v[$key]);
                }
            }
        }

        return 0;
    }

    /**
     * @return array<string, int>
     */
    private static function defaultsForTier(string $tier): array {
        return match ($tier) {
            "simple" => [
                "simple" => 80,
                "unique" => 15,
                "elite" => 4,
                "exotic" => 1,
            ],
            "unique" => [
                "simple" => 60,
                "unique" => 25,
                "elite" => 10,
                "exotic" => 4,
                "legendary" => 1,
            ],
            "elite" => [
                "simple" => 40,
                "unique" => 30,
                "elite" => 20,
                "exotic" => 8,
                "legendary" => 2,
            ],
            "exotic" => [
                "simple" => 20,
                "unique" => 30,
                "elite" => 30,
                "exotic" => 15,
                "legendary" => 5,
            ],
            "legendary" => [
                "simple" => 5,
                "unique" => 15,
                "elite" => 30,
                "exotic" => 30,
                "legendary" => 20,
            ],
            "godly" => [
                "simple" => 1,
                "unique" => 5,
                "elite" => 15,
                "exotic" => 25,
                "legendary" => 25,
                "ultimate" => 29,
            ],
            default => [
                "simple" => 100,
            ],
        };
    }
}
