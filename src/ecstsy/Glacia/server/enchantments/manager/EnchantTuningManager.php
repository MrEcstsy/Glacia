<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\manager;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\enchantments\CustomEnchantments;
use ecstsy\MartianUtilities\utils\GeneralUtils;

final class EnchantTuningManager
{
    /** @var array<string, mixed> */
    private static array $config = [];
    /** @var array<string, true> */
    private static array $warnings = [];

    public static function loadFromConfig(): void
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "ce_values.yml");
        self::$config = $config?->getAll() ?? [];
        self::$warnings = [];
        EnchantTuningMath::clearCache();
    }

    public static function isEnabled(string $enchantId, bool $default = true): bool
    {
        $entry = self::getEntry($enchantId);
        if ($entry === []) {
            self::warnDefault("enchants." . strtolower(trim($enchantId)) . ".enabled", $default, "missing-enchant");
            return $default;
        }

        return (bool) ($entry["enabled"] ?? $default);
    }

    public static function chance(string $enchantId, int $level, float $default = 0.0): float
    {
        $entry = self::getEntry($enchantId);
        $pathBase = "enchants." . strtolower(trim($enchantId));
        foreach ([["proc", "chance"], ["proc", "chance-percent"], ["chance"], ["chance-percent"]] as $path) {
            $node = self::getNested($entry, $path);
            if ($node !== null) {
                return max(0.0, min(100.0, self::resolveNode($node, $level, $default, $pathBase . "." . implode(".", $path))));
            }
        }

        return max(0.0, min(100.0, self::resolveNumericWithLegacy($enchantId, "chance", $level, $default)));
    }

    public static function value(string $enchantId, string $key, int $level, float $default = 0.0): float
    {
        $entry = self::getEntry($enchantId);
        $pathBase = "enchants." . strtolower(trim($enchantId));
        foreach (self::valuePathCandidates($key) as $path) {
            $node = self::getNested($entry, $path);
            if ($node !== null) {
                return self::resolveNode($node, $level, $default, $pathBase . "." . implode(".", $path));
            }
        }

        return self::resolveNumericWithLegacy($enchantId, $key, $level, $default);
    }

    public static function valueInt(string $enchantId, string $key, int $level, int $default = 0): int
    {
        return (int) round(self::value($enchantId, $key, $level, (float) $default));
    }

    public static function cooldownSeconds(string $enchantId, int $level, int $default = 0): int
    {
        $entry = self::getEntry($enchantId);
        $basePath = "enchants." . strtolower(trim($enchantId));

        $procSeconds = self::getNested($entry, ["proc", "cooldown-seconds"]);
        if ($procSeconds !== null) {
            return max(0, (int) round(self::resolveNode($procSeconds, $level, $default, $basePath . ".proc.cooldown-seconds")));
        }

        $procTicks = self::getNested($entry, ["proc", "cooldown-ticks"]);
        if ($procTicks !== null) {
            $ticks = max(0, (int) round(self::resolveNode($procTicks, $level, $default * 20, $basePath . ".proc.cooldown-ticks")));
            return (int) ceil($ticks / 20);
        }

        if (array_key_exists("cooldown-seconds", $entry)) {
            return max(0, (int) round(self::resolveNode($entry["cooldown-seconds"], $level, $default, $basePath . ".cooldown-seconds")));
        }

        if (array_key_exists("cooldown-ticks", $entry)) {
            $ticks = max(0, (int) round(self::resolveNode($entry["cooldown-ticks"], $level, $default * 20, $basePath . ".cooldown-ticks")));
            return (int) ceil($ticks / 20);
        }

        $legacy = self::getLevelValue($enchantId, "cooldown-seconds-by-level", $level, null);
        if ($legacy === null) {
            $legacy = self::getLevelValue($enchantId, "cooldown", $level, null);
        }
        if ($legacy === null && array_key_exists("cooldown", $entry)) {
            $legacy = $entry["cooldown"];
        }

        if ($legacy !== null) {
            return max(0, (int) round(self::resolveNode($legacy, $level, $default, $basePath . ".cooldown")));
        }

        self::warnDefault($basePath . ".cooldown-seconds", $default, "missing");
        return max(0, $default);
    }

    public static function cooldownTicks(string $enchantId, int $level, int $default = 0): int
    {
        $entry = self::getEntry($enchantId);
        $basePath = "enchants." . strtolower(trim($enchantId));

        $procTicks = self::getNested($entry, ["proc", "cooldown-ticks"]);
        if ($procTicks !== null) {
            return max(0, (int) round(self::resolveNode($procTicks, $level, $default, $basePath . ".proc.cooldown-ticks")));
        }

        $procSeconds = self::getNested($entry, ["proc", "cooldown-seconds"]);
        if ($procSeconds !== null) {
            return max(0, (int) round(self::resolveNode($procSeconds, $level, (int) ceil($default / 20), $basePath . ".proc.cooldown-seconds"))) * 20;
        }

        if (array_key_exists("cooldown-ticks", $entry)) {
            return max(0, (int) round(self::resolveNode($entry["cooldown-ticks"], $level, $default, $basePath . ".cooldown-ticks")));
        }

        if (array_key_exists("cooldown-seconds", $entry)) {
            return self::cooldownSeconds($enchantId, $level, (int) ceil($default / 20)) * 20;
        }

        return self::cooldownSeconds($enchantId, $level, (int) ceil($default / 20)) * 20;
    }

    public static function getValue(string $enchantId, string $key, mixed $default = null): mixed
    {
        $enchantId = strtolower(trim($enchantId));
        $entry = self::getEntry($enchantId);
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

    public static function getBalanceValue(string $key, mixed $default = null): mixed
    {
        $balance = self::getSettingsArray("balance");
        return $balance[$key] ?? $default;
    }

    public static function getBalanceFloat(string $key, float $default = 0.0): float
    {
        return (float) self::getBalanceValue($key, $default);
    }

    public static function getBalanceStringList(string $key): array
    {
        $value = self::getBalanceValue($key, []);
        if (is_string($value)) {
            $trimmed = trim($value);
            return $trimmed !== "" ? [$trimmed] : [];
        }

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn(mixed $entry): string => strtolower(trim((string) $entry)),
            $value
        ), static fn(string $entry): bool => $entry !== ""));
    }

    /**
     * @return array<string|int, mixed>
     */
    public static function getArmorSetSettings(string $setId): array
    {
        $setId = strtolower(trim($setId));
        if ($setId === "") {
            return [];
        }

        $settings = self::getSettingsArray("armor-sets");
        $entry = $settings[$setId] ?? null;
        if (is_array($entry)) {
            return $entry;
        }

        // Backwards compatibility for older misplaced configs.
        $legacy = self::getSettingsArray("charm-upgrades");
        $legacySets = is_array($legacy["armor-sets"] ?? null) ? $legacy["armor-sets"] : [];
        $legacyEntry = $legacySets[$setId] ?? null;
        return is_array($legacyEntry) ? $legacyEntry : [];
    }

    public static function getArmorSetValue(string $setId, string $key, mixed $default = null): mixed
    {
        $entry = self::getArmorSetSettings($setId);
        return $entry[$key] ?? $default;
    }

    public static function getArmorSetFloat(string $setId, string $key, float $default = 0.0): float
    {
        return (float) self::getArmorSetValue($setId, $key, $default);
    }

    public static function getArmorSetInt(string $setId, string $key, int $default = 0): int
    {
        return (int) round((float) self::getArmorSetValue($setId, $key, $default));
    }

    public static function getArmorSetBool(string $setId, string $key, bool $default = false): bool
    {
        return (bool) self::getArmorSetValue($setId, $key, $default);
    }

    public static function getBool(string $enchantId, string $key, bool $default = false): bool
    {
        return (bool) self::getValue($enchantId, $key, $default);
    }

    public static function getLevelMap(string $enchantId, string $key): array
    {
        $value = self::getValue($enchantId, $key, []);
        if (is_array($value)) {
            return $value;
        }

        $levels = self::getLevels($enchantId);
        $derived = [];
        foreach ($levels as $level => $row) {
            if (!is_array($row) || !array_key_exists($key, $row)) {
                $aliases = self::levelKeyAliases($key);
                $matched = false;
                foreach ($aliases as $alias) {
                    if (!array_key_exists($alias, $row)) {
                        continue;
                    }

                    $derived[(string) $level] = $row[$alias];
                    $matched = true;
                    break;
                }

                if (!$matched) {
                    continue;
                }
                continue;
            }
            $derived[(string) $level] = $row[$key];
        }

        return $derived;
    }

    public static function getLevelInt(string $enchantId, string $key, int $level, int $default = 0): int
    {
        return (int) round(self::resolveNode(self::getLevelValue($enchantId, $key, $level, $default), $level, (float) $default, "enchants." . strtolower(trim($enchantId)) . "." . $key));
    }

    public static function getLevelFloat(string $enchantId, string $key, int $level, float $default = 0.0): float
    {
        return self::resolveNode(self::getLevelValue($enchantId, $key, $level, $default), $level, $default, "enchants." . strtolower(trim($enchantId)) . "." . $key);
    }

    public static function getString(string $enchantId, string $key, string $default = ""): string
    {
        $value = self::getValue($enchantId, $key, $default);
        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * @return list<string>
     */
    public static function getStringList(string $enchantId, string $key): array
    {
        $value = self::getValue($enchantId, $key, []);
        if (is_string($value)) {
            $trimmed = trim($value);
            return $trimmed !== "" ? [$trimmed] : [];
        }

        if (!is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $line) {
            $text = trim((string) $line);
            if ($text !== "") {
                $out[] = $text;
            }
        }

        return $out;
    }

    public static function getLevelCooldown(string $enchantId, int $level, int $default = 0): int
    {
        return self::cooldownSeconds($enchantId, $level, $default);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function getLevelEffects(string $enchantId, int $level): array
    {
        $levels = self::getLevels($enchantId);
        $levelRow = $levels[(string) $level] ?? null;
        if (!is_array($levelRow)) {
            return [];
        }

        $effects = $levelRow["effects"] ?? [];
        return is_array($effects) ? array_values(array_filter($effects, "is_array")) : [];
    }

    /**
     * @return array{0: float, 1: float}|null
     */
    public static function parseNumericRange(mixed $value): ?array
    {
        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric(trim($value)))) {
            $number = (float) $value;
            return [$number, $number];
        }

        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if (preg_match('/^\{?\s*(-?\d+(?:\.\d+)?)\s*-\s*(-?\d+(?:\.\d+)?)\s*\}?$/', $trimmed, $matches) !== 1) {
            return null;
        }

        $min = (float) $matches[1];
        $max = (float) $matches[2];
        if ($max < $min) {
            [$min, $max] = [$max, $min];
        }

        return [$min, $max];
    }

    public static function rollNumericRange(mixed $value, float $default): float
    {
        $range = self::parseNumericRange($value);
        if ($range === null) {
            return $default;
        }

        [$min, $max] = $range;
        if ($min === $max) {
            return $min;
        }

        if (floor($min) !== $min || floor($max) !== $max) {
            return $min + (lcg_value() * ($max - $min));
        }

        return (float) mt_rand((int) $min, (int) $max);
    }

    /**
     * @return array<string, mixed>
     */
    private static function getEntry(string $enchantId): array
    {
        $normalized = strtolower(trim($enchantId));
        $legacyId = CustomEnchantments::getLegacyIdForName($normalized);
        if (isset(self::$config["enchants"]) && is_array(self::$config["enchants"])) {
            $entry = self::$config["enchants"][$normalized] ?? null;
            if (is_array($entry)) {
                return (array) $entry;
            }
            if ($legacyId !== null) {
                $legacyEntry = self::$config["enchants"][$legacyId] ?? null;
                if (is_array($legacyEntry)) {
                    return (array) $legacyEntry;
                }
            }
            return [];
        }

        $entry = self::$config[$normalized] ?? ($legacyId !== null ? (self::$config[$legacyId] ?? null) : null);
        return is_array($entry) ? (array) $entry : [];
    }

    /**
     * @return array<string, mixed>
     */
    private static function getLevels(string $enchantId): array
    {
        $entry = self::getEntry($enchantId);
        return is_array($entry["levels"] ?? null) ? (array) $entry["levels"] : [];
    }

    private static function getLevelValue(string $enchantId, string $key, int $level, mixed $default = null): mixed
    {
        $levels = self::getLevels($enchantId);
        $levelRow = $levels[(string) $level] ?? null;
        if (is_array($levelRow) && array_key_exists($key, $levelRow)) {
            return $levelRow[$key];
        }

        if (is_array($levelRow)) {
            foreach (self::levelKeyAliases($key) as $alias) {
                if (array_key_exists($alias, $levelRow)) {
                    return $levelRow[$alias];
                }
            }
        }

        $map = self::getLevelMap($enchantId, $key);
        return $map[(string) $level] ?? $default;
    }

    /**
     * @return list<string>
     */
    private static function levelKeyAliases(string $key): array
    {
        $aliases = [];

        if (
            $key === "chance-by-level" ||
            str_ends_with($key, "chance-by-level") ||
            str_ends_with($key, "chance-percent-by-level") ||
            str_ends_with($key, "proc-percent-by-level")
        ) {
            $aliases[] = "chance";
        }

        if ($key === "cooldown-by-level" || str_ends_with($key, "cooldown-seconds-by-level")) {
            $aliases[] = "cooldown";
        }

        if (str_ends_with($key, "-by-level")) {
            $aliases[] = substr($key, 0, -strlen("-by-level"));
        }

        return array_values(array_unique(array_filter($aliases, static fn(string $alias): bool => $alias !== "")));
    }

    public static function isProcLuckEnabled(string $enchantId, bool $default = true): bool
    {
        $entry = self::getEntry($enchantId);
        $procLuck = (array) ($entry["proc-luck"] ?? []);
        if (array_key_exists("enabled", $procLuck)) {
            return (bool) $procLuck["enabled"];
        }

        return self::usesLegacyProcLuck($enchantId) ? $default : false;
    }

    public static function getProcLuckPerMiss(string $enchantId, int $default): int
    {
        $entry = self::getEntry($enchantId);
        $procLuck = (array) ($entry["proc-luck"] ?? []);
        return max(0, (int) ($procLuck["luck-per-miss"] ?? $default));
    }

    public static function getProcLuckMaxBonus(string $enchantId, int|float $default): float
    {
        $entry = self::getEntry($enchantId);
        $procLuck = (array) ($entry["proc-luck"] ?? []);
        return max(0.0, (float) ($procLuck["max-bonus"] ?? $default));
    }

    /**
     * Extra proc % added AFTER the enchant handler computes its level-based chance (clamped per roll).
     * Use 0 or omit base-chance to rely only on handler + chance-by-level.
     */
    public static function getAdditiveBaseChancePercent(string $enchantId): float
    {
        if (!self::usesLegacyAdditiveBaseChance($enchantId)) {
            return 0.0;
        }

        $entry = self::getEntry($enchantId);
        if (!array_key_exists("base-chance", $entry)) {
            return 0.0;
        }

        return max(0.0, min(100.0, (float) $entry["base-chance"]));
    }

    /**
     * @return string[]
     */
    public static function getConflicts(string $enchantId): array
    {
        $entry = self::getEntry($enchantId);
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

    /**
     * @return list<string>
     */
    public static function getWeaponProcGateExemptEnchants(): array
    {
        $entry = (array) (self::$config["settings"]["weapon-proc-gate"] ?? []);
        $value = $entry["exempt-enchants"] ?? [];
        if (is_string($value)) {
            $trimmed = strtolower(trim($value));
            return $trimmed !== "" ? [$trimmed] : [];
        }

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn(mixed $id): string => strtolower(trim((string) $id)),
            $value
        ), static fn(string $id): bool => $id !== ""));
    }

    public static function isWeaponProcGateExempt(string $enchantId): bool
    {
        $normalized = strtolower(trim($enchantId));
        if (!self::usesLegacyWeaponProcGate($normalized)) {
            return true;
        }

        return in_array($normalized, self::getWeaponProcGateExemptEnchants(), true);
    }

    public static function usesLegacyWeaponProcGate(string $enchantId): bool
    {
        return self::getProcLegacyFlag($enchantId, "weapon-proc-gate", self::shouldUseLegacyProcDefaults($enchantId));
    }

    public static function usesLegacyWeaponProcCap(string $enchantId): bool
    {
        return self::getProcLegacyFlag($enchantId, "weapon-proc-cap", self::shouldUseLegacyProcDefaults($enchantId));
    }

    public static function usesLegacyAdditiveBaseChance(string $enchantId): bool
    {
        return self::getProcLegacyFlag($enchantId, "additive-base-chance", self::shouldUseLegacyProcDefaults($enchantId));
    }

    public static function usesLegacyProcLuck(string $enchantId): bool
    {
        return self::getProcLegacyFlag($enchantId, "proc-luck", self::shouldUseLegacyProcDefaults($enchantId));
    }

    public static function usesLegacyRuneBonus(string $enchantId): bool
    {
        return self::getProcLegacyFlag($enchantId, "rune-bonus", self::shouldUseLegacyProcDefaults($enchantId));
    }

    public static function usesLegacyCombatBonus(string $enchantId): bool
    {
        return self::getProcLegacyFlag($enchantId, "combat-bonus", self::shouldUseLegacyProcDefaults($enchantId));
    }

    public static function usesLegacyRuneswellBonus(string $enchantId): bool
    {
        return self::getProcLegacyFlag($enchantId, "runeswell-bonus", self::shouldUseLegacyProcDefaults($enchantId));
    }

    public static function usesLegacySoulGemCost(string $enchantId): bool
    {
        return self::getProcLegacyFlag($enchantId, "soul-gem-cost", self::shouldUseLegacyProcDefaults($enchantId));
    }

    public static function getStackableArmorPercentCap(): float
    {
        return max(0.0, self::getBalanceFloat("stackable-armor-percent-cap", 5.0));
    }

    public static function getWeaponProcChanceCapPercent(): float
    {
        return max(0.0, self::getBalanceFloat("weapon-proc-percent-cap", 2.5));
    }

    public static function getEnchantExtraDamageCap(): float
    {
        return max(0.0, self::getBalanceFloat("extra-damage-cap", 5.0));
    }

    public static function getPerEnchantAddedDamageCap(string $enchantId, int $level, ?float $default = null): ?float
    {
        $value = self::getLevelValue($enchantId, "max-added-damage-by-level", $level, null);
        if ($value === null) {
            $value = self::getValue($enchantId, "max-added-damage", $default);
        }

        if ($value === null || $value === "") {
            return $default;
        }

        return max(0.0, (float) $value);
    }

    public static function getLifestealMinHeal(): float
    {
        return max(0.0, self::getBalanceFloat("lifesteal-min-heal", 2.0));
    }

    public static function getVampireMinHeal(): float
    {
        return max(0.0, self::getBalanceFloat("vampire-min-heal", 2.0));
    }

    public static function getDodgeProcCapPercent(): float
    {
        return max(0.0, self::getBalanceFloat("dodge-percent-cap", 2.0));
    }

    private static function resolveNumericWithLegacy(string $enchantId, string $key, int $level, float $default): float
    {
        $entry = self::getEntry($enchantId);
        $path = "enchants." . strtolower(trim($enchantId)) . "." . $key;

        if (array_key_exists($key, $entry)) {
            return self::resolveNode($entry[$key], $level, $default, $path);
        }

        $legacy = self::getLevelValue($enchantId, $key, $level, null);
        if ($legacy === null) {
            $legacy = self::getLevelValue($enchantId, $key . "-by-level", $level, null);
        }
        if ($legacy !== null) {
            return self::resolveNode($legacy, $level, $default, $path);
        }

        self::warnDefault($path, $default, "missing");
        return $default;
    }

    /**
     * @param list<string> $path
     */
    private static function getNested(array $entry, array $path): mixed
    {
        $current = $entry;
        foreach ($path as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }
            $current = $current[$segment];
        }

        return $current;
    }

    /**
     * @return list<list<string>>
     */
    private static function valuePathCandidates(string $key): array
    {
        $normalized = strtolower(trim($key));
        $candidates = [
            ["values", $normalized],
        ];

        if ($normalized === "heal") {
            $candidates[] = ["values", "heal-hearts"];
        }
        if ($normalized === "damage") {
            $candidates[] = ["values", "damage-hearts"];
        }
        if ($normalized === "duration") {
            $candidates[] = ["values", "duration-seconds"];
            $candidates[] = ["values", "duration-ticks"];
        }

        return $candidates;
    }

    private static function resolveNode(mixed $definition, int $level, float $default, string $path): float
    {
        return EnchantTuningMath::resolveNumber(
            $definition,
            $level,
            $default,
            fn(string $warnPath, float|int|string|bool|null $warnDefault, string $reason): bool => self::warnDefault($warnPath, $warnDefault, $reason),
            $path
        );
    }

    private static function shouldUseLegacyProcDefaults(string $enchantId): bool
    {
        $entry = self::getEntry($enchantId);
        if ($entry === []) {
            return true;
        }

        return !is_array($entry["proc"] ?? null);
    }

    private static function getProcLegacyFlag(string $enchantId, string $key, bool $default): bool
    {
        $entry = self::getEntry($enchantId);
        $legacy = self::getNested($entry, ["proc", "legacy"]);
        if (!is_array($legacy) || !array_key_exists($key, $legacy)) {
            return $default;
        }

        return (bool) $legacy[$key];
    }

    private static function warnDefault(string $path, mixed $default, string $reason): bool
    {
        $key = strtolower($path . "|" . $reason);
        if (isset(self::$warnings[$key])) {
            return false;
        }

        self::$warnings[$key] = true;
        $renderedDefault = is_scalar($default) || $default === null ? var_export($default, true) : gettype($default);
        Loader::getInstance()->getLogger()->warning("[EnchantTuning] {$path} {$reason}; using default {$renderedDefault}");
        return true;
    }
}
