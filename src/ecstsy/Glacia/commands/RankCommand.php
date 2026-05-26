<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\commands\sub\rank\AddPlayerPermissionSubCommand;
use ecstsy\Glacia\commands\sub\rank\AddRankPermissionSubCommand;
use ecstsy\Glacia\commands\sub\rank\ClearPlayerPermissionsSubCommand;
use ecstsy\Glacia\commands\sub\rank\CreateRankSubCommand;
use ecstsy\Glacia\commands\sub\rank\DeleteRankSubCommand;
use ecstsy\Glacia\commands\sub\rank\ListRankPermissionsSubCommand;
use ecstsy\Glacia\commands\sub\rank\ListPlayerPermissionsSubCommand;
use ecstsy\Glacia\commands\sub\rank\ListRanksSubCommand;
use ecstsy\Glacia\commands\sub\rank\RemovePlayerPermissionSubCommand;
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
        $this->registerSubCommand(new AddPlayerPermissionSubCommand($plugin, "userperm", "Add a custom permission to a player"));
        $this->registerSubCommand(new RemovePlayerPermissionSubCommand($plugin, "removeuserperm", "Remove a custom permission from a player"));
        $this->registerSubCommand(new ListPlayerPermissionsSubCommand($plugin, "listuserperms", "List a player's custom permissions"));
        $this->registerSubCommand(new ClearPlayerPermissionsSubCommand($plugin, "clearuserperms", "Clear all custom permissions from a player"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        $this->sendRankHelp($sender, $aliasUsed);
    }

    protected function sendUsage(): void
    {
        $this->sendRankHelp($this->currentSender, "ranks");
    }

    private function sendRankHelp(CommandSender $sender, string $aliasUsed): void
    {
        $cmd = "/" . ($aliasUsed !== "" ? $aliasUsed : "ranks");

        $sender->sendMessage(C::colorize("&r&d&lRank Commands"));
        $sender->sendMessage(C::colorize("&r&8Manage rank setup, player ranks, and permissions."));
        $sender->sendMessage(C::colorize("&r&7"));
        $sender->sendMessage(C::colorize("&r&dBasics"));
        $sender->sendMessage(C::colorize("&r&7- &f{$cmd} list &8- &7Show all ranks"));
        $sender->sendMessage(C::colorize("&r&7- &f{$cmd} create <key> <display> &8- &7Create a rank"));
        $sender->sendMessage(C::colorize("&r&7- &f{$cmd} delete <rank> &8- &7Delete a rank"));
        $sender->sendMessage(C::colorize("&r&7- &f{$cmd} set <rank> <player> &8- &7Set a player's rank"));
        $sender->sendMessage(C::colorize("&r&7"));
        $sender->sendMessage(C::colorize("&r&dRank Appearance"));
        $sender->sendMessage(C::colorize("&r&7- &f{$cmd} setdisplay <rank> <display>"));
        $sender->sendMessage(C::colorize("&r&7- &f{$cmd} setchat <rank> <format> &8- &7Must include &f{name} &7and &f{message}"));
        $sender->sendMessage(C::colorize("&r&7- &f{$cmd} setnametag <rank> <format> &8- &7Must include &f{name}"));
        $sender->sendMessage(C::colorize("&r&7- &f{$cmd} setparent <rank> <parent|none>"));
        $sender->sendMessage(C::colorize("&r&7"));
        $sender->sendMessage(C::colorize("&r&dRank Permissions"));
        $sender->sendMessage(C::colorize("&r&7- &f{$cmd} addperm <rank> <permission>"));
        $sender->sendMessage(C::colorize("&r&7- &f{$cmd} removeperm <rank> <permission>"));
        $sender->sendMessage(C::colorize("&r&7- &f{$cmd} perms <rank>"));
        $sender->sendMessage(C::colorize("&r&7"));
        $sender->sendMessage(C::colorize("&r&dPlayer Permissions"));
        $sender->sendMessage(C::colorize("&r&7- &f{$cmd} userperm <permission> <player>"));
        $sender->sendMessage(C::colorize("&r&7- &f{$cmd} removeuserperm <permission> <player>"));
        $sender->sendMessage(C::colorize("&r&7- &f{$cmd} listuserperms <player>"));
        $sender->sendMessage(C::colorize("&r&7- &f{$cmd} clearuserperms <player>"));
    }

    public function getPermission(): string {
        return "glacia.command.ranks";
    }
}
