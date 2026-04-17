<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\rank;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\server\ranks\RankManager;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as C;

final class AddRankPermissionSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("rank", false));
        $this->registerArgument(1, new RawStringArgument("permission", false));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        $key = strtolower($args["rank"]);
        $perm = $args["permission"];

        $rm = RankManager::getInstance();
        $rank = $rm->getRank($key);
        if ($rank === null) {
            $sender->sendMessage("§cError: Rank '$key' does not exist.");
            return;
        }

        if (in_array($perm, $rank->getPermissions(), true)) {
            $sender->sendMessage("§eRank '$key' already has permission '$perm'.");
            return;
        }

        $rm->addPermissionToRank($key, $perm);
        $sender->sendMessage("§aAdded permission '$perm' to rank '$key'.");
    }

    public function getPermission(): string {
        return "glacia.command.ranks";
    }
}
