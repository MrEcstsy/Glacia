<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\rank;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\server\ranks\RankManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as C;

final class SetRankDisplaySubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("rank"));
        $this->registerArgument(1, new RawStringArgument("display"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        $key = strtolower(trim((string) $args["rank"]));
        $display = trim((string) $args["display"]);
        $rank = RankManager::getInstance()->getRank($key);

        if ($rank === null) {
            $sender->sendMessage(C::colorize("&r&cRank '$key' not found."));
            return;
        }

        if ($display === "" || mb_strlen($display) > 16) {
            $sender->sendMessage(C::colorize("&r&cRank display name must be between 1 and 16 characters."));
            return;
        }

        $oldDisplay = $rank->getDisplayName();
        $rank->setDisplayName($display);

        $sender->sendMessage(C::colorize("&r&aDisplay name for rank '$key' set to '$display'."));
        GlaciaUtils::logAdminAction("Set Rank Display", [
            "admin" => $sender->getName(),
            "target" => $key,
            "old_display" => $oldDisplay,
            "new_display" => $display
        ]);
    }

    public function getPermission(): string {
        return "glacia.command.ranks";
    }
}
