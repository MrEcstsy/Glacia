<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use pocketmine\block\inventory\CraftingTableInventory;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class CraftCommand extends BaseCommand
{
    public function prepare(): void
    {
        $this->setPermission($this->getPermission());
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage(C::colorize("&r&cRun this command in-game."));
            return;
        }

        $opened = $sender->setCurrentWindow(new CraftingTableInventory($sender->getPosition()));
        if (!$opened) {
            $sender->sendMessage(C::colorize("&r&cCould not open crafting table right now."));
            return;
        }

        $sender->sendMessage(C::colorize("&r&aOpened portable crafting table."));
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }
}
