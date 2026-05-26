<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\factions\contribution;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\StorageUtils;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class FactionContributionManager
{
    /** @var array<string, mixed> */
    private static array $config = [];
    /** @var array<string, array{points:int,milestone:int,tasks:array<string,int>}> */
    private static array $factions = [];
    /** @var array<string, int> */
    private static array $players = [];
    private static bool $loaded = false;
    private static string $dataPath = "";

    public static function init(Loader $plugin): void
    {
        self::$dataPath = $plugin->getDataFolder() . "faction_contribution.json";
        self::loadFromConfig();
        self::loadState();
    }

    public static function loadFromConfig(): void
    {
        $misc = GeneralUtils::getConfiguration(Loader::getInstance(), "misc.yml");
        $block = (array) ($misc?->get("faction-contribution", []) ?? []);

        self::$config = [
            "enabled" => (bool) ($block["enabled"] ?? true),
            "feedback-actionbar" => (bool) ($block["feedback-actionbar"] ?? true),
            "sources" => (array) ($block["sources"] ?? []),
            "milestones" => is_array($block["milestones"] ?? null) ? array_values($block["milestones"]) : [],
        ];

        // Keep existing runtime state alive across reloads; milestones are re-evaluated lazily.
    }

    public static function save(): void
    {
        if (self::$dataPath === "") {
            return;
        }

        $payload = [
            "factions" => self::$factions,
            "players" => self::$players,
        ];

        StorageUtils::writeJsonFile(self::$dataPath, $payload, "faction contribution state");
    }

    public static function isEnabled(): bool
    {
        return (bool) (self::$config["enabled"] ?? false);
    }

    public static function addMobKillContribution(Player $player, string $tier = "default"): void
    {
        if (!self::isEnabled()) {
            return;
        }
        $sources = (array) (self::$config["sources"]["mob-kill"] ?? []);
        $points = (int) ($sources[$tier] ?? $sources["default"] ?? 0);
        self::award($player, $points, "Mob Contribution");
    }

    public static function addFishingContribution(Player $player, string $rarity = "common"): void
    {
        if (!self::isEnabled()) {
            return;
        }
        $sources = (array) (self::$config["sources"]["fishing"] ?? []);
        $points = (int) ($sources[$rarity] ?? $sources["common"] ?? 0);
        self::award($player, $points, "Fishing Contribution");
    }

    public static function addSellContribution(Player $player, float $soldValue): void
    {
        if (!self::isEnabled() || $soldValue <= 0) {
            return;
        }
        $sources = (array) (self::$config["sources"]["sell"] ?? []);
        $ppd = max(0.0, (float) ($sources["points-per-dollar"] ?? 0.02));
        $cap = max(0, (int) ($sources["max-per-transaction"] ?? 250));
        $points = (int) floor($soldValue * $ppd);
        if ($cap > 0) {
            $points = min($cap, $points);
        }
        self::award($player, $points, "Sell Contribution");
    }

    public static function addLootbagContribution(Player $player): void
    {
        if (!self::isEnabled()) {
            return;
        }
        $points = (int) (self::$config["sources"]["lootbag-open"] ?? 0);
        self::award($player, $points, "Loot Contribution");
    }

    public static function recordTaskProgress(Player $player, string $taskKey, int $amount = 1): void
    {
        if (!self::isEnabled() || $amount <= 0) {
            return;
        }
        $factionId = self::getFactionId($player);
        if ($factionId === null) {
            return;
        }
        $state = self::getFactionState($factionId);
        $state["tasks"][$taskKey] = max(0, (int) ($state["tasks"][$taskKey] ?? 0) + $amount);
        self::$factions[$factionId] = $state;
        self::tryUnlockMilestones($factionId);
    }

    public static function getDepositPointsForItemKey(string $key): int
    {
        $map = (array) (self::$config["sources"]["special-deposits"]["items"] ?? []);
        return max(0, (int) ($map[strtolower(trim($key))] ?? 0));
    }

    public static function addSpecialDeposit(Player $player, int $points, string $label): void
    {
        self::award($player, $points, $label);
    }

    public static function getPersonalPoints(Player $player): int
    {
        return max(0, (int) (self::$players[$player->getUniqueId()->toString()] ?? 0));
    }

    public static function getFactionPoints(string $factionId): int
    {
        return self::getFactionState($factionId)["points"];
    }

    public static function transferFactionPoints(string $fromFactionId, string $toFactionId, float $percent, string $source): int
    {
        if (!self::isEnabled() || $fromFactionId === $toFactionId) {
            return 0;
        }

        $percent = max(0.0, min(1.0, $percent));
        if ($percent <= 0.0) {
            return 0;
        }

        $from = self::getFactionState($fromFactionId);
        $amount = (int) floor($from["points"] * $percent);
        if ($amount <= 0) {
            return 0;
        }

        $from["points"] = max(0, $from["points"] - $amount);
        $from["milestone"] = self::calculateMilestoneForState($from);
        self::$factions[$fromFactionId] = $from;

        $to = self::getFactionState($toFactionId);
        $to["points"] += $amount;
        self::$factions[$toFactionId] = $to;

        self::tryUnlockMilestones($toFactionId);

        Loader::getFactionManager()->getFactionById($fromFactionId)?->refreshScoreboards();
        Loader::getFactionManager()->getFactionById($toFactionId)?->refreshScoreboards();
        self::save();
        return $amount;
    }

    public static function getFactionMilestone(string $factionId): int
    {
        return self::getFactionState($factionId)["milestone"];
    }

    /**
     * @return array{target:int,progress:int,percent:float}
     */
    public static function getProgressBarData(string $factionId): array
    {
        $state = self::getFactionState($factionId);
        $milestones = self::getMilestones();
        $idx = $state["milestone"];
        $target = isset($milestones[$idx]) ? max(1, (int) ($milestones[$idx]["points"] ?? 1)) : max(1, $state["points"]);
        $progress = min($target, $state["points"]);
        $percent = $target > 0 ? min(1.0, $progress / $target) : 1.0;
        return ["target" => $target, "progress" => $progress, "percent" => $percent];
    }

    public static function getBuffMultiplierForPlayer(Player $player, string $buffKey, float $default = 1.0): float
    {
        $fid = self::getFactionId($player);
        if ($fid === null) {
            return $default;
        }
        return self::getBuffMultiplierForFaction($fid, $buffKey, $default);
    }

    public static function getBuffMultiplierForFaction(string $factionId, string $buffKey, float $default = 1.0): float
    {
        if (!self::isEnabled()) {
            return $default;
        }
        $state = self::getFactionState($factionId);
        $milestones = self::getMilestones();
        $mult = $default;
        $unlocked = min($state["milestone"], count($milestones));
        for ($i = 0; $i < $unlocked; $i++) {
            $buffs = (array) ($milestones[$i]["buffs"] ?? []);
            $value = isset($buffs[$buffKey]) ? (float) $buffs[$buffKey] : 1.0;
            if ($value > 0) {
                $mult *= $value;
            }
        }
        return max(0.0, $mult);
    }

    /**
     * @return array<string, int>
     */
    public static function getRequiredTasksForNextMilestone(string $factionId): array
    {
        $state = self::getFactionState($factionId);
        $milestones = self::getMilestones();
        $next = $state["milestone"];
        if (!isset($milestones[$next])) {
            return [];
        }
        $tasks = (array) ($milestones[$next]["tasks"] ?? []);
        $out = [];
        foreach ($tasks as $key => $value) {
            $out[(string) $key] = max(0, (int) $value);
        }
        return $out;
    }

    /**
     * @return array<string, int>
     */
    public static function getTaskProgress(string $factionId): array
    {
        return self::getFactionState($factionId)["tasks"];
    }

    private static function award(Player $player, int $points, string $source): void
    {
        if (!self::isEnabled() || $points <= 0) {
            return;
        }

        $playerUuid = $player->getUniqueId()->toString();
        self::$players[$playerUuid] = max(0, (int) (self::$players[$playerUuid] ?? 0) + $points);

        $factionId = self::getFactionId($player);
        if ($factionId === null) {
            return;
        }

        $state = self::getFactionState($factionId);
        $state["points"] += $points;
        self::$factions[$factionId] = $state;
        self::tryUnlockMilestones($factionId);

        if ((bool) (self::$config["feedback-actionbar"] ?? true)) {
            $player->sendActionBarMessage(C::colorize("&r&a+{$points} Contribution &7({$source})"));
        }
    }

    private static function tryUnlockMilestones(string $factionId): void
    {
        $state = self::getFactionState($factionId);
        $milestones = self::getMilestones();
        $changed = false;

        while (isset($milestones[$state["milestone"]])) {
            $current = $milestones[$state["milestone"]];
            $requiredPoints = max(0, (int) ($current["points"] ?? 0));
            if ($state["points"] < $requiredPoints) {
                break;
            }
            if (!self::areTasksMet($state, (array) ($current["tasks"] ?? []))) {
                break;
            }
            $state["milestone"]++;
            $changed = true;
        }

        if ($changed) {
            self::$factions[$factionId] = $state;
            self::notifyFactionUnlocked($factionId, $state["milestone"]);
            $faction = Loader::getFactionManager()->getFactionById($factionId);
            $faction?->refreshScoreboards();
        }
    }

    /**
     * @param array<string, int> $requirements
     */
    private static function areTasksMet(array $state, array $requirements): bool
    {
        foreach ($requirements as $task => $needed) {
            $neededInt = max(0, (int) $needed);
            if ($neededInt <= 0) {
                continue;
            }
            $done = max(0, (int) (($state["tasks"] ?? [])[(string) $task] ?? 0));
            if ($done < $neededInt) {
                return false;
            }
        }
        return true;
    }

    private static function calculateMilestoneForState(array $state): int
    {
        $milestones = self::getMilestones();
        $level = 0;
        while (isset($milestones[$level])) {
            $current = $milestones[$level];
            $requiredPoints = max(0, (int) ($current["points"] ?? 0));
            if ((int) ($state["points"] ?? 0) < $requiredPoints) {
                break;
            }
            if (!self::areTasksMet($state, (array) ($current["tasks"] ?? []))) {
                break;
            }
            ++$level;
        }

        return $level;
    }

    private static function notifyFactionUnlocked(string $factionId, int $milestoneNumber): void
    {
        $faction = Loader::getFactionManager()->getFactionById($factionId);
        if ($faction === null) {
            return;
        }
        foreach ($faction->getOnlineMembers() as $member) {
            $member->sendMessage(C::colorize("&r&6Faction Milestone Unlocked! &eTier &f{$milestoneNumber}&e is now active."));
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function getMilestones(): array
    {
        return is_array(self::$config["milestones"] ?? null) ? self::$config["milestones"] : [];
    }

    /**
     * @return array{points:int,milestone:int,tasks:array<string,int>}
     */
    private static function getFactionState(string $factionId): array
    {
        if (!isset(self::$factions[$factionId])) {
            self::$factions[$factionId] = [
                "points" => 0,
                "milestone" => 0,
                "tasks" => [],
            ];
        }
        return self::$factions[$factionId];
    }

    private static function getFactionId(Player $player): ?string
    {
        $session = Loader::getPlayerManager()->getSession($player);
        $fid = $session?->getFactionId();
        if (!is_string($fid) || trim($fid) === "") {
            return null;
        }
        return $fid;
    }

    private static function loadState(): void
    {
        if (self::$loaded || self::$dataPath === "") {
            return;
        }
        self::$loaded = true;
        if (!is_file(self::$dataPath)) {
            return;
        }
        $decoded = StorageUtils::readJsonFile(self::$dataPath, "faction contribution state");
        if (!is_array($decoded)) {
            return;
        }
        $factions = $decoded["factions"] ?? [];
        if (is_array($factions)) {
            foreach ($factions as $fid => $state) {
                if (!is_string($fid) || !is_array($state)) {
                    continue;
                }
                self::$factions[$fid] = [
                    "points" => max(0, (int) ($state["points"] ?? 0)),
                    "milestone" => max(0, (int) ($state["milestone"] ?? 0)),
                    "tasks" => is_array($state["tasks"] ?? null) ? array_map(static fn($v): int => max(0, (int) $v), $state["tasks"]) : [],
                ];
            }
        }
        $players = $decoded["players"] ?? [];
        if (is_array($players)) {
            foreach ($players as $uuid => $points) {
                if (!is_string($uuid)) {
                    continue;
                }
                self::$players[$uuid] = max(0, (int) $points);
            }
        }
    }
}
