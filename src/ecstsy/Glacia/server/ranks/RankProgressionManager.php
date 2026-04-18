<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\ranks;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\items\GlaciaItemFactory;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class RankProgressionManager {

    /** @var array<string, array{display: string, color: string, duration: int, money: int, extras: array<string, int>}> */
    private const TIERS = [
        "cryoshield" => [
            "display" => "Cryoshield",
            "color" => "&3",
            "duration" => 30,
            "money" => 11250,
            "extras" => []
        ],
        "winterborn" => [
            "display" => "Winterborn",
            "color" => "&b",
            "duration" => 45,
            "money" => 25000,
            "extras" => ["simple_book" => 1]
        ],
        "glacian" => [
            "display" => "Glacian",
            "color" => "&a",
            "duration" => 60,
            "money" => 50000,
            "extras" => ["unique_book" => 1]
        ],
        "iceheart" => [
            "display" => "Iceheart",
            "color" => "&9",
            "duration" => 90,
            "money" => 100000,
            "extras" => ["elite_book" => 1, "whitescroll" => 1]
        ],
        "frostlord" => [
            "display" => "Frostlord",
            "color" => "&d",
            "duration" => 120,
            "money" => 250000,
            "extras" => ["legendary_book" => 1, "renametag" => 1]
        ],
    ];

    public static function getTiers(): array
    {
        return self::TIERS;
    }

    public static function getTier(string $rank): ?array
    {
        return self::TIERS[strtolower($rank)] ?? null;
    }

    public static function isRankVoucher(string $identifier): bool
    {
        return str_ends_with($identifier, "_voucher") && self::getTier(str_replace("_voucher", "", $identifier)) !== null;
    }

    public static function isRankQuest(string $identifier): bool
    {
        return str_ends_with($identifier, "_quest") && self::getTier(str_replace("_quest", "", $identifier)) !== null;
    }

    public static function extractRankFromIdentifier(string $identifier): ?string
    {
        foreach (["_voucher", "_quest"] as $suffix) {
            if (str_ends_with($identifier, $suffix)) {
                $rank = str_replace($suffix, "", $identifier);
                return self::getTier($rank) !== null ? $rank : null;
            }
        }

        return null;
    }

    public static function createVoucher(string $rank): Item
    {
        return GlaciaItemFactory::create(strtolower($rank) . "_voucher");
    }

    public static function createQuest(string $rank): Item
    {
        $key = strtolower($rank);
        $item = GlaciaItemFactory::create($key . "_quest");
        return self::updateQuestItem($item, $key, self::TIERS[$key]["duration"], false);
    }

    public static function updateQuestItem(Item $item, string $rank, int $remainingSeconds, bool $complete): Item
    {
        $tier = self::getTier($rank);
        if ($tier === null) {
            return $item;
        }

        $timeText = $complete ? "0s" : GeneralUtils::translateTime($remainingSeconds);
        $item->setCustomName(C::colorize("&r&l" . $tier["color"] . $tier["display"] . " Rank Quest&r&f: &f&l<&r&f" . $timeText . "&l&f>"));
        $item->setLore(self::getQuestLore($rank, $remainingSeconds, $complete));

        $nbt = $item->getNamedTag();
        $glacia = $nbt->getCompoundTag("Glacia");
        if ($glacia !== null) {
            $glacia->setInt("remaining", max(0, $remainingSeconds));
            $glacia->setByte("complete", $complete ? 1 : 0);
            $nbt->setTag("Glacia", $glacia);
            $item->setNamedTag($nbt);
        }

        return $item;
    }

    public static function getQuestLore(string $rank, int $remainingSeconds, bool $complete): array
    {
        $tier = self::getTier($rank);
        if ($tier === null) {
            return [];
        }

        $money = number_format($tier["money"]);
        $time = GeneralUtils::translateTime(max(0, $remainingSeconds));

        $lore = [
            C::colorize("&r&7Right-Click this Rank Quest while in"),
            C::colorize("&r&7the &cWarzone &r&7to track your survival challenge."),
            "",
            C::colorize("&r&7Survive in the Warzone until the timer"),
            C::colorize("&r&7runs out to unlock your promotion package."),
            "",
            C::colorize("&r&fReward Preview:"),
            C::colorize("&r&f * " . $tier["color"] . $tier["display"] . " Rank Voucher"),
            C::colorize("&r&f * &a$" . $money . " Bank Note"),
        ];

        foreach ($tier["extras"] as $extra => $count) {
            $lore[] = C::colorize("&r&f * &b" . $count . "x " . self::getExtraDisplayName($extra));
        }

        $lore[] = "";
        if ($complete) {
            $lore[] = C::colorize("&r&l&aCOMPLETE");
            $lore[] = C::colorize("&r&7Right-Click to claim your rewards.");
        } else {
            $lore[] = C::colorize("&r&7Time Remaining: " . $tier["color"] . $time);
            $lore[] = C::colorize("&r&7Only counts while you are in the &cWarzone&r&7.");
        }

        return $lore;
    }

    public static function tickPlayer(Player $player): void
    {
        $warzone = Loader::getRegionManager()->getRegionByName("Warzone");
        $currentRegion = Loader::getRegionManager()->getRegionAt($player->getPosition());
        if ($warzone === null || $currentRegion !== $warzone) {
            return;
        }

        $inventory = $player->getInventory();
        foreach ($inventory->getContents() as $slot => $item) {
            $identifier = GlaciaItemFactory::getGlaciaIdentifier($item);
            if (!self::isRankQuest($identifier)) {
                continue;
            }

            $rank = self::extractRankFromIdentifier($identifier);
            if ($rank === null) {
                continue;
            }

            $glacia = $item->getNamedTag()->getCompoundTag("Glacia");
            if ($glacia === null) {
                continue;
            }

            $remaining = $glacia->getInt("remaining", self::TIERS[$rank]["duration"]);
            $complete = self::readCompleteFlag($glacia);
            if ($complete || $remaining <= 0) {
                continue;
            }

            $remaining--;
            $complete = $remaining <= 0;
            $inventory->setItem($slot, self::updateQuestItem($item, $rank, $remaining, $complete));

            if ($complete) {
                $player->sendMessage(C::colorize("&r&l&a[!] &r&aYour " . self::TIERS[$rank]["display"] . " Rank Quest is complete."));
                PlayerUtils::playSound($player, "random.levelup");
            }
        }
    }

    public static function canUseVoucher(string $currentRank, string $targetRank): bool
    {
        return !RankManager::getInstance()->isSameOrHigherThan($currentRank, $targetRank);
    }

    public static function claimQuestRewards(Player $player, string $rank): void
    {
        $tier = self::getTier($rank);
        if ($tier === null) {
            return;
        }

        $rewards = [
            self::createVoucher($rank),
            GlaciaItemFactory::bankNote($player, $tier["money"])
        ];

        foreach ($tier["extras"] as $extra => $count) {
            $reward = match ($extra) {
                "simple_book" => GlaciaItemFactory::randomEnchantBook("simple"),
                "unique_book" => GlaciaItemFactory::randomEnchantBook("unique"),
                "elite_book" => GlaciaItemFactory::randomEnchantBook("elite"),
                "legendary_book" => GlaciaItemFactory::randomEnchantBook("legendary"),
                "whitescroll" => GlaciaItemFactory::whitescroll(),
                "renametag" => GlaciaItemFactory::renametag(),
                default => null
            };

            if ($reward === null) {
                continue;
            }

            $reward->setCount($count);
            $rewards[] = $reward;
        }

        foreach ($rewards as $reward) {
            $leftovers = $player->getInventory()->addItem($reward);
            foreach ($leftovers as $leftover) {
                $player->getWorld()->dropItem($player->getPosition(), $leftover);
            }
        }
    }

    private static function getExtraDisplayName(string $extra): string
    {
        return match ($extra) {
            "simple_book" => "Simple Random Book",
            "unique_book" => "Unique Random Book",
            "elite_book" => "Elite Random Book",
            "legendary_book" => "Legendary Random Book",
            "whitescroll" => "White Scroll",
            "renametag" => "Rename Tag",
            default => ucwords(str_replace("_", " ", $extra))
        };
    }

    private static function readCompleteFlag(\pocketmine\nbt\tag\CompoundTag $glacia): bool
    {
        $tag = $glacia->getTag("complete");
        if ($tag instanceof ByteTag || $tag instanceof IntTag) {
            return $tag->getValue() === 1;
        }

        return false;
    }
}
