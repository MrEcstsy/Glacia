<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\quests;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\items\GlaciaItemFactory;
use ecstsy\Glacia\utils\RewardResolver;
use ecstsy\Glacia\utils\StorageUtils;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class QuestInstance {

    /** @var array<string, array<string, mixed>> */
    private static array $tiers = [];
    /** @var array<string, array<string, mixed>> */
    private static array $questTemplates = [];
    /** @var array<string, list<array<string, mixed>>> */
    private static array $rewardTables = [];

    /** @var array<string, array<string, mixed>> */
    private const DEFAULT_TIERS = [
        "dormant" => [
            "display" => "Dormant",
            "material" => "amethyst_shard",
            "color" => "&b",
            "symbol" => "*",
            "quests" => [
                [
                    "type" => "mine",
                    "name" => "Cold Start Survey",
                    "objectives" => [
                        ["block" => "coal_ore", "amount" => 32],
                        ["block" => "iron_ore", "amount" => 16]
                    ]
                ],
                [
                    "type" => "mine",
                    "name" => "Torchline Run",
                    "objectives" => [
                        ["block" => "coal_ore", "amount" => 48]
                    ]
                ],
                [
                    "type" => "place",
                    "name" => "Frontier Footing",
                    "objectives" => [
                        ["block" => "cobblestone", "amount" => 64],
                        ["block" => "oak_planks", "amount" => 32]
                    ]
                ],
                [
                    "type" => "place",
                    "name" => "Outpost Walls",
                    "objectives" => [
                        ["block" => "stone", "amount" => 64]
                    ]
                ],
                [
                    "type" => "kill",
                    "name" => "Graveyard Sweep",
                    "objectives" => [
                        ["entity" => "zombie", "amount" => 20],
                        ["entity" => "skeleton", "amount" => 10]
                    ]
                ],
                [
                    "type" => "kill",
                    "name" => "Webbreaker",
                    "objectives" => [
                        ["entity" => "spider", "amount" => 16]
                    ]
                ],
                [
                    "type" => "sell",
                    "name" => "Harvest Cart",
                    "objectives" => [
                        ["item" => "wheat", "amount" => 64],
                        ["item" => "carrot", "amount" => 48]
                    ]
                ],
                [
                    "type" => "sell",
                    "name" => "Timber Pile",
                    "objectives" => [
                        ["item" => "oak_log", "amount" => 64],
                        ["item" => "spruce_log", "amount" => 32]
                    ]
                ],
            ],
            "rewards" => [
                ["material" => "iron_ingot", "count" => 12],
                ["material" => "amethyst_shard", "count" => 6],
                ["id" => "money_note", "amount" => 5000],
                ["id" => "money_note", "amount" => 8000],
                ["id" => "xpnote", "amount" => 2500],
                ["id" => "xpnote", "amount" => 4000],
            ],
        ],
        "awakened" => [
            "display" => "Awakened",
            "material" => "echo_shard",
            "color" => "&5",
            "symbol" => "+",
            "quests" => [
                [
                    "type" => "mine",
                    "name" => "Deep Vein Survey",
                    "objectives" => [
                        ["block" => "iron_ore", "amount" => 40],
                        ["block" => "gold_ore", "amount" => 24],
                        ["block" => "redstone_ore", "amount" => 32]
                    ]
                ],
                [
                    "type" => "mine",
                    "name" => "Blue Dust Contract",
                    "objectives" => [
                        ["block" => "lapis_ore", "amount" => 28],
                        ["block" => "redstone_ore", "amount" => 36]
                    ]
                ],
                [
                    "type" => "place",
                    "name" => "Watchpost Frame",
                    "objectives" => [
                        ["block" => "stone_bricks", "amount" => 80],
                        ["block" => "spruce_planks", "amount" => 48]
                    ]
                ],
                [
                    "type" => "kill",
                    "name" => "Night Shift",
                    "objectives" => [
                        ["entity" => "skeleton", "amount" => 24],
                        ["entity" => "spider", "amount" => 24],
                        ["entity" => "zombie", "amount" => 24]
                    ]
                ],
                [
                    "type" => "sell",
                    "name" => "Cane Syndicate",
                    "objectives" => [
                        ["item" => "sugar_cane", "amount" => 128],
                        ["item" => "cactus", "amount" => 64]
                    ]
                ],
                [
                    "type" => "sell",
                    "name" => "Rot and Bone",
                    "objectives" => [
                        ["item" => "rotten_flesh", "amount" => 96],
                        ["item" => "bone", "amount" => 64],
                        ["item" => "string", "amount" => 48]
                    ]
                ],
                [
                    "type" => "sell",
                    "name" => "Patchwork Produce",
                    "objectives" => [
                        ["item" => "pumpkin", "amount" => 64],
                        ["item" => "melon", "amount" => 96],
                        ["item" => "beetroot", "amount" => 64]
                    ]
                ],
            ],
            "rewards" => [
                ["material" => "gold_ingot", "count" => 10],
                ["material" => "emerald", "count" => 4],
                ["material" => "echo_shard", "count" => 2],
                ["id" => "money_note", "amount" => 15000],
                ["id" => "money_note", "amount" => 22000],
                ["id" => "xpnote", "amount" => 7500],
                ["id" => "xpnote", "amount" => 10000],
            ],
        ],
        "ascendent" => [
            "display" => "Ascendent",
            "material" => "nether_star",
            "color" => "&e",
            "symbol" => "#",
            "sell-values" => [
                "raw" => 45000,
                "complete" => 120000
            ],
            "quests" => [
                [
                    "type" => "mine",
                    "name" => "Gemrunner Route",
                    "objectives" => [
                        ["block" => "diamond_ore", "amount" => 24],
                        ["block" => "gold_ore", "amount" => 40],
                        ["block" => "redstone_ore", "amount" => 64]
                    ]
                ],
                [
                    "type" => "mine",
                    "name" => "Nether Ledger",
                    "objectives" => [
                        ["block" => "nether_quartz_ore", "amount" => 72],
                        ["block" => "gold_ore", "amount" => 32]
                    ]
                ],
                [
                    "type" => "place",
                    "name" => "Breach Stockpile",
                    "objectives" => [
                        ["block" => "obsidian", "amount" => 96],
                        ["block" => "stone_bricks", "amount" => 96]
                    ]
                ],
                [
                    "type" => "kill",
                    "name" => "Blazeline",
                    "objectives" => [
                        ["entity" => "blaze", "amount" => 32],
                        ["entity" => "skeleton", "amount" => 48]
                    ]
                ],
                [
                    "type" => "sell",
                    "name" => "Smelter's Dividend",
                    "objectives" => [
                        ["item" => "iron_ingot", "amount" => 96],
                        ["item" => "gold_ingot", "amount" => 64],
                        ["item" => "redstone", "amount" => 96]
                    ]
                ],
                [
                    "type" => "sell",
                    "name" => "Pearl and Powder",
                    "objectives" => [
                        ["item" => "ender_pearl", "amount" => 32],
                        ["item" => "gunpowder", "amount" => 96],
                        ["item" => "blaze_rod", "amount" => 24]
                    ]
                ],
                [
                    "type" => "sell",
                    "name" => "Nether Harvest Exchange",
                    "objectives" => [
                        ["item" => "nether_wart", "amount" => 160],
                        ["item" => "cactus", "amount" => 128],
                        ["item" => "sugar_cane", "amount" => 160]
                    ]
                ],
            ],
            "rewards" => [
                ["material" => "diamond", "count" => 6],
                ["material" => "emerald", "count" => 6],
                ["material" => "nether_star", "count" => 1],
                ["id" => "money_note", "amount" => 55000],
                ["id" => "money_note", "amount" => 85000],
                ["id" => "xpnote", "amount" => 20000],
                ["id" => "xpnote", "amount" => 30000],
            ],
        ],
        "eternal" => [
            "display" => "Eternal",
            "material" => "dragon_egg",
            "color" => "&c",
            "symbol" => "@",
            "sell-values" => [
                "raw" => 135000,
                "complete" => 350000
            ],
            "quests" => [
                [
                    "type" => "mine",
                    "name" => "Crystal Treasury",
                    "objectives" => [
                        ["block" => "diamond_ore", "amount" => 48],
                        ["block" => "emerald_ore", "amount" => 36],
                        ["block" => "nether_quartz_ore", "amount" => 96]
                    ]
                ],
                [
                    "type" => "mine",
                    "name" => "War Chest Reserve",
                    "objectives" => [
                        ["block" => "redstone_ore", "amount" => 128],
                        ["block" => "gold_ore", "amount" => 64]
                    ]
                ],
                [
                    "type" => "place",
                    "name" => "Citadel Core",
                    "objectives" => [
                        ["block" => "obsidian", "amount" => 192],
                        ["block" => "stone_bricks", "amount" => 128],
                        ["block" => "nether_bricks", "amount" => 96]
                    ]
                ],
                [
                    "type" => "kill",
                    "name" => "Rift Cull",
                    "objectives" => [
                        ["entity" => "blaze", "amount" => 72],
                        ["entity" => "skeleton", "amount" => 96],
                        ["entity" => "zombie", "amount" => 120]
                    ]
                ],
                [
                    "type" => "sell",
                    "name" => "Siege Treasury",
                    "objectives" => [
                        ["item" => "diamond", "amount" => 48],
                        ["item" => "emerald", "amount" => 32],
                        ["item" => "gold_ingot", "amount" => 128]
                    ]
                ],
                [
                    "type" => "sell",
                    "name" => "Black Market Caravan",
                    "objectives" => [
                        ["item" => "gunpowder", "amount" => 160],
                        ["item" => "blaze_rod", "amount" => 64],
                        ["item" => "ender_pearl", "amount" => 64]
                    ]
                ],
                [
                    "type" => "sell",
                    "name" => "Overgrowth Tax",
                    "objectives" => [
                        ["item" => "sugar_cane", "amount" => 256],
                        ["item" => "nether_wart", "amount" => 192],
                        ["item" => "pumpkin", "amount" => 128],
                        ["item" => "cactus", "amount" => 192]
                    ]
                ],
            ],
            "rewards" => [
                ["material" => "diamond", "count" => 18],
                ["material" => "emerald", "count" => 12],
                ["material" => "nether_star", "count" => 2],
                ["id" => "money_note", "amount" => 250000],
                ["id" => "money_note", "amount" => 325000],
                ["id" => "xpnote", "amount" => 100000],
                ["id" => "xpnote", "amount" => 150000],
            ],
        ],
    ];

    public static function registerDefaults(): void
    {
        self::loadFromConfig();
    }

    public static function loadFromConfig(): void
    {
        self::$tiers = [];
        self::$questTemplates = [];
        self::$rewardTables = [];

        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "quests.yml");
        self::$questTemplates = self::parseQuestTemplates((array) ($config?->get("quest-templates", []) ?? []));
        self::$rewardTables = self::parseRewardTables((array) ($config?->get("reward-tables", []) ?? []));
        $rawTiers = $config?->get("tiers");

        if (!is_array($rawTiers) || $rawTiers === []) {
            $rawTiers = self::DEFAULT_TIERS;
        }

        foreach ($rawTiers as $tierId => $tierData) {
            if (!is_array($tierData)) {
                continue;
            }

            $parsed = self::parseTier((string) $tierId, $tierData);
            if ($parsed !== null) {
                self::$tiers[(string) $tierId] = $parsed;
            }
        }
    }

    public static function giveQuestItem(Player $player, string $tier, int $questIndex = 0): void
    {
        $data = self::$tiers[$tier] ?? null;
        if ($data === null) {
            self::sendQuestMessage($player, "&r&cThat quest tier does not exist.");
            return;
        }

        $quest = $data["quests"][$questIndex] ?? null;
        if ($quest === null) {
            self::sendQuestMessage($player, "&r&cThat quest could not be found.");
            return;
        }

        $material = StringToItemParser::getInstance()->parse((string) $data["material"]) ?? VanillaItems::AMETHYST_SHARD();
        $symbol = (string) ($data["symbol"] ?? "*");
        $color = (string) ($data["color"] ?? "&7");

        $item = $material->setCustomName(C::colorize("&r&7{$symbol} {$color}{$data['display']} Sigil &7{$symbol}"));

        $nbt = $item->getNamedTag();
        $glacia = $nbt->getCompoundTag("Glacia") ?? new CompoundTag();
        $glacia->setString("glaciaItem", "quest_sigil");
        $glacia->setString("questTier", $tier);
        $nbt->setTag("Glacia", $glacia);
        $nbt->setString("glacia_quest_tier", $tier);
        $nbt->setInt("glacia_quest_index", $questIndex);
        $nbt->setString("glacia_quest_type", (string) $quest["type"]);
        $nbt->setString(
            "glacia_quest_progress",
            self::encodeProgressData(array_fill(0, count($quest["objectives"]), 0), "quest {$tier}:{$questIndex} initial progress")
        );
        $item->setNamedTag($nbt);

        self::updateQuestLore($item);
        $leftovers = $player->getInventory()->addItem($item);
        foreach ($leftovers as $leftover) {
            $player->getWorld()->dropItem($player->getPosition(), $leftover);
        }

        self::sendQuestMessage($player, "&r&aYou received the quest &f{$quest['name']}&a.");
    }

    public static function updateQuestLore(Item $item): void
    {
        $nbt = $item->getNamedTag();
        $tier = $nbt->getString("glacia_quest_tier", "");
        $data = self::$tiers[$tier] ?? null;
        if ($data === null) {
            return;
        }

        $questIndex = $nbt->getInt("glacia_quest_index", 0);
        $quest = $data["quests"][$questIndex] ?? null;
        if ($quest === null) {
            return;
        }

        $progressData = self::decodeProgressData($nbt);

        $lore = [];
        $lore[] = C::colorize("&r&7A mystical sigil that records your deeds.");
        $lore[] = C::colorize("&r&7Tier: &f{$data['display']}");
        $lore[] = "";
        $lore[] = C::colorize("&r&7Quest Name: &f{$quest['name']}");
        $lore[] = C::colorize("&r&7Objectives:");

        $totalProgress = 0;
        $totalGoal = 0;
        $objectiveStrings = [];

        foreach ($quest["objectives"] as $index => $objective) {
            $completed = (int) ($progressData[$index] ?? 0);
            $goal = max(1, (int) ($objective["amount"] ?? 1));
            $totalProgress += min($completed, $goal);
            $totalGoal += $goal;

            $color = $completed >= $goal ? "&a" : "&f";
            $objectiveStrings[] = "{$color}x{$goal} " . (string) $objective["label"];
        }

        $action = match ((string) $quest["type"]) {
            "sell" => "Sell",
            "mine" => "Mine",
            "place" => "Place",
            "kill" => "Slay",
            default => "Complete",
        };

        $lore[] = C::colorize("&r&7{$action}: " . implode("&7, ", $objectiveStrings));
        $lore[] = C::colorize(self::generateQuestProgressBar($totalProgress, $totalGoal, (string) ($data["color"] ?? "&a")));

        $sellValues = (array) ($data["sell-values"] ?? []);
        $rawValue = isset($sellValues["raw"]) ? (float) $sellValues["raw"] : null;
        $completeValue = isset($sellValues["complete"]) ? (float) $sellValues["complete"] : null;
        if ($rawValue !== null || $completeValue !== null) {
            $lore[] = "";
            if ($rawValue !== null) {
                $lore[] = C::colorize("&r&7Raw Sale: &a$" . number_format($rawValue, 2));
            }
            if ($completeValue !== null) {
                $lore[] = C::colorize("&r&7Completed Sale: &a$" . number_format($completeValue, 2));
            }
            $lore[] = C::colorize("&r&8Claim the reward or cash the sigil out.");
        }

        if ($totalProgress >= $totalGoal) {
            $nbt->setByte("glacia_quest_complete", 1);
            $lore[] = C::colorize("&r&a&lQuest Complete!");
        } else {
            $nbt->removeTag("glacia_quest_complete");
        }

        $item->setNamedTag($nbt);
        $item->setLore($lore);
    }

    public static function addProgress(Player $player, string $eventType, mixed $subject = null, int $amount = 1): void
    {
        $inventory = $player->getInventory();

        foreach ($inventory->getContents() as $slot => $item) {
            $nbt = $item->getNamedTag();
            if (!$nbt->getTag("glacia_quest_type")) {
                continue;
            }

            if ($nbt->getString("glacia_quest_type", "") !== $eventType) {
                continue;
            }

            $tier = $nbt->getString("glacia_quest_tier", "");
            $index = $nbt->getInt("glacia_quest_index", 0);
            $quest = self::$tiers[$tier]["quests"][$index] ?? null;
            if ($quest === null) {
                continue;
            }

            $progressData = self::decodeProgressData($nbt);

            $updated = false;
            foreach ($quest["objectives"] as $objectiveIndex => $objective) {
                if (!self::matchesObjective($eventType, $subject, $objective)) {
                    continue;
                }

                $goal = max(1, (int) ($objective["amount"] ?? 1));
                $progressData[$objectiveIndex] = min($goal, (int) ($progressData[$objectiveIndex] ?? 0) + $amount);
                $updated = true;
            }

            if (!$updated) {
                continue;
            }

            $nbt->setString(
                "glacia_quest_progress",
                self::encodeProgressData($progressData, "quest {$tier}:{$index} progress")
            );
            $item->setNamedTag($nbt);
            self::updateQuestLore($item);
            $inventory->setItem($slot, $item);
        }
    }

    public static function onSell(Player $player, Item $soldItem, int $count): void
    {
        self::addProgress($player, "sell", $soldItem, $count);
    }

    public static function getTierData(string $tier): ?array
    {
        return self::$tiers[$tier] ?? null;
    }

    public static function getSellPrice(Item $item): ?float
    {
        $nbt = $item->getNamedTag();
        if (!$nbt->getTag("glacia_quest_tier")) {
            return null;
        }

        $tier = $nbt->getString("glacia_quest_tier", "");
        $tierData = self::$tiers[$tier] ?? null;
        if ($tierData === null) {
            return null;
        }

        $sellValues = (array) ($tierData["sell-values"] ?? []);
        $rawValue = isset($sellValues["raw"]) ? round((float) $sellValues["raw"], 2) : null;
        $completeValue = isset($sellValues["complete"]) ? round((float) $sellValues["complete"], 2) : null;

        if (self::isQuestItemComplete($nbt)) {
            return $completeValue ?? $rawValue;
        }

        return $rawValue;
    }

    public static function rollReward(string $tier, ?Player $player = null): ?Item
    {
        return self::rollRewardFromTierData(self::getTierData($tier), $player);
    }

    public static function rollRewardForItem(Item $item, ?Player $player = null): ?Item
    {
        $nbt = $item->getNamedTag();
        $tier = $nbt->getString("glacia_quest_tier", "");
        $questIndex = $nbt->getInt("glacia_quest_index", -1);
        $tierData = self::getTierData($tier);
        if ($tierData === null) {
            return null;
        }

        $quest = $tierData["quests"][$questIndex] ?? null;
        if (is_array($quest)) {
            $rewardTableId = (string) ($quest["reward-table"] ?? "");
            $rewards = self::resolveRewardSet($quest["rewards"] ?? [], $rewardTableId);
            if ($rewards !== []) {
                return self::rollResolvedReward($rewards, $player);
            }
        }

        return self::rollRewardFromTierData($tierData, $player);
    }

    private static function rollRewardFromTierData(?array $tierData, ?Player $player = null): ?Item
    {
        if ($tierData === null) {
            return null;
        }

        $rewardTableId = (string) ($tierData["reward-table"] ?? "");
        $rewards = self::resolveRewardSet($tierData["rewards"] ?? [], $rewardTableId);
        if (!is_array($rewards) || $rewards === []) {
            return null;
        }

        return self::rollResolvedReward($rewards, $player);
    }

    /**
     * @param list<array<string, mixed>> $rewards
     */
    private static function rollResolvedReward(array $rewards, ?Player $player = null): ?Item
    {
        $rewardData = $rewards[array_rand($rewards)];
        if (!is_array($rewardData)) {
            return null;
        }

        return RewardResolver::resolveItem($rewardData, $player);
    }

    private static function parseTier(string $tierId, array $tierData): ?array
    {
        $display = (string) ($tierData["display"] ?? ucfirst($tierId));
        $material = (string) ($tierData["material"] ?? "amethyst_shard");
        $color = (string) ($tierData["color"] ?? "&7");
        $symbol = (string) ($tierData["symbol"] ?? "*");

        $quests = [];
        foreach ((array) ($tierData["quests"] ?? []) as $questData) {
            if (!is_array($questData)) {
                continue;
            }

            $parsedQuest = self::parseQuest($questData);
            if ($parsedQuest !== null) {
                $quests[] = $parsedQuest;
            }
        }

        if ($quests === []) {
            return null;
        }

        $rewards = [];
        foreach ((array) ($tierData["rewards"] ?? []) as $rewardData) {
            if (is_string($rewardData)) {
                $rewardData = ["material" => $rewardData];
            }

            if (is_array($rewardData)) {
                $rewards[] = $rewardData;
            }
        }

        return [
            "display" => $display,
            "material" => $material,
            "color" => $color,
            "symbol" => $symbol,
            "sell-values" => self::normalizeSellValues((array) ($tierData["sell-values"] ?? [])),
            "quests" => $quests,
            "rewards" => $rewards,
            "reward-table" => self::normalizeIdentifier((string) ($tierData["reward-table"] ?? "")),
        ];
    }

    private static function parseQuest(array $questData): ?array
    {
        $questData = self::mergeQuestTemplate($questData);
        $type = strtolower((string) ($questData["type"] ?? ""));
        if (!in_array($type, ["mine", "place", "kill", "sell"], true)) {
            return null;
        }

        $objectives = [];
        foreach ((array) ($questData["objectives"] ?? []) as $objectiveData) {
            if (!is_array($objectiveData)) {
                continue;
            }

            $parsedObjective = self::parseObjective($type, $objectiveData);
            if ($parsedObjective !== null) {
                $objectives[] = $parsedObjective;
            }
        }

        if ($objectives === []) {
            return null;
        }

        return [
            "type" => $type,
            "name" => (string) ($questData["name"] ?? ucfirst($type) . " Quest"),
            "objectives" => $objectives,
            "rewards" => self::normalizeRewardEntries((array) ($questData["rewards"] ?? [])),
            "reward-table" => self::normalizeIdentifier((string) ($questData["reward-table"] ?? "")),
        ];
    }

    /**
     * @param array<string, mixed> $questData
     * @return array<string, mixed>
     */
    private static function mergeQuestTemplate(array $questData): array
    {
        $templateId = self::normalizeIdentifier((string) ($questData["template"] ?? $questData["extends"] ?? ""));
        if ($templateId === "" || !isset(self::$questTemplates[$templateId])) {
            return $questData;
        }

        return self::mergeAssocRecursive(self::$questTemplates[$templateId], $questData);
    }

    /**
     * @param array<string, mixed> $templates
     * @return array<string, array<string, mixed>>
     */
    private static function parseQuestTemplates(array $templates): array
    {
        $parsed = [];
        foreach ($templates as $templateId => $templateData) {
            if (!is_array($templateData)) {
                continue;
            }

            $normalizedId = self::normalizeIdentifier((string) $templateId);
            if ($normalizedId === "") {
                continue;
            }

            $parsed[$normalizedId] = $templateData;
        }

        return $parsed;
    }

    /**
     * @param array<string, mixed> $tables
     * @return array<string, list<array<string, mixed>>>
     */
    private static function parseRewardTables(array $tables): array
    {
        $parsed = [];
        foreach ($tables as $tableId => $tableData) {
            if (!is_array($tableData)) {
                continue;
            }

            $normalizedId = self::normalizeIdentifier((string) $tableId);
            if ($normalizedId === "") {
                continue;
            }

            $entries = self::normalizeRewardEntries($tableData);
            if ($entries !== []) {
                $parsed[$normalizedId] = $entries;
            }
        }

        return $parsed;
    }

    /**
     * @param array<int|string, mixed> $entries
     * @return list<array<string, mixed>>
     */
    private static function normalizeRewardEntries(array $entries): array
    {
        $normalized = [];
        foreach ($entries as $entry) {
            if (is_string($entry)) {
                $entry = ["material" => $entry];
            }

            if (is_array($entry)) {
                $normalized[] = $entry;
            }
        }

        return $normalized;
    }

    /**
     * @param array<int|string, mixed> $inlineRewards
     * @return list<array<string, mixed>>
     */
    private static function resolveRewardSet(array $inlineRewards, string $rewardTableId): array
    {
        $rewards = self::normalizeRewardEntries($inlineRewards);
        if ($rewards !== []) {
            return $rewards;
        }

        if ($rewardTableId !== "" && isset(self::$rewardTables[$rewardTableId])) {
            return self::$rewardTables[$rewardTableId];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $override
     * @return array<string, mixed>
     */
    private static function mergeAssocRecursive(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (
                is_string($key) &&
                isset($base[$key]) &&
                is_array($base[$key]) &&
                is_array($value) &&
                self::isAssoc($base[$key]) &&
                self::isAssoc($value)
            ) {
                $base[$key] = self::mergeAssocRecursive($base[$key], $value);
                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }

    /**
     * @param array<mixed> $array
     */
    private static function isAssoc(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }

    private static function parseObjective(string $type, array $objectiveData): ?array
    {
        $amount = max(1, (int) ($objectiveData["amount"] ?? 1));

        return match ($type) {
            "mine", "place" => self::parseItemObjective("block", $objectiveData, $amount),
            "sell" => self::parseItemObjective("item", $objectiveData, $amount),
            "kill" => self::parseEntityObjective($objectiveData, $amount),
            default => null,
        };
    }

    private static function parseItemObjective(string $key, array $objectiveData, int $amount): ?array
    {
        $raw = $objectiveData[$key] ?? null;

        if ($raw instanceof Item) {
            $identifier = self::normalizeItemIdentifier($raw);
        } else {
            $identifier = self::normalizeIdentifier((string) $raw);
        }

        if ($identifier === "") {
            return null;
        }

        $parsed = StringToItemParser::getInstance()->parse($identifier);
        $label = $parsed?->getVanillaName() ?? ucwords(str_replace("_", " ", $identifier));

        return [
            "amount" => $amount,
            "match" => $identifier,
            "label" => $label,
        ];
    }

    private static function parseEntityObjective(array $objectiveData, int $amount): ?array
    {
        $raw = $objectiveData["entity"] ?? $objectiveData["mob"] ?? null;
        $identifier = self::normalizeEntityIdentifier($raw);
        if ($identifier === "") {
            return null;
        }

        return [
            "amount" => $amount,
            "match" => $identifier,
            "label" => ucwords(str_replace("_", " ", $identifier)),
        ];
    }

    private static function matchesObjective(string $eventType, mixed $subject, array $objective): bool
    {
        $match = (string) ($objective["match"] ?? "");
        if ($match === "") {
            return false;
        }

        return match ($eventType) {
            "mine", "place", "sell" => $subject instanceof Item && self::normalizeItemIdentifier($subject) === $match,
            "kill" => self::normalizeEntityIdentifier($subject) === $match,
            default => false,
        };
    }

    private static function normalizeItemIdentifier(Item $item): string
    {
        return self::normalizeIdentifier($item->getVanillaName());
    }

    private static function normalizeEntityIdentifier(mixed $entity): string
    {
        if (is_string($entity)) {
            $entity = ltrim($entity, "\\");
            if (str_contains($entity, "\\")) {
                $parts = explode("\\", $entity);
                $entity = end($parts);
            }

            return self::normalizeIdentifier($entity);
        }

        if (is_object($entity)) {
            $reflection = new \ReflectionClass($entity);
            return self::normalizeIdentifier($reflection->getShortName());
        }

        return "";
    }

    private static function normalizeIdentifier(string $value): string
    {
        $value = strtolower(trim($value));
        $value = str_replace(["minecraft:", " "], ["", "_"], $value);
        return preg_replace('/[^a-z0-9_]/', '', $value) ?? "";
    }

    private static function generateQuestProgressBar(int $progress, int $goal, string $color = "&a"): string
    {
        $barLength = 10;
        $fillCount = (int) floor(($progress / max(1, $goal)) * $barLength);
        $filled = str_repeat("|", $fillCount);
        $empty = str_repeat("|", $barLength - $fillCount);
        $percent = min(100, (int) round(($progress / max(1, $goal)) * 100));
        return "&r&7[{$color}{$filled}&8{$empty}&7] &f{$percent}%";
    }

    /**
     * @return array<int, int>
     */
    private static function decodeProgressData(CompoundTag $nbt): array
    {
        $progressData = StorageUtils::decodeJsonString($nbt->getString("glacia_quest_progress", "[]"), "quest progress tag") ?? [];

        $normalized = [];
        foreach ($progressData as $index => $value) {
            $normalized[(int) $index] = max(0, (int) $value);
        }

        return $normalized;
    }

    /**
     * @param array<int, int> $progressData
     */
    private static function encodeProgressData(array $progressData, string $context): string
    {
        $normalized = [];
        foreach ($progressData as $index => $value) {
            $normalized[(int) $index] = max(0, (int) $value);
        }

        return StorageUtils::encodeJsonString($normalized, $context) ?? "[]";
    }

    /**
     * @param array<string, mixed> $sellValues
     * @return array<string, float>
     */
    private static function normalizeSellValues(array $sellValues): array
    {
        $normalized = [];

        foreach (["raw", "complete"] as $key) {
            $value = $sellValues[$key] ?? null;
            if (!is_numeric($value)) {
                continue;
            }

            $normalized[$key] = round(max(0.0, (float) $value), 2);
        }

        return $normalized;
    }

    private static function isQuestItemComplete(CompoundTag $nbt): bool
    {
        $completeTag = $nbt->getTag("glacia_quest_complete");
        return ($completeTag instanceof ByteTag || $completeTag instanceof IntTag) && $completeTag->getValue() === 1;
    }

    private static function sendQuestMessage(Player $player, string $message): void
    {
        $player->sendMessage(C::colorize($message));
    }
}
