<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\server\player\BackLocationManager;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class BackCommand extends BaseCommand {

    public function prepare() : void
    {
        $this->setPermission($this->getPermission());
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args) : void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $back = BackLocationManager::get($sender);
        if ($back === null) {
            $sender->sendMessage(C::colorize("&r&cYou do not have a previous location to return to."));
            return;
        }

        $world = $sender->getServer()->getWorldManager()->getWorldByName($back["world"]);
        if ($world === null) {
            $sender->sendMessage(C::colorize("&r&cThat previous world is not currently loaded."));
            return;
        }

        $sender->teleport(new \pocketmine\entity\Location(
            (float) $back["x"],
            (float) $back["y"],
            (float) $back["z"],
            $world,
            (float) $back["yaw"],
            (float) $back["pitch"]
        ));
        $sender->sendMessage(C::colorize("&r&aTeleported you back to your previous location."));
    }

    public function getPermission() : string
    {
        return "glacia.default";
    }
}
