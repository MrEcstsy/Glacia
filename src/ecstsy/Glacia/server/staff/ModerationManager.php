<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\staff;

use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\Glacia\utils\StorageUtils;
use pocketmine\utils\SingletonTrait;

final class ModerationManager {
    use SingletonTrait;

    private string $storageFile = "";
    /** @var array<string, int> */
    private array $warnCounts = [];
    /** @var array<string, list<array<string, mixed>>> */
    private array $history = [];
    /** @var array<string, array<string, mixed>> */
    private array $frozen = [];
    /** @var array<int, array<string, mixed>> */
    private array $reports = [];
    private int $nextReportId = 1;
    /** @var array<int, string> warn_count => duration */
    private array $warnEscalation = [3 => "1d", 5 => "7d"];
    private string $reportNotifyPermission = "glacia.staff.reports";
    private bool $reportDiscordEnabled = true;
    private int $reportCooldownSeconds = 60;
    private int $reportDuplicateWindowSeconds = 120;
    /** @var array<string, int> */
    private array $reportCooldowns = [];

    public function __construct() {
        self::setInstance($this);
        $this->storageFile = Loader::getInstance()->getDataFolder() . "moderation_state.json";
        $this->loadStorage();
        $this->loadFromConfig();
    }

    public function loadFromConfig(): void {
        $cfg = GeneralUtils::getConfiguration(Loader::getInstance(), "misc.yml");
        $mod = (array) ($cfg?->get("moderation", []) ?? []);

        $rawEsc = (array) ($mod["warn-escalation"] ?? [3 => "1d", 5 => "7d"]);
        $this->warnEscalation = [];
        foreach ($rawEsc as $k => $v) {
            $count = (int) $k;
            $duration = trim((string) $v);
            if ($count > 0 && $duration !== "") {
                $this->warnEscalation[$count] = $duration;
            }
        }
        ksort($this->warnEscalation);

        $report = (array) ($mod["report"] ?? []);
        $this->reportNotifyPermission = trim((string) ($report["notify-permission"] ?? "glacia.staff.reports"));
        if ($this->reportNotifyPermission === "") {
            $this->reportNotifyPermission = "glacia.staff.reports";
        }
        $this->reportDiscordEnabled = (bool) ($report["discord-enabled"] ?? true);
        $this->reportCooldownSeconds = max(0, (int) ($report["cooldown-seconds"] ?? 60));
        $this->reportDuplicateWindowSeconds = max(0, (int) ($report["duplicate-window-seconds"] ?? 120));
    }

    public function save(): void {
        $payload = [
            "warn_counts" => $this->warnCounts,
            "history" => $this->history,
            "frozen" => $this->frozen,
            "reports" => $this->reports,
            "next_report_id" => $this->nextReportId,
        ];
        StorageUtils::writeJsonFile($this->storageFile, $payload, "moderation state");
    }

    public function incrementWarn(string $targetName, string $targetKey, string $staffName, string $reason): int {
        $count = ((int) ($this->warnCounts[$targetKey] ?? 0)) + 1;
        $this->warnCounts[$targetKey] = $count;
        $this->addHistory($targetKey, [
            "at" => time(),
            "action" => "warn",
            "staff" => $staffName,
            "target" => $targetName,
            "reason" => $reason,
            "count" => $count,
        ]);
        $this->save();
        return $count;
    }

    public function getEscalationDurationForCount(int $count): ?string {
        $chosen = null;
        foreach ($this->warnEscalation as $threshold => $duration) {
            if ($count >= $threshold) {
                $chosen = $duration;
            }
        }
        return $chosen;
    }

    public function setFrozen(string $uuid, string $targetName, string $staffName, string $reason, ?int $untilUnix): void {
        $this->frozen[$uuid] = [
            "target" => $targetName,
            "staff" => $staffName,
            "reason" => $reason,
            "until" => $untilUnix,
            "at" => time(),
        ];
        $this->addHistory($this->normalizeKey($targetName, $uuid), [
            "at" => time(),
            "action" => "freeze",
            "staff" => $staffName,
            "target" => $targetName,
            "reason" => $reason,
            "until" => $untilUnix,
        ]);
        $this->save();
    }

    public function unfreeze(string $uuid, string $staffName = "system"): bool {
        $existing = $this->frozen[$uuid] ?? null;
        if ($existing === null) {
            return false;
        }
        unset($this->frozen[$uuid]);
        $target = (string) ($existing["target"] ?? "Unknown");
        $this->addHistory($this->normalizeKey($target, $uuid), [
            "at" => time(),
            "action" => "unfreeze",
            "staff" => $staffName,
            "target" => $target,
        ]);
        $this->save();
        return true;
    }

    public function isFrozen(string $uuid): bool {
        $entry = $this->frozen[$uuid] ?? null;
        if ($entry === null) {
            return false;
        }
        $until = $entry["until"] ?? null;
        if (is_int($until) && $until > 0 && $until <= time()) {
            unset($this->frozen[$uuid]);
            $this->save();
            return false;
        }
        return true;
    }

    public function getFreezeEntry(string $uuid): ?array {
        if (!$this->isFrozen($uuid)) {
            return null;
        }
        return $this->frozen[$uuid] ?? null;
    }

