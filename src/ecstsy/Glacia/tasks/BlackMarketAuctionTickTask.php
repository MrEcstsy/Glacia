<?php

declare(strict_types=1);

namespace ecstsy\Glacia\tasks;

use ecstsy\Glacia\Loader;
use pocketmine\scheduler\Task;

final class BlackMarketAuctionTickTask extends Task {

    public function onRun(): void {
        Loader::getBlackMarketAuctionInstance()->tick();
    }
}