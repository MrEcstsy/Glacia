<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\staff\StaffModeManager;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

final class AdminModeCommand extends BaseCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $session = Loader::getPlayerManager()->getSession($sender);
        StaffModeManager::toggle($sender, $session);
    }

    public function getPermission(): string {
        return 'glacia.admin';
    }
}
