<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class SuicideCommand extends BaseCommand
{
    public function prepare(): void
    {
        $this->setPermission($this->getPermission());
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage(C::colorize("&r&cRun this command in-game."));
            return;
        }

        if (Loader::getCombatManager()->isInCombat($sender)) {
            $sender->sendMessage(C::colorize("&r&cYou cannot use this while in combat."));
            return;
        }

        if (!$sender->isAlive()) {
            $sender->sendMessage(C::colorize("&r&eYou are already dead."));
            return;
        }

        $sender->setHealth(0.0);
        $sender->sendMessage(C::colorize("&r&cYou chose death."));
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }
}
