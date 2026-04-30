<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class DeathInventoryLogCommand extends BaseCommand{

	public function prepare() : void{
		$this->setPermission($this->getPermission());
		$this->registerArgument(0, new RawStringArgument("action", true));
		$this->registerArgument(1, new RawStringArgument("arg1", true));
		$this->registerArgument(2, new RawStringArgument("arg2", true));
	}

	public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void{
		$manager = Loader::getDeathInventoryLogManager();
		$action = strtolower(trim((string) ($args["action"] ?? "help")));

		if(!$manager->isEnabled()){
			$sender->sendMessage(C::colorize("&r&cDeath inventory logs are disabled."));
			return;
		}

		if($action === "history"){
			$playerName = trim((string) ($args["arg1"] ?? ""));
			$page = max(1, (int) ($args["arg2"] ?? 1));
			if($playerName === ""){
				$sender->sendMessage(C::colorize("&r&cUsage: /dil history <player> [page]"));
				return;
			}
			$rows = $manager->getHistory($playerName, $page, 8);
			if($rows === []){
				$sender->sendMessage(C::colorize("&r&7No death logs found for &f{$playerName}&7."));
				return;
			}
			$sender->sendMessage(C::colorize("&r&6Death Logs &8| &f{$playerName} &7(Page {$page})"));
			foreach($rows as $row){
				$id = (int) ($row["id"] ?? 0);
				$timeAgo = GeneralUtils::translateTime(max(1, time() - (int) ($row["created_at"] ?? time())));
				$cause = trim((string) ($row["cause"] ?? ""));
				$sender->sendMessage(C::colorize("&r&e#{$id} &7- &f{$timeAgo} ago" . ($cause !== "" ? " &8| &7{$cause}" : "")));
			}
			return;
		}

		if($action === "claim"){
			if(!$sender instanceof Player){
				return;
			}
			$id = (int) ($args["arg1"] ?? 0);
			if($id <= 0){
				$sender->sendMessage(C::colorize("&r&cUsage: /dil claim <id>"));
				return;
			}
			$entry = $manager->getEntry($id);
			if($entry === null){
				$sender->sendMessage(C::colorize("&r&cNo death log with id &f{$id}&c."));
				return;
			}
			$items = $manager->decodeEntryItems($entry);
			if($items === []){
				$sender->sendMessage(C::colorize("&r&cDeath log #{$id} has no recoverable items."));
				return;
			}
			foreach($sender->getInventory()->addItem(...$items) as $left){
				$sender->getWorld()->dropItem($sender->getPosition(), $left);
			}
			$sender->sendMessage(C::colorize("&r&aRecovered &f" . count($items) . " &aitem stack(s) from death log &f#{$id}&a."));
			return;
		}

		$sender->sendMessage(C::colorize("&r&6Death Inventory Log"));
		$sender->sendMessage(C::colorize("&r&7- &f/dil history <player> [page]"));
		$sender->sendMessage(C::colorize("&r&7- &f/dil claim <id> &7(admin, in-game)"));
	}

	public function getPermission() : string{
		return "glacia.admin";
	}
}

