<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\commands\sub\blackmarket\BlackMarketClearHistorySubCommand;
use ecstsy\Glacia\commands\sub\blackmarket\BlackMarketSetSubCommand;
use ecstsy\Glacia\commands\sub\blackmarket\BlackMarketTimeSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\screens\blackmarket\BlackMarketScreen;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

final class BlackMarketCommand extends BaseCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());

        $this->registerSubCommand(new BlackMarketTimeSubCommand(Loader::getInstance(), "time", "Set the time of the black market"));
        $this->registerSubCommand(new BlackMarketClearHistorySubCommand(Loader::getInstance(), "clearhistory", "Clear the black market history"));
        $this->registerSubCommand(new BlackMarketSetSubCommand(Loader::getInstance(), "set", "Set next BAH listing from pool (saved to blackmarket.yml)"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        BlackMarketScreen::display($sender);
    }

    public function getPermission(): string {
        return "glacia.default";
    }
}
