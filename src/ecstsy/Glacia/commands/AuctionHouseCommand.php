<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\commands\sub\auctionhouse\AuctionAdminListingSubCommand;
use ecstsy\Glacia\commands\sub\auctionhouse\AuctionAdminRemoveSellerSubCommand;
use ecstsy\Glacia\commands\sub\auctionhouse\AuctionRelistSubCommand;
use ecstsy\Glacia\commands\sub\auctionhouse\AuctionSearchSubCommand;
use ecstsy\Glacia\commands\sub\auctionhouse\AuctionSellSubCommand;
use ecstsy\Glacia\commands\sub\auctionhouse\AuctionWatchSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\screens\auctionhouse\AuctionHouseScreen;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

final class AuctionHouseCommand extends BaseCommand {

    public function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerSubCommand(new AuctionSellSubCommand(Loader::getInstance(), "sell", "Sell the item in your hand on the auction house"));
        $this->registerSubCommand(new AuctionSearchSubCommand(Loader::getInstance(), "search", "Search/filter auction listings"));
        $this->registerSubCommand(new AuctionWatchSubCommand(Loader::getInstance(), "watch", "Manage your AH watchlist"));
        $this->registerSubCommand(new AuctionRelistSubCommand(Loader::getInstance(), "relist", "Relist an item from collection by entry id"));
        $this->registerSubCommand(new AuctionAdminRemoveSellerSubCommand(Loader::getInstance(), "removeplayer", "Remove a player's auction listings"));
        $this->registerSubCommand(new AuctionAdminListingSubCommand(Loader::getInstance(), "removelisting", "Remove one listing by id"));
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
