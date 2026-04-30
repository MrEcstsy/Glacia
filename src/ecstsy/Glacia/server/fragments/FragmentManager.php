<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\fragments;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\items\GlaciaItemFactory;
use ecstsy\Glacia\server\items\GlaciaItems;
use ecstsy\Glacia\utils\RewardResolver;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class FragmentManager
{
    /** @var array<string, array<string, mixed>> */
    private static array $recipes = [];

    public static function loadFromConfig() : void
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "fragments.yml");
        self::$recipes = [];

        foreach ((array) ($config?->get("recipes", []) ?? []) as $id => $entry) {
            if (!is_array($entry) || !((bool) ($entry["enabled"] ?? true))) {
                continue;
            }

            $key = strtolower(trim((string) $id));
            if ($key === "") {
                continue;
            }

            self::$recipes[$key] = $entry;
        }
    }

    public static function registerItemDefinitions() : void
    {
        $definitionIds = [];
        foreach (self::$recipes as $id => $entry) {
            $material = StringToItemParser::getInstance()->parse((string) ($entry["material"] ?? "amethyst_shard"))
                ?? StringToItemParser::getInstance()->parse("amethyst_shard");
            $needed = max(1, (int) ($entry["amount-to-combine"] ?? 1));
            $lore = [];
            foreach ((array) ($entry["lore"] ?? []) as $line) {
                if (is_string($line)) {
                    $lore[] = str_replace("{needed}", (string) $needed, $line);
                }
            }

            GlaciaItemFactory::$definitions["fragment_" . $id] = [
                "material" => $material,
                "name" => (string) ($entry["item-name"] ?? "&r&d" . ucfirst($id) . " Fragment"),
                "lore" => $lore,
                "nbt" => [
                    "glaciaItem" => "fragment",
                    "fragment_id" => $id,
                ],
            ];
            $definitionIds[] = "fragment_" . $id;
        }

        if ($definitionIds !== []) {
            GlaciaItems::registerCategory("fragments", "Fragments", $definitionIds);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function getRecipe(string $id) : ?array
    {
        return self::$recipes[strtolower(trim($id))] ?? null;
    }

    public static function handleUse(Player $player, string $fragmentId) : bool
    {
        $recipe = self::getRecipe($fragmentId);
        if ($recipe === null) {
            return false;
        }

        $needed = max(1, (int) ($recipe["amount-to-combine"] ?? 1));
        $owned = self::countFragments($player, $fragmentId);
        if ($owned < $needed) {
            $player->sendMessage(C::colorize("&r&cYou need &f{$needed} &cfragments to combine this reward. &7({$owned}/{$needed})"));
            return true;
        }

        $times = $player->isSneaking() ? max(1, intdiv($owned, $needed)) : 1;
        self::removeFragments($player, $fragmentId, $needed * $times);

        for ($i = 0; $i < $times; $i++) {
            $reward = RewardResolver::resolveItem((array) ($recipe["reward"] ?? []), $player);
            if ($reward instanceof Item) {
                foreach ($player->getInventory()->addItem($reward) as $leftover) {
                    $player->getWorld()->dropItem($player->getPosition(), $leftover);
                }
            }
        }

        $player->sendMessage(C::colorize("&r&aCombined &f" . ($needed * $times) . " &afragment(s) into &f{$times} &areward(s)."));
        return true;
    }

    private static function countFragments(Player $player, string $fragmentId) : int
    {
        $count = 0;
        foreach ($player->getInventory()->getContents() as $item) {
            $glacia = $item->getNamedTag()->getCompoundTag("Glacia");
            if (
                $glacia !== null &&
                $glacia->getString("glaciaItem", "") === "fragment" &&
                strtolower($glacia->getString("fragment_id", "")) === strtolower($fragmentId)
            ) {
                $count += $item->getCount();
            }
        }

        return $count;
    }

    private static function removeFragments(Player $player, string $fragmentId, int $amount) : void
    {
        foreach ($player->getInventory()->getContents() as $slot => $item) {
            if ($amount <= 0) {
                break;
            }

            $glacia = $item->getNamedTag()->getCompoundTag("Glacia");
            if (
                $glacia === null ||
                $glacia->getString("glaciaItem", "") !== "fragment" ||
                strtolower($glacia->getString("fragment_id", "")) !== strtolower($fragmentId)
            ) {
                continue;
            }

            $take = min($amount, $item->getCount());
            $item->pop($take);
            $player->getInventory()->setItem($slot, $item);
            $amount -= $take;
        }
    }
}
