<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\factions\relations;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\factions\relations\RelationManager;
use ecstsy\Glacia\server\factions\FactionManager;
use ecstsy\Glacia\server\factions\relations\RelationRequestManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class AllySubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("target", false));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        if (!$sender instanceof Player) return;

        $session = Loader::getPlayerManager()->getSession($sender);
        $ownId = $session?->getFactionId();

        if ($ownId === null) {
            $sender->sendMessage(GlaciaUtils::format("player.error.no_faction"));
            return;
        }

        $role = $session->getFactionRole();

        if (!in_array($role, ["leader", "officer"], true)) {
            $sender->sendMessage(GlaciaUtils::format("faction.error.permission_denied"));
            return;
        }

        $ownAllyCount = count(RelationManager::getInstance()->getRelationsFor($ownId, "ally"));
        if ($ownAllyCount >= 2) {
            $sender->sendMessage(GlaciaUtils::format("faction.error.max_allies", ["faction" => $session->getFaction()?->getName()]));
            return;
        }

        $input = $args["target"];

        $targetFaction = FactionManager::getInstance()->getFactionByName($input);

        if ($targetFaction === null) {
            $p = PlayerUtils::getPlayerByPrefix($input);
            $targetFaction = $p !== null
                ? Loader::getPlayerManager()->getSession($p)?->getFaction()
                : null;
        }

        if ($targetFaction === null) {
            $sender->sendMessage(GlaciaUtils::format("player.error.invalid", ["name" => $input]));
            return;
        }

        $targetId = $targetFaction->getFactionId();

        if ($targetId === $ownId) {
            $sender->sendMessage(GlaciaUtils::format("faction.error.cant_ally_self"));
            return;
        }

        $targetAllyCount = count(RelationManager::getInstance()->getRelationsFor($targetId, "ally"));
        if ($targetAllyCount >= 2) {
            $sender->sendMessage(GlaciaUtils::format("faction.error.max_allies", ["faction" => $targetFaction->getName()]));
            return;
        }

        if (RelationManager::getInstance()->getRelation($ownId, $targetId) === "ally") {
            $sender->sendMessage(GlaciaUtils::format("faction.error.already_allied", ["faction" => $targetFaction->getName()]));
            return;
        }

        $leaderName = $targetFaction->getLeaderName();
        $targetPlayer = Loader::getInstance()->getServer()->getPlayerExact($leaderName);
        if ($targetPlayer === null) {
            $sender->sendMessage(C::colorize("&r&l&c(✘) &r&cThis faction has no leader online."));
            return;
        }

        if (RelationRequestManager::getInstance()->hasRequestFrom($ownId, $targetId)) {
            $sender->sendMessage(C::colorize('&r&l&c(✘) &r&cYou have already sent an alliance request to this faction.'));
            return;
        }

        RelationRequestManager::getInstance()->addRequest($ownId,  $targetId);

        $ownFactionName = $session->getFaction()?->getName() ?? "Unknown";

        $message = C::colorize("&d&lAlliance Request! &r&7You have received an alliance request from &6{$ownFactionName}&7. "
            . "Please tell your leader or officer to use &e/f allyaccept&r&7 to accept.");

        foreach ($targetFaction->getOnlineMembers() as $member) {
            $member->sendMessage($message);
        }

        $sender->sendMessage(C::colorize("&d&lAlliance Request! &r&7You have sent an alliance request to &6{$targetFaction->getName()}&7."));
    }

    public function getPermission(): string {
        return "glacia.default";
    }
}
