<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\quests;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\watchdogs\ChunkWatchdogManager;
use ecstsy\Glacia\utils\QueryStmts;
use ecstsy\Glacia\utils\RewardResolver;
use ecstsy\Glacia\utils\StorageUtils;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\TextFormat as C;

final class QuestBoardManager
{
    private const DEFAULT_DAILY_REFRESH = 86400;
    private const DEFAULT_SHOP_REFRESH = 14400;

    /** @var array<string, mixed> */
    private array $config = [];
    /** @var array<string, array<string, mixed>> */
    private array $tierDefinitions = [];
    /** @var array<string, array<string, mixed>> */
    private array $shopDefinitions = [];
    /** @var array<string, string> */
    private array $sigilTierMap = [];
    /** @var array<int, array<string, int>> */
    private array $reputationTierWeights = [];
    /** @var array<string, array<string, mixed>> */
    private array $profiles = [];
    /** @var string[] */
    private array $shopRotation = [];
    private int $shopRefreshedAt = 0;
    private int $shopRefreshSeconds = self::DEFAULT_SHOP_REFRESH;
    private int $dailyRefreshSeconds = self::DEFAULT_DAILY_REFRESH;
    private int $shopRotationSize = 7;
    private int $dailyQuestCount = 3;
    private int $maxBonusQuests = 3;
    private int $reputationPointsPerLevel = 20;
    private int $maxReputationLevel = 10;
    private bool $enabled = true;
    private bool $loaded = false;

    public function __construct()
    {
        $this->loadFromConfig();
        $this->loadState();
        Loader::getInstance()->getScheduler()->scheduleRepeatingTask(new ClosureTask(function(): void {
            $this->tick();
        }), 20 * 30);
    }

