<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\manager;

use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\GeneralUtils;

final class EnchantTuningManager
{
    /** @var array<string, mixed> */
    private static array $config = [];

    public static function loadFromConfig(): void
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "ce_values.yml");
        self::$config = $config?->getAll() ?? [];
    }

    public static function getValue(string $enchantId, string $key, mixed $default = null): mixed
    {
        $enchantId = strtolower(trim($enchantId));
        $entry = (array) (self::$config["enchants"][$enchantId] ?? []);
        return $entry[$key] ?? $default;
    }

    public static function getInt(string $enchantId, string $key, int $default = 0): int
    {
        return (int) self::getValue($enchantId, $key, $default);
    }

    public static function getFloat(string $enchantId, string $key, float $default = 0.0): float
    {
        return (float) self::getValue($enchantId, $key, $default);
    }

    public static function getBool(string $enchantId, string $key, bool $default = false): bool
    {
        return (bool) self::getValue($enchantId, $key, $default);
    }

    public static function getLevelMap(string $enchantId, string $key): array
    {
        $value = self::getValue($enchantId, $key, []);
        return is_array($value) ? $value : [];
    }

    public static function getLevelInt(string $enchantId, string $key, int $level, int $default = 0): int
    {
        $map = self::getLevelMap($enchantId, $key);
        return (int) ($map[(string) $level] ?? $default);
    }

    public static function getLevelFloat(string $enchantId, string $key, int $level, float $default = 0.0): float
    {
        $map = self::getLevelMap($enchantId, $key);
        return (float) ($map[(string) $level] ?? $default);
    }

    public static function isProcLuckEnabled(string $enchantId, bool $default = true): bool
    {
        $entry = (array) (self::$config["enchants"][strtolower($enchantId)] ?? []);
        $procLuck = (array) ($entry["proc-luck"] ?? []);
        return (bool) ($procLuck["enabled"] ?? $default);
    }

    public static function getProcLuckPerMiss(string $enchantId, int $default): int
    {
        $entry = (array) (self::$config["enchants"][strtolower($enchantId)] ?? []);
        $procLuck = (array) ($entry["proc-luck"] ?? []);
        return max(0, (int) ($procLuck["luck-per-miss"] ?? $default));
    }

    public static function getProcLuckMaxBonus(string $enchantId, int $default): int
    {
        $entry = (array) (self::$config["enchants"][strtolower($enchantId)] ?? []);
        $procLuck = (array) ($entry["proc-luck"] ?? []);
        return max(0, (int) ($procLuck["max-bonus"] ?? $default));
    }

    public static function getProcBaseChance(string $enchantId, int $fallback): int
    {
        return max(0, min(100, self::getInt($enchantId, "base-chance", $fallback)));
    }

    /**
     * @return string[]
     */
    public static function getConflicts(string $enchantId): array
    {
        $entry = (array) (self::$config["enchants"][strtolower($enchantId)] ?? []);
        return array_values(array_filter(array_map(
            static fn(mixed $id): string => strtolower(trim((string) $id)),
            (array) ($entry["conflicts-with"] ?? [])
        ), static fn(string $id): bool => $id !== ""));
    }

    public static function isWeaponProcGateEnabled(): bool
    {
        $entry = (array) (self::$config["settings"]["weapon-proc-gate"] ?? []);
        return (bool) ($entry["enabled"] ?? true);
    }

    public static function getWeaponProcGateMinHits(): int
    {
        $entry = (array) (self::$config["settings"]["weapon-proc-gate"] ?? []);
        return max(0, (int) ($entry["min-hits"] ?? 3));
    }

    public static function getWeaponProcGateMaxHits(): int
    {
        $entry = (array) (self::$config["settings"]["weapon-proc-gate"] ?? []);
        return max(0, (int) ($entry["max-hits"] ?? 5));
    }

    public static function getSettingsValue(string $key, mixed $default = null): mixed
    {
        $entry = (array) (self::$config["settings"] ?? []);
        return $entry[$key] ?? $default;
    }

    /**
     * @return array<string|int, mixed>
     */
    public static function getSettingsArray(string $key): array
    {
        $value = self::getSettingsValue($key, []);
        return is_array($value) ? $value : [];
    }
}

