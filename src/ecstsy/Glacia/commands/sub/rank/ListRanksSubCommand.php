<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\rank;

use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\server\ranks\RankManager;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as C;

final class ListRanksSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        $ranks = RankManager::getInstance()->getAllRanks();
        if (empty($ranks)) {
            $sender->sendMessage(C::colorize("&r&cNo ranks found."));
            return;
        }

        $sender->sendMessage(C::colorize("&r&d== Rank List =="));

        foreach ($ranks as $rank) {
            $sender->sendMessage(C::colorize("&r&a" . $rank->getKey() . " &7(" . $rank->getDisplayName() . ")" . ($rank->getParentKey() !== null ? " &eparent: " . $rank->getParentKey() : "")));
        }
    }

    public function getPermission(): string {
        return "glacia.command.ranks";
    }
}