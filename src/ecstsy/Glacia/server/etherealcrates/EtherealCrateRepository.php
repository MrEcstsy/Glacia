<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\etherealcrates;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\RewardResolver;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\utils\Config;

final class EtherealCrateRepository
{
    /** @var array<string, array<string, mixed>> */
    private static array $crates = [];

    public static function reload(): void
    {
        self::$crates = [];
        $directory = Loader::getInstance()->getDataFolder() . "etherealcrates";
        if (!is_dir($directory)) {
            @mkdir($directory, 0777, true);
        }

        foreach (glob($directory . DIRECTORY_SEPARATOR . "*.yml") ?: [] as $file) {
            $config = new Config($file, Config::YAML);
            $data = $config->getAll();
            if (!is_array($data)) {
                continue;
            }

            $id = strtolower((string) pathinfo($file, PATHINFO_FILENAME));
            $data["id"] = strtolower((string) ($data["id"] ?? $id));
            $data["name"] = (string) ($data["name"] ?? ucwords(str_replace(["_", "-"], " ", $id)));
            self::$crates[$data["id"]] = $data;
        }
    }

    /**
     * @return string[]
     */
    public static function getBundledResourceFiles(): array
    {
        $resourceDirectory = dirname(__DIR__, 5) . DIRECTORY_SEPARATOR . "resources" . DIRECTORY_SEPARATOR . "etherealcrates";
        $files = [];
        foreach (glob($resourceDirectory . DIRECTORY_SEPARATOR . "*.yml") ?: [] as $file) {
            $files[] = "etherealcrates/" . basename($file);
        }
        sort($files, SORT_NATURAL | SORT_FLAG_CASE);
        return $files;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function getCrateByLookup(string $lookup): ?array
    {
        self::ensureLoaded();
        $lookup = strtolower(str_replace([" ", "_", "-"], "", trim($lookup)));
        if ($lookup === "") {
            return null;
        }

        foreach (self::$crates as $crate) {
            $ids = [(string) ($crate["id"] ?? "")];
            foreach ((array) ($crate["aliases"] ?? []) as $alias) {
                if (is_string($alias) && trim($alias) !== "") {
                    $ids[] = $alias;
                }
            }

            foreach ($ids as $id) {
                $normalized = strtolower(str_replace([" ", "_", "-"], "", trim($id)));
                if ($normalized === $lookup) {
                    return $crate;
                }
            }
        }

        return null;
    }

    /**
     * @return string[]
     */
    public static function getCrateIds(): array
    {
        self::ensureLoaded();
        return array_keys(self::$crates);
    }

    /**
     * @return array<string, string[]>
     */
    public static function getLookupMap(): array
    {
        self::ensureLoaded();
        $map = [];
        foreach (self::$crates as $crate) {
            $map[(string) ($crate["id"] ?? "")] = self::getIdentifiersForCrate($crate);
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $crate
     * @return Item[]
     */
    public static function getDisplayItems(array $crate, ?Player $player = null): array
    {
        $out = [];
        foreach (["admin", "cosmetic", "treasure", "bonus"] as $table) {
            foreach (self::getPool($crate, $table) as $entry) {
                $item = RewardResolver::resolveItem($entry, $player);
                if ($item !== null) {
                    $out[] = $item;
                }
            }
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $crate
     * @return array{items: (Item|null)[], used_fingerprints: list<string>}
     */
    public static function rollMainRewards(array $crate, ?Player $player = null, int $count = 9): array
    {
        $count = max(1, $count);
        $adminPool = self::getPool($crate, "admin");
        $cosmeticPool = self::getPool($crate, "cosmetic");
        $treasurePool = self::getPool($crate, "treasure");

        if ($count >= 2) {
            $order = range(0, $count - 1);
            shuffle($order);
            $adminSlot = $order[0];
            $cosmeticSlot = $order[1];
        } else {
            $adminSlot = 0;
            $cosmeticSlot = -1;
        }

        /** @var array<string, true> $usedFp */
        $usedFp = [];
        $items = array_fill(0, $count, null);

        for ($i = 0; $i < $count; $i++) {
            if ($i === $adminSlot) {
                $items[$i] = self::rollOneUniqueItem($adminPool, $usedFp, $player);
            } elseif ($i === $cosmeticSlot) {
                $items[$i] = self::rollOneUniqueItem($cosmeticPool, $usedFp, $player);
            } else {
                $items[$i] = self::rollOneUniqueItem($treasurePool, $usedFp, $player);
            }
        }

        return [
            "items" => $items,
            "used_fingerprints" => array_keys($usedFp),
        ];
    }

    /**
     * One rolled reward that has not been used yet this opening (fingerprint), no duplicates across tables.
     *
     * @param array<int, array<string, mixed>> $pool
     * @param array<string, true> $usedFp
     */
    private static function rollOneUniqueItem(array &$pool, array &$usedFp, ?Player $player): ?Item
    {
        while ($pool !== []) {
            $entry = RewardResolver::rollEntryRemove($pool);
            if ($entry === null) {
                break;
            }
            $fp = RewardResolver::fingerprintLootEntry($entry);
            if (isset($usedFp[$fp])) {
                continue;
            }
            $item = RewardResolver::resolveItem($entry, $player);
            if ($item === null) {
                continue;
            }
            $usedFp[$fp] = true;
            return $item;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $crate
     * @param list<string> $excludeFingerprints
     */
    public static function rollBonusReward(array $crate, ?Player $player = null, array $excludeFingerprints = []): ?Item
    {
        $pool = self::getPool($crate, "bonus");
        if ($pool === []) {
            return null;
        }
        if ($excludeFingerprints !== []) {
            $exclude = array_fill_keys($excludeFingerprints, true);
            $filtered = [];
            foreach ($pool as $e) {
                if (!is_array($e)) {
                    continue;
                }
                if (!isset($exclude[RewardResolver::fingerprintLootEntry($e)])) {
                    $filtered[] = $e;
                }
            }
            if ($filtered !== []) {
                $pool = $filtered;
            }
        }
        $entry = RewardResolver::rollEntry($pool);
        return $entry !== null ? RewardResolver::resolveItem($entry, $player) : null;
    }

    /**
     * @param array<string, mixed> $crate
     * @return string[]
     */
    public static function getAnimationPaneColors(array $crate): array
    {
        $colors = array_values(array_filter(array_map("strval", (array) ($crate["animation"]["pane-colors"] ?? [])), static fn(string $s): bool => trim($s) !== ""));
        return $colors !== [] ? $colors : ["black", "purple", "cyan"];
    }

    /**
     * @param array<string, mixed> $crate
     */
    public static function getAnimationSeconds(array $crate): int
    {
        return max(1, (int) ($crate["animation"]["seconds"] ?? 6));
    }

    /**
     * @param array<string, mixed> $crate
     */
    public static function getFinalAnimationSeconds(array $crate): int
    {
        return max(1, (int) ($crate["animation"]["final-seconds"] ?? $crate["animation"]["seconds"] ?? 6));
    }

    /**
     * @param array<string, mixed> $crate
     * @param int[] $default
     * @return int[]
     */
    public static function getAnimationPathForSlot(array $crate, int $slot, array $default = []): array
    {
        $paths = (array) ($crate["animation"]["paths"] ?? []);
        $raw = $paths[(string) $slot] ?? $paths[$slot] ?? $default;
        if (!is_array($raw)) {
            return $default;
        }

        $out = [];
        foreach ($raw as $candidate) {
            if (!is_numeric($candidate)) {
                continue;
            }
            $asInt = (int) $candidate;
            if ($asInt >= 0 && $asInt < 54) {
                $out[] = $asInt;
            }
        }

        return $out !== [] ? array_values(array_unique($out)) : $default;
    }

    /**
     * Optional extra pane slots to color while animating a reward slot.
     * Useful to "fill corners" for smoother looking trails.
     *
     * @param array<string, mixed> $crate
     * @param int[] $default
     * @return int[]
     */
    public static function getAnimationCornerFillForSlot(array $crate, int $slot, array $default = []): array
    {
        $fills = (array) ($crate["animation"]["corner-fill"] ?? []);
        $raw = $fills[(string) $slot] ?? $fills[$slot] ?? $default;
        if (!is_array($raw)) {
            return $default;
        }

        $out = [];
        foreach ($raw as $candidate) {
            if (!is_numeric($candidate)) {
                continue;
            }
            $asInt = (int) $candidate;
            if ($asInt >= 0 && $asInt < 54) {
                $out[] = $asInt;
            }
        }

        return $out !== [] ? array_values(array_unique($out)) : $default;
    }

    /**
     * @param array<string, mixed> $crate
     * @param string[] $default
     * @return string[]
     */
    public static function getUiLore(array $crate, string $key, array $default): array
    {
        $raw = $crate["ui"]["lore"][$key] ?? null;
        if (!is_array($raw)) {
            return $default;
        }

        $lines = array_values(array_filter(array_map("strval", $raw), static fn(string $line): bool => trim($line) !== ""));
        return $lines !== [] ? $lines : $default;
    }

    /**
     * @param array<string, mixed> $crate
     */
    public static function getUiLabel(array $crate, string $key, string $default): string
    {
        $raw = $crate["ui"]["labels"][$key] ?? null;
        return is_string($raw) && trim($raw) !== "" ? $raw : $default;
    }

    /**
     * @param array<string, mixed> $crate
     * @param string[] $default
     * @return string[]
     */
    public static function getSoundSequence(array $crate, string $key, array $default): array
    {
        $raw = $crate["sounds"][$key] ?? null;
        if (is_string($raw) && trim($raw) !== "") {
            return [trim($raw)];
        }
        if (!is_array($raw)) {
            return $default;
        }

        $sounds = array_values(array_filter(array_map("strval", $raw), static fn(string $sound): bool => trim($sound) !== ""));
        return $sounds !== [] ? $sounds : $default;
    }

    private static function ensureLoaded(): void
    {
        if (self::$crates === []) {
            self::reload();
        }
    }

    /**
     * @param array<string, mixed> $crate
     * @return string[]
     */
    private static function getIdentifiersForCrate(array $crate): array
    {
        $identifiers = [(string) ($crate["id"] ?? "")];
        foreach ((array) ($crate["aliases"] ?? []) as $alias) {
            if (is_string($alias) && trim($alias) !== "") {
                $identifiers[] = trim($alias);
            }
        }

        return array_values(array_unique(array_filter($identifiers, static fn(string $value): bool => $value !== "")));
    }

    /**
     * @param array<string, mixed> $crate
     * @return array<int, array<string, mixed>>
     */
    private static function getPool(array $crate, string $table): array
    {
        $entries = $crate["loot-tables"][strtolower($table)] ?? [];
        return is_array($entries) ? array_values(array_filter($entries, static fn(mixed $e): bool => is_array($e))) : [];
    }
}
