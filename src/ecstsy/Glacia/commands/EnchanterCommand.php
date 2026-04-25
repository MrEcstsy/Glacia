<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\screens\enchanter\EnchanterFormScreen;
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

        $session = Loader::getPlayerManager()->getSession($sender);
        if ($session !== null && $session->getSetting("chest_inventories") === false) {
            EnchanterFormScreen::display($sender);
            return;
        }

        (new EnchanterScreen())->display($sender);
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }
}
