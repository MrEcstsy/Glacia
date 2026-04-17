<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\factions\relations;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\factions\FactionManager;
use ecstsy\Glacia\server\factions\relations\RelationManager;
use ecstsy\Glacia\server\factions\relations\RelationRequestManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class AllyAcceptSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("faction", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void {
        if (!$sender instanceof Player) return;

        $session = Loader::getPlayerManager()->getSession($sender);
        $ownId = $session?->getFactionId();
        if ($ownId === null) {
            $sender->sendMessage(GlaciaUtils::format("player.error.no_faction"));
            return;
        }

        $argFaction = strtolower($args["faction"] ?? "");

        $request = RelationRequestManager::getInstance()->getRequest($ownId);
        if ($request === null) {
            $sender->sendMessage(C::colorize("&r&l&c(✘) &r&cYou have no pending ally requests."));
            return;
        }

        $fromFactionId = $request->fromFactionId;
        $expires = $request->expiresAt;

        if ($argFaction !== "" && strtolower($fromFactionId) !== $argFaction) {
            $sender->sendMessage(GlaciaUtils::format("faction.error.no_pending_allies"));
            return;
        }

        $allyCountSelf = count(RelationManager::getInstance()->getRelationsFor($ownId, "ally"));
        $allyCountOther = count(RelationManager::getInstance()->getRelationsFor($fromFactionId, "ally"));

        if ($allyCountSelf >= 2 || $allyCountOther >= 2) {
            $sender->sendMessage(GlaciaUtils::format("faction.error.max_allies", ["faction" => FactionManager::getInstance()->getFactionById($fromFactionId)?->getName()]));
            return;
        }

        RelationManager::getInstance()->setRelation($ownId, $fromFactionId, "ally");
        RelationManager::getInstance()->setRelation($fromFactionId, $ownId, "ally");

        RelationRequestManager::getInstance()->removeRequest($fromFactionId, $ownId);

        $fromFaction = FactionManager::getInstance()->getFactionById($fromFactionId);

        $ownFaction = $session->getFaction();
        $fromFactionName = $fromFaction->getName();
        $ownFactionName = $ownFaction?->getName() ?? "Unknown";

        $message = C::colorize("&d&lAllied! &r&7Your faction is now allied&r&7 with &d{$fromFactionName}.");
        foreach ($ownFaction->getOnlineMembers() as $member) {
            $member->sendMessage($message);
        }

        $reverseMessage = C::colorize("&d&lAllied! &r&7Your faction is now allied&r&7 with &d{$ownFactionName}.");
        foreach ($fromFaction->getOnlineMembers() as $member) {
            $member->sendMessage($reverseMessage);
        }
    }

    public function getPermission(): string {
        return "glacia.default";
    }
}
