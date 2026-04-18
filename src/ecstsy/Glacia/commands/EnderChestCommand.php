<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\type\InvMenuTypeIds;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class EnderChestCommand extends BaseCommand {

    public function prepare() : void
    {
        $this->setPermission($this->getPermission());
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $menu = InvMenu::create(InvMenuTypeIds::TYPE_CHEST, $sender->getEnderInventory());
        $menu->setName(C::colorize("&r&8Ender Chest"));
        $menu->send($sender);
        $sender->sendMessage(C::colorize("&r&aOpened your ender chest."));
    }

    public function getPermission() : string
    {
        return "glacia.default";
    }
}
