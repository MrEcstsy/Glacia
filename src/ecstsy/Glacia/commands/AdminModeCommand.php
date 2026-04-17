<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

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

        if ($session->isInAdminMode()) {
            $session->setInAdminMode(false);
            $sender->sendMessage(GlaciaUtils::format("admin-mode.disabled"));
        } else {
            $session->setInAdminMode(true);
            $sender->sendMessage(GlaciaUtils::format("admin-mode.enabled"));
        }
    }

    public function getPermission(): string {
        return 'glacia.admin';
    }
}