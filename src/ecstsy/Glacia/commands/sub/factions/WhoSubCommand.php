<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands\sub\factions;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseSubCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\factions\Faction;
use ecstsy\Glacia\server\factions\FactionManager;
use ecstsy\Glacia\server\factions\relations\RelationManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\Glacia\utils\QueryStmts;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use Ramsey\Uuid\UuidInterface;

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

        $memberEntries = [
            "leader" => [],
            "officer" => [],
            "member" => [],
            "recruit" => [],
        ];

        foreach ($faction->getMembers() as $member) {
            $uuid = $member->getUuid();
            $role = $member->getRole();
            if (!isset($memberEntries[$role])) {
                $role = "member";
            }

            $session = $playerManager->getSessionByUuid($uuid);
            $name = $session !== null
                ? $session->getUsername()
                : $this->resolveUsernameFromDatabase($uuid);

            $symbol = GlaciaUtils::getFactionRoleSymbol($role);
            $online = $session !== null && $session->isConnected();
            $state = $online ? "&a" : "&7";
            $memberEntries[$role][] = "{$state}{$symbol}{$name}&7" . ($online ? " &a●" : " &8○");
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

        $nameColor = match (strtolower($relation)) {
            "own" => "&a",
            "enemy" => "&c",
            "ally" => "&d",
            default => "&f",
        };

        $description = trim($faction->getDescription()) !== "" ? $faction->getDescription() : "No description set.";
        $factionName = $faction->getName();
        $centeredTitle = self::padCenterAmpersand("{$nameColor}&l{$factionName}");

        $lines = [];
        $lines[] = $centeredTitle;
        $lines[] = "&r&bLeader: &f{$faction->getLeaderName()}";
        $lines[] = "&r&bDescription: &7{$description}";
        $lines[] = "&r&bMembers: &f" . count($faction->getMembers());

        foreach ($orderedMembers !== [] ? array_chunk($orderedMembers, 3) : [] as $chunk) {
            $lines[] = "&r&8· &r" . implode("&r&7 │ &r", $chunk);
        }

        $lines[] = C::colorize("&r&bPower / Max / Claims: &f{$faction->getFactionPower()} &8| &f{$faction->getMaxPower()} &8| &f{$faction->getClaimCount()}");
        $lines[] = C::colorize("&r&bFaction Value: &f$" . number_format($faction->getFactionValue()));
        $lines[] = C::colorize("&r&bFaction Bank: &f$" . number_format(Loader::getFactionUpgradeManager()->getBankBalance($faction->getFactionId()), 2));
        $lines[] = "&r&bAllies: &7" . ($allyNames === [] ? "None" : implode("&7, ", $allyNames));
        $lines[] = "&r&bEnemies: &7" . ($enemyNames === [] ? "None" : implode("&7, ", $enemyNames));

        foreach ($lines as $line) {
            $sender->sendMessage(C::colorize($line));
        }
    }

    private function resolveUsernameFromDatabase(UuidInterface $uuid): string {
        $name = null;
        Loader::getDatabase()->executeSelect(QueryStmts::PLAYERS_USERNAME_BY_UUID, [
            "uuid" => $uuid->toString(),
        ], function (array $rows) use (&$name): void {
            $name = isset($rows[0]["username"]) ? (string) $rows[0]["username"] : null;
        });
        Loader::getDatabase()->waitAll();

        return ($name !== null && $name !== "") ? $name : "Unknown";
    }

    /** Centers &amp;-formatted text using plain-text width (approx. chat strip). */
    private static function padCenterAmpersand(string $ampersandFormatted, int $lineWidth = 52): string {
        $stripped = preg_replace('/&[0-9a-fk-or]/i', '', $ampersandFormatted) ?? "";
        $len = strlen($stripped);
        $pad = max(0, (int) floor(($lineWidth - $len) / 2));

        return "&r" . str_repeat(" ", $pad) . $ampersandFormatted;
    }

    public function getPermission(): string {
        return "glacia.default";
    }
}
