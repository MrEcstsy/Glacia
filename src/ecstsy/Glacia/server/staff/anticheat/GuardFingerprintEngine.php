<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\staff\anticheat;

use JsonException;
use pocketmine\player\Player;
use pocketmine\scheduler\AsyncTask;

/**
 * Per-player Welford fingerprints persisted to JSON (local only, no network).
 *
 * @phpstan-type Stats array{mean: float, var: float, n: int}
 */
final class GuardFingerprintEngine
{
    private const MAX_N = 6000;

    private readonly string $fingerprintsDirectory;

    public function __construct(string $fingerprintsDirectory)
    {
        $this->fingerprintsDirectory = rtrim($fingerprintsDirectory, "/\\") . DIRECTORY_SEPARATOR;
        if (!is_dir($this->fingerprintsDirectory)) {
            @mkdir($this->fingerprintsDirectory, 0777, true);
        }
    }

    /**
     * Load stored stats into the live profile.
     */
    public function loadIntoProfile(Player $player, AntiCheatProfile $profile): void
    {
        $file = $this->pathFor($player, $profile);
        if (!is_file($file)) {
            return;
        }

        $raw = @file_get_contents($file);
        if (!is_string($raw) || $raw === "") {
            return;
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return;
        }

        if (!is_array($data)) {
            return;
        }

        foreach ($data as $metric => $stats) {
            if (!is_string($metric) || !is_array($stats)) {
                continue;
            }
            if (!isset($stats["mean"], $stats["var"], $stats["n"])) {
                continue;
            }
            $profile->setFingerprintMetric($metric, [
                "mean" => (float) $stats["mean"],
                "var" => (float) $stats["var"],
                "n" => (int) $stats["n"],
            ]);
        }
    }

    public function saveAsync(Player $player, AntiCheatProfile $profile): void
    {
        $path = $this->pathFor($player, $profile);
        try {
            $data = json_encode($profile->getFingerprintMetrics(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
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
        $this->deleteDirectoryContents($this->fingerprintsDirectory);
    }

    /**
     * Update running mean/variance for a metric on the profile.
     *
     * @param Stats $stats
     * @return Stats
     */
    public function record(AntiCheatProfile $profile, string $metric, float $value): array
    {
        $prior = $profile->getFingerprintMetric($metric) ?? ["mean" => 0.0, "var" => 0.0, "n" => 0];
        $next = $this->welfordUpdate($prior, $value);
        $profile->setFingerprintMetric($metric, $next);

        return $next;
    }

    public function isAnomalous(AntiCheatProfile $profile, string $metric, float $currentValue, int $minSamples = 20): bool
    {
        $own = $profile->getFingerprintMetric($metric);
        if ($own === null || $own["n"] < $minSamples) {
            return false;
        }

        return $this->outsideTwoSigma($own, $currentValue);
    }

    /**
     * @param Stats $stats
     */
    private function welfordUpdate(array $stats, float $value): array
    {
        $n = (int) $stats["n"] + 1;
        $mean = (float) $stats["mean"];
        $var = (float) $stats["var"];
        if ($n === 1) {
            return ["mean" => $value, "var" => 0.0, "n" => 1];
        }

        $delta = $value - $mean;
        $mean += $delta / $n;
        $var = (($n - 2) / ($n - 1)) * $var + ($delta ** 2) / $n;

        return ["mean" => $mean, "var" => max(0.0, $var), "n" => min($n, self::MAX_N)];
    }

    /**
     * @param Stats $stats
     */
    private function outsideTwoSigma(array $stats, float $value): bool
    {
        $sigma = sqrt(max(0.0001, $stats["var"]));

        return abs($value - $stats["mean"]) > 2.0 * $sigma;
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

        return $this->fingerprintsDirectory . $safe . ".json";
    }
}
