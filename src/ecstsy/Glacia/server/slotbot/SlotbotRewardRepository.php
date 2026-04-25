<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\slotbot;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\RewardResolver;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\item\Item;
use pocketmine\player\Player;

final class SlotbotRewardRepository {

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function getEntries(string $pool = "rewards") : array
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "slotbot.yml");
        $entries = $config?->get($pool, []);

        if(!is_array($entries)){
            return [];
        }

        return array_values(array_filter($entries, static fn(mixed $entry) : bool => is_array($entry)));
    }

    /**
     * @return Item[]
     */
    public static function getRewards(string $pool = "rewards"): array
    {
        $rewards = [];
        foreach (self::getEntries($pool) as $entry) {
            $item = self::resolveReward($entry);
            if ($item !== null) {
                $rewards[] = $item;
            }
        }

        return $rewards;
    }

    public static function getRandomReward(string $pool = "rewards", ?Player $player = null): ?Item
    {
        $entry = RewardResolver::rollEntry(self::getEntries($pool));
        if ($entry === null) {
            return null;
        }

        $item = self::resolveReward($entry, $player);
        return $item !== null ? clone $item : null;
    }

    public static function getSpinReward(?Player $player = null): ?Item
    {
        $normalEntries = self::getEntries();
        $jackpotEntries = self::getEntries("jackpot-rewards");

        if ($normalEntries === [] && $jackpotEntries === []) {
            return null;
        }

        if ($normalEntries === []) {
            return self::getJackpotReward($player);
        }

        if ($jackpotEntries !== [] && mt_rand(1, 10000) <= (int) round(self::getJackpotChance() * 100)) {
            return self::getJackpotReward($player);
        }

        return self::getRandomReward("rewards", $player);
    }

    public static function getJackpotReward(?Player $player = null): ?Item
    {
        $entry = RewardResolver::rollEntry(self::getEntries("jackpot-rewards"));
        if ($entry === null) {
            return null;
        }

        $item = self::resolveReward($entry, $player);
        return $item !== null ? clone $item : null;
    }

    public static function getDisplayReward(): ?Item
    {
        $normalEntries = self::getEntries();
        $jackpotEntries = self::getEntries("jackpot-rewards");

        if ($normalEntries === [] && $jackpotEntries === []) {
            return null;
        }

        if ($normalEntries === []) {
            return self::getJackpotDisplayReward();
        }

        if ($jackpotEntries !== [] && mt_rand(1, 100) <= 20) {
            return self::getJackpotDisplayReward();
        }

        return self::getRandomReward();
    }

    public static function getJackpotDisplayReward(): ?Item
    {
        return self::getJackpotReward();
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
    private static function resolveReward(array $entry, ?Player $player = null): ?Item
    {
        $item = RewardResolver::resolveItem($entry, $player);
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
