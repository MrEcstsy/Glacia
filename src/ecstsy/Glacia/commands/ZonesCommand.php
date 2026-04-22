<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\utils\screens\zones\ZonesScreen;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

final class ZonesCommand extends BaseCommand {

    protected function prepare(): void
    {
        $this->setPermission($this->getPermission());
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        ZonesScreen::display($sender);
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }
}
