<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\slotbot;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\items\pets\PetManager;
use ecstsy\Glacia\utils\PermissionTierUtils;
use ecstsy\Glacia\utils\RewardResolver;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianUtilities\utils\ItemUtils;
use pocketmine\item\Item;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\player\Player;
use function abs;
use function array_filter;
use function array_values;
use function count;
use function intdiv;
use function is_array;
use function max;
use function min;
use function sprintf;
use function time;

final class SlotbotRewardRepository {
    private static int $creditShopRerollNonce = 0;
    private static ?int $creditShopForcedStartedAt = null;

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
     * @return array<int, array<string, mixed>>
     */
    private static function getCreditShopEntries() : array
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "slotbot.yml");
        $section = $config?->get("credit-shop", []);
        if (!is_array($section)) {
            return [];
        }

        $entries = $section["items"] ?? [];
        if (!is_array($entries)) {
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

        if ($player instanceof Player && $jackpotEntries !== [] && PetManager::consumeJackpotSlotGuarantee($player)) {
            return self::getJackpotReward($player);
        }

        if ($jackpotEntries !== [] && mt_rand(1, 10000) <= (int) round(self::getJackpotChanceFor($player) * 100)) {
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

    public static function getJackpotChanceFor(?Player $player = null): float
    {
        $chance = self::getJackpotChance();
        if (!$player instanceof Player) {
            return $chance;
        }

        $bonus = PermissionTierUtils::getHighestIntPermission($player, "glacia.slotbot.jackpot_bonus.", 0, 100);
        return max(0.0, min(100.0, $chance + $bonus));
    }

    /**
     * @return array{
     *   key:string,
     *   price:int,
     *   started_at:int,
     *   ends_at:int,
     *   seconds_remaining:int,
     *   entry:array<string,mixed>,
     *   item:Item
     * }|null
     */
    public static function getCurrentCreditShopOffer(?Player $player = null): ?array
    {
        $entries = self::getCreditShopEntries();
        if ($entries === []) {
            return null;
        }

        $valid = [];
        foreach ($entries as $index => $entry) {
            $price = max(1, (int) ($entry["price"] ?? 0));
            if ($price <= 0) {
                continue;
            }

            $preview = self::resolveReward($entry, null);
            if ($preview === null) {
                continue;
            }

            $entry["_shop_index"] = $index;
            $valid[] = $entry;
        }

        if ($valid === []) {
            return null;
        }

        $rotationSeconds = self::getCreditShopRotationSeconds();
        $startedAt = self::resolveCreditShopStartedAt($rotationSeconds);
        $seed = "slotbot_credit_shop:" . $startedAt . ":" . self::$creditShopRerollNonce;
        $endsAt = $startedAt + $rotationSeconds;
        $entry = self::pickDeterministicEntry($valid, $seed);
        if ($entry === null) {
            return null;
        }

        $item = self::resolveReward($entry, $player);
        if ($item === null) {
            $item = self::resolveReward($entry, null);
        }
        if ($item === null) {
            return null;
        }

        $index = (int) ($entry["_shop_index"] ?? 0);
        unset($entry["_shop_index"]);
        return [
            "key" => "rotation:" . $startedAt . ":reroll:" . self::$creditShopRerollNonce . ":entry:" . $index,
            "price" => max(1, (int) ($entry["price"] ?? 1)),
            "started_at" => $startedAt,
            "ends_at" => $endsAt,
            "seconds_remaining" => max(0, $endsAt - time()),
            "entry" => $entry,
            "item" => clone $item,
        ];
    }

    public static function rerollCreditShop(): void
    {
        self::$creditShopRerollNonce++;
        self::$creditShopForcedStartedAt = time();
    }

    public static function getCreditShopRotationSeconds(): int
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "slotbot.yml");
        $section = $config?->get("credit-shop", []);
        if (!is_array($section)) {
            return 43200;
        }

        $raw = trim((string) ($section["rotation"] ?? "12h"));
        $seconds = GeneralUtils::parseTimeString($raw);
        return max(60, $seconds);
    }

    private static function resolveCreditShopStartedAt(int $rotationSeconds): int
    {
        $now = time();
        if (self::$creditShopForcedStartedAt !== null) {
            $forcedStartedAt = self::$creditShopForcedStartedAt;
            if (($forcedStartedAt + $rotationSeconds) > $now) {
                return $forcedStartedAt;
            }

            self::$creditShopForcedStartedAt = null;
        }

        return intdiv($now, $rotationSeconds) * $rotationSeconds;
    }

    /**
     * @param array<int, array<string, mixed>> $pool
     */
    private static function pickDeterministicEntry(array $pool, string $seed): ?array
    {
        $weighted = [];
        $totalWeight = 0;

        foreach ($pool as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $chance = max(0, (int) ($entry["chance"] ?? 0));
            if ($chance <= 0) {
                continue;
            }

            $weighted[] = [
                "weight" => $chance,
                "entry" => $entry,
            ];
            $totalWeight += $chance;
        }

        if ($totalWeight <= 0) {
            return null;
        }

        $roll = ((int) sprintf("%u", crc32($seed))) % $totalWeight + 1;
        $current = 0;
        foreach ($weighted as $data) {
            $current += (int) $data["weight"];
            if ($roll <= $current) {
                return $data["entry"];
            }
        }

        return $weighted[count($weighted) - 1]["entry"] ?? null;
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

        $safeItem = self::ensureClientSafe($item);
        if ($safeItem === null) {
            Loader::getInstance()->getLogger()->warning("[Slotbot] Skipped an unsafe reward item while resolving slotbot loot.");
            return null;
        }

        return $safeItem;
    }

    private static function ensureClientSafe(Item $item): ?Item
    {
        if (method_exists(ItemUtils::class, "ensureClientSafe")) {
            try {
                /** @var ?Item $safeItem */
                $safeItem = ItemUtils::ensureClientSafe($item);
                return $safeItem;
            } catch (\Throwable) {
                // Fall through to the local compatibility path below.
            }
        }

        try {
            $safeItem = clone $item;
            if ($safeItem->isNull()) {
                return null;
            }

            $count = $safeItem->getCount();
            if ($count <= 0) {
                $count = 1;
            }

            $maxCount = max(1, min(255, $safeItem->getMaxStackSize()));
            if ($count > $maxCount) {
                $count = $maxCount;
            }

            $safeItem->setCount($count);
            TypeConverter::getInstance()->coreItemStackToNet($safeItem);
            return $safeItem;
        } catch (\Throwable) {
            return null;
        }
    }
}
