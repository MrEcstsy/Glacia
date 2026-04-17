<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\rank;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\server\ranks\RankManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as C;

final class RemoveRankPermissionSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("rank"));
        $this->registerArgument(1, new RawStringArgument("permission"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        $key = strtolower(trim((string) $args["rank"]));
        $permission = trim((string) $args["permission"]);

        $rank = RankManager::getInstance()->getRank($key);
        if ($rank === null) {
            $sender->sendMessage(C::colorize("&r&cError: Rank '$key' does not exist."));
            return;
        }

        if (!in_array($permission, $rank->getOwnPermissions(), true)) {
            $sender->sendMessage(C::colorize("&r&eRank '$key' does not have permission '$permission'."));
            return;
        }

        RankManager::getInstance()->removePermissionFromRank($key, $permission);
        $sender->sendMessage(C::colorize("&r&aRemoved permission '$permission' from rank '$key'."));

        GlaciaUtils::logAdminAction("Remove Rank Permission", [
            "admin" => $sender->getName(),
            "target" => $key,
            "permission" => $permission
        ]);
    }

    public function getPermission(): string {
        return "glacia.command.ranks";
    }
}
