<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\factions;

use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\factions\FactionManager;
use ecstsy\Glacia\server\factions\invites\FactionInviteManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\MartianUtilities\utils\commands\arguments\PlayerArgument;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class InviteSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());

        $this->registerArgument(0, new PlayerArgument("target"));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $target = $args["target"] ?? null;

        if (!$target instanceof Player) {
            $sender->sendMessage(GlaciaUtils::format("player.error.invalid", ["name" => $args["target"]]));
            return;
        }

        $playerData = Loader::getPlayerManager()->getSession($sender);
        $factionId = $playerData->getFactionId();

        if ($factionId === null) {
            $sender->sendMessage(GlaciaUtils::format("player.error.no_faction"));
            return;
        }

        $faction = FactionManager::getInstance()->getFaction($factionId);

        if ($faction === null) {
            $sender->sendMessage(GlaciaUtils::format("faction.error.not_exists", ["name" => $factionId]));
            return;
        }

        $targetData = Loader::getPlayerManager()->getSession($target);
        if ($targetData !== null && $targetData->getFactionId() !== null) {
            $sender->sendMessage(GlaciaUtils::format("player.error.already_in_faction"));
            return;
        }

        $targetUuid = $target->getUniqueId();
        FactionInviteManager::getInstance()->invitePlayer($targetUuid, $factionId);
        
        $inviteTime = GeneralUtils::translateTime(FactionInviteManager::getInstance()->getInviteTime($targetUuid, $factionId));
        $factionName = $faction->getName();
        $sender->sendMessage(GlaciaUtils::format("faction.status.invite_sent", [
            "player" => $target->getName(),
            "faction" => $factionName,
            "time" => $inviteTime,
        ], "none"));
        $target->sendMessage(GlaciaUtils::format("faction.status.invite_received", [
            "faction" => $factionName,
            "time" => $inviteTime,
        ], "none"));
    }

    public function getPermission(): string {
        return "glacia.default";
    }
}