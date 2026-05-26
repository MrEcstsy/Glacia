<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils;

use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\plugin\Plugin;
use pocketmine\utils\Config;
use Throwable;
use function array_is_list;
use function array_key_exists;
use function basename;
use function is_array;

/**
 * YAML sync driven only by bundled {@code schema-version} — staff never edits that field.
 *
 * When you ship a newer {@code schema-version} in resources, locals are reconciled once:
 *
 * • Keys removed from defaults are preserved locally (no destructive pruning).
 * • New default keys appear with bundled values if missing locally.
 * • Existing keys keep staff/local values while nested maps still pick up structural changes.
 *
 * {@code pets.yml}: under {@code pets}, defaults drive structure; purely local pet IDs survive.
 *
 * Additive list ({@see ConfigResourceCatalog::isSchemaAdditiveFile}): only missing keys are copied from
 * the bundle; staff keys and values are never removed.
 *
 * Bump {@code schema-version} only in plugin resources — not in production overrides.
 *
 * Differs from {@see \JackMD\ConfigUpdater\ConfigUpdater} (full wipe + *_old rename).
 */
final class ConfigSchemaUpdater {

    public const SCHEMA_VERSION_KEY = "schema-version";

    /**
     * @return string[]
     */
    public static function trackedFiles(): array
    {
        return ConfigResourceCatalog::getSchemaTrackedConfigFiles();
    }

    public static function isTracked(string $relativePath): bool
    {
        return in_array(str_replace('\\', '/', $relativePath), self::trackedNormalized(), true);
    }

    /**
     * @return array<string, true>
     */
    private static function trackedNormalized(): array
    {
        $out = [];
        foreach (self::trackedFiles() as $path) {
            $out[str_replace('\\', '/', $path)] = true;
        }
        return $out;
    }

    /**
     * @param string $relativePath e.g. {@code pets.yml}
     *
     * @return bool true if disk file was rewritten
     */
    public static function ensureMerged(Plugin $plugin, string $relativePath, string $schemaKey = self::SCHEMA_VERSION_KEY): bool
    {
        $relativePath = str_replace('\\', '/', $relativePath);
        $defaults = self::yamlFromResourcePath($plugin, $relativePath);
        if ($defaults === null) {
            return false;
        }

        $bundledVersion = self::nestedInt($defaults, $schemaKey, 1);

        GeneralUtils::syncResourceFile($plugin, $relativePath, false);
        GeneralUtils::forgetConfiguration($plugin, $relativePath);
        $filePath = $plugin->getDataFolder() . $relativePath;

        if (!file_exists($filePath)) {
            GeneralUtils::syncResourceFile($plugin, $relativePath, true);
            GeneralUtils::forgetConfiguration($plugin, $relativePath);
            return false;
        }

        $cfg = GeneralUtils::getConfiguration($plugin, $relativePath);
        if (!$cfg instanceof Config) {
            return false;
        }

        /** @phpstan-var array<mixed> $user */
        $user = $cfg->getAll();

        $userRaw = array_key_exists($schemaKey, $user) ? $user[$schemaKey] : null;
        $userVer = ($userRaw === null || $userRaw === "") ? 0 : (int) $userRaw;

        if ($userVer > $bundledVersion) {
            $plugin->getLogger()->notice(
                "[ConfigSchema] {$relativePath} has {$schemaKey}={$userVer} > bundled {$bundledVersion}; leaving unchanged."
            );
            return false;
        }

        if ($userVer === $bundledVersion) {
            return false;
        }

        $merged = ConfigResourceCatalog::isSchemaAdditiveFile($relativePath)
            ? self::reconcileAdditiveDocument($defaults, $user, $schemaKey)
            : self::reconcileStructuredDocument($relativePath, $defaults, $user, $schemaKey);
        $merged[$schemaKey] = $bundledVersion;

        $cfg->setAll($merged);

        try {
            $cfg->save();
        } catch (Throwable $e) {
            $plugin->getLogger()->error("[ConfigSchema] Failed saving {$relativePath}: " . $e->getMessage());

            return false;
        }

        GeneralUtils::forgetConfiguration($plugin, $relativePath);
        $pretty = basename($relativePath);
        $mode = ConfigResourceCatalog::isSchemaAdditiveFile($relativePath)
            ? "additive (new default keys only)"
            : "structured (default keys updated; local unknown keys preserved)";
        $plugin->getLogger()->info("[ConfigSchema] Reconciled {$pretty} — {$mode}; {$schemaKey} {$userVer}→{$bundledVersion}.");

        return true;
    }

    /** Run reconcile pass for every tracked file. */
    public static function mergeAllTracked(Plugin $plugin): void
    {
        foreach (self::trackedFiles() as $file) {
            self::ensureMerged($plugin, $file);
        }
    }

    /**
     * Adds missing keys from defaults recursively; never removes or overwrites existing entries.
     *
     * @phpstan-param array<mixed> $defaults
     * @phpstan-param array<mixed> $user
     *
     * @phpstan-return array<mixed>
     */
    private static function reconcileAdditiveDocument(array $defaults, array $user, string $schemaKey): array
    {
        /** @phpstan-var array<mixed> $out */
        $out = self::deepCopyValue($user);
        if (!is_array($out)) {
            $out = [];
        }

        unset($out[$schemaKey]);
        self::mergeAdditiveDeep($out, $defaults, $schemaKey);

        return $out;
    }

