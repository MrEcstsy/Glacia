<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\factions\relations;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\factions\FactionManager;
use ecstsy\Glacia\server\factions\relations\RelationRequestManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class AllyDenySubCommand extends BaseSubCommand {

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
            $sender->sendMessage(GlaciaUtils::format("faction.error.no_pending_allies"));
            return;
        }

        $fromFactionId = $request->fromFactionId;

        if ($argFaction !== "" && strtolower($fromFactionId) !== $argFaction) {
            $sender->sendMessage(C::colorize("&r&l&cERROR: &r&cYou have no request for an alliance with this faction."));
            return;
        }

        RelationRequestManager::getInstance()->removeRequest($fromFactionId, $ownId);

        $fromFaction = FactionManager::getInstance()->getFactionById($fromFactionId);
        $ownFaction = $session->getFaction();

        if ($fromFaction !== null) {
            $ownFactionName = $ownFaction?->getName() ?? "Unknown";

            $messageToFromFaction = C::colorize("&c&lAlliance Denied! &r&7Your alliance request was denied by &f{$ownFactionName}&7.");
            $messageToOwnFactionLeaders = C::colorize("&c&lAlliance Denied! &r&7Your alliance request was denied by &f{$fromFaction->getName()}&7.");

            foreach ($fromFaction->getOnlineMembers() as $member) {
                $member->sendMessage($messageToFromFaction);
            }

            foreach ($ownFaction->getOnlineMembers() as $member) {
                $role = Loader::getPlayerManager()->getSession($member)?->getFactionRole();
                if (in_array($role, ["leader", "officer"], true)) {
                    $member->sendMessage($messageToOwnFactionLeaders);
                }
            }
        }

        $sender->sendMessage(C::colorize("&r&l&a(✓) &r&2You have denied the alliance request."));
    }

    public function getPermission(): string {
        return "glacia.default";
    }
}
