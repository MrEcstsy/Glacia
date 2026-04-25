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
            $faction = $session?->getFaction();

            if ($faction === null) {
                $sender->sendMessage(GlaciaUtils::format("player.error.no_faction"));
                return;
            }

            $this->displayFactionInfo($sender, $faction);
            return;
        }

        $faction = null;
        foreach (FactionManager::getInstance()->getAllFactions() as $candidate) {
            if (strcasecmp($candidate->getName(), (string) $target) === 0) {
                $faction = $candidate;
                break;
            }
        }

        if ($faction === null) {
            $player = PlayerUtils::getPlayerByPrefix((string) $target);
            $session = $player !== null
                ? Loader::getPlayerManager()->getSession($player)
                : Loader::getPlayerManager()->getSessionByPrefix((string) $target);
            $faction = $session?->getFaction();
        }

        if ($faction === null) {
            $sender->sendMessage(GlaciaUtils::format("faction.error.not_exists", ["name" => $target]));
            return;
        }

        $this->displayFactionInfo($sender, $faction);
    }

    private function displayFactionInfo(CommandSender $sender, Faction $faction): void
    {
        $playerManager = PlayerManager::getInstance();
        $allSessions = $playerManager->getSessions();

        $memberEntries = [
            "leader" => [],
            "officer" => [],
            "member" => [],
            "recruit" => [],
        ];

        foreach ($allSessions as $session) {
            /** @var GlaciaPlayer $session */
            if ($session->getFactionId() !== $faction->getFactionId()) {
                continue;
            }

            $role = $session->getFactionRole() ?? "recruit";
            $symbol = GlaciaUtils::getFactionRoleSymbol($role);
            $color = $session->isConnected() ? "&a" : "&c";
            $memberEntries[$role][] = "{$color}{$symbol}{$session->getUsername()}";
        }

        $orderedMembers = [];
        foreach (["leader", "officer", "member", "recruit"] as $role) {
            $orderedMembers = array_merge($orderedMembers, $memberEntries[$role] ?? []);
        }

        $ownId = $faction->getFactionId();
        $relationManager = RelationManager::getInstance();
        $allyIds = array_keys($relationManager->getRelationsFor($ownId, "ally", true));
        $enemyIds = array_keys($relationManager->getRelationsFor($ownId, "enemy", true));

        $factionManager = Loader::getFactionManager();
        $allyNames = array_values(array_filter(array_map(
            static fn(string $id): ?string => $factionManager->getFaction($id)?->getName(),
            $allyIds
        )));
        $enemyNames = array_values(array_filter(array_map(
            static fn(string $id): ?string => $factionManager->getFaction($id)?->getName(),
            $enemyIds
        )));

        $relation = "neutral";
        if ($sender instanceof Player) {
            $viewerFaction = Loader::getPlayerManager()->getSession($sender)?->getFaction();
            if ($viewerFaction !== null) {
                $relation = $viewerFaction->getFactionId() === $faction->getFactionId()
                    ? "own"
                    : RelationManager::getInstance()->getRelation($viewerFaction->getFactionId(), $faction->getFactionId());
            }
        }

        $relationColor = GlaciaUtils::getRelationColor($relation);
        $lines = [];
        $lines[] = "&r&b------ {$relationColor}{$faction->getName()} &3------";
        $lines[] = "&r&b> &fDescription: &7{$faction->getDescription()}";
        $lines[] = "&r&b> &fLeader: &7{$faction->getLeaderName()}";
        $lines[] = C::colorize("&r&b> &fMembers: &7" . count($orderedMembers));

        if ($orderedMembers !== []) {
            $lines[] = "   " . implode("&7, ", $orderedMembers);
        }

        $lines[] = C::colorize("&r&b> &fPower/Max/Claims: &7{$faction->getFactionPower()}/{$faction->getMaxPower()}/{$faction->getClaimCount()}");
        $lines[] = C::colorize("&r&b> &fFaction Value: &7$" . number_format($faction->getFactionValue()));
        $lines[] = C::colorize("&r&b> &fFaction Bank: &7$" . number_format(Loader::getFactionUpgradeManager()->getBankBalance($faction->getFactionId()), 2));
        $lines[] = "&r&b> &fAllies: &7" . ($allyNames === [] ? "None" : implode("&7, ", $allyNames));
        $lines[] = "&r&b> &fEnemies: &7" . ($enemyNames === [] ? "None" : implode("&7, ", $enemyNames));

        foreach ($lines as $line) {
            $sender->sendMessage(C::colorize($line));
        }
    }

    public function getPermission(): string {
        return "glacia.default";
    }
}
