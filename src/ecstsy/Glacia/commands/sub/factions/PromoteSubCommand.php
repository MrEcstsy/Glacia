<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\factions;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\Glacia\utils\screens\faction\LeaderTransferConfirmScreen;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class PromoteSubCommand extends BaseSubCommand {
    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("player", false));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        if (!$sender instanceof Player) {
            return;
        }

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
            $sender->sendMessage(GlaciaUtils::format("faction.error.permission_denied"));
            return;
        }

        $newRole = $roleOrder[min($theirIdx + 1, $yourIdx)];
        if ($newRole === "leader") {
            LeaderTransferConfirmScreen::display($sender, $targetSession->getUsername());
            return;
        }

        $faction->setMemberRole($faction->getFactionId(), $targetSession->getUuid(), $newRole);
        $sender->sendMessage(C::colorize('&r&l&a(✓) &r&aYou promoted &2' . $targetName . ' &r&ato &2' . $newRole . '&a.'));
        $targetSession->getPlayer()?->sendMessage(C::colorize('&r&l&a(✓) &r&aYou have been promoted to &2' . $newRole . '&a.'));
    }

    public function getPermission(): string {
        return "glacia.default";
    }
}
