<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\utils\screens\enchanter\EnchanterScreen;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

final class EnchanterCommand extends BaseCommand {

    public function prepare(): void
    {
        $this->setPermission($this->getPermission());
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        (new EnchanterScreen())->display($sender);
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }
}
