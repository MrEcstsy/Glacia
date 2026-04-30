<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use pocketmine\command\CommandSender;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\utils\TextFormat as C;

final class FastChestCommand extends BaseCommand{

	public function prepare() : void{
		$this->setPermission($this->getPermission());
		$this->registerArgument(0, new RawStringArgument("action", true));
		$this->registerArgument(1, new RawStringArgument("world", true));
	}

	public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void{
		$action = strtolower(trim((string) ($args["action"] ?? "status")));
		$world = trim((string) ($args["world"] ?? ""));

		$plugin = $sender->getServer()->getPluginManager()->getPlugin("FasterChest");
		if($plugin === null || !$plugin->isEnabled()){
			$sender->sendMessage(C::colorize("&r&cFasterChest plugin is not loaded. Install/enable it first."));
			return;
		}

		if($action === "convert" || $action === "revert"){
			if($world === ""){
				$sender->sendMessage(C::colorize("&r&cUsage: /fastchest {$action} <world>"));
				return;
			}
			$console = new ConsoleCommandSender($sender->getServer(), $sender->getServer()->getLanguage());
			$sender->getServer()->dispatchCommand($console, "save-all");
			$sender->getServer()->dispatchCommand($console, "fasterchest {$action} {$world}");
			$sender->sendMessage(C::colorize("&r&aRan FasterChest {$action} for &f{$world}&a (with /save-all first)."));
			return;
		}

		$sender->sendMessage(C::colorize("&r&bFasterChest bridge ready."));
		$sender->sendMessage(C::colorize("&r&7Use: &f/fastchest convert <world> &7or &f/fastchest revert <world>"));
	}

	public function getPermission() : string{
		return "glacia.admin";
	}
}

