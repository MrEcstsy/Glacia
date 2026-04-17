<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
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

        GlaciaUtils::timedTeleport($sender, Server::getInstance()->getWorldManager()->getDefaultWorld()->getSpawnLocation()->asVector3());
    }

    public function getPermission(): string {
        return "glacia.default";
    }
}