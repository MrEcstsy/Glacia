<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\factions\audit;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\StorageUtils;

/**
 * In-memory + JSON ring buffer of recent faction audit events (for /f audit).
 * Server file logs under logs/ remain the full durable trail.
 */
final class FactionAuditFeed {

    private const MAX_PER_FACTION = 80;

    private static string $storageFile = "";

    /** @var array<string, list<array<string, mixed>>> */
    private static array $byFaction = [];

    public static function init(Loader $loader): void {
        self::$storageFile = $loader->getDataFolder() . "faction_audit_feed.json";
        self::load();
    }

    public static function save(): void {
        if (self::$storageFile === "") {
            return;
        }
        StorageUtils::writeJsonFile(self::$storageFile, self::$byFaction, "faction audit feed");
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function append(string $factionId, string $action, array $data): void {
        $factionId = trim($factionId);
        if ($factionId === "") {
            return;
        }

        if (!isset(self::$byFaction[$factionId])) {
            self::$byFaction[$factionId] = [];
        }

        self::$byFaction[$factionId][] = [
            "ts" => time(),
            "action" => $action,
            "data" => self::sanitizeData($data),
        ];

        $count = count(self::$byFaction[$factionId]);
        if ($count > self::MAX_PER_FACTION) {
            self::$byFaction[$factionId] = array_slice(self::$byFaction[$factionId], $count - self::MAX_PER_FACTION);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function getPage(string $factionId, int $page, int $perPage): array {
        $factionId = trim($factionId);
        if ($factionId === "" || !isset(self::$byFaction[$factionId])) {
            return [];
        }

        $perPage = max(1, min(12, $perPage));
        $page = max(1, $page);

        $list = self::$byFaction[$factionId];
        $total = count($list);
        $offset = ($page - 1) * $perPage;

        /** @var list<array<string, mixed>> $slice */
        $slice = [];
        $i = 0;
        for ($idx = $total - 1 - $offset; $idx >= 0 && $i < $perPage; $idx--, $i++) {
            $slice[] = $list[$idx];
        }

        return $slice;
    }

    public static function getTotalPages(string $factionId, int $perPage): int {
        $factionId = trim($factionId);
        if ($factionId === "" || !isset(self::$byFaction[$factionId])) {
            return 1;
        }

        $perPage = max(1, min(12, $perPage));
        $total = count(self::$byFaction[$factionId]);

        return max(1, (int) ceil($total / $perPage));
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function sanitizeData(array $data): array {
        $out = [];
        foreach ($data as $k => $v) {
            if (!is_string($k)) {
                continue;
            }
            if (is_scalar($v) || $v === null) {
                $out[$k] = $v;
            } elseif (is_string($v)) {
                $out[$k] = preg_replace('/[\x00-\x1F\x7F]/', '', str_replace(["\r", "\n"], ' ', $v)) ?? '';
            } else {
                $out[$k] = StorageUtils::encodeJsonString($v, "faction audit field {$k}") ?? "[unserializable]";
            }
        }
        return $out;
    }

    private static function load(): void {
        if (self::$storageFile === "") {
            return;
        }

        $decoded = StorageUtils::readJsonFile(self::$storageFile, "faction audit feed");
        if ($decoded === null) {
            return;
        }

        $clean = [];
        foreach ($decoded as $factionId => $entries) {
            if (!is_string($factionId) || !is_array($entries)) {
                continue;
            }
            /** @var list<array<string, mixed>> $list */
            $list = [];
            foreach ($entries as $e) {
                if (is_array($e)) {
                    $list[] = $e;
                }
            }
            $clean[$factionId] = array_slice($list, -self::MAX_PER_FACTION);
        }
        self::$byFaction = $clean;
    }
}
