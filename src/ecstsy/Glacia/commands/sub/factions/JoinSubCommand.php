<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\factions;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\server\factions\FactionManager;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\factions\invites\FactionInviteManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\Glacia\utils\QueryStmts;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class JoinSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("faction"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        if (!$sender instanceof Player) return;

        $factionName = $args["faction"] ?? null;
        if ($factionName === null) {
            $sender->sendMessage(C::colorize("&cUsage: /f join <faction>"));
            return;
        }

        $uuid = $sender->getUniqueId();
        $name = $sender->getName();

        $playerManager = Loader::getPlayerManager(); 
        $playerData = $playerManager->getSessionByUuid($uuid);
        if ($playerData === null) {
            $sender->sendMessage(GlaciaUtils::format("player.error.no_session"));
            return;
        }

        if ($playerData->getFactionId() !== null) {
            $sender->sendMessage(GlaciaUtils::format("player.error.already_in_faction"));
            return;
        }

        $faction = null;
        foreach (FactionManager::getInstance()->getAllFactions() as $f) {
            if (strtolower($f->getName()) === strtolower($factionName)) {
                $faction = $f;
                break;
            }
        }

        if ($faction === null) {
            $sender->sendMessage(GlaciaUtils::format("faction.error.not_exists", ["name" => $factionName]));
            return;
        }

        $inviteManager = FactionInviteManager::getInstance();
        if (!$inviteManager->hasInvite($uuid, $faction->getFactionId())) {
            $sender->sendMessage(GlaciaUtils::format("faction.error.no_invite", ["name" => $faction->getName()]));
            return;
        }

        $playerData->joinFaction($faction->getFactionId());

        $faction->addMember($faction->getFactionId(), $uuid);

        foreach ($faction->getOnlineMembers() as $member) {
            if ($member->getName() !== $name) {
                $member->sendMessage(GlaciaUtils::format("faction.status.joined", ["name" => $name], "none"));
            }
        }

        $inviteManager->revokeInvite($uuid, $faction->getFactionId());

        $sender->sendMessage(GlaciaUtils::format("player.status.joined_faction", ["name" => $faction->getName(), "faction" => $faction->getName()], "success"));
    }

    public function getPermission(): string {
        return "glacia.default";
    }
}