    /** @phpstan-param array<mixed> $target @phpstan-param array<mixed> $defaults */
    private static function mergeAdditiveDeep(array &$target, array $defaults, string $schemaKey): void
    {
        foreach ($defaults as $k => $defVal) {
            $key = (string) $k;
            if ($key === $schemaKey) {
                continue;
            }
            if (!array_key_exists($key, $target)) {
                $target[$key] = self::deepCopyValue($defVal);
                continue;
            }
            if (
                is_array($defVal) && !array_is_list($defVal)
                && is_array($target[$key]) && !array_is_list($target[$key])
            ) {
                /** @phpstan-var array<mixed> $nested */
                $nested = $target[$key];
                self::mergeAdditiveDeep($nested, $defVal, $schemaKey);
                $target[$key] = $nested;
            }
        }
    }

    /**
     * Output follows bundled defaults for known keys while preserving unknown local keys.
     *
     * @phpstan-param array<mixed> $defaults
     * @phpstan-param array<mixed> $user
     */
    private static function reconcileStructuredDocument(string $relativePath, array $defaults, array $user, string $schemaKey): array
    {
        $out = [];
        foreach ($defaults as $k => $defVal) {
            if ($k === $schemaKey) {
                continue;
            }
            $key = (string) $k;
            $hasLocal = array_key_exists($key, $user);
            $localVal = $hasLocal ? $user[$key] : null;

            if ($relativePath === "pets.yml" && $key === "pets" && is_array($defVal) && !array_is_list($defVal)) {
                /** @phpstan-var array<mixed> $defVal */
                $userPets = ($hasLocal && is_array($localVal) && !array_is_list($localVal))
                    /** @phpstan-var array<mixed> $localVal */
                    ? $localVal
                    : [];
                $out[$key] = self::reconcilePetsSubtree($defVal, $userPets);
                continue;
            }

            $out[$key] = self::reconcileBranch($defVal, $localVal, $hasLocal);
        }
        // Preserve staff/local top-level keys that are not in bundled defaults.
        foreach ($user as $k => $v) {
            $key = (string) $k;
            if ($key === $schemaKey || array_key_exists($key, $out)) {
                continue;
            }
            $out[$key] = self::deepCopyValue($v);
        }

        return $out;
    }

    /** @phpstan-param array<mixed> $defaults @phpstan-param array<mixed> $userMap */
    private static function reconcilePetsSubtree(array $defaults, array $userMap): array
    {
        $out = [];

        foreach ($defaults as $petId => $defPet) {
            /** @phpstan-var array<mixed> $defPet */
            $sid = (string) $petId;
            $has = array_key_exists($sid, $userMap);

            $out[$sid] = is_array($defPet) && !array_is_list($defPet)
                ? self::reconcileMap($defPet, $has && is_array($userMap[$sid]) && !array_is_list($userMap[$sid])
                    /** @phpstan-var array<mixed> $nested */
                    ? $userMap[$sid]
                    : [])
                : ($has ? $userMap[$sid] : $defPet);
        }

        foreach ($userMap as $petId => $petData) {
            $sid = (string) $petId;
            if (array_key_exists($sid, $out)) {
                continue;
            }
            $out[$sid] = $petData;
        }

        return $out;
    }

    /** @phpstan-param array<mixed> $defMap */
    private static function reconcileMap(array $defMap, array $userMap): array
    {
        $out = [];
        foreach ($defMap as $k => $defVal) {
            $key = (string) $k;
            $has = array_key_exists($key, $userMap);

            /** @phpstan-var mixed|null $prior */
            $prior = $has ? $userMap[$key] : null;
            $out[$key] = self::reconcileBranch($defVal, $prior, $has);
        }
        // Preserve unknown local nested keys so schema bumps never delete staff-defined content.
        foreach ($userMap as $k => $v) {
            $key = (string) $k;
            if (array_key_exists($key, $out)) {
                continue;
            }
            $out[$key] = self::deepCopyValue($v);
        }

        return $out;
    }

    private static function reconcileBranch(mixed $defVal, mixed $localVal, bool $hasLocal): mixed
    {
        if (!is_array($defVal) || array_is_list($defVal)) {
            return $hasLocal ? $localVal : $defVal;
        }

        /** @phpstan-var array<mixed> $defVal */
        $userAssoc = ($hasLocal && is_array($localVal) && !array_is_list($localVal));

        return self::reconcileMap(
            $defVal,
            $userAssoc ? $localVal : []
        );
    }

    private static function deepCopyValue(mixed $v): mixed
    {
        if (!is_array($v)) {
            return $v;
        }

        if (array_is_list($v)) {
            $list = [];
            foreach ($v as $elem) {
                $list[] = self::deepCopyValue($elem);
            }
            return $list;
        }

        $map = [];
        foreach ($v as $k => $elem) {
            $map[(string) $k] = self::deepCopyValue($elem);
        }

        return $map;
    }

    /** @phpstan-return array<mixed>|null */
    private static function yamlFromResourcePath(Plugin $plugin, string $relativePath): ?array
    {
        $fh = $plugin->getResource($relativePath);
        if ($fh === null) {
            return null;
        }

        $raw = stream_get_contents($fh);
        fclose($fh);
        if ($raw === false || $raw === "") {
            return null;
        }

        $parsed = yaml_parse(Config::fixYAMLIndexes($raw));
        if (!is_array($parsed)) {
            return null;
        }

        /** @phpstan-var array<mixed> $parsed */
        return $parsed;
    }

    /** @phpstan-param array<mixed> $data */
    private static function nestedInt(array $data, string $key, int $fallback): int
    {
        if (!array_key_exists($key, $data)) {
            return max(1, $fallback);
        }
        return max(1, (int) $data[$key]);
    }
}
