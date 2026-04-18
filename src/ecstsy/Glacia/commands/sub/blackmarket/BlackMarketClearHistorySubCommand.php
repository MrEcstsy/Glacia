<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\blackmarket;

use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as C;

final class BlackMarketClearHistorySubCommand extends BaseSubCommand {

    protected function prepare(): void {
        $this->setPermission($this->getPermission());
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        Loader::getBlackMarketAuctionInstance()->clearHistory();
        $sender->sendMessage(C::colorize("&r&aCleared the black market auction history."));
    }

    public function getPermission(): string {
        return "glacia.admin";
    }
}
