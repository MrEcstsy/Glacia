<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\items\trackers;

use ecstsy\Glacia\server\enchantments\manager\SoulGemSessionManager;
use ecstsy\Glacia\server\items\lore\ItemLoreLayout;
use pocketmine\item\Axe;
use pocketmine\item\Bow;
use pocketmine\item\Hoe;
use pocketmine\item\Item;
use pocketmine\item\Pickaxe;
use pocketmine\item\Shovel;
use pocketmine\item\Sword;
use pocketmine\utils\TextFormat as C;

final class TrackerManager {

    public const BLOCKS = "blocks";
    public const PLAYER_KILLS = "player_kills";
    public const MOB_KILLS = "mob_kills";
    public const XP = "xp";
    public const SOULS = "souls";

    /** @var array<string, array{name: string, color: string, valueColor: string, line: string}> */
    private const TRACKERS = [
        self::BLOCKS => [
            "name" => "Blocks Broken",
            "color" => "&b",
            "valueColor" => "&b",
            "line" => "Blocks Broken"
        ],
        self::PLAYER_KILLS => [
            "name" => "Player Kill Tracker",
            "color" => "&c",
            "valueColor" => "&c",
            "line" => "Player Kills"
        ],
        self::MOB_KILLS => [
            "name" => "Mob Kill Tracker",
            "color" => "&a",
            "valueColor" => "&a",
            "line" => "Mob Kills"
        ],
        self::XP => [
            "name" => "XP Tracker",
            "color" => "&e",
            "valueColor" => "&e",
            "line" => "XP Gained"
        ],
        self::SOULS => [
            "name" => "Soul Tracker",
            "color" => "&4",
            "valueColor" => "&c",
            "line" => "Souls Collected"
        ],
    ];

    /** @var string[] */
    private const ORDER = [
        self::BLOCKS,
        self::PLAYER_KILLS,
        self::MOB_KILLS,
        self::XP,
        self::SOULS,
    ];

    public static function getItemIdentifier(string $trackerType): string
    {
        return match ($trackerType) {
            self::BLOCKS => "block_tracker",
            self::PLAYER_KILLS => "kill_tracker",
            self::MOB_KILLS => "mob_tracker",
            self::XP => "xp_tracker",
            self::SOULS => "soul_tracker",
            default => throw new \InvalidArgumentException("Unknown tracker type {$trackerType}")
        };
    }

    public static function getLabel(string $trackerType): string
    {
        return self::TRACKERS[$trackerType]["name"] ?? "Tracker";
    }

    public static function canApplyTo(Item $item, string $trackerType): bool
    {
        return match ($trackerType) {
            self::BLOCKS => $item instanceof Pickaxe || $item instanceof Shovel || $item instanceof Axe || $item instanceof Hoe,
            self::PLAYER_KILLS, self::MOB_KILLS, self::XP, self::SOULS => $item instanceof Sword || $item instanceof Axe || $item instanceof Bow,
            default => false
        };
    }

    public static function hasTracker(Item $item, string $trackerType): bool
    {
        $glacia = $item->getNamedTag()->getCompoundTag("Glacia");
        return $glacia !== null && $glacia->getTag(self::getCountKey($trackerType)) !== null;
    }

    public static function applyTracker(Item $item, string $trackerType): Item
    {
        $clone = clone $item;
        $root = $clone->getNamedTag();
        $glacia = $root->getCompoundTag("Glacia") ?? new \pocketmine\nbt\tag\CompoundTag();
        $glacia->setInt(self::getCountKey($trackerType), 0);
        $root->setTag("Glacia", $glacia);
        $clone->setNamedTag($root);

        return self::refreshLore($clone);
    }

    public static function addProgress(Item $item, string $trackerType, int $amount = 1): Item
    {
        if ($amount <= 0 || !self::hasTracker($item, $trackerType)) {
            return $item;
        }

        $clone = clone $item;
        $root = $clone->getNamedTag();
        $glacia = $root->getCompoundTag("Glacia") ?? new \pocketmine\nbt\tag\CompoundTag();
        $current = $glacia->getInt(self::getCountKey($trackerType), 0);
        $glacia->setInt(self::getCountKey($trackerType), max(0, $current + $amount));
        $root->setTag("Glacia", $glacia);
        $clone->setNamedTag($root);

        return self::refreshLore($clone);
    }

    public static function getProgress(Item $item, string $trackerType): int
    {
        if (!self::hasTracker($item, $trackerType)) {
            return 0;
        }

        $glacia = $item->getNamedTag()->getCompoundTag("Glacia");
        return max(0, $glacia?->getInt(self::getCountKey($trackerType), 0) ?? 0);
    }

    public static function setProgress(Item $item, string $trackerType, int $amount): Item
    {
        if (!self::hasTracker($item, $trackerType)) {
            return $item;
        }

        $clone = clone $item;
        $root = $clone->getNamedTag();
        $glacia = $root->getCompoundTag("Glacia") ?? new \pocketmine\nbt\tag\CompoundTag();
        $glacia->setInt(self::getCountKey($trackerType), max(0, $amount));
        $root->setTag("Glacia", $glacia);
        $clone->setNamedTag($root);

        return self::refreshLore($clone);
    }

    public static function refreshLore(Item $item): Item
    {
        $clone = clone $item;
        $baseLore = [];

        foreach ($clone->getLore() as $line) {
            if (self::isTrackerLoreLine($line)) {
                continue;
            }

            $baseLore[] = $line;
        }

        $trackerLore = [];
        foreach (self::ORDER as $trackerType) {
            if (!self::hasTracker($clone, $trackerType)) {
                continue;
            }

            $trackerLore[] = self::formatTrackerLine($clone, $trackerType);
        }

        if ($trackerLore !== []) {
            if ($baseLore !== [] && end($baseLore) !== "") {
                $baseLore[] = "";
            }

            $baseLore = array_merge($baseLore, $trackerLore);
        }

        $clone->setLore($baseLore);
        return ItemLoreLayout::normalizeForCombatGear($clone);
    }

    private static function isTrackerLoreLine(string $line): bool
    {
        $clean = strtolower(trim(C::clean($line)));
        $clean = ltrim($clean, " \t\n\r\0\x0B*>-.:");

        foreach (self::TRACKERS as $tracker) {
            $label = strtolower($tracker["line"]) . ":";
            if (str_contains($clean, $label)) {
                return true;
            }
        }

        return false;
    }

    private static function formatTrackerLine(Item $item, string $trackerType): string
    {
        $config = self::TRACKERS[$trackerType];
        $glacia = $item->getNamedTag()->getCompoundTag("Glacia");
        $amount = $glacia?->getInt(self::getCountKey($trackerType), 0) ?? 0;

        if ($trackerType === self::SOULS) {
            return C::colorize(str_replace("{souls}", SoulGemSessionManager::formatSoulCount($amount), SoulGemSessionManager::getTrackerLoreTemplate()));
        }

        return C::colorize("&r&8> &r&7{$config["line"]}: {$config["valueColor"]}" . number_format($amount));
    }

    private static function getCountKey(string $trackerType): string
    {
        return match ($trackerType) {
            self::BLOCKS => "tracker_blocks",
            self::PLAYER_KILLS => "tracker_player_kills",
            self::MOB_KILLS => "tracker_mob_kills",
            self::XP => "tracker_xp",
            self::SOULS => "tracker_souls",
            default => throw new \InvalidArgumentException("Unknown tracker type {$trackerType}")
        };
    }
}
