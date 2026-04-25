<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\server\zones\ZoneManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\Server;

final class SpawnCommand extends BaseCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $worldManager = Server::getInstance()->getWorldManager();
        $world = $worldManager->getDefaultWorld();
        if ($world === null && $worldManager->loadWorld("world")) {
            $world = $worldManager->getWorldByName("world");
        }

        if ($world === null) {
            return;
        }

        ZoneManager::allowNextBoundaryCrossing($sender, 15);
        GlaciaUtils::timedTeleport($sender, $world->getSpawnLocation());
    }

    public function getPermission(): string {
        return "glacia.default";
    }
}
