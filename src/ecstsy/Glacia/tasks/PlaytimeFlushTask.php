<?php

declare(strict_types=1);

namespace ecstsy\Glacia\tasks;

use ecstsy\Glacia\Loader;
use pocketmine\scheduler\Task;
use pocketmine\Server;

/**
 * Periodically persists online playtime so crashes do not lose more than one interval.
 */
final class PlaytimeFlushTask extends Task
{
    public function onRun(): void
    {
        foreach (Server::getInstance()->getOnlinePlayers() as $player) {
            $session = Loader::getPlayerManager()->getSession($player);
            $session?->flushPlaytimeSlice();
        }
    }
}
