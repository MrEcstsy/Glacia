<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\Glacia\utils\screens\BragScreen;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class InvSeeCommand extends BaseCommand {

    protected function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("player"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $targetName = (string) ($args["player"] ?? "");
        $target = PlayerUtils::getPlayerByPrefix($targetName);
        if (!$target instanceof Player) {
            $sender->sendMessage(C::colorize("&r&cPlayer '&f{$targetName}&c' was not found online."));
            return;
        }

        BragScreen::displayTo($sender, $target);
        GlaciaUtils::logAdminAction("InvSee", [
            "admin" => $sender->getName(),
            "target" => $target->getName()
        ]);
    }

    public function getPermission(): string
    {
        return "glacia.admin";
    }
}
