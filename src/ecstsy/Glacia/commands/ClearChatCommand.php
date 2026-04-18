<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as C;

final class ClearChatCommand extends BaseCommand {

    protected function prepare(): void
    {
        $this->setPermission($this->getPermission());
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        foreach (Loader::getInstance()->getServer()->getOnlinePlayers() as $player) {
            for ($i = 0; $i < 120; $i++) {
                $player->sendMessage(" ");
            }

            $player->sendMessage(C::colorize("&r&8[&bChat&8] &r&fChat was cleared by &b" . $sender->getName() . "&f."));
        }

        GlaciaUtils::logAdminAction("Clear Chat", [
            "admin" => $sender->getName()
        ]);
    }

    public function getPermission(): string
    {
        return "glacia.admin";
    }
}
