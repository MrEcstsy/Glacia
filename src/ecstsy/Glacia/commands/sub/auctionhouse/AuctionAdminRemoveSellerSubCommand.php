<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\auctionhouse;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as C;

final class AuctionAdminRemoveSellerSubCommand extends BaseSubCommand {

    public function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("seller"));
        $this->registerArgument(1, new RawStringArgument("mode", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        $seller = trim((string) ($args["seller"] ?? ""));
        $mode = strtolower(trim((string) ($args["mode"] ?? "return")));
        $return = $mode !== "delete";

        $removed = Loader::getAuctionHouseManager()->adminRemoveSellerListings($seller, $return);
        $sender->sendMessage(C::colorize("&r&aRemoved &f" . $removed . "&a auction listing(s) for &f" . $seller . "&a."));

        GlaciaUtils::logAdminAction("Auction House Remove Seller Listings", [
            "admin" => $sender->getName(),
            "seller" => $seller,
            "mode" => $return ? "return" : "delete",
            "removed" => (string) $removed
        ]);
    }

    public function getPermission(): string
    {
        return "glacia.admin";
    }
}
