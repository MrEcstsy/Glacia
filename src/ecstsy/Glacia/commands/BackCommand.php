<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\server\player\BackLocationManager;
use ecstsy\Glacia\utils\lang\MessageTemplates;
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
            $sender->sendMessage(C::colorize(MessageTemplates::render("back.error.none")));
            return;
        }

        $worldManager = $sender->getServer()->getWorldManager();
        $world = $worldManager->getWorldByName($back["world"]);
        if ($world === null && $back["world"] !== "" && $worldManager->loadWorld($back["world"])) {
            $world = $worldManager->getWorldByName($back["world"]);
        }

        if ($world === null) {
            $sender->sendMessage(C::colorize(MessageTemplates::render("back.error.world_unloaded")));
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
        $sender->sendMessage(C::colorize(MessageTemplates::render("back.success")));
    }

    public function getPermission() : string
    {
        return "glacia.default";
    }
}