    public function loadFromConfig(): void
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "quests.yml");
        $board = (array) ($config?->get("board", []) ?? []);
        $settings = (array) ($board["settings"] ?? []);
        $tiers = (array) ($board["tiers"] ?? []);
        $sigils = (array) ($board["sigils"] ?? []);
        $shop = (array) ($board["shop"] ?? []);
        $reputation = (array) ($board["reputation"] ?? []);

        $this->enabled = (bool) ($settings["enabled"] ?? true);
        $this->dailyRefreshSeconds = max(3600, GeneralUtils::parseTimeString((string) ($settings["daily-refresh"] ?? "24h")));
        $this->shopRefreshSeconds = max(1800, GeneralUtils::parseTimeString((string) ($settings["shop-refresh"] ?? "4h")));
        $this->shopRotationSize = max(1, min(7, (int) ($settings["shop-rotation-size"] ?? 7)));
        $this->dailyQuestCount = max(1, min(10, (int) ($settings["daily-quest-count"] ?? 3)));
        $this->maxBonusQuests = max(1, min(10, (int) ($settings["max-bonus-quests"] ?? 3)));
        $this->reputationPointsPerLevel = max(1, (int) ($reputation["points-per-level"] ?? 20));
        $this->maxReputationLevel = max(1, min(100, (int) ($reputation["max-level"] ?? 10)));

        $this->tierDefinitions = [];
        foreach ($tiers as $tierId => $tierData) {
            if (!is_array($tierData)) {
                continue;
            }

            $points = max(1, (int) ($tierData["points"] ?? 1));
            $display = (string) ($tierData["display"] ?? strtoupper(str_replace("_", " ", (string) $tierId)));
            $quests = [];
            foreach ((array) ($tierData["quests"] ?? []) as $questId => $questData) {
                if (!is_array($questData)) {
                    continue;
                }

                $normalizedId = strtolower(trim((string) ($questData["id"] ?? $questId)));
                $objectiveType = strtolower(trim((string) ($questData["type"] ?? "")));
                $amount = max(1, (int) ($questData["amount"] ?? 1));
                if ($normalizedId === "" || $objectiveType === "") {
                    continue;
                }

                $quests[$normalizedId] = [
                    "id" => $normalizedId,
                    "name" => (string) ($questData["name"] ?? ucfirst(str_replace("_", " ", $normalizedId))),
                    "description" => (string) ($questData["description"] ?? "Complete this quest objective."),
                    "type" => $objectiveType,
                    "amount" => $amount,
                    "points" => $points,
                    "tier" => strtolower((string) $tierId),
                    "display_tier" => $display,
                    "filters" => is_array($questData["filters"] ?? null) ? $questData["filters"] : [],
                ];
            }

            if ($quests === []) {
                continue;
            }

            $this->tierDefinitions[strtolower((string) $tierId)] = [
                "id" => strtolower((string) $tierId),
                "display" => $display,
                "points" => $points,
                "quests" => $quests,
            ];
        }

        $this->sigilTierMap = [];
        foreach ($sigils as $sigilId => $tierId) {
            $normalizedSigil = strtolower(trim((string) $sigilId));
            $normalizedTier = strtolower(trim((string) $tierId));
            if ($normalizedSigil === "" || !isset($this->tierDefinitions[$normalizedTier])) {
                continue;
            }
            $this->sigilTierMap[$normalizedSigil] = $normalizedTier;
        }

        $this->shopDefinitions = [];
        foreach ((array) ($shop["rewards"] ?? []) as $rewardId => $rewardData) {
            if (!is_array($rewardData)) {
                continue;
            }

            $normalizedId = strtolower(trim((string) ($rewardData["id"] ?? $rewardId)));
            $rewardEntry = $rewardData["reward"] ?? null;
            if ($normalizedId === "" || !is_array($rewardEntry)) {
                continue;
            }

            $this->shopDefinitions[$normalizedId] = [
                "id" => $normalizedId,
                "display" => (string) ($rewardData["display"] ?? ucfirst(str_replace("_", " ", $normalizedId))),
                "cost" => max(1, (int) ($rewardData["cost"] ?? 1)),
                "weight" => max(1, (int) ($rewardData["weight"] ?? 1)),
                "reward" => $rewardEntry,
            ];
        }

        $this->reputationTierWeights = [];
        foreach ((array) ($reputation["tiers"] ?? []) as $level => $weights) {
            if (!is_array($weights)) {
                continue;
            }

            $normalized = [];
            foreach ($weights as $tierId => $weight) {
                $normalizedTier = strtolower(trim((string) $tierId));
                if ($normalizedTier === "" || !isset($this->tierDefinitions[$normalizedTier])) {
                    continue;
                }
                $normalized[$normalizedTier] = max(0, (int) $weight);
            }

            if ($normalized !== []) {
                $this->reputationTierWeights[max(1, (int) $level)] = $normalized;
            }
        }

        if ($this->reputationTierWeights === []) {
            $this->reputationTierWeights = [
                1 => ["tier_1" => 80, "tier_2" => 18, "tier_3" => 2],
                2 => ["tier_1" => 75, "tier_2" => 20, "tier_3" => 5],
                3 => ["tier_1" => 70, "tier_2" => 20, "tier_3" => 10],
                4 => ["tier_1" => 65, "tier_2" => 22, "tier_3" => 13],
                5 => ["tier_1" => 60, "tier_2" => 25, "tier_3" => 15],
                6 => ["tier_1" => 55, "tier_2" => 27, "tier_3" => 18],
                7 => ["tier_1" => 50, "tier_2" => 30, "tier_3" => 20],
                8 => ["tier_1" => 45, "tier_2" => 32, "tier_3" => 23],
                9 => ["tier_1" => 40, "tier_2" => 35, "tier_3" => 25],
                10 => ["tier_1" => 35, "tier_2" => 35, "tier_3" => 30],
            ];
        }

        if ($this->shopRotation === [] && $this->shopDefinitions !== []) {
            $this->rollShopRotation(true);
        }
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

        if ($this->shopRotation === [] && $this->shopDefinitions !== []) {
            $this->rollShopRotation(true);
        }

        if ($this->shopRefreshedAt <= 0 || (time() - $this->shopRefreshedAt) >= $this->shopRefreshSeconds) {
            $this->rollShopRotation(true);
        }
    }

    public function ensureProfile(Player $player): void
    {
        $uuid = $player->getUniqueId()->toString();
        if (!isset($this->profiles[$uuid])) {
            $this->profiles[$uuid] = [
                "uuid" => $uuid,
                "username" => $player->getName(),
                "quest_points" => 0,
                "assigned_at" => 0,
                "daily_quests" => [],
                "bonus_quests" => [],
                "meta" => [
                    "bah_win_streak" => 0,
                    "reputation_points" => 0,
                ],
            ];
            $this->insertProfile($uuid);
        } else {
            $this->profiles[$uuid]["username"] = $player->getName();
        }

        $this->normalizeProfile($uuid);

        if ($this->needsDailyRefresh($uuid)) {
            $this->assignDailyQuests($uuid, $player);
        }

        $this->saveProfile($uuid);
    }

    public function getProfile(Player $player): array
    {
        $this->ensureProfile($player);
        return $this->profiles[$player->getUniqueId()->toString()];
    }

    public function getQuestPoints(Player $player): int
    {
        $this->ensureProfile($player);
        return max(0, (int) ($this->profiles[$player->getUniqueId()->toString()]["quest_points"] ?? 0));
    }

    public function setQuestPoints(Player $player, int $amount): int
    {
        $this->ensureProfile($player);
        $uuid = $player->getUniqueId()->toString();
        $this->profiles[$uuid]["quest_points"] = max(0, $amount);
        $this->saveProfile($uuid);
        return (int) $this->profiles[$uuid]["quest_points"];
    }

    public function addQuestPoints(Player $player, int $amount): int
    {
        if ($amount <= 0) {
            return $this->getQuestPoints($player);
        }

        return $this->setQuestPoints($player, $this->getQuestPoints($player) + $amount);
    }

    public function removeQuestPoints(Player $player, int $amount): int
    {
        if ($amount <= 0) {
            return $this->getQuestPoints($player);
        }

        return $this->setQuestPoints($player, $this->getQuestPoints($player) - $amount);
    }

    public function getReputationPoints(Player $player): int
    {
        $this->ensureProfile($player);
        return max(0, (int) (($this->profiles[$player->getUniqueId()->toString()]["meta"]["reputation_points"] ?? 0)));
    }

    public function getReputationLevel(Player $player): int
    {
        $this->ensureProfile($player);
        return $this->calculateReputationLevel($this->profiles[$player->getUniqueId()->toString()]);
    }

    /**
     * @return array{level:int,current:int,needed:int,remaining:int,percent:float,max:bool}
     */
    public function getReputationProgress(Player $player): array
    {
        $this->ensureProfile($player);
        return $this->calculateReputationProgress($this->profiles[$player->getUniqueId()->toString()]);
    }

    /**
     * @return array<string, int>
     */
    public function getTierOdds(Player $player): array
    {
        $this->ensureProfile($player);
        $weights = $this->getTierWeightsForProfile($this->profiles[$player->getUniqueId()->toString()]);
        $total = array_sum($weights);
        if ($total <= 0) {
            return [];
        }

        $odds = [];
        foreach ($weights as $tierId => $weight) {
            $odds[$tierId] = (int) round(($weight / $total) * 100);
        }

        return $odds;
    }

    public function getDailyRefreshRemaining(): int
    {
        if ($this->dailyRefreshSeconds <= 0) {
            return 0;
        }

        $now = time();
        $next = (int) (floor($now / $this->dailyRefreshSeconds) * $this->dailyRefreshSeconds) + $this->dailyRefreshSeconds;
        return max(0, $next - $now);
    }

    public function getShopRefreshRemaining(): int
    {
        if ($this->shopRefreshedAt <= 0) {
            return 0;
        }

        return max(0, ($this->shopRefreshedAt + $this->shopRefreshSeconds) - time());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getActiveQuestRows(Player $player): array
    {
        $profile = $this->getProfile($player);
        $rows = [];

        foreach ((array) ($profile["daily_quests"] ?? []) as $index => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $definition = $this->resolveQuestDefinition((string) ($entry["tier"] ?? $index), (string) ($entry["quest_id"] ?? ""));
            if ($definition === null) {
                continue;
            }

            $entry["record_index"] = (int) $index;
            $rows[] = $this->buildQuestRow($definition, $entry, false);
        }

        foreach ((array) ($profile["bonus_quests"] ?? []) as $index => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $definition = $this->resolveQuestDefinition((string) ($entry["tier"] ?? ""), (string) ($entry["quest_id"] ?? ""));
            if ($definition === null) {
                continue;
            }

            $entry["record_index"] = (int) $index;
            $rows[] = $this->buildQuestRow($definition, $entry, true);
        }

        usort($rows, static function(array $left, array $right): int {
            if ($left["bonus"] !== $right["bonus"]) {
                return $left["bonus"] ? 1 : -1;
            }

            $tierCompare = strcmp((string) $left["tier_key"], (string) $right["tier_key"]);
            if ($tierCompare !== 0) {
                return $tierCompare;
            }

            return strcmp((string) $left["name"], (string) $right["name"]);
        });

        return $rows;
    }

    public function useSigil(Player $player, string $sigilTier): bool
    {
        $this->ensureProfile($player);
        $uuid = $player->getUniqueId()->toString();
        $sigilTier = strtolower(trim($sigilTier));
        $tierId = $this->sigilTierMap[$sigilTier] ?? "";
        if ($tierId === "" || !isset($this->tierDefinitions[$tierId])) {
            $player->sendMessage(C::colorize("&r&cThat quest sigil is not configured."));
            PlayerUtils::playSound($player, "note.bass");
            return false;
        }

        $bonus = (array) ($this->profiles[$uuid]["bonus_quests"] ?? []);
        if (count($bonus) >= $this->maxBonusQuests) {
            $player->sendMessage(C::colorize("&r&cYou already have the maximum number of bonus quests active."));
            PlayerUtils::playSound($player, "note.bass");
            return false;
        }

        $activeQuestIds = [];
        foreach ((array) ($this->profiles[$uuid]["daily_quests"] ?? []) as $entry) {
            if (is_array($entry)) {
                $activeQuestIds[] = (string) ($entry["quest_id"] ?? "");
            }
        }
        foreach ($bonus as $entry) {
            if (is_array($entry)) {
                $activeQuestIds[] = (string) ($entry["quest_id"] ?? "");
            }
        }

        $definition = $this->pickQuestDefinition($tierId, $activeQuestIds);
        if ($definition === null) {
            $player->sendMessage(C::colorize("&r&cNo bonus quest could be rolled from that sigil right now."));
            PlayerUtils::playSound($player, "note.bass");
            return false;
        }

        $record = $this->createQuestRecord($definition);
        $record["bonus_id"] = strtolower($sigilTier) . "_" . time() . "_" . mt_rand(1000, 9999);
        $record["source"] = $sigilTier;
        $bonus[] = $record;
        $this->profiles[$uuid]["bonus_quests"] = array_values($bonus);
        $this->saveProfile($uuid);

        $player->sendMessage(C::colorize("&r&dQuest Sigil &8| &r&aAdded bonus quest: &f" . $definition["name"] . "&a."));
        PlayerUtils::playSound($player, "random.levelup");
        return true;
    }

    public function rerollQuest(Player $player, bool $bonusQuest, int $recordIndex): bool
    {
        $this->ensureProfile($player);
        $uuid = $player->getUniqueId()->toString();
        $bucket = $bonusQuest ? "bonus_quests" : "daily_quests";
        $records = (array) ($this->profiles[$uuid][$bucket] ?? []);
        $record = $records[$recordIndex] ?? null;
        if (!is_array($record)) {
            $player->sendMessage(C::colorize("&r&cThat quest could not be found."));
            return false;
        }

        if ((bool) ($record["completed"] ?? false) || (bool) ($record["rewarded"] ?? false)) {
            $player->sendMessage(C::colorize("&r&cYou cannot reroll a completed quest."));
            return false;
        }

        $tierId = strtolower((string) ($record["tier"] ?? ""));
        $currentQuestId = strtolower((string) ($record["quest_id"] ?? ""));
        $tier = $this->tierDefinitions[$tierId] ?? null;
        if ($tierId === "" || $currentQuestId === "" || !is_array($tier)) {
            $player->sendMessage(C::colorize("&r&cThat quest is no longer valid."));
            return false;
        }

        $avoidQuestIds = [$currentQuestId];
        foreach ((array) ($this->profiles[$uuid]["daily_quests"] ?? []) as $index => $entry) {
            if (!is_array($entry) || ($bucket === "daily_quests" && $index === $recordIndex)) {
                continue;
            }
            $avoidQuestIds[] = strtolower((string) ($entry["quest_id"] ?? ""));
        }
        foreach ((array) ($this->profiles[$uuid]["bonus_quests"] ?? []) as $index => $entry) {
            if (!is_array($entry) || ($bucket === "bonus_quests" && $index === $recordIndex)) {
                continue;
            }
            $avoidQuestIds[] = strtolower((string) ($entry["quest_id"] ?? ""));
        }

        $pool = array_filter(
            (array) ($tier["quests"] ?? []),
            static fn(array $definition): bool => !in_array(strtolower((string) ($definition["id"] ?? "")), $avoidQuestIds, true)
        );
        if ($pool === []) {
            $player->sendMessage(C::colorize("&r&cThere are no other quests available in that tier right now."));
            return false;
        }

        $pool = array_values($pool);
        $definition = $pool[array_rand($pool)];
        $newRecord = $this->createQuestRecord($definition);
        if ($bonusQuest) {
            $newRecord["bonus_id"] = (string) ($record["bonus_id"] ?? ($tierId . "_" . time() . "_" . mt_rand(1000, 9999)));
            $newRecord["source"] = (string) ($record["source"] ?? "");
        }

        $this->profiles[$uuid][$bucket][$recordIndex] = $newRecord;
        $this->saveProfile($uuid);

        $player->sendMessage(C::colorize("&r&dQuest Reroll &8| &r&aReplaced your old quest with &f" . (string) ($definition["name"] ?? $currentQuestId) . "&a."));
        PlayerUtils::playSound($player, "random.levelup");
        return true;
    }

    public function recordProgress(Player $player, string $eventType, int $amount = 1, array $context = []): void
    {
        if (!$this->enabled || $amount <= 0) {
            return;
        }

        $this->ensureProfile($player);
        $uuid = $player->getUniqueId()->toString();
        $eventType = strtolower(trim($eventType));
        $profileChanged = false;

        foreach ((array) ($this->profiles[$uuid]["daily_quests"] ?? []) as $index => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $definition = $this->resolveQuestDefinition((string) ($entry["tier"] ?? $index), (string) ($entry["quest_id"] ?? ""));
            if ($definition === null) {
                continue;
            }

            $updated = $this->applyProgressToRecord($player, $definition, $entry, $eventType, $amount, $context);
            if ($updated !== null) {
                $this->profiles[$uuid]["daily_quests"][$index] = $updated;
                $profileChanged = true;
            }
        }

        $bonus = [];
        foreach ((array) ($this->profiles[$uuid]["bonus_quests"] ?? []) as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $definition = $this->resolveQuestDefinition((string) ($entry["tier"] ?? ""), (string) ($entry["quest_id"] ?? ""));
            if ($definition === null) {
                $bonus[] = $entry;
                continue;
            }

            $updated = $this->applyProgressToRecord($player, $definition, $entry, $eventType, $amount, $context);
            $bonus[] = $updated ?? $entry;
            if ($updated !== null) {
                $profileChanged = true;
            }
        }

        $this->profiles[$uuid]["bonus_quests"] = $bonus;
        if ($profileChanged) {
            $this->saveProfile($uuid);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getShopRotation(): array
    {
        $offers = [];
        foreach ($this->shopRotation as $offerId) {
            $definition = $this->shopDefinitions[$offerId] ?? null;
            if ($definition !== null) {
                $offers[] = $definition;
            }
        }
        return $offers;
    }

    public function purchaseShopOffer(Player $player, string $offerId): bool
    {
        $this->ensureProfile($player);
        $offerId = strtolower(trim($offerId));
        if ($offerId === "" || !in_array($offerId, $this->shopRotation, true)) {
            $player->sendMessage(C::colorize("&r&cThat quest shop offer is no longer available."));
            PlayerUtils::playSound($player, "note.bass");
            return false;
        }

        $definition = $this->shopDefinitions[$offerId] ?? null;
        if ($definition === null) {
            $player->sendMessage(C::colorize("&r&cThat quest shop reward is invalid."));
            PlayerUtils::playSound($player, "note.bass");
            return false;
        }

        $uuid = $player->getUniqueId()->toString();
        $points = max(0, (int) ($this->profiles[$uuid]["quest_points"] ?? 0));
        $cost = max(1, (int) ($definition["cost"] ?? 1));
        if ($points < $cost) {
            $player->sendMessage(C::colorize("&r&cYou need &f{$cost} &cquest points for that purchase."));
            PlayerUtils::playSound($player, "note.bass");
            return false;
        }

        $reward = RewardResolver::resolveItem((array) $definition["reward"], $player);
        if (!$reward instanceof Item) {
            $player->sendMessage(C::colorize("&r&cThat quest shop reward failed to resolve."));
            PlayerUtils::playSound($player, "note.bass");
            return false;
        }

        $this->profiles[$uuid]["quest_points"] = $points - $cost;
        $this->saveProfile($uuid);

        $session = Loader::getPlayerManager()->getSession($player);
        foreach ($player->getInventory()->addItem($reward) as $leftover) {
            if ($session !== null) {
                $session->addItemToCollection($leftover);
            } else {
                $player->getWorld()->dropItem($player->getPosition(), $leftover);
            }
        }

        $player->sendMessage(C::colorize("&r&aPurchased " . (string) ($definition["display"] ?? "that reward") . " &afor &f{$cost} &aquest points."));
        PlayerUtils::playSound($player, "random.levelup");
        return true;
    }

    public function noteBahOutcome(Player $winner): void
    {
        $this->ensureProfile($winner);
        $uuid = $winner->getUniqueId()->toString();
        $meta = (array) ($this->profiles[$uuid]["meta"] ?? []);
        $meta["bah_win_streak"] = max(0, (int) ($meta["bah_win_streak"] ?? 0)) + 1;
        $this->profiles[$uuid]["meta"] = $meta;
        $this->saveProfile($uuid);
        $this->recordProgress($winner, "bah_wins", 1, [
            "streak" => (int) $meta["bah_win_streak"],
        ]);
    }

    public function resetBahStreakForAllExcept(string $winnerUuid): void
    {
        foreach ($this->profiles as $uuid => $profile) {
            if ($uuid === $winnerUuid) {
                continue;
            }

            $meta = (array) ($profile["meta"] ?? []);
            if ((int) ($meta["bah_win_streak"] ?? 0) === 0) {
                continue;
            }
            $meta["bah_win_streak"] = 0;
            $this->profiles[$uuid]["meta"] = $meta;
            $this->saveProfile($uuid);
        }
    }

    private function loadState(): void
    {
        if ($this->loaded) {
            return;
        }
        $this->loaded = true;

        Loader::getDatabase()->executeSelect(QueryStmts::PLAYER_QUESTS_SELECT, [], function(array $rows): void {
            foreach ($rows as $row) {
                $uuid = (string) ($row["uuid"] ?? "");
                if ($uuid === "") {
                    continue;
                }

                $this->profiles[$uuid] = [
                    "uuid" => $uuid,
                    "username" => (string) ($row["username"] ?? "Unknown"),
                    "quest_points" => max(0, (int) ($row["quest_points"] ?? 0)),
                    "assigned_at" => max(0, (int) ($row["assigned_at"] ?? 0)),
                    "daily_quests" => StorageUtils::decodeJsonString((string) ($row["daily_quests"] ?? "{}"), "quest daily quests") ?? [],
                    "bonus_quests" => StorageUtils::decodeJsonString((string) ($row["bonus_quests"] ?? "[]"), "quest bonus quests") ?? [],
                    "meta" => StorageUtils::decodeJsonString((string) ($row["meta"] ?? "{}"), "quest meta") ?? ["bah_win_streak" => 0, "reputation_points" => 0],
                ];
                $this->normalizeProfile($uuid);
            }
        });

        Loader::getDatabase()->executeSelect(QueryStmts::QUEST_SHOP_STATE_SELECT, [], function(array $rows): void {
            foreach ($rows as $row) {
                $key = (string) ($row["state_key"] ?? "");
                $value = (string) ($row["value"] ?? "");
                if ($key === "rotation") {
                    $decoded = StorageUtils::decodeJsonString($value, "quest shop rotation");
                    if (is_array($decoded)) {
                        $this->shopRotation = array_values(array_filter(array_map(
                            static fn(mixed $entry): string => strtolower(trim((string) $entry)),
                            $decoded
                        ), static fn(string $entry): bool => $entry !== ""));
                    }
                }
                if ($key === "refreshed_at") {
                    $this->shopRefreshedAt = max(0, (int) $value);
                }
            }

            if ($this->shopRotation === [] && $this->shopDefinitions !== []) {
                $this->rollShopRotation(true);
            }
        });
    }

    private function insertProfile(string $uuid): void
    {
        $profile = $this->profiles[$uuid];
        Loader::getDatabase()->executeInsert(QueryStmts::PLAYER_QUESTS_CREATE, [
            "uuid" => $uuid,
            "username" => (string) $profile["username"],
            "quest_points" => (int) $profile["quest_points"],
            "assigned_at" => (int) $profile["assigned_at"],
            "daily_quests" => StorageUtils::encodeJsonString((array) $profile["daily_quests"], "quest daily quests") ?? "{}",
            "bonus_quests" => StorageUtils::encodeJsonString((array) $profile["bonus_quests"], "quest bonus quests") ?? "[]",
            "meta" => StorageUtils::encodeJsonString((array) $profile["meta"], "quest meta") ?? "{}",
        ]);
    }

    private function saveProfile(string $uuid): void
    {
        $profile = $this->profiles[$uuid] ?? null;
        if (!is_array($profile)) {
            return;
        }

        Loader::getDatabase()->executeChange(QueryStmts::PLAYER_QUESTS_UPDATE, [
            "uuid" => $uuid,
            "username" => (string) $profile["username"],
            "quest_points" => (int) $profile["quest_points"],
            "assigned_at" => (int) $profile["assigned_at"],
            "daily_quests" => StorageUtils::encodeJsonString((array) $profile["daily_quests"], "quest daily quests") ?? "{}",
            "bonus_quests" => StorageUtils::encodeJsonString((array) $profile["bonus_quests"], "quest bonus quests") ?? "[]",
            "meta" => StorageUtils::encodeJsonString((array) $profile["meta"], "quest meta") ?? "{}",
        ]);
    }

    private function needsDailyRefresh(string $uuid): bool
    {
        $profile = $this->profiles[$uuid] ?? null;
        if (!is_array($profile)) {
            return true;
        }

        $assignedAt = max(0, (int) ($profile["assigned_at"] ?? 0));
        if ($assignedAt <= 0 || (time() - $assignedAt) >= $this->dailyRefreshSeconds) {
            return true;
        }

        if (count((array) ($profile["daily_quests"] ?? [])) < $this->dailyQuestCount) {
            return true;
        }

        return false;
    }

    private function assignDailyQuests(string $uuid, ?Player $player = null): void
    {
        $profile = $this->profiles[$uuid] ?? null;
        if (!is_array($profile)) {
            return;
        }

        $daily = [];
        $usedQuestIds = [];
        $dailyQuestCount = $this->dailyQuestCount;
        if ($player !== null && ChunkWatchdogManager::shouldGrantExtraDailyQuest($player)) {
            ++$dailyQuestCount;
        }

        for ($slot = 0; $slot < $dailyQuestCount; $slot++) {
            $tierId = $this->rollTierIdForProfile($profile);
            $definition = $this->pickQuestDefinition($tierId, $usedQuestIds);
            if ($definition === null) {
                $definition = $this->pickQuestDefinitionFromAnyTier($usedQuestIds);
            }
            if ($definition === null) {
                continue;
            }

            $daily[] = $this->createQuestRecord($definition);
            $usedQuestIds[] = (string) $definition["id"];
        }

        $profile["daily_quests"] = $daily;
        $profile["assigned_at"] = time();
        $profile["bonus_quests"] = [];
        $meta = is_array($profile["meta"] ?? null) ? $profile["meta"] : [];
        $meta["bah_win_streak"] = (int) ($meta["bah_win_streak"] ?? 0);
        $meta["reputation_points"] = max(0, (int) ($meta["reputation_points"] ?? 0));
        $profile["meta"] = $meta;
        $this->profiles[$uuid] = $profile;
    }

    private function rollShopRotation(bool $persist): void
    {
        if ($this->shopDefinitions === []) {
            $this->shopRotation = [];
            return;
        }

        $pool = $this->shopDefinitions;
        $rotation = [];
        while ($pool !== [] && count($rotation) < $this->shopRotationSize) {
            $pickedId = $this->rollWeightedOfferKey($pool);
            if ($pickedId === null) {
                break;
            }
            $rotation[] = $pickedId;
            unset($pool[$pickedId]);
        }

        $this->shopRotation = $rotation;
        $this->shopRefreshedAt = time();

        if ($persist) {
            Loader::getDatabase()->executeInsert(QueryStmts::QUEST_SHOP_STATE_UPSERT, [
                "state_key" => "rotation",
                "value" => StorageUtils::encodeJsonString($this->shopRotation, "quest shop rotation") ?? "[]",
            ]);
            Loader::getDatabase()->executeInsert(QueryStmts::QUEST_SHOP_STATE_UPSERT, [
                "state_key" => "refreshed_at",
                "value" => (string) $this->shopRefreshedAt,
            ]);
        }
    }

    private function rollWeightedOfferKey(array $pool): ?string
    {
        $totalWeight = 0;
        foreach ($pool as $definition) {
            $totalWeight += max(1, (int) ($definition["weight"] ?? 1));
        }
        if ($totalWeight <= 0) {
            return null;
        }

        $roll = mt_rand(1, $totalWeight);
        $cursor = 0;
        foreach ($pool as $offerId => $definition) {
            $cursor += max(1, (int) ($definition["weight"] ?? 1));
            if ($roll <= $cursor) {
                return (string) $offerId;
            }
        }

        return array_key_first($pool);
    }

    private function pickQuestDefinitionFromAnyTier(array $avoidQuestIds = []): ?array
    {
        $pool = [];
        foreach ($this->tierDefinitions as $tier) {
            foreach ((array) ($tier["quests"] ?? []) as $definition) {
                if (!in_array((string) ($definition["id"] ?? ""), $avoidQuestIds, true)) {
                    $pool[] = $definition;
                }
            }
        }

        if ($pool === []) {
            foreach ($this->tierDefinitions as $tier) {
                foreach ((array) ($tier["quests"] ?? []) as $definition) {
                    $pool[] = $definition;
                }
            }
        }

        if ($pool === []) {
            return null;
        }

        $pool = array_values($pool);
        return $pool[array_rand($pool)];
    }

    private function pickQuestDefinition(string $tierId, array $avoidQuestIds = []): ?array
    {
        $tier = $this->tierDefinitions[$tierId] ?? null;
        if (!is_array($tier)) {
            return null;
        }

        $pool = array_filter(
            (array) ($tier["quests"] ?? []),
            static fn(array $definition): bool => !in_array((string) ($definition["id"] ?? ""), $avoidQuestIds, true)
        );

        if ($pool === []) {
            $pool = (array) ($tier["quests"] ?? []);
        }
        if ($pool === []) {
            return null;
        }

        $pool = array_values($pool);
        return $pool[array_rand($pool)];
    }

    private function resolveQuestDefinition(string $tierId, string $questId): ?array
    {
        $tierId = strtolower($tierId);
        $questId = strtolower($questId);
        if ($tierId === "" || $questId === "") {
            return null;
        }

        return $this->tierDefinitions[$tierId]["quests"][$questId] ?? null;
    }

    private function createQuestRecord(array $definition): array
    {
        return [
            "tier" => (string) $definition["tier"],
            "quest_id" => (string) $definition["id"],
            "progress" => 0,
            "completed" => false,
            "rewarded" => false,
            "data" => [],
        ];
    }

    public function resetSeasonState(): void
    {
        $this->profiles = [];
        $this->shopRotation = [];
        $this->shopRefreshedAt = 0;
        if ($this->shopDefinitions !== []) {
            $this->rollShopRotation(true);
        }
    }

    private function buildQuestRow(array $definition, array $record, bool $bonus): array
    {
        $progress = max(0, (int) ($record["progress"] ?? 0));
        $target = max(1, (int) ($definition["amount"] ?? 1));
        return [
            "id" => (string) $definition["id"],
            "tier_key" => (string) $definition["tier"],
            "tier" => (string) $definition["display_tier"],
            "bonus" => $bonus,
            "record_index" => (int) ($record["record_index"] ?? -1),
            "name" => (string) $definition["name"],
            "description" => (string) $definition["description"],
            "progress" => min($target, $progress),
            "target" => $target,
            "points" => max(1, (int) ($definition["points"] ?? 1)),
            "completed" => (bool) ($record["completed"] ?? false),
            "source" => (string) ($record["source"] ?? ""),
        ];
    }

    private function applyProgressToRecord(Player $player, array $definition, array $record, string $eventType, int $amount, array $context): ?array
    {
        if ((string) $definition["type"] !== $eventType) {
            return null;
        }
        if ((bool) ($record["rewarded"] ?? false) === true) {
            return null;
        }
        if (!$this->passesFilters((array) ($definition["filters"] ?? []), $context)) {
            return null;
        }

        $target = max(1, (int) ($definition["amount"] ?? 1));
        $progress = max(0, (int) ($record["progress"] ?? 0));
        $data = is_array($record["data"] ?? null) ? $record["data"] : [];
        $increment = $amount;

        $filters = (array) ($definition["filters"] ?? []);
        if (($filters["unique"] ?? false) === true) {
            $field = (string) ($filters["unique_field"] ?? "unique");
            $uniqueValue = strtolower(trim((string) ($context[$field] ?? "")));
            if ($uniqueValue === "") {
                return null;
            }

            $seen = is_array($data["seen"] ?? null) ? $data["seen"] : [];
            if (in_array($uniqueValue, $seen, true)) {
                return null;
            }
            $seen[] = $uniqueValue;
            $data["seen"] = $seen;
            $increment = 1;
        }

        if (isset($filters["streak_at_least"])) {
            $streak = max(0, (int) ($context["streak"] ?? 0));
            if ($streak < max(1, (int) $filters["streak_at_least"])) {
                return null;
            }
            $increment = 1;
        }

        $progress = min($target, $progress + $increment);
        $record["progress"] = $progress;
        $record["data"] = $data;

        if ($progress >= $target && !((bool) ($record["rewarded"] ?? false))) {
            $record["completed"] = true;
            $record["rewarded"] = true;
            $this->awardQuestPoints($player, $definition, $record);
        }

        return $record;
    }

    private function passesFilters(array $filters, array $context): bool
    {
        foreach ($filters as $key => $expected) {
            if (in_array($key, ["unique", "unique_field", "streak_at_least"], true)) {
                continue;
            }

            $actual = $context[$key] ?? null;
            if (is_array($expected)) {
                if (!in_array($actual, $expected, true)) {
                    return false;
                }
                continue;
            }

            if ((is_int($expected) || is_float($expected)) && str_starts_with((string) $key, "min_")) {
                $field = substr((string) $key, 4);
                if ((float) ($context[$field] ?? 0) < (float) $expected) {
                    return false;
                }
                continue;
            }

            if ($actual !== $expected) {
                return false;
            }
        }

        return true;
    }

    private function awardQuestPoints(Player $player, array $definition, array $record): void
    {
        $uuid = $player->getUniqueId()->toString();
        $points = max(1, (int) ($definition["points"] ?? 1));
        $beforeLevel = $this->calculateReputationLevel($this->profiles[$uuid]);
        $this->profiles[$uuid]["quest_points"] = max(0, (int) ($this->profiles[$uuid]["quest_points"] ?? 0)) + $points;
        $meta = (array) ($this->profiles[$uuid]["meta"] ?? []);
        $meta["reputation_points"] = max(0, (int) ($meta["reputation_points"] ?? 0)) + 1;
        $this->profiles[$uuid]["meta"] = $meta;
        $afterLevel = $this->calculateReputationLevel($this->profiles[$uuid]);

        $prefix = ($record["source"] ?? "") !== "" ? "&dBonus Quest" : "&bQuests";
        $player->sendMessage(C::colorize("{$prefix} &8| &r&aCompleted &f" . $definition["name"] . " &aand earned &f{$points} &aquest point" . ($points === 1 ? "" : "s") . "&a."));
        $player->sendMessage(C::colorize("&r&dReputation &8| &r+1 reputation progress &7(" . number_format((int) $meta["reputation_points"]) . " total)&r."));
        if ($afterLevel > $beforeLevel) {
            $player->sendMessage(C::colorize("&r&dReputation &8| &r&aYour quest reputation increased to &fLevel {$afterLevel}&a."));
        }
        PlayerUtils::playSound($player, "random.levelup");
    }

    private function normalizeProfile(string $uuid): void
    {
        $profile = $this->profiles[$uuid] ?? null;
        if (!is_array($profile)) {
            return;
        }

        $profile["daily_quests"] = $this->normalizeQuestRecordList((array) ($profile["daily_quests"] ?? []));
        $profile["bonus_quests"] = $this->normalizeQuestRecordList((array) ($profile["bonus_quests"] ?? []));
        $meta = is_array($profile["meta"] ?? null) ? $profile["meta"] : [];
        $meta["bah_win_streak"] = max(0, (int) ($meta["bah_win_streak"] ?? 0));
        $meta["reputation_points"] = max(0, (int) ($meta["reputation_points"] ?? 0));
        $profile["meta"] = $meta;
        $this->profiles[$uuid] = $profile;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeQuestRecordList(array $records): array
    {
        $normalized = [];
        foreach ($records as $key => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            if (!isset($entry["tier"]) && is_string($key)) {
                $entry["tier"] = strtolower($key);
            }

            if (!isset($entry["quest_id"]) || !isset($entry["tier"])) {
                continue;
            }

            $entry["tier"] = strtolower((string) $entry["tier"]);
            $entry["progress"] = max(0, (int) ($entry["progress"] ?? 0));
            $entry["completed"] = (bool) ($entry["completed"] ?? false);
            $entry["rewarded"] = (bool) ($entry["rewarded"] ?? false);
            $entry["data"] = is_array($entry["data"] ?? null) ? $entry["data"] : [];
            $normalized[] = $entry;
        }

        return $normalized;
    }

    private function calculateReputationLevel(array $profile): int
    {
        $points = max(0, (int) (($profile["meta"]["reputation_points"] ?? 0)));
        return min($this->maxReputationLevel, intdiv($points, $this->reputationPointsPerLevel) + 1);
    }

    /**
     * @return array{level:int,current:int,needed:int,remaining:int,percent:float,max:bool}
     */
    private function calculateReputationProgress(array $profile): array
    {
        $points = max(0, (int) (($profile["meta"]["reputation_points"] ?? 0)));
        $level = $this->calculateReputationLevel($profile);
        if ($level >= $this->maxReputationLevel) {
            return [
                "level" => $level,
                "current" => $this->reputationPointsPerLevel,
                "needed" => $this->reputationPointsPerLevel,
                "remaining" => 0,
                "percent" => 1.0,
                "max" => true,
            ];
        }

        $current = $points % $this->reputationPointsPerLevel;
        $needed = $this->reputationPointsPerLevel;
        return [
            "level" => $level,
            "current" => $current,
            "needed" => $needed,
            "remaining" => max(0, $needed - $current),
            "percent" => $needed > 0 ? min(1.0, $current / $needed) : 1.0,
            "max" => false,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function getTierWeightsForProfile(array $profile): array
    {
        $level = $this->calculateReputationLevel($profile);
        $weights = $this->reputationTierWeights[$level] ?? $this->reputationTierWeights[array_key_last($this->reputationTierWeights)] ?? [];
        $normalized = [];
        foreach ($this->tierDefinitions as $tierId => $_tier) {
            $normalized[$tierId] = max(0, (int) ($weights[$tierId] ?? 0));
        }

        if (array_sum($normalized) <= 0) {
            foreach (array_keys($normalized) as $tierId) {
                $normalized[$tierId] = 1;
            }
        }

        return $normalized;
    }

    private function rollTierIdForProfile(array $profile): string
    {
        $weights = $this->getTierWeightsForProfile($profile);
        $total = array_sum($weights);
        if ($total <= 0) {
            return (string) array_key_first($this->tierDefinitions);
        }

        $roll = mt_rand(1, $total);
        $cursor = 0;
        foreach ($weights as $tierId => $weight) {
            $cursor += $weight;
            if ($roll <= $cursor) {
                return $tierId;
            }
        }

        return (string) array_key_first($weights);
    }
}
