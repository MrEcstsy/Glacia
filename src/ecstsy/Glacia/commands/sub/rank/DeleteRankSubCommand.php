<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\rank;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\server\ranks\RankManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as C;

final class DeleteRankSubCommand extends BaseSubCommand {

    private const PROTECTED_RANKS = ["frostborn", "admin"];

    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("rank"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        $key = strtolower(trim((string) $args["rank"]));
        $rank = RankManager::getInstance()->getRank($key);

        if ($rank === null) {
            $sender->sendMessage(C::colorize("&r&cRank '$key' not found."));
            return;
        }

        if (in_array($key, self::PROTECTED_RANKS, true)) {
            $sender->sendMessage(C::colorize("&r&cThat rank is protected and cannot be deleted."));
            return;
        }

        RankManager::getInstance()->deleteRank($key);
        $sender->sendMessage(C::colorize("&r&aDeleted rank '$key'. Players using it were moved to 'frostborn'."));

        GlaciaUtils::logAdminAction("Delete Rank", [
            "admin" => $sender->getName(),
            "target" => $key
        ]);
    }

    public function getPermission(): string {
        return "glacia.command.ranks";
    }
}
