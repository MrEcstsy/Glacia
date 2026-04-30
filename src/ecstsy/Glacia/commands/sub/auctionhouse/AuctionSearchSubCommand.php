<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\auctionhouse;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\screens\auctionhouse\AuctionHouseScreen;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class AuctionSearchSubCommand extends BaseSubCommand{

	public function prepare() : void{
		$this->setPermission($this->getPermission());
		$this->registerArgument(0, new RawStringArgument("query", true));
		$this->registerArgument(1, new RawStringArgument("min", true));
		$this->registerArgument(2, new RawStringArgument("max", true));
		$this->registerArgument(3, new RawStringArgument("sort", true));
	}

	public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void{
		if(!$sender instanceof Player){
			return;
		}

		$query = trim((string) ($args["query"] ?? ""));
		$min = trim((string) ($args["min"] ?? ""));
		$max = trim((string) ($args["max"] ?? ""));
		$sort = strtolower(trim((string) ($args["sort"] ?? "newest")));
		$minPrice = is_numeric($min) ? max(0, (int) $min) : null;
		$maxPrice = is_numeric($max) ? max(0, (int) $max) : null;
		$validSort = in_array($sort, ["newest", "ending_soon", "price_low", "price_high"], true) ? $sort : "newest";

		$results = Loader::getAuctionHouseManager()->searchListings($query, $minPrice, $maxPrice, $validSort);
		$sender->sendMessage(C::colorize("&r&bAH search found &f" . count($results) . " &blisting(s)."));
		(new AuctionHouseScreen($sender, $query, $minPrice, $maxPrice, $validSort))->display($sender);
	}

	public function getPermission() : string{
		return "glacia.default";
	}
}

