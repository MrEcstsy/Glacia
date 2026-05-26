<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\managers\BragTracker;
use ecstsy\Glacia\utils\screens\BragScreen;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class SeeBragCommand extends BaseCommand
{
    protected function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("name", false));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $targetName = trim((string) ($args["name"] ?? ""));
        if ($targetName === "") {
            $sender->sendMessage(C::colorize("&r&cUsage: /cbrag <player>"));
            return;
        }

        $target = PlayerUtils::getPlayerByPrefix($targetName);
        $resolvedName = $target?->getName() ?? $targetName;
        if (!BragTracker::hasLastInventoryBrag($resolvedName)) {
            $sender->sendMessage(C::colorize("&r&cThat player has not bragged recently."));
            return;
        }

        BragScreen::display($sender, $resolvedName);
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }
}
