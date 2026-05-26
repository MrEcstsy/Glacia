<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\server\factions\claims\ChunkBorderManager;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class ChunkBordersCommand extends BaseCommand
{
    protected function prepare(): void
    {
        $this->setPermission($this->getPermission());
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage(C::colorize("&cThis command can only be used in-game."));
            return;
        }

        $enabled = ChunkBorderManager::toggle($sender);
        $sender->sendMessage(C::colorize(
            $enabled
                ? "&r&aChunk border view enabled."
                : "&r&cChunk border view disabled."
        ));
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }
}

