<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\rank;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\server\ranks\RankManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as C;

final class SetRankParentSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("rank"));
        $this->registerArgument(1, new RawStringArgument("parent"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        $key = strtolower(trim((string) $args["rank"]));
        $parentKey = strtolower(trim((string) $args["parent"]));
        $rankManager = RankManager::getInstance();

        $rank = $rankManager->getRank($key);
        if ($rank === null) {
            $sender->sendMessage(C::colorize("&r&cError: Rank '$key' does not exist."));
            return;
        }

        if ($parentKey === "none" || $parentKey === "null") {
            $oldParent = $rank->getParentKey() ?? "none";
            $rank->setParentKey(null);
            $rankManager->refreshPermissionsForOnlinePlayers();
            $sender->sendMessage(C::colorize("&r&aCleared the parent for rank '$key'."));

            GlaciaUtils::logAdminAction("Set Rank Parent", [
                "admin" => $sender->getName(),
                "target" => $key,
                "old_parent" => $oldParent,
                "new_parent" => "none"
            ]);
            return;
        }

        if ($parentKey === $key) {
            $sender->sendMessage(C::colorize("&r&cA rank cannot inherit from itself."));
            return;
        }

        $parent = $rankManager->getRank($parentKey);
        if ($parent === null) {
            $sender->sendMessage(C::colorize("&r&cError: Parent rank '$parentKey' does not exist."));
            return;
        }

        $oldParent = $rank->getParentKey() ?? "none";
        $rank->setParentKey($parentKey);
        $rankManager->refreshPermissionsForOnlinePlayers();

        $sender->sendMessage(C::colorize("&r&aSet parent of '$key' to '$parentKey'."));
        GlaciaUtils::logAdminAction("Set Rank Parent", [
            "admin" => $sender->getName(),
            "target" => $key,
            "old_parent" => $oldParent,
            "new_parent" => $parentKey
        ]);
    }

    public function getPermission(): string {
        return "glacia.command.ranks";
    }
}
