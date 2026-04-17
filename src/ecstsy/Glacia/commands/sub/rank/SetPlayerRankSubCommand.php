<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\rank;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\ranks\RankManager;
use ecstsy\MartianUtilities\utils\commands\arguments\PlayerArgument;
use pocketmine\command\CommandSender;

final class SetPlayerRankSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("rank"));
        $this->registerArgument(1, new PlayerArgument("player"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        $target = $args["player"];
        $rankKey = strtolower(trim($args["rank"]));
        $rank = RankManager::getInstance()->getRank($rankKey);
        if ($rank === null) {
            $sender->sendMessage("§cError: Rank '$rankKey' does not exist.");
            return;
        }

        $session = Loader::getPlayerManager()->getSession($target);
        if ($session === null) {
            $sender->sendMessage("§cError: Player session not found.");
            return;
        }

        if ($session->getRank() === $rankKey) {
            $sender->sendMessage("§e{$target->getName()} already has the rank '$rankKey'.");
            return;
        }

        $session->setRank($rankKey); 

        try {
            $attachment = RankManager::getInstance()->getAttachment($target);
            RankManager::getInstance()->updatePermission($target);
        } catch (\Throwable $e) {
            // If they didn't have an attachment yet (e.g. just joined), ignore
        }

        $sender->sendMessage("§aSet {$target->getName()}'s rank to '{$rankKey}'.");
        $target->sendMessage("§aYour rank has been set to '{$rankKey}'.");
    }

    public function getPermission(): string {
        return "glacia.command.ranks";
    }
}