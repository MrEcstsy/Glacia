<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\lootboxes;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\RewardResolver;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\utils\Config;

final class LootboxRepository {

    /** @var array<string, array<string, mixed>> */
    private static array $boxes = [];

    public static function reload(): void
    {
        self::$boxes = [];

        $directory = Loader::getInstance()->getDataFolder() . "lootboxes";
        if (!is_dir($directory)) {
            @mkdir($directory, 0777, true);
        }

        foreach (glob($directory . DIRECTORY_SEPARATOR . "*.yml") ?: [] as $file) {
            $config = new Config($file, Config::YAML);
            $data = $config->getAll();
            if (!is_array($data)) {
                continue;
            }

            $data = self::normalizeBoxData($data);

            $id = strtolower((string) pathinfo($file, PATHINFO_FILENAME));
            $data["id"] = strtolower((string) ($data["id"] ?? $id));
            $data["name"] = (string) ($data["name"] ?? self::formatId($id));
            self::$boxes[$data["id"]] = $data;
        }
    }

    /**
     * @return string[]
     */
    public static function getBundledResourceFiles(): array
    {
        $resourceDirectory = dirname(__DIR__, 5) . DIRECTORY_SEPARATOR . "resources" . DIRECTORY_SEPARATOR . "lootboxes";
        $files = [];

        foreach (glob($resourceDirectory . DIRECTORY_SEPARATOR . "*.yml") ?: [] as $file) {
            $files[] = "lootboxes/" . basename($file);
        }

        sort($files, SORT_NATURAL | SORT_FLAG_CASE);
        return $files;
    }

