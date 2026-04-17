<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\factions;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\player\GlaciaPlayer;
use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\factions\Faction;
use ecstsy\Glacia\server\factions\FactionManager;
use ecstsy\Glacia\server\factions\relations\RelationManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class WhoSubCommand extends BaseSubCommand {

    public function prepare(): void {
        $this->setPermission($this->getPermission());

        $this->registerArgument(0, new RawStringArgument("target", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender instanceof Player) {
            return;
        }

        $target = $args["target"] ?? null;

        if ($target === null) {
            $session = Loader::getPlayerManager()->getSession($sender);
            $faction = $session->getFaction();

            if ($faction === null) {
                $sender->sendMessage(GlaciaUtils::format("player.error.no_faction"));
                return;
            }
            $this->displayFactionInfo($sender, $faction);
            return;
        }
        
        /** @var ?Faction $faction */
        $faction = null;

        foreach (FactionManager::getInstance()->getAllFactions() as $f) {
            if (strcasecmp($f->getName(), $target) === 0) {
                $faction = $f;
                break;
            }
        }

        if ($faction === null) {
            $player = PlayerUtils::getPlayerByPrefix($target);
            if ($player !== null) {
                $session = Loader::getPlayerManager()->getSession($player);
            } else {
                $session = Loader::getPlayerManager()->getSessionByName($target);
            }
            $faction = $session?->getFaction();
        }

        if ($faction === null) {
            $sender->sendMessage(GlaciaUtils::format("faction.error.not_exists", ["name" => $target]));
            return;
        }

        $this->displayFactionInfo($sender, $faction);
    }

    private function displayFactionInfo(CommandSender $sender, Faction $f): void {
        $pm = PlayerManager::getInstance();
        $allSessions = $pm->getSessions();

        $memberEntries = [
            "leader" => [],
            "officer" => [],
            "member" => [],
            "recruit" => [],
        ];

        foreach ($allSessions as $sess) {
            /** @var ?GlaciaPlayer $sess */ 
            if ($sess->getFactionId() !== $f->getFactionId()) {
                continue;
            }
            
            $role = $sess->getFactionRole() ?? "recruit";
            $symbol = GlaciaUtils::getFactionRoleSymbol($role);
            $color = $sess->isConnected() ? "&a" : "&c";

            $entry = "{$color}{$symbol}{$sess->getUsername()}";
            $memberEntries[$role][] = $entry;
        }

        $ordered = [];

        foreach (["leader", "officer", "member", "recruit"] as $role) {
            if (isset($memberEntries[$role])) {
                $ordered = array_merge($ordered, $memberEntries[$role]);
            }
        }

        $ownId = $f->getFactionId();
        $rm = RelationManager::getInstance();
        $allyIds = array_keys($rm->getRelationsFor($ownId, "ally", true));
        $enemyIds = array_keys($rm->getRelationsFor($ownId, "enemy", true));

        $fm = Loader::getFactionManager();
        $allyNames = array_map(fn(string $id) => $fm->getFaction($id)?->getName(), $allyIds);
        $enemyNames = array_map(fn(string $id) => $fm->getFaction($id)?->getName(), $enemyIds);

        $relation = "neutral";
        if ($sender instanceof Player) {
            $viewerSession = Loader::getPlayerManager()->getSession($sender);
            $viewerFaction = $viewerSession?->getFaction();

            if ($viewerFaction !== null) {
                if ($viewerFaction->getFactionId() === $f->getFactionId()) {
                    $relation = "own";
                } else {
                    $relation = RelationManager::getInstance()->getRelation($viewerFaction->getFactionId(), $f->getFactionId());
                }
            }
        }

        $relationColor = GlaciaUtils::getRelationColor($relation);

        $lines = [];
        $lines[] = "&r&b―――――― {$relationColor}{$f->getName()} &3――――――";
        $lines[] = "&r&b» &fDescription: &7{$f->getDescription()}";
        $lines[] = "&r&b» &fLeader: &7{$f->getLeaderName()}";

        $totalMembers = count($ordered);
        $lines[] = C::colorize("&r&b» &fMembers: &7{$totalMembers}");
        if (!empty($ordered)) {
            $lines[] = "   " . implode("&7, ", $ordered);
        }
        $currentPower = $f->getFactionPower();
        $maxPower = $f->getMaxPower();
        $claimCount = $f->getClaimCount();
        $lines[] = C::colorize("&r&b» &fPower/Max/Claims: &7{$currentPower}/{$maxPower}/{$claimCount}");

        $lines[] = "&r&b» &fAllies: &7" . (empty($allyNames) ? "None" : implode("&7, ", $allyNames));
        $lines[] = "&r&b» &fEnemies: &7" . (empty($enemyNames)  ? "None" : implode("&7, ", $enemyNames));

        foreach ($lines as $line) {
            $sender->sendMessage(C::colorize($line));
        }
    }

    public function getPermission(): string {
        return "glacia.default";
    }
}