<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\rank;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\ranks\RankManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class SetPlayerRankSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("rank"));
        $this->registerArgument(1, new RawStringArgument("player"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        $lookup = trim((string) $args["player"]);
        $rankKey = strtolower(trim((string) $args["rank"]));
        $rank = RankManager::getInstance()->getRank($rankKey);
        if ($rank === null) {
            $sender->sendMessage(C::colorize("&r&cError: Rank '$rankKey' does not exist."));
            return;
        }

        $online = $lookup !== "" ? PlayerUtils::getPlayerByPrefix($lookup) : null;
        $session = $online !== null
            ? Loader::getPlayerManager()->getSession($online)
            : Loader::getPlayerManager()->getSessionByPrefix($lookup);

        if ($session === null) {
            $sender->sendMessage(C::colorize("&r&cError: Player not found."));
            return;
        }

        $targetName = $session->getUsername();
        if ($session->getRank() === $rankKey) {
            $sender->sendMessage(C::colorize("&r&e{$targetName} already has the rank '$rankKey'."));
            return;
        }

        $oldRank = $session->getRank();
        $session->setRank($rankKey);

        $targetPlayer = $session->getPlayer();
        if ($targetPlayer instanceof Player) {
            try {
                RankManager::getInstance()->updatePermission($targetPlayer);
            } catch (\Throwable) {
                // Ignore if the target does not have an attachment yet.
            }
            $targetPlayer->sendMessage(C::colorize("&r&aYour rank has been set to '$rankKey'."));
        }

        $sender->sendMessage(C::colorize("&r&aSet {$targetName}'s rank to '$rankKey'."));

        GlaciaUtils::logAdminAction("Set Player Rank", [
            "admin" => $sender->getName(),
            "target" => $targetName,
            "old_rank" => $oldRank,
            "new_rank" => $rankKey
        ]);
    }

    public function getPermission(): string {
        return "glacia.command.ranks";
    }
}
