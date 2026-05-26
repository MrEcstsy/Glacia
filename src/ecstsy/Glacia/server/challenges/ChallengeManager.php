<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\challenges;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\factions\Faction;
use ecstsy\Glacia\server\factions\member\FactionMember;
use ecstsy\Glacia\utils\RewardResolver;
use ecstsy\Glacia\utils\StorageUtils;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianUtilities\utils\ItemUtils;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\player\Player;
use pocketmine\Server;
use Ramsey\Uuid\Uuid;

final class ChallengeManager {

    private bool $enabled = true;
    private int $resetInterval = 86400;
    private int $activeCount = 5;
    private int $factionParticipantLimit = 3;
    /** @var array<string, array<string, mixed>> */
    private array $pool = [];
    /** @var array<string, mixed> */
    private array $state = [];
    private string $storageFile;

    public function __construct()
    {
        $this->storageFile = Loader::getInstance()->getDataFolder() . "challenges_state.json";
    }

    public function loadFromConfig(): void
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "challenges.yml");
        if ($config === null) {
            return;
        }

        $this->enabled = (bool) $config->get("enabled", true);
        $this->resetInterval = max(3600, (int) $config->get("reset-seconds", 86400));
        $this->activeCount = max(1, (int) $config->get("active-count", 5));
        $this->factionParticipantLimit = max(1, (int) $config->get("faction-participant-limit", 3));

        $pool = (array) ($config->get("challenge-pool", []) ?? []);
        $this->pool = [];
        foreach ($pool as $challenge) {
            if (!is_array($challenge)) {
                continue;
            }

            $id = strtolower(trim((string) ($challenge["id"] ?? "")));
            if ($id === "") {
                continue;
            }

            $challenge["id"] = $id;
            $challenge["display"] = (string) ($challenge["display"] ?? ucfirst(str_replace("_", " ", $id)));
            $challenge["metric"] = (string) ($challenge["metric"] ?? $id);
            $this->pool[$id] = $challenge;
        }

        $this->loadState();
        $this->ensureCurrentWindow(true);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function tick(): void
    {
        if (!$this->enabled) {
            return;
        }
        $this->ensureCurrentWindow(false);
    }

    public function incrementProgress(Player $player, string $metric, int $amount = 1): void
    {
        if (!$this->enabled || $amount <= 0) {
            return;
        }

        $session = Loader::getPlayerManager()->getSession($player);
        $factionId = $session?->getFactionId();
        if ($factionId === null || $factionId === "") {
            return;
        }

        $this->ensureCurrentWindow(false);
        $metric = strtolower(trim($metric));
        foreach ($this->getActiveChallengeIds() as $challengeId) {
            $challenge = $this->pool[$challengeId] ?? null;
            if (!is_array($challenge) || strtolower((string) ($challenge["metric"] ?? "")) !== $metric) {
                continue;
            }

            $uuid = $player->getUniqueId()->toString();
            $participants = (array) ($this->state["participants"][$challengeId][$factionId] ?? []);
            $participants = array_values(array_unique(array_filter(array_map("strval", $participants), static fn(string $id): bool => $id !== "")));
            if (!in_array($uuid, $participants, true)) {
                $cap = $this->getParticipantCapForFaction($factionId);
                if (count($participants) >= $cap) {
                    continue;
                }
                $participants[] = $uuid;
                $this->state["participants"][$challengeId][$factionId] = $participants;
            }

            $this->state["progress"][$challengeId][$factionId][$uuid] = ((int) ($this->state["progress"][$challengeId][$factionId][$uuid] ?? 0)) + $amount;
        }
        $this->saveState();
    }

    /**
     * @return array<int, array{id:string,display:string,metric:string,timeRemaining:int,myProgress:int,myRank:int,myParticipants:int,myParticipantCap:int,firstPlaceProgress:int,myGapToFirst:int,leaders:array<int,array{name:string,value:int,members:array<int,string>,contributors:array<int,string>}>}>
     */
    public function getChallengeView(Player $player): array
    {
        $this->ensureCurrentWindow(false);
        $rows = [];
        $session = Loader::getPlayerManager()->getSession($player);
        $factionId = $session?->getFactionId();
        foreach ($this->getActiveChallengeIds() as $challengeId) {
            $challenge = $this->pool[$challengeId] ?? [];
            $progress = (array) ($this->state["progress"][$challengeId] ?? []);
            $totals = [];
            foreach ($progress as $leaderFactionId => $byPlayer) {
                if (!is_array($byPlayer)) {
                    continue;
                }
                $totals[(string) $leaderFactionId] = array_sum(array_map("intval", $byPlayer));
            }
            arsort($totals);
            $leaders = [];
            $i = 0;
            foreach ($totals as $leaderFactionId => $value) {
                $name = $this->resolveFactionName((string) $leaderFactionId);
                $leaders[] = [
                    "name" => $name,
                    "value" => (int) $value,
                    "members" => $this->resolveFactionMemberNames((string) $leaderFactionId),
                    "contributors" => $this->resolvePlayerNames($this->getParticipantUuids($challengeId, (string) $leaderFactionId)),
                ];
                $i++;
                if ($i >= 3) {
                    break;
                }
            }

            $rank = 0;
            $idx = 1;
            if ($factionId !== null && $factionId !== "") {
                foreach ($totals as $progressFactionId => $value) {
                    if ($progressFactionId === $factionId) {
                        $rank = $idx;
                        break;
                    }
                    $idx++;
                }
            }

            $myProgress = 0;
            $myParticipants = 0;
            $myParticipantCap = 0;
            $firstPlaceProgress = (int) (reset($totals) ?: 0);
            if ($factionId !== null && $factionId !== "") {
                $myProgress = (int) ($totals[$factionId] ?? 0);
                $myParticipants = count((array) ($this->state["participants"][$challengeId][$factionId] ?? []));
                $myParticipantCap = $this->getParticipantCapForFaction($factionId);
            }

            $rows[] = [
                "id" => $challengeId,
                "display" => (string) ($challenge["display"] ?? $challengeId),
                "metric" => (string) ($challenge["metric"] ?? $challengeId),
                "timeRemaining" => max(0, ((int) ($this->state["window_end"] ?? time())) - time()),
                "myProgress" => $myProgress,
                "myRank" => $rank,
                "myParticipants" => $myParticipants,
                "myParticipantCap" => $myParticipantCap,
                "firstPlaceProgress" => $firstPlaceProgress,
                "myGapToFirst" => max(0, $firstPlaceProgress - $myProgress),
                "leaders" => $leaders
            ];
        }
        return $rows;
    }

    public function claimPendingRewards(Player $player): int
    {
        $uuid = $player->getUniqueId()->toString();
        $pending = (array) ($this->state["pending_rewards"][$uuid] ?? []);
        if ($pending === []) {
            return 0;
        }

        $claimed = 0;
        foreach ($pending as $encoded) {
            if (!is_string($encoded) || $encoded === "") {
                continue;
            }
            $item = ItemUtils::decodeItem($encoded);
            foreach ($player->getInventory()->addItem($item) as $leftover) {
                $player->getWorld()->dropItem($player->getPosition(), $leftover);
            }
            $claimed++;
        }

        unset($this->state["pending_rewards"][$uuid]);
        $this->saveState();
        return $claimed;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getLastWinnersSnapshot(): array
    {
        return array_values((array) ($this->state["last_winners"] ?? []));
    }

    private function ensureCurrentWindow(bool $bootstrap): void
    {
        $now = time();
        $windowEnd = (int) ($this->state["window_end"] ?? 0);
        if ($windowEnd <= 0) {
            $this->startNewWindow($now, $bootstrap);
            return;
        }

        if ($windowEnd > $now) {
            return;
        }

        $this->finalizeWindow();
        $this->startNewWindow($now, false);
    }

    private function startNewWindow(int $now, bool $bootstrap): void
    {
        $ids = array_keys($this->pool);
        shuffle($ids);
        $ids = array_slice($ids, 0, min($this->activeCount, count($ids)));
        $this->state["active"] = $ids;
        $this->state["progress"] = [];
        $this->state["participants"] = [];
        $this->state["window_start"] = $now;
        $this->state["window_end"] = $now + $this->resetInterval;
        if ($bootstrap && !isset($this->state["pending_rewards"])) {
            $this->state["pending_rewards"] = [];
        }
        $this->saveState();
    }

    private function finalizeWindow(): void
    {
        $lastWinners = [];
        foreach ($this->getActiveChallengeIds() as $challengeId) {
            $challenge = $this->pool[$challengeId] ?? null;
            if (!is_array($challenge)) {
                continue;
            }

            $progress = (array) ($this->state["progress"][$challengeId] ?? []);
            $totals = [];
            foreach ($progress as $factionId => $byPlayer) {
                if (!is_array($byPlayer)) {
                    continue;
                }
                $totals[(string) $factionId] = array_sum(array_map("intval", $byPlayer));
            }
            if ($totals === []) {
                continue;
            }

            arsort($totals);
            $ranked = array_slice($totals, 0, 3, true);
            $rank = 1;
            foreach ($ranked as $factionId => $value) {
                $placement = $this->formatPlacement($rank);
                $participants = $this->getParticipantUuids($challengeId, (string) $factionId);
                $lastWinners[] = [
                    "challenge" => (string) ($challenge["display"] ?? $challengeId),
                    "metric" => (string) ($challenge["metric"] ?? $challengeId),
                    "faction" => $this->resolveFactionName((string) $factionId),
                    "faction_id" => (string) $factionId,
                    "participants" => count($participants),
                    "value" => (int) $value,
                    "placement" => $placement
                ];
                foreach ($participants as $uuid) {
                    $this->payoutWinner($uuid, $challenge, $placement);
                }
                $rank++;
            }
        }

        $this->state["last_winners"] = $lastWinners;
    }

    private function getParticipantCapForFaction(string $factionId): int
    {
        $cap = $this->factionParticipantLimit;
        $faction = Loader::getFactionManager()->getFaction($factionId);
        if ($faction !== null) {
            $upgradeManager = Loader::getFactionUpgradeManager();
            if (method_exists($upgradeManager, "getChallengeParticipantBonus")) {
                /** @var int $bonus */
                $bonus = $upgradeManager->getChallengeParticipantBonus($faction);
                $cap += $bonus;
            }
        }
        return max(1, $cap);
    }

    private function resolveFactionName(string $factionId): string
    {
        $faction = Loader::getFactionManager()->getFaction($factionId);
        if ($faction !== null) {
            return $faction->getName();
        }
        return "Unknown";
    }

    /**
     * @return string[]
     */
    private function resolveFactionMemberNames(string $factionId): array
    {
        $faction = Loader::getFactionManager()->getFaction($factionId);
        if (!$faction instanceof Faction) {
            return [];
        }

        $names = [];
        foreach ($faction->getMembers() as $member) {
            if (!$member instanceof FactionMember) {
                continue;
            }

            $names[] = $this->resolvePlayerName($member->getUuid()->toString());
        }

        natcasesort($names);
        return array_values(array_unique(array_filter($names, static fn(string $name): bool => trim($name) !== "")));
    }

    private function formatPlacement(int $rank): string
    {
        return $rank . ($rank === 1 ? "st" : ($rank === 2 ? "nd" : "rd"));
    }

    /**
     * @return string[]
     */
    private function getParticipantUuids(string $challengeId, string $factionId): array
    {
        $participants = (array) ($this->state["participants"][$challengeId][$factionId] ?? []);
        $participants = array_values(array_unique(array_filter(array_map("strval", $participants), static fn(string $id): bool => $id !== "")));
        $cap = $this->getParticipantCapForFaction($factionId);
        if (count($participants) > $cap) {
            $participants = array_slice($participants, 0, $cap);
        }
        return $participants;
    }

    /**
     * @param array<string, mixed> $challenge
     */
    private function payoutWinner(string $uuid, array $challenge, string $placement): void
    {
        $rewards = $this->resolvePlacementRewards($challenge, $placement);
        if ($rewards === []) {
            return;
        }

        $player = $this->getOnlinePlayerByUuid($uuid);
        $console = new ConsoleCommandSender(Server::getInstance(), Server::getInstance()->getLanguage());
        foreach ($rewards as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $commands = $entry["commands"] ?? null;
            if (is_array($commands)) {
                foreach ($commands as $raw) {
                    if (!is_string($raw) || trim($raw) === "") {
                        continue;
                    }
                    $command = str_replace(["{player}", "{uuid}"], [$this->resolvePlayerName($uuid), $uuid], $raw);
                    Server::getInstance()->dispatchCommand($console, $command);
                }
            }

            $item = RewardResolver::resolveItem($entry, $player instanceof Player ? $player : null);
            if ($item === null) {
                continue;
            }

            if ($player instanceof Player && $player->isConnected()) {
                foreach ($player->getInventory()->addItem($item) as $leftover) {
                    $player->getWorld()->dropItem($player->getPosition(), $leftover);
                }
                continue;
            }

            $this->state["pending_rewards"][$uuid][] = ItemUtils::encodeItem($item);
        }
    }

    /**
     * @param array<string, mixed> $challenge
     * @return array<int, array<string, mixed>>
     */
    private function resolvePlacementRewards(array $challenge, string $placement): array
    {
        $raw = ($challenge["rewards"] ?? [])[$placement] ?? [];
        if (!is_array($raw) || $raw === []) {
            return [];
        }

        if ($this->isListArray($raw)) {
            return array_values(array_filter($raw, static fn(mixed $entry): bool => is_array($entry)));
        }

        $pool = $raw["pool"] ?? $raw["items"] ?? $raw["rewards"] ?? [];
        if (!is_array($pool) || $pool === []) {
            return [];
        }

        $rewardPool = array_values(array_filter($pool, static fn(mixed $entry): bool => is_array($entry)));
        if ($rewardPool === []) {
            return [];
        }

        $rollCountInput = $raw["reward-count"] ?? $raw["rolls"] ?? $raw["count"] ?? count($rewardPool);
        $rollCount = max(1, GeneralUtils::parseRandomNumber($rollCountInput));
        $unique = !array_key_exists("unique", $raw) || $this->coerceBool($raw["unique"]);

        $resolved = [];
        if ($unique) {
            $workingPool = $rewardPool;
            for ($i = 0; $i < $rollCount && $workingPool !== []; $i++) {
                $entry = RewardResolver::rollEntryRemove($workingPool);
                if ($entry !== null) {
                    $resolved[] = $entry;
                }
            }
            return $resolved;
        }

        for ($i = 0; $i < $rollCount; $i++) {
            $entry = RewardResolver::rollEntry($rewardPool);
            if ($entry !== null) {
                $resolved[] = $entry;
            }
        }

        return $resolved;
    }

    /**
     * @return string[]
     */
    private function getActiveChallengeIds(): array
    {
        $ids = (array) ($this->state["active"] ?? []);
        return array_values(array_filter(array_map("strval", $ids), fn(string $id): bool => isset($this->pool[$id])));
    }

    private function resolvePlayerName(string $uuid): string
    {
        $player = $this->getOnlinePlayerByUuid($uuid);
        if ($player instanceof Player) {
            return $player->getName();
        }
        try {
            $session = Loader::getPlayerManager()->getSessionByUuid(Uuid::fromString($uuid));
            if ($session !== null) {
                return $session->getUsername();
            }
        } catch (\Throwable) {
        }
        return (string) ((array) ($this->state["known_names"] ?? []))[$uuid] ?? substr($uuid, 0, 8);
    }

    /**
     * @param string[] $uuids
     * @return string[]
     */
    private function resolvePlayerNames(array $uuids): array
    {
        $names = [];
        foreach ($uuids as $uuid) {
            $names[] = $this->resolvePlayerName((string) $uuid);
        }

        natcasesort($names);
        return array_values(array_unique(array_filter($names, static fn(string $name): bool => trim($name) !== "")));
    }

    /**
     * @param array<mixed> $value
     */
    private function isListArray(array $value): bool
    {
        return array_keys($value) === range(0, count($value) - 1);
    }

    private function coerceBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ["1", "true", "yes", "on"], true);
    }

    private function loadState(): void
    {
        $this->state = [
            "active" => [],
            "progress" => [],
            "participants" => [],
            "window_start" => 0,
            "window_end" => 0,
            "last_winners" => [],
            "pending_rewards" => [],
            "known_names" => []
        ];

        if (!file_exists($this->storageFile)) {
            return;
        }

        $decoded = StorageUtils::readJsonFile($this->storageFile, "challenge state");
        if (is_array($decoded)) {
            $this->state = array_replace($this->state, $decoded);
        }
    }

    private function saveState(): void
    {
        foreach (Server::getInstance()->getOnlinePlayers() as $online) {
            $this->state["known_names"][$online->getUniqueId()->toString()] = $online->getName();
        }
        StorageUtils::writeJsonFile($this->storageFile, $this->state, "challenge state");
    }

    private function getOnlinePlayerByUuid(string $uuid): ?Player
    {
        try {
            return Server::getInstance()->getPlayerByUUID(Uuid::fromString($uuid));
        } catch (\Throwable) {
            return null;
        }
    }
}
