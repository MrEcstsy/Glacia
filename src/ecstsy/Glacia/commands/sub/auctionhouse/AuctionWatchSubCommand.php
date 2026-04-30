<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\auctionhouse;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class AuctionWatchSubCommand extends BaseSubCommand{

	public function prepare() : void{
		$this->setPermission($this->getPermission());
		$this->registerArgument(0, new RawStringArgument("action", true));
		$this->registerArgument(1, new RawStringArgument("keyword", true));
	}

	public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void{
		if(!$sender instanceof Player){
			return;
		}
		$manager = Loader::getAuctionHouseManager();
		$action = strtolower(trim((string) ($args["action"] ?? "list")));
		$keyword = trim((string) ($args["keyword"] ?? ""));

		if($action === "add"){
			if($keyword === ""){
				$sender->sendMessage(C::colorize("&r&cUsage: /ah watch add <keyword>"));
				return;
			}
			$ok = $manager->addWatchKeyword($sender, $keyword);
			$sender->sendMessage(C::colorize($ok ? "&r&aAdded AH watch keyword: &f{$keyword}" : "&r&eKeyword already in your watchlist."));
			return;
		}

		if($action === "remove"){
			if($keyword === ""){
				$sender->sendMessage(C::colorize("&r&cUsage: /ah watch remove <keyword>"));
				return;
			}
			$ok = $manager->removeWatchKeyword($sender, $keyword);
			$sender->sendMessage(C::colorize($ok ? "&r&aRemoved AH watch keyword: &f{$keyword}" : "&r&eThat keyword was not in your watchlist."));
			return;
		}

		if($action === "clear"){
			$manager->clearWatchlist($sender);
			$sender->sendMessage(C::colorize("&r&aCleared your AH watchlist."));
			return;
		}

		$list = $manager->getWatchlist($sender);
		$sender->sendMessage(C::colorize("&r&dAH Watchlist"));
		if($list === []){
			$sender->sendMessage(C::colorize("&r&7No keywords yet. Use &f/ah watch add <keyword>&7."));
			return;
		}
		foreach($list as $row){
			$sender->sendMessage(C::colorize("&r&7- &f" . $row));
		}
	}

	public function getPermission() : string{
		return "glacia.default";
	}
}

