<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\rank;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\ranks\RankManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\MartianUtilities\utils\commands\arguments\PlayerArgument;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as C;

final class SetPlayerRankSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("rank"));
        $this->registerArgument(1, new PlayerArgument("player"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        $target = $args["player"];
        $rankKey = strtolower(trim((string) $args["rank"]));
        $rank = RankManager::getInstance()->getRank($rankKey);
        if ($rank === null) {
            $sender->sendMessage(C::colorize("&r&cError: Rank '$rankKey' does not exist."));
            return;
        }

        $session = Loader::getPlayerManager()->getSession($target);
        if ($session === null) {
            $sender->sendMessage(C::colorize("&r&cError: Player session not found."));
            return;
        }

        if ($session->getRank() === $rankKey) {
            $sender->sendMessage(C::colorize("&r&e{$target->getName()} already has the rank '$rankKey'."));
            return;
        }

        $oldRank = $session->getRank();
        $session->setRank($rankKey);

        try {
            RankManager::getInstance()->updatePermission($target);
        } catch (\Throwable) {
            // Ignore if the target does not have an attachment yet.
        }

        $sender->sendMessage(C::colorize("&r&aSet {$target->getName()}'s rank to '$rankKey'."));
        $target->sendMessage(C::colorize("&r&aYour rank has been set to '$rankKey'."));

        GlaciaUtils::logAdminAction("Set Player Rank", [
            "admin" => $sender->getName(),
            "target" => $target->getName(),
            "old_rank" => $oldRank,
            "new_rank" => $rankKey
        ]);
    }

    public function getPermission(): string {
        return "glacia.command.ranks";
    }
}
