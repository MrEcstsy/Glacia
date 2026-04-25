<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\factions;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\command\CommandSender;

final class DemoteSubCommand extends BaseSubCommand {
    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("player", false));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        $session = Loader::getPlayerManager()->getSession($sender);
        $faction = $session?->getFaction();
        if ($session === null || $faction === null) {
            $sender->sendMessage(GlaciaUtils::format("player.error.no_faction"));
            return;
        }

        $roleOrder = ["recruit", "member", "officer", "leader"];
        $yourRole = $session->getFactionRole();
        $targetName = (string) ($args["player"] ?? "");
        $targetSession = Loader::getPlayerManager()->getSessionByPrefix($targetName);
        if ($targetSession === null || $targetSession->getFactionId() !== $faction->getFactionId()) {
            $sender->sendMessage(GlaciaUtils::format("faction.error.not_in_your_faction", ["player" => $targetName]));
            return;
        }

        $theirRole = $targetSession->getFactionRole();
        $yourIdx = array_search($yourRole, $roleOrder, true);
        $theirIdx = array_search($theirRole, $roleOrder, true);
        if ($yourIdx === false || $theirIdx === false || $yourIdx <= $theirIdx || $yourIdx < 2) {
            $sender->sendMessage(GlaciaUtils::format("faction.error.cant_demote"));
            return;
        }

        $newRole = $roleOrder[max($theirIdx - 1, 0)];
        $faction->setMemberRole($faction->getFactionId(), $targetSession->getUuid(), $newRole);
        $sender->sendMessage(GlaciaUtils::format("faction.status.demote.sender", ["player" => $targetSession->getUsername(), "role" => $newRole]));
        $targetSession->getPlayer()?->sendMessage(GlaciaUtils::format("faction.status.demote.target", ["role" => $newRole], "none"));
    }

    public function getPermission(): string {
        return "glacia.default";
    }
}
