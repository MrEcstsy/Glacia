<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\server\economy\SellHandler;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

final class SellAllCommand extends BaseCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        SellHandler::sellAll($sender);
    }

    public function getPermission(): string {
        return "glacia.sellall";
    }
}