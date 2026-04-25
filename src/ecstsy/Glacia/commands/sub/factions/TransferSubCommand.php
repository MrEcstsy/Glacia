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

final class TransferSubCommand extends BaseSubCommand {

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
        if ($session === null || $faction === null || $session->getFactionRole() !== "leader") {
            $sender->sendMessage(GlaciaUtils::format("faction.error.permission_denied"));
            return;
        }

        $target = Loader::getPlayerManager()->getSessionByPrefix((string) ($args["player"] ?? ""));
        if ($target === null || $target->getFactionId() !== $faction->getFactionId()) {
            $sender->sendMessage(GlaciaUtils::format("faction.error.not_in_your_faction", ["player" => (string) ($args["player"] ?? "")]));
            return;
        }

        LeaderTransferConfirmScreen::display($sender, $target->getUsername());
    }

    public function getPermission(): string {
        return "glacia.default";
    }
}
