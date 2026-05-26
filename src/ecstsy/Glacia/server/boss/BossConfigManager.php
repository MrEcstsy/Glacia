<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\boss;

use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\utils\Config;
use function array_is_list;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_numeric;
use function is_scalar;
use function is_string;
use function str_replace;
use function strtolower;
use function trim;

final class BossConfigManager
{
    /** @var array<string, array<string, mixed>> */
    private static array $bosses = [];

    public static function loadFromConfig(): void
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "bosses.yml");
        if (!$config instanceof Config) {
            self::$bosses = [];
            return;
        }

        $root = $config->get("bosses", []);
        if (!is_array($root)) {
            Loader::getInstance()->getLogger()->warning("[BossConfig] bosses.yml has an invalid 'bosses' section. Falling back to defaults.");
            self::$bosses = [];
            return;
        }

        $loaded = [];
        foreach ($root as $bossId => $data) {
            if (!is_string($bossId) || !is_array($data)) {
                continue;
            }

            $normalizedId = self::normalizeId($bossId);
            if ($normalizedId === "") {
                continue;
            }

            $loaded[$normalizedId] = $data;
        }

        self::$bosses = $loaded;
    }

    /**
     * @param array<string, mixed> $defaults
     * @return array<string, mixed>
     */
    public static function getBossConfig(string $bossId, array $defaults): array
    {
        $bossId = self::normalizeId($bossId);
        $configured = self::$bosses[$bossId] ?? [];
        if (!is_array($configured)) {
            return $defaults;
        }

        return self::mergeTyped($defaults, $configured);
    }

    /**
     * @param array<string, mixed> $defaults
     * @param array<string, mixed> $configured
     * @return array<string, mixed>
     */
    private static function mergeTyped(array $defaults, array $configured): array
    {
        $merged = $defaults;

        foreach ($defaults as $key => $defaultValue) {
            if (!array_key_exists($key, $configured)) {
                continue;
            }

            $userValue = $configured[$key];

            if (is_array($defaultValue)) {
                if (!is_array($userValue)) {
                    continue;
                }

                if (array_is_list($defaultValue)) {
                    $merged[$key] = self::normalizeList($defaultValue, $userValue);
                    continue;
                }

                $merged[$key] = self::mergeTyped($defaultValue, $userValue);
                continue;
            }

            $coerced = self::coerceScalar($defaultValue, $userValue);
            if ($coerced !== null) {
                $merged[$key] = $coerced;
            }
        }

        return $merged;
    }

    /**
     * @param list<mixed> $defaultList
     * @param list<mixed> $userList
     * @return list<mixed>
     */
    private static function normalizeList(array $defaultList, array $userList): array
    {
        if ($userList === []) {
            return [];
        }

        if ($defaultList === []) {
            return array_values($userList);
        }

        $sample = $defaultList[0];
        $normalized = [];

        foreach ($userList as $entry) {
            if (is_array($sample)) {
                if (!is_array($entry)) {
                    continue;
                }

                if (array_is_list($sample)) {
                    $normalized[] = self::normalizeList($sample, $entry);
                } else {
                    $normalized[] = self::mergeTyped($sample, $entry);
                }
                continue;
            }

            $coerced = self::coerceScalar($sample, $entry);
            if ($coerced !== null) {
                $normalized[] = $coerced;
            }
        }

        return $normalized;
    }

    private static function coerceScalar(mixed $defaultValue, mixed $userValue): mixed
    {
        if (is_int($defaultValue)) {
            return is_numeric($userValue) ? (int) $userValue : null;
        }

        if (is_float($defaultValue)) {
            return is_numeric($userValue) ? (float) $userValue : null;
        }

        if (is_bool($defaultValue)) {
            return is_bool($userValue) ? $userValue : null;
        }

        if (is_string($defaultValue)) {
            return is_scalar($userValue) ? (string) $userValue : null;
        }

        return null;
    }

    private static function normalizeId(string $bossId): string
    {
        return strtolower(trim(str_replace(" ", "_", $bossId)));
    }
}
