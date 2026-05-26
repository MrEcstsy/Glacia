<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\TextArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\MartianUtilities\utils\commands\arguments\PlayerArgument;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class SudoCommand extends BaseCommand
{
    protected function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new PlayerArgument("player"));
        $this->registerArgument(1, new TextArgument("message"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        /** @var Player|null $target */
        $target = $args["player"] ?? null;
        $message = trim((string) ($args["message"] ?? ""));

        if (!$target instanceof Player || !$target->isConnected()) {
            $sender->sendMessage(C::colorize("&r&cThat player could not be found."));
            return;
        }

        if ($message === "") {
            $sender->sendMessage(C::colorize("&r&cUsage: /sudo <player> <message or /command>"));
            return;
        }

        $target->chat($message);

        $type = str_starts_with($message, "/") ? "command" : "chat";
        $sender->sendMessage(C::colorize("&r&aForced &f" . $target->getName() . "&a to " . $type . ": &f" . $message));

        GlaciaUtils::logAdminAction("Sudo", [
            "admin" => $sender->getName(),
            "target" => $target->getName(),
            "type" => $type,
            "payload" => $message,
        ]);
    }

    public function getPermission(): string
    {
        return "glacia.admin";
    }
}
