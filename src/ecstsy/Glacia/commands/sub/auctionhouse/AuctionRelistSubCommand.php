<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\auctionhouse;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class AuctionRelistSubCommand extends BaseSubCommand{

	public function prepare() : void{
		$this->setPermission($this->getPermission());
		$this->registerArgument(0, new RawStringArgument("entryId"));
		$this->registerArgument(1, new RawStringArgument("price"));
	}

	public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void{
		if(!$sender instanceof Player){
			return;
		}
		$entryId = trim((string) ($args["entryId"] ?? ""));
		$priceInput = trim((string) ($args["price"] ?? ""));
		$price = is_numeric($priceInput) ? (int) $priceInput : (int) PlayerUtils::parseShorthandAmount($priceInput);
		if($entryId === "" || $price <= 0){
			$sender->sendMessage(C::colorize("&r&cUsage: /ah relist <collectionEntryId> <price>"));
			return;
		}

		$result = Loader::getAuctionHouseManager()->relistCollectionEntry($sender, $entryId, $price);
		$sender->sendMessage(C::colorize($result === "" ? "&r&aRelisted collection item for &f$" . number_format($price) . "&a." : "&r&c" . $result));
	}

	public function getPermission() : string{
		return "glacia.default";
	}
}

