<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\TextArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as C;

final class BroadcastCommand extends BaseCommand {

    protected function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new TextArgument("message"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        $message = trim((string) ($args["message"] ?? ""));
        if ($message === "") {
            $sender->sendMessage(C::colorize("&r&cUsage: /broadcast <message>"));
            return;
        }

        $formatted = C::colorize("&r&8[&bBroadcast&8] &r&f" . $message);
        Loader::getInstance()->getServer()->broadcastMessage($formatted);

        GlaciaUtils::logAdminAction("Broadcast", [
            "admin" => $sender->getName(),
            "message" => $message
        ]);
    }

    public function getPermission(): string
    {
        return "glacia.admin";
    }
}
