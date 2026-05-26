<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class UndisguiseCommand extends BaseCommand
{
    public function prepare(): void
    {
        $this->setPermission($this->getPermission());
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage(C::colorize("&cThis command can only be used in-game."));
            return;
        }

        $manager = Loader::getDisguiseManager();
        if (!$manager->isDisguised($sender)) {
            $sender->sendMessage(C::colorize("&r&7You are not currently disguised."));
            return;
        }

        $manager->clear($sender);
        $sender->sendMessage(C::colorize("&r&aYour disguise has faded."));
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }
}
