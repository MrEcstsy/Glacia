<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\jackpot;

use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as C;

final class JackpotDrawSubCommand extends BaseSubCommand
{
    public function prepare(): void
    {
        $this->setPermission($this->getPermission());
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        Loader::getJackpotInstance()->forceDraw();
        $sender->sendMessage(C::colorize("&r&aForced the jackpot to draw immediately."));
    }

    public function getPermission(): string
    {
        return "glacia.admin";
    }
}
