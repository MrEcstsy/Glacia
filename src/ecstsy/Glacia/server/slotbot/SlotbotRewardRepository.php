<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\slotbot;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\RewardResolver;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\item\Item;

final class SlotbotRewardRepository {

    /**
     * @return Item[]
     */
    public static function getRewards(string $pool = "rewards"): array
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "slotbot.yml");
        $entries = $config?->get($pool, []);

        if (!is_array($entries)) {
            return [];
        }

        $rewards = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $item = self::resolveReward($entry);
            if ($item !== null) {
                $rewards[] = $item;
            }
        }

        return $rewards;
    }

    public static function getRandomReward(string $pool = "rewards"): ?Item
    {
        $rewards = self::getRewards($pool);
        if ($rewards === []) {
            return null;
        }

        return clone $rewards[array_rand($rewards)];
    }

    public static function getSpinReward(): ?Item
    {
        $normalRewards = self::getRewards();
        $jackpotRewards = self::getRewards("jackpot-rewards");

        if ($normalRewards === [] && $jackpotRewards === []) {
            return null;
        }

        if ($normalRewards === []) {
            return clone $jackpotRewards[array_rand($jackpotRewards)];
        }

        if ($jackpotRewards !== [] && mt_rand(1, 10000) <= (int) round(self::getJackpotChance() * 100)) {
            return clone $jackpotRewards[array_rand($jackpotRewards)];
        }

        return clone $normalRewards[array_rand($normalRewards)];
    }

    public static function getDisplayReward(): ?Item
    {
        $normalRewards = self::getRewards();
        $jackpotRewards = self::getRewards("jackpot-rewards");

        if ($normalRewards === [] && $jackpotRewards === []) {
            return null;
        }

        if ($normalRewards === []) {
            return clone $jackpotRewards[array_rand($jackpotRewards)];
        }

        if ($jackpotRewards !== [] && mt_rand(1, 100) <= 20) {
            return clone $jackpotRewards[array_rand($jackpotRewards)];
        }

        return clone $normalRewards[array_rand($normalRewards)];
    }

    public static function getJackpotChance(): float
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "slotbot.yml");
        $chance = (float) ($config?->get("jackpot-chance", 1.0) ?? 1.0);
        return max(0.0, min(100.0, $chance));
    }

    /**
     * @param array<string, mixed> $entry
     */
    private static function resolveReward(array $entry): ?Item
    {
        $normalized = $entry;

        if (isset($entry["item"]) && !isset($entry["material"]) && !isset($entry["id"])) {
            $normalized["material"] = $entry["item"];
        }

        if (isset($entry["amount"]) && !isset($entry["count"])) {
            $material = strtolower((string) ($normalized["material"] ?? $normalized["id"] ?? ""));
            if (!in_array($material, ["xpnote", "xp_note", "money_note", "moneynote"], true)) {
                $normalized["count"] = $entry["amount"];
            }
        }

        $item = RewardResolver::resolveItem($normalized);
        if ($item === null) {
            return null;
        }

        if (isset($entry["nbt"]) && is_array($entry["nbt"])) {
            $tag = $item->getNamedTag();
            foreach ($entry["nbt"] as $key => $value) {
                if (is_int($value)) {
                    $tag->setInt((string) $key, $value);
                } else {
                    $tag->setString((string) $key, (string) $value);
                }
            }
            $item->setNamedTag($tag);
        }

        return $item;
    }
}
