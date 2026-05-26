<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\args\TextArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\server\chat\PrivateMessageManager;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class TellCommand extends BaseCommand
{
    public function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("player"));
        $this->registerArgument(1, new TextArgument("message"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $targetLookup = trim((string) ($args["player"] ?? ""));
        $message = trim((string) ($args["message"] ?? ""));
        $target = $targetLookup !== "" ? PlayerUtils::getPlayerByPrefix($targetLookup) : null;

        if (!$target instanceof Player || $message === "") {
            $sender->sendMessage(C::colorize("&r&cUsage: /tell <player> <message>"));
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
