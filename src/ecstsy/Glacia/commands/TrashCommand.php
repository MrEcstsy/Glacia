<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\type\InvMenuTypeIds;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class TrashCommand extends BaseCommand {

    protected function prepare(): void
    {
        $this->setPermission($this->getPermission());
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $menu = InvMenu::create(InvMenuTypeIds::TYPE_DOUBLE_CHEST);
        $menu->setName(C::colorize("&r&8Trash"));
        $menu->setInventoryCloseListener(function(Player $player, \pocketmine\inventory\Inventory $inventory): void {
            $inventory->clearAll();
            $player->sendMessage(C::colorize("&r&7Closed trash. All items left inside were deleted."));
        });

        $menu->send($sender);
        $sender->sendMessage(C::colorize("&r&eAnything left inside this menu will be deleted when it closes."));
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }
}
