<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\TextArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\server\chat\PrivateMessageManager;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class ReplyCommand extends BaseCommand
{
    public function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new TextArgument("message"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $message = trim((string) ($args["message"] ?? ""));
        if ($message === "") {
            $sender->sendMessage(C::colorize("&r&cUsage: /reply <message>"));
            return;
        }

        $target = PrivateMessageManager::getLastContact($sender);
        if (!$target instanceof Player || !$target->isOnline()) {
            $sender->sendMessage(C::colorize("&r&cYou have nobody to reply to right now."));
            return;
        }

        if ($target->getUniqueId()->equals($sender->getUniqueId())) {
            $sender->sendMessage(C::colorize("&r&cYou cannot message yourself."));
            return;
        }

        PrivateMessageManager::send($sender, $target, $message);
    }

    public function getPermission(): string
    {
        return "glacia.default";
    }
}
