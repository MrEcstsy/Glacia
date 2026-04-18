<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\commands\sub\auctionhouse\AuctionAdminRemoveSellerSubCommand;
use ecstsy\Glacia\commands\sub\auctionhouse\AuctionSellSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\screens\auctionhouse\AuctionHouseScreen;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

final class AuctionHouseCommand extends BaseCommand {

    public function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerSubCommand(new AuctionSellSubCommand(Loader::getInstance(), "sell", "Sell the item in your hand on the auction house"));
        $this->registerSubCommand(new AuctionAdminRemoveSellerSubCommand(Loader::getInstance(), "removeplayer", "Remove a player's auction listings"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        (new AuctionHouseScreen($sender))->display($sender);
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }
}
