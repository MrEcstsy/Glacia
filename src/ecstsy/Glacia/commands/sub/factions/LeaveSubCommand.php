<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\factions;

use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\factions\FactionManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\Glacia\utils\QueryStmts;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class LeaveSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) return;

        $session = Loader::getPlayerManager()->getSession($sender);
        $factionId = $session->getFactionId();

        if ($factionId === null) {
            $sender->sendMessage(GlaciaUtils::format("player.error.no_faction"));
            return;
        }

        $faction = FactionManager::getInstance()->getFaction($factionId);

        if ($faction === null) {
            $sender->sendMessage(GlaciaUtils::format("faction.error.not_exists"));
            return;
        }

        if ($faction->getLeaderUuid() === $sender->getUniqueId()->toString()) {
            $sender->sendMessage(C::colorize("&r&l&c(✘) &r&cYou are the leader! Use /f transfer to give leadership before leaving."));
            return;
        }

        $faction->kickMember($factionId, $sender->getUniqueId());

        $session->joinFaction(null, null);
        $sender->sendMessage(C::colorize("&r&l&a(✓) &r&2You have left the faction &a" . $faction->getName()));

        foreach ($faction->getOnlineMembers() as $member) {
            if ($member->getName() !== $sender->getName()) {
                $member->sendMessage(C::colorize("&r&l&c(✘) &r&4{$sender->getName()} &chas left the faction."));
            }
        }
    }

    public function getPermission(): string {
        return "glacia.default";
    }
}