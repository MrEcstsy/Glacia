<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class VoteCommand extends BaseCommand{

	public function prepare() : void{
		$this->setPermission($this->getPermission());
		$this->registerArgument(0, new RawStringArgument("action", true));
	}

	public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void{
		if(!$sender instanceof Player){
			return;
		}

		$manager = Loader::getVoteManager();
		$action = strtolower(trim((string) ($args["action"] ?? "info")));

		if(!$manager->isEnabled()){
			$sender->sendMessage(C::colorize("&r&cVoting is currently disabled."));
			return;
		}

		switch($action){
			case "claim":
				$remaining = $manager->getRemainingLocalCooldown($sender->getName());
				if($remaining > 0){
					$sender->sendMessage(C::colorize("&r&eYou already claimed recently. Try again in &f" . \ecstsy\MartianUtilities\utils\GeneralUtils::translateTime($remaining) . "&r&e."));
					return;
				}

				if(!$manager->queueClaim($sender, true)){
					$sender->sendMessage(C::colorize("&r&eA vote check is already in progress."));
				}else{
					$sender->sendMessage(C::colorize("&r&7Checking for a pending vote..."));
				}
				return;

			case "top":
				$top = $manager->getTopVoters(10);
				$sender->sendMessage(C::colorize("&r&6Top local vote claims"));
				if($top === []){
					$sender->sendMessage(C::colorize("&r&7No claims yet."));
					return;
				}
				$rank = 1;
				foreach($top as $name => $count){
					$sender->sendMessage(C::colorize("&r&e#{$rank} &f{$name} &7- &a{$count}"));
					$rank++;
				}
				return;

			case "info":
			default:
				$sender->sendMessage(C::colorize("&r&6Vote to earn rewards"));
				foreach($manager->getVoteSites() as $site){
					$sender->sendMessage(C::colorize("&r&b- &f" . $site));
				}
				$sender->sendMessage(C::colorize("&r&7Use &f/vote claim &7after voting."));
				return;
		}
	}

	public function getPermission() : string{
		return "glacia.default";
	}
}

