<?php

declare(strict_types=1);

namespace ecstsy\Glacia\tasks;

use ecstsy\Glacia\utils\screens\blackmarket\BlackMarketScreen;
use pocketmine\player\Player;
use pocketmine\scheduler\Task;

final class BlackMarketCenterUpdateTask extends Task {

    public function __construct(
        private readonly Player $player,
        private readonly BlackMarketScreen $screen
    ) {}

    public function onRun(): void {
        if(!$this->player->isConnected()){
            $this->screen->stopUpdateTask();
            $this->getHandler()?->cancel();
            return;
        }

        $this->screen->updateCenterItem();
    }
}