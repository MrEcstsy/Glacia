<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\quests;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\items\GlaciaItemFactory;
use ecstsy\Glacia\utils\RewardResolver;
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
    private const DEFAULT_TIERS = [
        "dormant" => [
            "display" => "Dormant",
            "material" => "amethyst_shard",
            "color" => "&b",
            "symbol" => "*",
            "quests" => [
                [
                    "type" => "mine",
                    "name" => "Coal Miner I",
                    "objectives" => [
                        ["block" => "coal_ore", "amount" => 20]
                    ]
                ],
                [
                    "type" => "mine",
                    "name" => "Iron Miner I",
                    "objectives" => [
                        ["block" => "iron_ore", "amount" => 20]
                    ]
                ],
                [
                    "type" => "place",
                    "name" => "Builder I",
                    "objectives" => [
                        ["block" => "stone", "amount" => 25]
                    ]
                ],
                [
                    "type" => "kill",
                    "name" => "Zombie Slayer I",
                    "objectives" => [
                        ["entity" => "zombie", "amount" => 10]
                    ]
                ],
                [
                    "type" => "sell",
                    "name" => "Farmers Market I",
                    "objectives" => [
                        ["item" => "wheat", "amount" => 15]
                    ]
                ],
            ],
            "rewards" => [
                ["material" => "iron_ingot", "count" => 8],
                ["material" => "amethyst_shard", "count" => 4],
                ["id" => "money_note", "amount" => 5000],
                ["id" => "xpnote", "amount" => 2500],
            ],
        ],
        "awakened" => [
            "display" => "Awakened",
            "material" => "echo_shard",
            "color" => "&5",
            "symbol" => "*",
            "quests" => [
                [
                    "type" => "mine",
                    "name" => "Iron Miner II",
                    "objectives" => [
                        ["block" => "iron_ore", "amount" => 50]
                    ]
                ],
                [
                    "type" => "mine",
                    "name" => "Gold Miner I",
                    "objectives" => [
                        ["block" => "gold_ore", "amount" => 20]
                    ]
                ],
                [
                    "type" => "place",
                    "name" => "Builder II",
                    "objectives" => [
                        ["block" => "oak_planks", "amount" => 50]
                    ]
                ],
                [
                    "type" => "kill",
                    "name" => "Mob Slayer II",
                    "objectives" => [
                        ["entity" => "zombie", "amount" => 25]
                    ]
                ],
                [
                    "type" => "sell",
                    "name" => "Farmers Market II",
                    "objectives" => [
                        ["item" => "wheat", "amount" => 25],
                        ["item" => "carrot", "amount" => 32]
                    ]
                ],
            ],
            "rewards" => [
                ["material" => "gold_ingot", "count" => 6],
                ["material" => "emerald", "count" => 2],
                ["id" => "money_note", "amount" => 15000],
                ["id" => "xpnote", "amount" => 7500],
            ],
        ],
        "ascendent" => [
            "display" => "Ascendent",
            "material" => "nether_star",
            "color" => "&e",
            "symbol" => "*",
            "quests" => [
                [
                    "type" => "mine",
                    "name" => "Diamond Hunter",
                    "objectives" => [
                        ["block" => "diamond_ore", "amount" => 30],
                        ["block" => "gold_ore", "amount" => 50]
                    ]
                ],
                [
                    "type" => "place",
                    "name" => "Fortress Builder",
                    "objectives" => [
                        ["block" => "obsidian", "amount" => 50],
                        ["block" => "stone_bricks", "amount" => 50]
                    ]
                ],
                [
                    "type" => "kill",
                    "name" => "Elite Slayer",
                    "objectives" => [
                        ["entity" => "zombie", "amount" => 50],
                        ["entity" => "blaze", "amount" => 20]
                    ]
                ],
                [
                    "type" => "sell",
                    "name" => "Market Expert",
                    "objectives" => [
                        ["item" => "carrot", "amount" => 50],
                        ["item" => "potato", "amount" => 40]
                    ]
                ],
            ],
            "rewards" => [
                ["material" => "diamond", "count" => 5],
                ["material" => "gold_ingot", "count" => 12],
                ["id" => "money_note", "amount" => 50000],
                ["id" => "xpnote", "amount" => 20000],
            ],
        ],
        "eternal" => [
            "display" => "Eternal",
            "material" => "dragon_egg",
            "color" => "&c",
            "symbol" => "*",
            "quests" => [
                [
                    "type" => "mine",
                    "name" => "Master Miner",
                    "objectives" => [
                        ["block" => "diamond_ore", "amount" => 100],
                        ["block" => "emerald_ore", "amount" => 50],
                        ["block" => "nether_quartz_ore", "amount" => 75]
                    ]
                ],
                [
                    "type" => "place",
                    "name" => "Grand Architect",
                    "objectives" => [
                        ["block" => "obsidian", "amount" => 100],
                        ["block" => "nether_bricks", "amount" => 75],
                        ["block" => "stone_bricks", "amount" => 100]
                    ]
                ],
                [
                    "type" => "kill",
                    "name" => "Ultimate Slayer",
                    "objectives" => [
                        ["entity" => "zombie", "amount" => 100],
                        ["entity" => "blaze", "amount" => 50],
                        ["entity" => "skeleton", "amount" => 50]
                    ]
                ],
                [
                    "type" => "sell",
                    "name" => "Tycoon",
                    "objectives" => [
                        ["item" => "carrot", "amount" => 100],
                        ["item" => "potato", "amount" => 100],
                        ["item" => "wheat", "amount" => 150]
                    ]
                ],
            ],
            "rewards" => [
                ["material" => "diamond", "count" => 20],
                ["material" => "emerald", "count" => 10],
                ["id" => "money_note", "amount" => 250000],
                ["id" => "xpnote", "amount" => 100000],
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

        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "quests.yml");
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
        $nbt->setString("glacia_quest_tier", $tier);
        $nbt->setInt("glacia_quest_index", $questIndex);
        $nbt->setString("glacia_quest_type", (string) $quest["type"]);
        $nbt->setString("glacia_quest_progress", json_encode(array_fill(0, count($quest["objectives"]), 0)));
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

            $nbt->setString("glacia_quest_progress", json_encode($progressData));
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

    public static function rollReward(string $tier, ?Player $player = null): ?Item
    {
        $tierData = self::getTierData($tier);
        if ($tierData === null) {
            return null;
        }

        $rewards = $tierData["rewards"] ?? [];
        if (!is_array($rewards) || $rewards === []) {
            return null;
        }

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
            "quests" => $quests,
            "rewards" => $rewards,
        ];
    }

    private static function parseQuest(array $questData): ?array
    {
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
        ];
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
        $progressData = json_decode($nbt->getString("glacia_quest_progress", "[]"), true);
        if (!is_array($progressData)) {
            return [];
        }

        $normalized = [];
        foreach ($progressData as $index => $value) {
            $normalized[(int) $index] = max(0, (int) $value);
        }

        return $normalized;
    }

    private static function sendQuestMessage(Player $player, string $message): void
    {
        $player->sendMessage(C::colorize($message));
    }
}