    /**
     * @return string[]
     */
    public static function getBoxIds(): array
    {
        self::ensureLoaded();
        return array_keys(self::$boxes);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function getBox(string $id): ?array
    {
        self::ensureLoaded();
        return self::$boxes[strtolower($id)] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function getBoxByLookup(string $lookup): ?array
    {
        self::ensureLoaded();

        $normalizedLookup = self::normalizeIdentifier($lookup);
        if ($normalizedLookup === '') {
            return null;
        }

        foreach (self::$boxes as $box) {
            foreach (self::getIdentifiersForBox($box) as $identifier) {
                if (self::normalizeIdentifier($identifier) === $normalizedLookup) {
                    return $box;
                }
            }
        }

        $matches = [];
        foreach (self::$boxes as $box) {
            foreach (self::getIdentifiersForBox($box) as $identifier) {
                if (str_starts_with(self::normalizeIdentifier($identifier), $normalizedLookup)) {
                    $matches[$box["id"]] = $box;
                    break;
                }
            }
        }

        return count($matches) === 1 ? array_values($matches)[0] : null;
    }

    /**
     * @return array<string, string[]>
     */
    public static function getLookupMap(): array
    {
        self::ensureLoaded();

        $map = [];
        foreach (self::$boxes as $box) {
            $map[(string) $box["id"]] = self::getIdentifiersForBox($box);
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $box
     * @return Item[]
     */
    public static function rollRewards(array $box, ?Player $player = null): array
    {
        $results = [];
        $explicitJackpotRoll = false;
        /** @var array<string, array<string, true>> $usedFingerprintsByPool */
        $usedFingerprintsByPool = [];

        foreach (self::getRollDefinitions($box) as $roll) {
            $poolName = self::normalizePoolName((string) ($roll["pool"] ?? "rewards"));
            $pool = self::getPool($box, $poolName);
            if ($pool === []) {
                continue;
            }

            $chance = max(0, min(100, (int) ($roll["chance"] ?? 100)));
            if ($chance < 100 && mt_rand(1, 100) > $chance) {
                continue;
            }

            if ($poolName === "jackpot-rewards") {
                $explicitJackpotRoll = true;
            }

            $mode = strtolower((string) ($roll["mode"] ?? "random"));
            if ($mode === "all") {
                foreach ($pool as $entry) {
                    $fingerprint = RewardResolver::fingerprintLootEntry($entry);
                    if (isset($usedFingerprintsByPool[$poolName][$fingerprint])) {
                        continue;
                    }

                    $item = RewardResolver::resolveItem($entry, $player);
                    if ($item !== null) {
                        $usedFingerprintsByPool[$poolName][$fingerprint] = true;
                        $results[] = $item;
                    }
                }
                continue;
            }

            $count = max(0, GeneralUtils::parseRandomNumber($roll["count"] ?? 1));
            for ($i = 0; $i < $count; $i++) {
                $entry = self::rollUniqueEntry($pool, $usedFingerprintsByPool[$poolName]);
                if ($entry === null) {
                    continue;
                }

                $item = RewardResolver::resolveItem($entry, $player);
                if ($item !== null) {
                    $results[] = $item;
                }
            }
        }

        $jackpotPool = self::getPool($box, "jackpot-rewards");
        if ($jackpotPool !== [] && !$explicitJackpotRoll) {
            $entry = self::rollUniqueEntry($jackpotPool, $usedFingerprintsByPool["jackpot-rewards"]);
            $item = $entry !== null ? RewardResolver::resolveItem($entry, $player) : null;
            if ($item !== null) {
                $results[] = $item;
            }
        }

        if ($results === []) {
            $fallbackPool = self::getPool($box, "rewards");
            $fallback = self::rollUniqueEntry($fallbackPool, $usedFingerprintsByPool["rewards"]);
            $fallbackItem = $fallback !== null ? RewardResolver::resolveItem($fallback, $player) : null;
            if ($fallbackItem !== null) {
                $results[] = $fallbackItem;
            }
        }

        return array_slice($results, 0, 7);
    }

    /**
     * @param array<string, mixed> $box
     * @return Item[]
     */
    public static function getDisplayItems(array $box, ?Player $player = null): array
    {
        $items = [];

        foreach (["rewards", "jackpot-rewards", "bonus-rewards"] as $poolName) {
            foreach (self::getPool($box, $poolName) as $entry) {
                $item = RewardResolver::resolveItem($entry, $player);
                if ($item !== null) {
                    $items[] = $item;
                }
            }
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $box
     * @return array<int, array{name: string, entries: array<int, array<string, mixed>>}>
     */
    public static function getPreviewGroups(array $box): array
    {
        $groups = [];

        foreach (["rewards", "jackpot-rewards", "bonus-rewards"] as $poolName) {
            $entries = self::getPool($box, $poolName);
            if ($entries === []) {
                continue;
            }

            $groups[] = [
                "name" => self::formatId($poolName),
                "entries" => $entries
            ];
        }

        return $groups;
    }

    private static function ensureLoaded(): void
    {
        if (self::$boxes === []) {
            self::reload();
        }
    }

    /**
     * @param array<string, mixed> $box
     * @return array<int, array<string, mixed>>
     */
    private static function getRollDefinitions(array $box): array
    {
        $rolls = $box["rolls"] ?? null;
        if (is_array($rolls) && $rolls !== []) {
            return array_values(array_filter($rolls, static fn(mixed $roll): bool => is_array($roll)));
        }

        return [[
            "pool" => "rewards",
            "count" => $box["reward-count"] ?? 1,
            "chance" => 100
        ]];
    }

    /**
     * @param array<string, mixed> $box
     * @return array<int, array<string, mixed>>
     */
    private static function getPool(array $box, string $pool): array
    {
        $key = self::normalizePoolName($pool);

        $entries = $box[$key] ?? [];
        return is_array($entries) ? array_values(array_filter($entries, static fn(mixed $entry): bool => is_array($entry))) : [];
    }

    private static function normalizePoolName(string $pool): string
    {
        return match (strtolower($pool)) {
            "reward", "rewards", "main", "main-rewards" => "rewards",
            "jackpot", "jackpot-rewards" => "jackpot-rewards",
            "bonus", "bonus-rewards" => "bonus-rewards",
            default => $pool,
        };
    }

    private static function formatId(string $id): string
    {
        return ucwords(str_replace(["_", "-"], " ", $id));
    }

    /**
     * @param array<string, mixed> $box
     * @return string[]
     */
    private static function getIdentifiersForBox(array $box): array
    {
        $identifiers = [(string) ($box["id"] ?? "")];

        if (isset($box["aliases"]) && is_array($box["aliases"])) {
            foreach ($box["aliases"] as $alias) {
                if (is_string($alias) && trim($alias) !== '') {
                    $identifiers[] = trim($alias);
                }
            }
        }

        return array_values(array_unique(array_filter($identifiers, static fn(string $value): bool => $value !== '')));
    }

    private static function normalizeIdentifier(string $value): string
    {
        return strtolower(str_replace([' ', '_', '-'], '', trim($value)));
    }

    /**
     * @param array<int, array<string, mixed>> $pool
     * @param array<string, true>|null $usedFingerprints
     * @return array<string, mixed>|null
     */
    private static function rollUniqueEntry(array &$pool, ?array &$usedFingerprints): ?array
    {
        $usedFingerprints ??= [];
        while ($pool !== []) {
            $entry = RewardResolver::rollEntryRemove($pool);
            if ($entry === null) {
                return null;
            }

            $fingerprint = RewardResolver::fingerprintLootEntry($entry);
            if (isset($usedFingerprints[$fingerprint])) {
                continue;
            }

            $usedFingerprints[$fingerprint] = true;
            return $entry;
        }

        return null;
    }

    /**
     * Repairs common pool-entry mistakes such as putting `- jackpot-rewards:`
     * on an entry inside the main `rewards:` list.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function normalizeBoxData(array $data): array
    {
        $poolMarkers = [
            "rewards" => "rewards",
            "reward" => "rewards",
            "jackpot-rewards" => "jackpot-rewards",
            "jackpot_rewards" => "jackpot-rewards",
            "jackpot" => "jackpot-rewards",
            "bonus-rewards" => "bonus-rewards",
            "bonus_rewards" => "bonus-rewards",
            "bonus" => "bonus-rewards",
        ];

        $normalizedPools = [
            "rewards" => [],
            "jackpot-rewards" => [],
            "bonus-rewards" => [],
        ];

        foreach (array_keys($normalizedPools) as $poolName) {
            $entries = $data[$poolName] ?? [];
            if (!is_array($entries)) {
                continue;
            }

            foreach ($entries as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $targetPool = $poolName;
                foreach ($poolMarkers as $marker => $mappedPool) {
                    if (!array_key_exists($marker, $entry)) {
                        continue;
                    }

                    $targetPool = $mappedPool;
                    unset($entry[$marker]);
                    break;
                }

                if ($entry === []) {
                    continue;
                }

                $normalizedPools[$targetPool][] = $entry;
            }
        }

        foreach ($normalizedPools as $poolName => $entries) {
            $data[$poolName] = $entries;
        }

        return $data;
    }
}
