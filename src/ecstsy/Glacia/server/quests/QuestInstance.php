<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\quests;

use ecstsy\Glacia\entity\mobs\Blaze;
use ecstsy\Glacia\entity\mobs\Skeleton;
use ecstsy\Glacia\entity\mobs\Zombie;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\items\GlaciaItemFactory;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\StringToItemParser;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class QuestInstance {

    private static array $tiers = [];

    public static function registerDefaults(): void {
        self::$tiers = [
            "dormant" => [
                "display" => "Dormant",
                "material" => "amethyst_shard",
                "quests" => [
                    [
                        "type" => "mine",
                        "name" => "Coal Miner I",
                        "objectives" => [["block" => VanillaBlocks::COAL_ORE()->asItem(), "amount" => 20]]
                    ],
                    [
                        "type" => "mine",
                        "name" => "Iron Miner I",
                        "objectives" => [["block" => VanillaBlocks::IRON()->asItem(), "amount" => 20]]
                    ],
                    [
                        "type" => "place",
                        "name" => "Builder I",
                        "objectives" => [["block" => VanillaBlocks::STONE()->asItem(), "amount" => 25]]
                    ],
                    [
                        "type" => "kill",
                        "name" => "Zombie Slayer I",
                        "objectives" => [["entity" => Zombie::class, "amount" => 10]]
                    ],
                    [
                        "type" => "sell",
                        "name" => "Farmers Market I",
                        "objectives" => [["item" => VanillaItems::WHEAT(), "amount" => 15]]
                    ],
                ],
                "rewards" => [
                    VanillaItems::IRON_INGOT()->setCount(8),
                    VanillaItems::AMETHYST_SHARD()->setCount(4),
                    GlaciaItemFactory::bankNote(null, 5000),
                    GlaciaItemFactory::xpNote(null, 2500)
                ],
            ],

            "awakened" => [
                "display" => "Awakened",
                "material" => "echo_shard",
                "quests" => [
                    [
                        "type" => "mine",
                        "name" => "Iron Miner II",
                        "objectives" => [["block" => VanillaBlocks::IRON()->asItem(), "amount" => 50]]
                    ],
                    [
                        "type" => "mine",
                        "name" => "Gold Miner I",
                        "objectives" => [["block" => VanillaBlocks::GOLD_ORE()->asItem(), "amount" => 20]]
                    ],
                    [
                        "type" => "place",
                        "name" => "Builder II",
                        "objectives" => [["block" => VanillaBlocks::OAK_PLANKS()->asItem(), "amount" => 50]]
                    ],
                    [
                        "type" => "kill",
                        "name" => "Mob Slayer II",
                        "objectives" => [["entity" => Zombie::class, "amount" => 25]]
                    ],
                    [
                        "type" => "sell",
                        "name" => "Farmers Market II",
                        "objectives" => [
                            ["item" => VanillaItems::WHEAT(), "amount" => 25],
                            ["item" => VanillaItems::CARROT(), "amount" => 32]
                        ]
                    ],
                ],
                "rewards" => [
                    VanillaItems::GOLD_INGOT()->setCount(6),
                    VanillaItems::EMERALD()->setCount(2),
                    GlaciaItemFactory::bankNote(null, 15000),
                    GlaciaItemFactory::xpNote(null, 7500)
                ],
            ],

            "ascendent" => [
                "display" => "Ascendent",
                "material" => "nether_star",
                "quests" => [
                    [
                        "type" => "mine",
                        "name" => "Diamond Hunter",
                        "objectives" => [
                            ["block" => VanillaBlocks::DIAMOND_ORE()->asItem(), "amount" => 30],
                            ["block" => VanillaBlocks::GOLD_ORE()->asItem(), "amount" => 50]
                        ]
                    ],
                    [
                        "type" => "place",
                        "name" => "Fortress Builder",
                        "objectives" => [
                            ["block" => VanillaBlocks::OBSIDIAN()->asItem(), "amount" => 50],
                            ["block" => VanillaBlocks::STONE_BRICKS()->asItem(), "amount" => 50]
                        ]
                    ],
                    [
                        "type" => "kill",
                        "name" => "Elite Slayer",
                        "objectives" => [
                            ["entity" => Zombie::class, "amount" => 50],
                            ["entity" => Blaze::class, "amount" => 20]
                        ]
                    ],
                    [
                        "type" => "sell",
                        "name" => "Market Expert",
                        "objectives" => [
                            ["item" => VanillaItems::CARROT(), "amount" => 50],
                            ["item" => VanillaItems::POTATO(), "amount" => 40]
                        ]
                    ],
                ],
                "rewards" => [
                    VanillaItems::DIAMOND()->setCount(5),
                    VanillaItems::GOLD_INGOT()->setCount(12),
                    GlaciaItemFactory::bankNote(null, 50000),
                    GlaciaItemFactory::xpNote(null, 20000),
                ],
            ],

            "eternal" => [
                "display" => "Eternal",
                "material" => "dragon_egg",
                "quests" => [
                    [
                        "type" => "mine",
                        "name" => "Master Miner",
                        "objectives" => [
                            ["block" => VanillaBlocks::DIAMOND_ORE()->asItem(), "amount" => 100],
                            ["block" => VanillaBlocks::EMERALD_ORE()->asItem(), "amount" => 50],
                            ["block" => VanillaBlocks::NETHER_QUARTZ_ORE()->asItem(), "amount" => 75]
                        ]
                    ],
                    [
                        "type" => "place",
                        "name" => "Grand Architect",
                        "objectives" => [
                            ["block" => VanillaBlocks::OBSIDIAN()->asItem(), "amount" => 100],
                            ["block" => VanillaBlocks::NETHER_BRICKS()->asItem(), "amount" => 75],
                            ["block" => VanillaBlocks::STONE_BRICKS()->asItem(), "amount" => 100]
                        ]
                    ],
                    [
                        "type" => "kill",
                        "name" => "Ultimate Slayer",
                        "objectives" => [
                            ["entity" => Zombie::class, "amount" => 100],
                            ["entity" => Blaze::class, "amount" => 50],
                            ["entity" => Skeleton::class, "amount" => 50]
                        ]
                    ],
                    [
                        "type" => "sell",
                        "name" => "Tycoon",
                        "objectives" => [
                            ["item" => VanillaItems::CARROT(), "amount" => 100],
                            ["item" => VanillaItems::POTATO(), "amount" => 100],
                            ["item" => VanillaItems::WHEAT(), "amount" => 150]
                        ]
                    ],
                ],
                "rewards" => [
                    VanillaItems::DIAMOND()->setCount(20),
                    VanillaItems::EMERALD()->setCount(10),
                    GlaciaItemFactory::bankNote(null, 250000),
                    GlaciaItemFactory::xpNote(null, 100000),
                ],
            ],
        ];
    }

    public static function giveQuestItem(Player $player, string $tier, int $questIndex = 0): void {
        $data = self::$tiers[$tier] ?? null;
        if ($data === null) {
            $player->sendMessage(C::colorize("&cInvalid quest tier."));
            return;
        }

        $quest = $data["quests"][$questIndex] ?? null;
        if ($quest === null) {
            $player->sendMessage(C::colorize("&cInvalid quest selection."));
            return;
        }

        $material = StringToItemParser::getInstance()->parse($data["material"]) ?? VanillaItems::AMETHYST_SHARD();

        $tierColor = match($tier) {
            "dormant" => "&b",
            "awakened" => "&5",
            "ascendent" => "&e",
            "eternal" => "&c",
            default => "&7",
        };

        $symbol = match($tier) {
            "dormant" => "❄",
            "awakened" => "✦",
            "ascendent" => "✪",
            "eternal" => "☯",
            default => "•",
        };

        $customName = "&r&7{$symbol} {$tierColor}{$data['display']} Sigil &7{$symbol}";
        $item = $material->setCustomName(C::colorize($customName));

        $nbt = $item->getNamedTag() ?? new CompoundTag();
        $nbt->setString("glacia_quest_tier", $tier);
        $nbt->setInt("glacia_quest_index", $questIndex);
        $nbt->setString("glacia_quest_type", $quest["type"]);
        $nbt->setString("glacia_quest_progress", json_encode(array_fill(0, count($quest['objectives']), 0)));
        $item->setNamedTag($nbt);

        self::updateQuestLore($item);
        $player->getInventory()->addItem($item);
        $player->sendMessage(C::colorize("&aYou received the quest: &d{$quest['name']}&a!"));
    }

    public static function updateQuestLore(Item $item): void {
        $nbt = $item->getNamedTag();
        if (!$nbt) return;

        $tier = $nbt->getString("glacia_quest_tier", "");
        $data = self::$tiers[$tier] ?? null;
        if (!$data) return;

        $questIndex = $nbt->getInt("glacia_quest_index", 0);
        $quest = $data["quests"][$questIndex] ?? null;
        if (!$quest) return;

        $progressData = json_decode($nbt->getString("glacia_quest_progress", "[]"), true);
        if (!is_array($progressData)) $progressData = [];

        $lore = [];
        $lore[] = C::colorize("&7A mystical sigil that records your deeds.");
        $lore[] = C::colorize("&r&7Tier: &f{$data['display']}");
        $lore[] = "";
        $lore[] = C::colorize("&r&7Quest Name: &f{$quest['name']}");
        $lore[] = C::colorize("&r&7Objectives:");

        $totalProgress = 0;
        $totalGoal = 0;
        $objectiveStrings = [];

        foreach ($quest['objectives'] as $i => $obj) {
            $completed = $progressData[$i] ?? 0;
            $goal = $obj['amount'] ?? 1;
            $totalProgress += $completed;
            $totalGoal += $goal;

            $name = "Unknown";
            if (isset($obj['item'])) $name = $obj['item']->getVanillaName();
            if (isset($obj['block'])) $name = $obj['block']->getVanillaName();
            if (isset($obj['entity'])) $name = (new \ReflectionClass($obj['entity']))->getShortName();

            $color = $completed >= $goal ? "&5" : "&f";
            $objectiveStrings[] = "{$color}x{$goal} {$name}";
        }

        $action = match($quest['type']) {
            "sell" => "Sell",
            "mine" => "Mine",
            "place" => "Place",
            "kill" => "Slay",
            default => "Complete",
        };

        $lore[] = C::colorize("&r&7{$action}: " . implode("&7, ", $objectiveStrings));

        $tierColor = match($tier) {
            "dormant" => "&b",
            "awakened" => "&5",
            "ascendent" => "&e",
            "eternal" => "&c",
            default => "&7",
        };
        $lore[] = C::colorize(self::generateQuestProgressBar($totalProgress, $totalGoal, $tierColor));

        if ($totalProgress >= $totalGoal) {
            $nbt->setByte("glacia_quest_complete", 1);
            $lore[] = C::colorize("&a&l✔ Quest Complete!");
        }

        $item->setNamedTag($nbt);
        $item->setLore($lore);
    }

    private static function generateQuestProgressBar(int $progress, int $goal, string $color = "&a"): string {
        $barLength = 10;
        $fillCount = (int)floor(($progress / max(1, $goal)) * $barLength);
        $filled = str_repeat("█", $fillCount);
        $empty = str_repeat("░", $barLength - $fillCount);
        $percent = min(100, (int)round(($progress / max(1, $goal)) * 100));
        return C::colorize("&r&7[{$color}{$filled}&8{$empty}&7] &f{$percent}%");
    }

    public static function addProgress(Player $player, string $eventType, mixed $subject = null, int $amount = 1): void {
        $inventory = $player->getInventory();

        foreach ($inventory->getContents() as $slot => $item) {
            $nbt = $item->getNamedTag();
            if (!$nbt || !$nbt->getTag("glacia_quest_type")) continue;
            if ($nbt->getString("glacia_quest_type") !== $eventType) continue;

            $tier = $nbt->getString("glacia_quest_tier", "");
            $index = $nbt->getInt("glacia_quest_index", 0);
            $quest = self::$tiers[$tier]['quests'][$index] ?? null;
            if (!$quest) continue;

            $progressData = json_decode($nbt->getString("glacia_quest_progress", "[]"), true);
            if (!is_array($progressData)) $progressData = [];
            $updated = false;

            foreach ($quest['objectives'] as $i => $obj) {
                $matches = false;

                switch ($eventType) {
                    case "sell":
                        if (!isset($obj['item']) || !($subject instanceof Item)) break;
                        $matches = $obj['item']->getVanillaName() === $subject->getVanillaName();
                        break;
                    case "mine":
                    case "place":
                        if (!isset($obj['block']) || !($subject instanceof Item)) break;
                        $matches = $obj['block']->getVanillaName() === $subject->getVanillaName();
                        break;
                    case "kill":
                        $expectedClass = $obj['entity'] ?? $obj['mob'] ?? null;
                        $subjectClass = is_string($subject) ? $subject : (is_object($subject) ? get_class($subject) : '');
                        $matches = is_string($expectedClass) && ($subjectClass === $expectedClass || is_subclass_of($subjectClass, $expectedClass));
                        break;
                }

                if ($matches) {
                    $progressData[$i] = min($obj['amount'], ($progressData[$i] ?? 0) + $amount);
                    $updated = true;
                }
            }

            if ($updated) {
                $nbt->setString("glacia_quest_progress", json_encode($progressData));
                self::updateQuestLore($item);
                $inventory->setItem($slot, $item);
            }
        }
    }

    public static function onSell(Player $player, Item $soldItem, int $count): void {
        $inventory = $player->getInventory();

        foreach ($inventory->getContents() as $slot => $item) {
            $nbt = $item->getNamedTag();
            if (!$nbt || !$nbt->getTag("glacia_quest_type")) continue;
            if ($nbt->getString("glacia_quest_type") !== "sell") continue;

            $tier = $nbt->getString("glacia_quest_tier", "");
            $questIndex = $nbt->getInt("glacia_quest_index", 0);
            $quest = self::$tiers[$tier]['quests'][$questIndex] ?? null;
            if (!$quest) continue;

            $progressData = json_decode($nbt->getString("glacia_quest_progress", "[]"), true);
            if (!is_array($progressData)) $progressData = [];
            $updated = false;

            foreach ($quest['objectives'] as $i => $obj) {
                if (!isset($obj['item'])) continue;

                if ($soldItem->getVanillaName() === $obj['item']->getVanillaName()) {
                    $progressData[$i] = min($obj['amount'], ($progressData[$i] ?? 0) + $count);
                    $updated = true;
                }
            }

            if ($updated) {
                $nbt->setString("glacia_quest_progress", json_encode($progressData));
                self::updateQuestLore($item);
                $inventory->setItem($slot, $item);
                $player->sendMessage("§aYour quest progress has been updated!");
            }
        }
    }

    public static function getTierData(string $tier): ?array {
        return self::$tiers[$tier] ?? null;
    }
}