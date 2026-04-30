<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\items;

use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use pocketmine\utils\TextFormat as C;

final class CustomMobItemManager
{
    /** @var array<string, float> */
    private static array $sellPrices = [];

    public static function loadFromConfig(): void
    {
        self::$sellPrices = [];

        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "custom_mob_items.yml");
        $items = (array) ($config?->get("items", []) ?? []);
        $registered = [];

        foreach ($items as $identifier => $data) {
            if (!is_array($data)) {
                Loader::getInstance()->getLogger()->warning("Custom mob item '{$identifier}' must be a map.");
                continue;
            }

            $id = self::normalizeIdentifier((string) $identifier);
            if ($id === "") {
                Loader::getInstance()->getLogger()->warning("Custom mob item identifier '{$identifier}' is invalid.");
                continue;
            }

            $material = StringToItemParser::getInstance()->parse((string) ($data["material"] ?? "paper")) ?? VanillaItems::PAPER();
            GlaciaItemFactory::$definitions[$id] = [
                "material" => $material,
                "name" => (string) ($data["name"] ?? "&r&f" . ucwords(str_replace("_", " ", $id))),
                "lore" => array_values(array_map(static fn(mixed $line): string => (string) $line, (array) ($data["lore"] ?? []))),
                "nbt" => [
                    "glaciaItem" => $id,
                    "custom_mob_item" => $id,
                ],
            ];

            $price = $data["sell-price"] ?? null;
            if (is_numeric($price)) {
                self::$sellPrices[$id] = round((float) $price, 2);
            }

            $registered[] = $id;
        }

        GlaciaItems::registerCategory("mobdrops", "Mob Drops", $registered);
    }

    public static function getSellPrice(Item $item): ?float
    {
        $tag = $item->getNamedTag()->getCompoundTag("Glacia");
        if ($tag === null || !$tag->getTag("custom_mob_item")) {
            return null;
        }

        $id = self::normalizeIdentifier($tag->getString("custom_mob_item", ""));
        return self::$sellPrices[$id] ?? null;
    }

    public static function getSellDisplayName(Item $item): string
    {
        $name = $item->getCustomName();
        return $name !== "" ? C::clean($name) : $item->getVanillaName();
    }

    private static function normalizeIdentifier(string $identifier): string
    {
        $identifier = strtolower(trim($identifier));
        return preg_match('/^[a-z0-9_]{3,48}$/', $identifier) === 1 ? $identifier : "";
    }
}
