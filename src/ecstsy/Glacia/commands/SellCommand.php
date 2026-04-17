<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\server\economy\SellHandler;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class SellCommand extends BaseCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $item = $sender->getInventory()->getItemInHand();

        if ($item->isNull()) {
            $sender->sendMessage(C::colorize("&r&cYou must be holding an item to sell."));
            return;
        }

        $success = SellHandler::sellItem($sender, $item);

        if (!$success) {
            return;
        }
    }

    public function getPermission(): string {
        return "glacia.default";
    }
}