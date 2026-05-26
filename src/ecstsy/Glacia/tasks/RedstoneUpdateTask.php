<?php

declare(strict_types=1);

namespace ecstsy\Glacia\tasks;

use ecstsy\Glacia\server\redstone\RedstoneManager;
use pocketmine\scheduler\Task;

final class RedstoneUpdateTask extends Task
{
    public function onRun(): void
    {
        RedstoneManager::getInstance()->flushQueuedUpdates();
    }
}
