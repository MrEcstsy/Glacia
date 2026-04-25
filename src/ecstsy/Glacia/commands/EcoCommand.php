<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\commands\sub\balance\GiveSubCommand;
use ecstsy\Glacia\commands\sub\balance\ResetSubCommand;
use ecstsy\Glacia\commands\sub\balance\SetSubCommand;
use ecstsy\Glacia\commands\sub\balance\TakeSubCommand;
use ecstsy\Glacia\Loader;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as C;

final class EcoCommand extends BaseCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());

        $this->registerSubCommand(new GiveSubCommand(Loader::getInstance(), "give", "Give money to a player"));
        $this->registerSubCommand(new TakeSubCommand(Loader::getInstance(), "take", "Take money from a player"));
        $this->registerSubCommand(new SetSubCommand(Loader::getInstance(), "set", "Set a player's balance"));
        $this->registerSubCommand(new ResetSubCommand(Loader::getInstance(), "reset", "Reset a player's balance"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        $messages = [
            '&r&eEconomy Admin',
            '&r&7/eco give <player> <amount> &f- add money',
            '&r&7/eco take <player> <amount> &f- remove money',
            '&r&7/eco set <player> <amount> &f- set balance exactly',
            '&r&7/eco reset <player> &f- reset to starting balance'
        ];

        foreach ($messages as $message) {
            $sender->sendMessage(C::colorize($message));
        }
    }

    public function getPermission(): string
    {
        return "glacia.eco";
    }
}
