<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\utils\screens\kits\KitScreen;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

final class KitCommand extends BaseCommand {

    public function prepare(): void
    {
        $this->setPermission($this->getPermission());
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        KitScreen::display($sender);
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }
}