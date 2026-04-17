<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\commands\sub\rank\AddPlayerPermissionSubCommand;
use ecstsy\Glacia\commands\sub\rank\AddRankPermissionSubCommand;
use ecstsy\Glacia\commands\sub\rank\CreateRankSubCommand;
use ecstsy\Glacia\commands\sub\rank\ListRanksSubCommand;
use ecstsy\Glacia\commands\sub\rank\SetChatFormatSubCommand;
use ecstsy\Glacia\commands\sub\rank\SetPlayerRankSubCommand;
use ecstsy\Glacia\Loader;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as C;

final class RankCommand extends BaseCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());

        $this->registerSubCommand(new CreateRankSubCommand(Loader::getInstance(), "create", "Create a rank"));
        $this->registerSubCommand(new SetPlayerRankSubCommand(Loader::getInstance(), "set", "Set a player's rank"));
        $this->registerSubCommand(new SetChatFormatSubCommand(Loader::getInstance(), "setchat", "Set a rank's chat format"));
        $this->registerSubCommand(new ListRanksSubCommand(Loader::getInstance(), "list", "List all ranks"));
        $this->registerSubCommand(new SetChatFormatSubCommand(Loader::getInstance(), "setchatformat", "Set a rank's chat format"));
        $this->registerSubCommand(new AddRankPermissionSubCommand(Loader::getInstance(), "addperm", "Add a permission to a rank"));
        $this->registerSubCommand(new AddPlayerPermissionSubCommand(Loader::getInstance(), "userperm", "Add a permission to a player"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        $sender->sendMessage(C::colorize("&r&cUsage: /glaciaranks <create|set|setchat|list|addperm|userperm>"));
    }

    public function getPermission(): string {
        return "glacia.command.ranks";
    }
}