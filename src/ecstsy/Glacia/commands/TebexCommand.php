<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as C;

final class TebexCommand extends BaseCommand{

	public function prepare() : void{
		$this->setPermission($this->getPermission());
		$this->registerArgument(0, new RawStringArgument("action", true));
	}

	public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void{
		$action = strtolower(trim((string) ($args["action"] ?? "status")));
		$manager = Loader::getTebexManager();

		if($action === "poll" || $action === "refresh"){
			if(!$manager->isEnabled()){
				$sender->sendMessage(C::colorize("&r&cTebex is disabled or missing secret in tebex.yml."));
				return;
			}
			if(!$manager->queuePoll(true)){
				$sender->sendMessage(C::colorize("&r&eA Tebex poll is already running."));
				return;
			}
			$sender->sendMessage(C::colorize("&r&aQueued a Tebex poll."));
			return;
		}

		$sender->sendMessage($manager->getStatusMessage());
	}

	public function getPermission() : string{
		return "glacia.admin";
	}
}

