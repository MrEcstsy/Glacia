<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\staff\anticheat;

use JsonException;
use pocketmine\player\Player;
use pocketmine\scheduler\AsyncTask;

final class GuardViolationHistoryEngine
{
    private readonly string $historyDirectory;

    public function __construct(string $historyDirectory)
    {
        $this->historyDirectory = rtrim($historyDirectory, "/\\") . DIRECTORY_SEPARATOR;
        if (!is_dir($this->historyDirectory)) {
            @mkdir($this->historyDirectory, 0777, true);
        }
    }

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
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return;
        }

        if (!is_array($decoded)) {
            return;
        }

        $profile->setViolations($decoded);
    }

    public function saveAsync(Player $player, AntiCheatProfile $profile): void
    {
        $path = $this->pathFor($player, $profile);
        try {
            $data = json_encode($profile->getViolations(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
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
