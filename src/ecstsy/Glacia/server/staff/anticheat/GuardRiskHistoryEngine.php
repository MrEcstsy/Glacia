<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\staff\anticheat;

use JsonException;
use pocketmine\player\Player;
use pocketmine\scheduler\AsyncTask;

final class GuardRiskHistoryEngine
{
    private readonly string $historyDirectory;

    public function __construct(
        string $historyDirectory,
        private readonly int $resetDays,
        private readonly float $maxScore,
    ) {
        $this->historyDirectory = rtrim($historyDirectory, "/\\") . DIRECTORY_SEPARATOR;
        if (!is_dir($this->historyDirectory)) {
            @mkdir($this->historyDirectory, 0777, true);
        }
    }

    public function loadIntoProfile(Player $player, AntiCheatProfile $profile): void
    {
        $file = $this->pathFor($player, $profile);
        $history = $this->blankHistory();
        if (is_file($file)) {
            $raw = @file_get_contents($file);
            if (is_string($raw) && $raw !== "") {
                try {
                    $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                    if (is_array($decoded)) {
                        $history = $this->normalizeHistory($decoded);
                    }
                } catch (JsonException) {
                }
            }
        }

        $profile->setRiskHistory($history);
    }

    public function saveAsync(Player $player, AntiCheatProfile $profile): void
    {
        $path = $this->pathFor($player, $profile);
        try {
            $data = json_encode($this->normalizeHistory($profile->getRiskHistory()), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return;
        }

        \pocketmine\Server::getInstance()->getAsyncPool()->submitTask(new class($path, $data) extends AsyncTask {
            public function __construct(
                private string $path,
                private string $data
            ) {
            }

            public function onRun(): void
            {
                $dir = dirname($this->path);
                if (!is_dir($dir)) {
                    @mkdir($dir, 0777, true);
                }
                @file_put_contents($this->path, $this->data);
            }
        });
    }

    public function delete(Player $player, AntiCheatProfile $profile): void
    {
        $file = $this->pathFor($player, $profile);
        if (is_file($file)) {
            @unlink($file);
        }
    }

    public function resetAll(): void
    {
        $this->deleteDirectoryContents($this->historyDirectory);
    }

    public function recordViolationAlert(AntiCheatProfile $profile, string $check, int $violation): array
    {
        $amount = min(20.0, 5.0 + max(0, $violation) * 0.75);
        return $this->record($profile, $amount, "violation:" . $check, true);
    }

    public function recordHeuristicSignal(AntiCheatProfile $profile, string $moduleLabel, float $confidence): array
    {
        $amount = min(14.0, max(2.0, $confidence / 14.0));
        return $this->record($profile, $amount, "signal:" . $moduleLabel, false);
    }

    public function secondsRemaining(AntiCheatProfile $profile): int
    {
        $history = $this->normalizeHistory($profile->getRiskHistory());
        $window = max(1, $this->resetDays) * 86400;
        $expiresAt = (int) $history["period_started_at"] + $window;
        return max(0, $expiresAt - time());
    }

    private function record(AntiCheatProfile $profile, float $amount, string $reason, bool $violationAlert): array
    {
        $history = $this->normalizeHistory($profile->getRiskHistory());
        $history["score"] = min($this->maxScore, max(0.0, (float) $history["score"] + max(0.0, $amount)));
        $history["updated_at"] = time();
        $history["last_reason"] = $reason;
        if ($violationAlert) {
            ++$history["violation_alerts"];
        } else {
            ++$history["heuristic_signals"];
        }

        $profile->setRiskHistory($history);
        return $history;
    }

    private function normalizeHistory(array $history): array
    {
        $base = $this->blankHistory();
        $base["period_started_at"] = max(0, (int) ($history["period_started_at"] ?? 0));
        $base["updated_at"] = max(0, (int) ($history["updated_at"] ?? 0));
        $base["score"] = min($this->maxScore, max(0.0, (float) ($history["score"] ?? 0.0)));
        $base["violation_alerts"] = max(0, (int) ($history["violation_alerts"] ?? 0));
        $base["heuristic_signals"] = max(0, (int) ($history["heuristic_signals"] ?? 0));
        $base["last_reason"] = (string) ($history["last_reason"] ?? "");

        $now = time();
        if ($base["period_started_at"] <= 0) {
            $base["period_started_at"] = $now;
        }

        return $base;
    }

    private function blankHistory(): array
    {
        return [
            "period_started_at" => 0,
            "updated_at" => 0,
            "score" => 0.0,
            "violation_alerts" => 0,
            "heuristic_signals" => 0,
            "last_reason" => "",
        ];
    }

    private function deleteDirectoryContents(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === "." || $item === "..") {
                continue;
            }

            $path = $directory . $item;
            if (is_dir($path)) {
                $this->deleteDirectoryContents(rtrim($path, "/\\") . DIRECTORY_SEPARATOR);
                @rmdir($path);
                continue;
            }

            @unlink($path);
        }
    }

    private function pathFor(Player $player, AntiCheatProfile $profile): string
    {
        $id = $profile->getStablePlayerId();
        $safe = preg_replace('/[^a-zA-Z0-9_\-]/', "_", $id) ?? "player";

        return $this->historyDirectory . $safe . ".json";
    }
}
