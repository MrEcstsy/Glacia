<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\auctionhouse;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as C;

final class AuctionAdminListingSubCommand extends BaseSubCommand{

	public function prepare() : void{
		$this->setPermission($this->getPermission());
		$this->registerArgument(0, new RawStringArgument("listingId"));
		$this->registerArgument(1, new RawStringArgument("mode", true));
	}

	public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void{
		$listingId = trim((string) ($args["listingId"] ?? ""));
		$mode = strtolower(trim((string) ($args["mode"] ?? "refund")));
		$refund = $mode !== "delete";

		if($listingId === ""){
			$sender->sendMessage(C::colorize("&r&cUsage: /ah removelisting <listingId> [refund|delete]"));
			return;
		}

		$result = Loader::getAuctionHouseManager()->adminRemoveListing($listingId, $refund);
		$sender->sendMessage(C::colorize($result === "" ? "&r&aListing removed (&f" . ($refund ? "refund" : "delete") . "&a)." : "&r&c" . $result));

		if($result === ""){
			GlaciaUtils::logAdminAction("Auction House Remove Listing", [
				"admin" => $sender->getName(),
				"listing_id" => $listingId,
				"mode" => $refund ? "refund" : "delete"
			]);
		}
	}

	public function getPermission() : string{
		return "glacia.admin";
	}
}

