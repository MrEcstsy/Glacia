<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\commands\sub\balance\GiveSubCommand;
use ecstsy\Glacia\commands\sub\balance\TakeSubCommand;
use ecstsy\Glacia\Loader;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class EcoCommand extends BaseCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());

        $this->registerSubCommand(new GiveSubCommand(Loader::getInstance(), "give", "Give money to a player"));
        $this->registerSubCommand(new TakeSubCommand(Loader::getInstance(), "take", "Take money from a player"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $messages = [
            '&r&6Description: &f' . $this->getDescription(),
            '&r&6Usage(s):',
            "&r&f/eco give &e<player> <amount> &6- Gives the specified player the",
            "&r&fspecified amount of money",
            "&r&f/eco take &e<player> <amount> &6- Takes the specified amount of",
            "&r&fmoney from the specified player",
            "&r&f/eco set &e<player> <amount> &6- Sets the specified player's",
            "&r&fbalance to the specified amount of money",
            "&r&f/eco reset &e<player> [amount] &6- Resets the specified player's",
            "&r&fbalance to the server's starting balance"
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