    public function createReport(string $reporter, string $reporterUuid, string $target, string $reason): int {
        $id = $this->nextReportId++;
        $this->reports[$id] = [
            "id" => $id,
            "reporter" => $reporter,
            "reporter_uuid" => $reporterUuid,
            "target" => $target,
            "reason" => $reason,
            "created_at" => time(),
            "status" => "open",
        ];
        $this->save();
        return $id;
    }

    /** @return list<array<string, mixed>> */
    public function getOpenReports(): array {
        $rows = array_values(array_filter($this->reports, static fn(array $r): bool => (($r["status"] ?? "open") === "open")));
        usort($rows, static fn(array $a, array $b): int => ((int) ($b["created_at"] ?? 0)) <=> ((int) ($a["created_at"] ?? 0)));
        return $rows;
    }

    public function closeReport(int $id, string $staff, string $note = ""): ?array {
        if (!isset($this->reports[$id])) {
            return null;
        }
        if ((string) ($this->reports[$id]["status"] ?? "open") !== "open") {
            return null;
        }
        $this->reports[$id]["status"] = "closed";
        $this->reports[$id]["closed_by"] = $staff;
        $this->reports[$id]["closed_at"] = time();
        if ($note !== "") {
            $this->reports[$id]["note"] = $note;
        }
        $closed = $this->reports[$id];
        $this->save();
        return $closed;
    }

    public function claimReport(int $id, string $staff): bool {
        if (!isset($this->reports[$id])) {
            return false;
        }
        if ((string) ($this->reports[$id]["status"] ?? "open") !== "open") {
            return false;
        }
        $claimedBy = (string) ($this->reports[$id]["claimed_by"] ?? "");
        if ($claimedBy !== "" && strcasecmp($claimedBy, $staff) !== 0) {
            return false;
        }
        $this->reports[$id]["claimed_by"] = $staff;
        $this->reports[$id]["claimed_at"] = time();
        $this->save();
        return true;
    }

    public function canSubmitReport(string $reporterUuid): bool {
        if ($this->reportCooldownSeconds <= 0) {
            return true;
        }
        $next = (int) ($this->reportCooldowns[$reporterUuid] ?? 0);
        return $next <= time();
    }

    public function markReportSubmitted(string $reporterUuid): void {
        if ($this->reportCooldownSeconds > 0) {
            $this->reportCooldowns[$reporterUuid] = time() + $this->reportCooldownSeconds;
        }
    }

    public function getReportCooldownRemaining(string $reporterUuid): int {
        return max(0, ((int) ($this->reportCooldowns[$reporterUuid] ?? 0)) - time());
    }

    public function findRecentDuplicateOpenReport(string $reporterUuid, string $target): ?array {
        if ($this->reportDuplicateWindowSeconds <= 0) {
            return null;
        }
        $now = time();
        $targetNorm = strtolower(trim($target));
        foreach ($this->reports as $row) {
            if (!is_array($row)) {
                continue;
            }
            if ((string) ($row["status"] ?? "open") !== "open") {
                continue;
            }
            if (strtolower((string) ($row["reporter_uuid"] ?? "")) !== strtolower($reporterUuid)) {
                continue;
            }
            if (strtolower((string) ($row["target"] ?? "")) !== $targetNorm) {
                continue;
            }
            $created = (int) ($row["created_at"] ?? 0);
            if ($created > 0 && ($now - $created) <= $this->reportDuplicateWindowSeconds) {
                return $row;
            }
        }
        return null;
    }

    public function getReportNotifyPermission(): string {
        return $this->reportNotifyPermission;
    }

    public function isReportDiscordEnabled(): bool {
        return $this->reportDiscordEnabled;
    }

    /** @return list<array<string, mixed>> */
    public function getHistoryPage(string $targetKey, int $page = 1, int $perPage = 8): array {
        $list = $this->history[$targetKey] ?? [];
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = max(0, ($page - 1) * $perPage);
        return array_slice($list, $offset, $perPage);
    }

    public function getHistoryPages(string $targetKey, int $perPage = 8): int {
        $count = count($this->history[$targetKey] ?? []);
        return max(1, (int) ceil($count / max(1, $perPage)));
    }

    public function resolveTargetKey(string $nameOrUuid): string {
        return strtolower(trim($nameOrUuid));
    }

    private function addHistory(string $targetKey, array $row): void {
        $key = strtolower(trim($targetKey));
        if ($key === "") {
            return;
        }
        if (!isset($this->history[$key])) {
            $this->history[$key] = [];
        }
        array_unshift($this->history[$key], $row);
        if (count($this->history[$key]) > 50) {
            $this->history[$key] = array_slice($this->history[$key], 0, 50);
        }
    }

    private function normalizeKey(string $targetName, string $uuid): string {
        $nameKey = strtolower(trim($targetName));
        return $nameKey !== "" ? $nameKey : strtolower($uuid);
    }

    private function loadStorage(): void {
        $decoded = StorageUtils::readJsonFile($this->storageFile, "moderation state");
        if ($decoded === null) {
            return;
        }
        $this->warnCounts = is_array($decoded["warn_counts"] ?? null) ? $decoded["warn_counts"] : [];
        $this->history = is_array($decoded["history"] ?? null) ? $decoded["history"] : [];
        $this->frozen = is_array($decoded["frozen"] ?? null) ? $decoded["frozen"] : [];
        $this->reports = is_array($decoded["reports"] ?? null) ? $decoded["reports"] : [];
        $this->nextReportId = max(1, (int) ($decoded["next_report_id"] ?? 1));
    }
}
