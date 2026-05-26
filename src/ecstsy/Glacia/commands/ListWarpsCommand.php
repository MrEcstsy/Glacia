<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class ListWarpsCommand extends BaseCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());

        $this->registerArgument(0, new RawStringArgument("warp", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        $warpManager = Loader::getWarpManager();
        $warps = $warpManager->getWarpList();

        if ($warps === null) {
            $sender->sendMessage(C::colorize("&4No Available Warps"));
            return;
        }

        $warpNames = [];
        foreach ($warps as $warp) {
            $warpNames[] = $warp->getName();
        }

        $warpList = implode(C::colorize("&f, &c"), $warpNames);
        $sender->sendMessage(C::colorize("&6Available Warps: &c" . $warpList));
    }

    public function getPermission(): string {
        return "glacia.default";
    }
}
