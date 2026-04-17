<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\commands\sub\rank\AddPlayerPermissionSubCommand;
use ecstsy\Glacia\commands\sub\rank\AddRankPermissionSubCommand;
use ecstsy\Glacia\commands\sub\rank\CreateRankSubCommand;
use ecstsy\Glacia\commands\sub\rank\DeleteRankSubCommand;
use ecstsy\Glacia\commands\sub\rank\ListRankPermissionsSubCommand;
use ecstsy\Glacia\commands\sub\rank\ListRanksSubCommand;
use ecstsy\Glacia\commands\sub\rank\RemoveRankPermissionSubCommand;
use ecstsy\Glacia\commands\sub\rank\SetChatFormatSubCommand;
use ecstsy\Glacia\commands\sub\rank\SetNametagFormatSubCommand;
use ecstsy\Glacia\commands\sub\rank\SetPlayerRankSubCommand;
use ecstsy\Glacia\commands\sub\rank\SetRankDisplaySubCommand;
use ecstsy\Glacia\commands\sub\rank\SetRankParentSubCommand;
use ecstsy\Glacia\Loader;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as C;

final class RankCommand extends BaseCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());

        $plugin = Loader::getInstance();
        $this->registerSubCommand(new CreateRankSubCommand($plugin, "create", "Create a rank"));
        $this->registerSubCommand(new DeleteRankSubCommand($plugin, "delete", "Delete a rank"));
        $this->registerSubCommand(new ListRanksSubCommand($plugin, "list", "List all ranks"));
        $this->registerSubCommand(new SetPlayerRankSubCommand($plugin, "set", "Set a player's rank"));
        $this->registerSubCommand(new SetRankDisplaySubCommand($plugin, "setdisplay", "Set a rank's display name"));
        $this->registerSubCommand(new SetChatFormatSubCommand($plugin, "setchat", "Set a rank's chat format"));
        $this->registerSubCommand(new SetChatFormatSubCommand($plugin, "setchatformat", "Set a rank's chat format"));
        $this->registerSubCommand(new SetNametagFormatSubCommand($plugin, "setnametag", "Set a rank's nametag format"));
        $this->registerSubCommand(new SetRankParentSubCommand($plugin, "setparent", "Set a rank's parent"));
        $this->registerSubCommand(new AddRankPermissionSubCommand($plugin, "addperm", "Add a permission to a rank"));
        $this->registerSubCommand(new RemoveRankPermissionSubCommand($plugin, "removeperm", "Remove a permission from a rank"));
        $this->registerSubCommand(new ListRankPermissionsSubCommand($plugin, "perms", "List a rank's permissions"));
        $this->registerSubCommand(new AddPlayerPermissionSubCommand($plugin, "userperm", "Add a temporary permission to an online player"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        $sender->sendMessage(C::colorize(
            "&r&cUsage: /glaciaranks <create|delete|list|set|setdisplay|setchat|setnametag|setparent|addperm|removeperm|perms|userperm>"
        ));
    }

    public function getPermission(): string {
        return "glacia.command.ranks";
    }
}
