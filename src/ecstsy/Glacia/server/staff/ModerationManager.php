<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\staff;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\StorageUtils;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\Server;
use pocketmine\utils\SingletonTrait;

final class ModerationManager {
    use SingletonTrait;

    private string $storageFile = "";
    /** @var array<string, int> */
    private array $warnCounts = [];
    /** @var array<string, array<string, int>> */
    private array $warnCategoryCounts = [];
    /** @var array<string, list<array<string, mixed>>> */
    private array $history = [];
    /** @var array<string, array<string, mixed>> */
    private array $frozen = [];
    /** @var array<string, array<string, mixed>> */
    private array $muted = [];
    /** @var array<int, array<string, mixed>> */
    private array $reports = [];
    private int $nextReportId = 1;
    /** @var array<int, string> */
    private array $warnEscalation = [3 => "1d", 5 => "7d"];
    /** @var list<array{id: string, label: string, action: string, duration: string, reason: string, warn_category: string}> */
    private array $punishmentOptions = [];
    /** @var array<string, array{label: string, steps: list<array{action: string, duration: string, reason: string}>}> */
    private array $warningCategories = [];
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
        $this->warningCategories = $this->parseWarningCategories((array) ($mod["warning-categories"] ?? []));
        $this->punishmentOptions = $this->parsePunishmentOptions((array) ($mod["punishment-options"] ?? []));
    }

    public function save(): void {
        $payload = [
            "warn_counts" => $this->warnCounts,
            "warn_category_counts" => $this->warnCategoryCounts,
            "history" => $this->history,
            "frozen" => $this->frozen,
            "muted" => $this->muted,
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

    /**
     * @return list<array{id: string, label: string, action: string, duration: string, reason: string, warn_category: string}>
     */
    public function getPunishmentOptions(): array {
        return $this->punishmentOptions;
    }

    /**
     * @return array{id: string, label: string, action: string, duration: string, reason: string, warn_category: string}|null
     */
    public function getPunishmentOptionById(string $id): ?array {
        $normalized = strtolower(trim($id));
        if ($normalized === "") {
            return null;
        }
        foreach ($this->punishmentOptions as $option) {
            if ($option["id"] === $normalized) {
                return $option;
            }
        }
        return null;
    }

    /**
     * @return array{
     *   action: string,
     *   target: string,
     *   reason: string,
     *   duration: string,
     *   duration_label: string,
     *   category: string,
     *   category_label: string,
     *   warn_count: int,
     *   banned: bool,
     *   muted: bool
     * }
     */
    public function executePunishmentOption(string $targetName, string $targetKey, string $staffName, array $option): array {
        if (($option["action"] ?? "ban") === "warn") {
            return $this->issueCategoryWarning($targetName, $targetKey, $staffName, (string) ($option["warn_category"] ?? ""), (string) ($option["reason"] ?? ""));
        }

        return $this->applyBan(
            $targetName,
            $staffName,
            (string) ($option["duration"] ?? ""),
            (string) ($option["reason"] ?? "Rule violation"),
            (string) ($option["label"] ?? "Ban")
        );
    }

    /**
     * @return array{
     *   action: string,
     *   target: string,
     *   reason: string,
     *   duration: string,
     *   duration_label: string,
     *   category: string,
     *   category_label: string,
     *   warn_count: int,
     *   banned: bool,
     *   muted: bool
     * }
     */
    public function issueCategoryWarning(string $targetName, string $targetKey, string $staffName, string $categoryId, string $fallbackReason = ""): array {
        $normalizedCategory = strtolower(trim($categoryId));
        $category = $this->warningCategories[$normalizedCategory] ?? null;
        if ($category === null) {
            return $this->issueGenericWarning($targetName, $targetKey, $staffName, $fallbackReason !== "" ? $fallbackReason : $categoryId);
        }

        $counts = $this->warnCategoryCounts[$targetKey] ?? [];
        $count = ((int) ($counts[$normalizedCategory] ?? 0)) + 1;
        $counts[$normalizedCategory] = $count;
        $this->warnCategoryCounts[$targetKey] = $counts;

        $steps = $category["steps"];
        $stepIndex = min(max(0, $count - 1), count($steps) - 1);
        $step = $steps[$stepIndex];
        $reason = $step["reason"] !== "" ? $step["reason"] : ($fallbackReason !== "" ? $fallbackReason : $category["label"]);
        $duration = $step["duration"];

        $this->addHistory($targetKey, [
            "at" => time(),
            "action" => "warn",
            "warn_category" => $normalizedCategory,
            "staff" => $staffName,
            "target" => $targetName,
            "reason" => $reason,
            "count" => $count,
            "result_action" => $step["action"],
            "result_duration" => $duration,
        ]);
        $this->save();

        if ($step["action"] === "ban") {
            $result = $this->applyBan($targetName, $staffName, $duration, $reason, $category["label"]);
            $result["category"] = $normalizedCategory;
            $result["category_label"] = $category["label"];
            $result["warn_count"] = $count;
            return $result;
        }

        $result = $this->setMuted($targetKey, $targetName, $staffName, $reason, $this->durationToExpiry($duration), $normalizedCategory, $count);
        $result["category"] = $normalizedCategory;
        $result["category_label"] = $category["label"];
        return $result;
    }

    /**
     * @return array{
     *   action: string,
     *   target: string,
     *   reason: string,
     *   duration: string,
     *   duration_label: string,
     *   category: string,
     *   category_label: string,
     *   warn_count: int,
     *   banned: bool,
     *   muted: bool
     * }
     */
    public function issueGenericWarning(string $targetName, string $targetKey, string $staffName, string $reason): array {
        $count = $this->incrementWarn($targetName, $targetKey, $staffName, $reason);
        $duration = $this->getEscalationDurationForCount($count);
        if ($duration !== null) {
            $result = $this->applyBan($targetName, $staffName, $duration, "Warn escalation ({$count} warnings)", "Warn escalation");
            $result["warn_count"] = $count;
            return $result;
        }

        return [
            "action" => "warn",
            "target" => $targetName,
            "reason" => $reason,
            "duration" => "",
            "duration_label" => "Warning only",
            "category" => "general",
            "category_label" => "General",
            "warn_count" => $count,
            "banned" => false,
            "muted" => false,
        ];
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

    /**
     * @return array{
     *   action: string,
     *   target: string,
     *   reason: string,
     *   duration: string,
     *   duration_label: string,
     *   category: string,
     *   category_label: string,
     *   warn_count: int,
     *   banned: bool,
     *   muted: bool
     * }
     */
    public function setMuted(string $targetKey, string $targetName, string $staffName, string $reason, ?int $untilUnix, string $category = "", int $warnCount = 0): array {
        $this->muted[$targetKey] = [
            "target" => $targetName,
            "staff" => $staffName,
            "reason" => $reason,
            "until" => $untilUnix,
            "category" => $category,
            "warn_count" => $warnCount,
            "at" => time(),
        ];
        $this->addHistory($targetKey, [
            "at" => time(),
            "action" => "mute",
            "staff" => $staffName,
            "target" => $targetName,
            "reason" => $reason,
            "until" => $untilUnix,
            "warn_category" => $category,
            "count" => $warnCount,
        ]);
        $this->save();

        return [
            "action" => "mute",
            "target" => $targetName,
            "reason" => $reason,
            "duration" => $untilUnix !== null ? max(0, $untilUnix - time()) . "s" : "",
            "duration_label" => $untilUnix !== null ? GeneralUtils::translateTime(max(1, $untilUnix - time())) : "Permanent",
            "category" => $category,
            "category_label" => $category !== "" ? ($this->warningCategories[$category]["label"] ?? $category) : "",
            "warn_count" => $warnCount,
            "banned" => false,
            "muted" => true,
        ];
    }

    public function isMuted(string $targetKey): bool {
        $entry = $this->muted[$targetKey] ?? null;
        if ($entry === null) {
            return false;
        }
        $until = $entry["until"] ?? null;
        if (is_int($until) && $until > 0 && $until <= time()) {
            unset($this->muted[$targetKey]);
            $this->save();
            return false;
        }
        return true;
    }

    public function getMuteEntry(string $targetKey): ?array {
        if (!$this->isMuted($targetKey)) {
            return null;
        }
        return $this->muted[$targetKey] ?? null;
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
        $this->warnCategoryCounts = is_array($decoded["warn_category_counts"] ?? null) ? $decoded["warn_category_counts"] : [];
        $this->history = is_array($decoded["history"] ?? null) ? $decoded["history"] : [];
        $this->frozen = is_array($decoded["frozen"] ?? null) ? $decoded["frozen"] : [];
        $this->muted = is_array($decoded["muted"] ?? null) ? $decoded["muted"] : [];
        $this->reports = is_array($decoded["reports"] ?? null) ? $decoded["reports"] : [];
        $this->nextReportId = max(1, (int) ($decoded["next_report_id"] ?? 1));
    }

    /**
     * @return list<array{id: string, label: string, action: string, duration: string, reason: string, warn_category: string}>
     */
    private function parsePunishmentOptions(array $rows): array {
        $options = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $option = $this->normalizePunishmentOption($row);
            if ($option["id"] === "" || $option["label"] === "") {
                continue;
            }
            if ($option["action"] === "warn" && $option["warn_category"] === "") {
                continue;
            }
            $options[] = $option;
        }
        if ($options !== []) {
            return $options;
        }

        return [
            ["id" => "cheating", "label" => "Any form of cheating", "action" => "ban", "duration" => "30d", "reason" => "Any form of cheating", "warn_category" => ""],
            ["id" => "toxicity", "label" => "Toxicity", "action" => "warn", "duration" => "", "reason" => "Toxicity", "warn_category" => "toxicity"],
            ["id" => "racism", "label" => "Racism", "action" => "warn", "duration" => "", "reason" => "Racism", "warn_category" => "racism"],
            ["id" => "ban-evasion", "label" => "Ban evasion", "action" => "ban", "duration" => "", "reason" => "Ban evasion", "warn_category" => ""],
            ["id" => "ddos", "label" => "DDoS attacks", "action" => "ban", "duration" => "", "reason" => "DDoS attacks", "warn_category" => ""],
            ["id" => "disrespect-staff", "label" => "Disrespect towards staff", "action" => "warn", "duration" => "", "reason" => "Disrespect towards staff", "warn_category" => "disrespect_towards_staff"],
            ["id" => "exploiting", "label" => "Exploiting", "action" => "ban", "duration" => "30d", "reason" => "Exploiting", "warn_category" => ""],
            ["id" => "duping", "label" => "Duping", "action" => "ban", "duration" => "30d", "reason" => "Duping", "warn_category" => ""],
        ];
    }

    /**
     * @return array{id: string, label: string, action: string, duration: string, reason: string, warn_category: string}
     */
    private function normalizePunishmentOption(array $row): array {
        $action = strtolower(trim((string) ($row["action"] ?? "ban")));
        if (!in_array($action, ["ban", "warn"], true)) {
            $action = "ban";
        }
        return [
            "id" => strtolower(trim((string) ($row["id"] ?? ""))),
            "label" => trim((string) ($row["label"] ?? "")),
            "action" => $action,
            "duration" => trim((string) ($row["duration"] ?? "")),
            "reason" => trim((string) ($row["reason"] ?? "")),
            "warn_category" => strtolower(trim((string) ($row["warn-category"] ?? $row["warn_category"] ?? ""))),
        ];
    }

    /**
     * @return array<string, array{label: string, steps: list<array{action: string, duration: string, reason: string}>}>
     */
    private function parseWarningCategories(array $rows): array {
        $categories = [];
        foreach ($rows as $key => $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = strtolower(trim((string) ($row["id"] ?? $key)));
            if ($id === "") {
                continue;
            }
            $label = trim((string) ($row["label"] ?? $id));
            $steps = [];
            foreach ((array) ($row["steps"] ?? []) as $stepRow) {
                if (!is_array($stepRow)) {
                    continue;
                }
                $action = strtolower(trim((string) ($stepRow["action"] ?? "mute")));
                if (!in_array($action, ["mute", "ban"], true)) {
                    continue;
                }
                $steps[] = [
                    "action" => $action,
                    "duration" => trim((string) ($stepRow["duration"] ?? "")),
                    "reason" => trim((string) ($stepRow["reason"] ?? $label)),
                ];
            }
            if ($steps === []) {
                continue;
            }
            $categories[$id] = [
                "label" => $label,
                "steps" => $steps,
            ];
        }

        if ($categories !== []) {
            return $categories;
        }

        return [
            "toxicity" => [
                "label" => "Toxicity",
                "steps" => [
                    ["action" => "mute", "duration" => "8h", "reason" => "Toxicity"],
                    ["action" => "mute", "duration" => "12h", "reason" => "Toxicity"],
                    ["action" => "mute", "duration" => "24h", "reason" => "Toxicity"],
                ],
            ],
            "racism" => [
                "label" => "Racism",
                "steps" => [
                    ["action" => "mute", "duration" => "8h", "reason" => "Racism"],
                    ["action" => "mute", "duration" => "12h", "reason" => "Racism"],
                    ["action" => "ban", "duration" => "30d", "reason" => "Racism"],
                ],
            ],
            "disrespect_towards_staff" => [
                "label" => "Disrespect towards staff",
                "steps" => [
                    ["action" => "mute", "duration" => "8h", "reason" => "Disrespect towards staff"],
                    ["action" => "mute", "duration" => "12h", "reason" => "Disrespect towards staff"],
                    ["action" => "mute", "duration" => "24h", "reason" => "Disrespect towards staff"],
                ],
            ],
        ];
    }

    private function durationToExpiry(string $duration): ?int {
        $normalized = strtolower(trim($duration));
        if ($normalized === "" || in_array($normalized, ["perm", "permanent"], true)) {
            return null;
        }
        $seconds = GeneralUtils::parseTimeString($normalized);
        if ($seconds <= 0) {
            return null;
        }
        return time() + $seconds;
    }

    /**
     * @return array{
     *   action: string,
     *   target: string,
     *   reason: string,
     *   duration: string,
     *   duration_label: string,
     *   category: string,
     *   category_label: string,
     *   warn_count: int,
     *   banned: bool,
     *   muted: bool
     * }
     */
    private function applyBan(string $targetName, string $staffName, string $durationSpec, string $reason, string $contextLabel): array {
        $banList = Server::getInstance()->getNameBans();
        $expires = null;
        $durationLabel = "Permanent";
        $normalizedDuration = strtolower(trim($durationSpec));
        if ($normalizedDuration !== "" && !in_array($normalizedDuration, ["perm", "permanent"], true)) {
            $seconds = GeneralUtils::parseTimeString($normalizedDuration);
            if ($seconds > 0) {
                $expires = new \DateTime("+" . $seconds . " seconds");
                $durationLabel = GeneralUtils::translateTime($seconds);
            }
        }
        $banList->addBan($targetName, $reason, $expires, $staffName);

        $onlineTarget = PlayerUtils::getPlayerByPrefix($targetName);
        if ($onlineTarget !== null && strcasecmp($onlineTarget->getName(), $targetName) === 0) {
            $onlineTarget->kick("You are banned.\nDuration: {$durationLabel}\nReason: {$reason}");
            $targetName = $onlineTarget->getName();
        }

        $targetKey = $this->resolveTargetKey($targetName);
        $this->addHistory($targetKey, [
            "at" => time(),
            "action" => "ban",
            "staff" => $staffName,
            "target" => $targetName,
            "reason" => $reason,
            "duration" => $normalizedDuration !== "" ? $normalizedDuration : "permanent",
            "context" => $contextLabel,
        ]);
        $this->save();

        return [
            "action" => "ban",
            "target" => $targetName,
            "reason" => $reason,
            "duration" => $normalizedDuration,
            "duration_label" => $durationLabel,
            "category" => "",
            "category_label" => "",
            "warn_count" => 0,
            "banned" => true,
            "muted" => false,
        ];
    }
}
