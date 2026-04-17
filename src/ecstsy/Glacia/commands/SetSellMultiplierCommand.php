<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class SetSellMultiplierCommand extends BaseCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());

        $this->registerArgument(0, new RawStringArgument("multiplier", false));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $session = Loader::getPlayerManager()->getSession($sender);

        $multiplier = (float)$args["multiplier"];
        if ($multiplier <= 0) {
            $sender->sendMessage(C::colorize("&cMultiplier must be greater than 0."));
            return;
        }

        $session = Loader::getPlayerManager()->getSession($sender);
        $session->setSellMultiplier($multiplier);

        $sender->sendMessage(C::colorize("&r&aYour sell multiplier has been set to &b(x" . number_format($multiplier, 2) . ")&r&a."));
    }

    public function getPermission(): string {
        return "glacia.admin";
    }
}