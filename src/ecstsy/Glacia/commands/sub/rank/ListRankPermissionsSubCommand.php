<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\rank;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\server\ranks\RankManager;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as C;

final class ListRankPermissionsSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("rank"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        $key = strtolower(trim((string) $args["rank"]));
        $rank = RankManager::getInstance()->getRank($key);
        if ($rank === null) {
            $sender->sendMessage(C::colorize("&r&cError: Rank '$key' does not exist."));
            return;
        }

        $sender->sendMessage(C::colorize("&r&bPermissions for '&f{$key}&b'"));

        $ownPermissions = $rank->getOwnPermissions();
        $effectivePermissions = $rank->getPermissions();

        if ($ownPermissions === []) {
            $sender->sendMessage(C::colorize("&r&7Own: &fNone"));
        } else {
            $sender->sendMessage(C::colorize("&r&7Own: &f" . implode("&7, &f", $ownPermissions)));
        }

        if ($effectivePermissions === []) {
            $sender->sendMessage(C::colorize("&r&7Effective: &fNone"));
            return;
        }

        $sender->sendMessage(C::colorize("&r&7Effective: &f" . implode("&7, &f", $effectivePermissions)));
    }

    public function getPermission(): string {
        return "glacia.command.ranks";
    }
}
