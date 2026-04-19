<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\managers\TPAManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\MartianUtilities\utils\commands\arguments\PlayerArgument;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class TPACommand extends BaseCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());

        $this->registerArgument(0, new PlayerArgument("target", false));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        /** @var Player|null $target */
        $target = $args["target"] ?? null;

        if ($target === null || !$target->isOnline()) {
            $sender->sendMessage(GlaciaUtils::format("player.error.invalid", ["name" => "that player"], "error"));
            return;
        }

        if ($target->getName() === $sender->getName()) {
            $sender->sendMessage(C::colorize("&r&l&b[/TPA] &r&fYou cannot send a teleport request to yourself."));
            return;
        }


        if (TPAManager::hasPendingRequest($target, $sender)) {
            $sender->sendMessage(C::colorize("&r&l&b[/TPA] &r&fYou already have a pending teleport request to {$target->getName()}."));
            return;
        }

        TPAManager::sendTPA($sender, $target, "tpa");
    }

    public function getPermission(): string {
        return "glacia.default";
    }
}
