<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils;

use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use function fclose;

/**
 * Append-only JSON line logs under plugin_data/Glacia/logs/ for audits and restores.
 */
final class AuditFileLogger {

    private static bool $enabledStaff = true;
    private static bool $enabledFaction = true;
    private static bool $staffDiscordEnabled = true;
    /** @var array<string, true> */
    private static array $staffDiscordMutedActions = [];
    private static int $maxFileBytes = 26214400; // 25MB default
    private static int $maxBackups = 5;
    private static int $pruneDays = 30;

    public static function loadFromConfig(): void {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "config.yml");
        $section = (array) ($config?->get("audit-log", []) ?? []);
        self::$enabledStaff = (bool) ($section["staff-enabled"] ?? true);
        self::$enabledFaction = (bool) ($section["faction-enabled"] ?? true);
        self::$staffDiscordEnabled = (bool) ($section["staff-discord-enabled"] ?? true);
        self::$maxFileBytes = max(1, (int) (($section["max-file-mb"] ?? 25) * 1024 * 1024));
        self::$maxBackups = max(1, (int) ($section["max-backups"] ?? 5));
        self::$pruneDays = max(0, (int) ($section["prune-days"] ?? 30));

        $muted = (array) ($section["staff-discord-muted-actions"] ?? ["Give Item"]);
        self::$staffDiscordMutedActions = [];
        foreach ($muted as $action) {
            if (!is_string($action)) {
                continue;
            }
            $key = strtolower(trim($action));
            if ($key !== "") {
                self::$staffDiscordMutedActions[$key] = true;
            }
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function staff(string $action, array $data): void {
        if (!self::$enabledStaff) {
            return;
        }
        self::writeLine("audit_staff.jsonl", "staff", $action, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function faction(string $action, array $data): void {
        if (!self::$enabledFaction) {
            return;
        }
        self::writeLine("audit_faction.jsonl", "faction", $action, $data);
    }

    public static function shouldSendStaffDiscord(string $action): bool {
        if (!self::$staffDiscordEnabled) {
            return false;
        }
        return !isset(self::$staffDiscordMutedActions[strtolower(trim($action))]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function writeLine(string $fileName, string $kind, string $action, array $data): void {
        try {
            $folder = Loader::getInstance()->getDataFolder() . "logs";
            if (!is_dir($folder) && !mkdir($folder, 0777, true) && !is_dir($folder)) {
                Loader::getInstance()->getLogger()->warning("[audit-log] Could not create logs directory.");
                return;
            }

            $path = $folder . DIRECTORY_SEPARATOR . $fileName;
            self::maintainLogFile($path);
            $payload = [
                "ts" => gmdate("c"),
                "kind" => $kind,
                "action" => $action,
                "data" => self::sanitizeData($data),
            ];
            $json = StorageUtils::encodeJsonString($payload, "audit log line");
            if ($json === null) {
                return;
            }
            $line = $json . "\n";
            file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            try {
                Loader::getInstance()->getLogger()->debug("[audit-log] " . $e->getMessage());
            } catch (\Throwable) {
            }
        }
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
            if (is_float($v) || is_int($v) || is_bool($v) || $v === null) {
                $out[$k] = $v;
            } elseif (is_string($v)) {
                $out[$k] = preg_replace('/[\x00-\x1F\x7F]/', '', str_replace(["\r", "\n"], " ", $v)) ?? '';
            } else {
                $out[$k] = (string) $v;
            }
        }
        return $out;
    }

    private static function maintainLogFile(string $path): void {
        if (is_file($path) && filesize($path) !== false && filesize($path) >= self::$maxFileBytes) {
            $rotated = $path . "." . gmdate("Ymd_His") . ".bak";
            if (!@rename($path, $rotated)) {
                Loader::getInstance()->getLogger()->warning("[audit-log] Failed to rotate " . basename($path));
            }
        }

        $pattern = $path . ".*.bak";
        $backups = glob($pattern) ?: [];
        if ($backups === []) {
            return;
        }

        usort($backups, static function (string $a, string $b): int {
            $ta = @filemtime($a) ?: 0;
            $tb = @filemtime($b) ?: 0;
            return $tb <=> $ta;
        });

        $now = time();
        $maxAge = self::$pruneDays > 0 ? (self::$pruneDays * 86400) : 0;
        foreach ($backups as $index => $file) {
            if ($index >= self::$maxBackups) {
                @unlink($file);
                continue;
            }

            if ($maxAge > 0) {
                $age = $now - ((int) (@filemtime($file) ?: $now));
                if ($age > $maxAge) {
                    @unlink($file);
                }
            }
        }
    }
}
