<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\utils\screens\enderchest\EnderChestScreen;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

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

        EnderChestScreen::displayOwn($sender);
    }

    public function getPermission() : string
    {
        return "glacia.default";
    }
}
