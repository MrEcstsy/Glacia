<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\factions;

use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\factions\FactionManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\Glacia\utils\screens\faction\DisbandConfirmationScreen;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class DisbandSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $session = Loader::getPlayerManager()->getSession($sender);
        $factionId = $session->getFactionId();

        if ($factionId === null) {
            $sender->sendMessage(GlaciaUtils::format("player.error.no_faction"));
            return;
        }

        $faction = FactionManager::getInstance()->getFaction($factionId);
        if ($faction === null) {
            $sender->sendMessage(GlaciaUtils::format("faction.error.not_exists", ["name" => $factionId]));
            return;
        }

        if ($faction->getLeaderUuid() !== $sender->getUniqueId()->toString()) {
            $sender->sendMessage(GlaciaUtils::format("faction.error.permission_denied"));
            return;
        }

        (new DisbandConfirmationScreen($sender))->displayForm($sender);
    }

    public function getPermission(): string {
        return "glacia.default";
    }
}