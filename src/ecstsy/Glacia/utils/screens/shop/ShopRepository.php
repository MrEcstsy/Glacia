<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\shop;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\economy\SellHandler;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\item\StringToItemParser;
use pocketmine\utils\TextFormat;

final class ShopRepository {

    public static function getItemsForCategory(string $category): array
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "shop.yml");
        $shopData = $config?->get("shop", []);
        $entries = $shopData[$category] ?? [];

        if (!is_array($entries)) {
            Loader::getInstance()->getLogger()->warning("Shop category '$category' is not a valid array.");
            return [];
        }

        $items = [];
        foreach ($entries as $entry) {
            $normalized = self::normalizeEntry($category, $entry);
            if ($normalized !== null) {
                $items[] = $normalized;
            }
        }

        return $items;
    }

    private static function normalizeEntry(string $category, mixed $entry): ?array
    {
        if (is_string($entry)) {
            $entry = ["id" => $entry];
        }

        if (!is_array($entry)) {
            Loader::getInstance()->getLogger()->warning("Invalid shop entry found in category '$category'.");
            return null;
        }

        $id = $entry["id"] ?? null;
        if (!is_string($id) || trim($id) === "") {
            Loader::getInstance()->getLogger()->warning("A shop entry in category '$category' is missing an item id.");
            return null;
        }

        $item = StringToItemParser::getInstance()->parse($id);
        if ($item === null) {
            Loader::getInstance()->getLogger()->warning("Shop item '$id' in category '$category' could not be parsed.");
            return null;
        }

        $sell = isset($entry["sell"]) && is_numeric($entry["sell"])
            ? round((float) $entry["sell"], 2)
            : SellHandler::getSellPrice($item->getTypeId());

        $buy = isset($entry["buy"]) && is_numeric($entry["buy"])
            ? round((float) $entry["buy"], 2)
            : ($sell !== null ? round($sell * 3, 2) : null);

        if ($buy === null) {
            Loader::getInstance()->getLogger()->warning("Shop item '$id' in category '$category' is missing a buy price.");
            return null;
        }

        return [
            "id" => $id,
            "name" => (string) ($entry["name"] ?? self::resolveDisplayName($id, $item)),
            "buy" => $buy,
            "sell" => $sell
        ];
    }

    private static function resolveDisplayName(string $id, object $item): string
    {
        if (str_ends_with($id, "_spawner")) {
            $mobName = preg_replace('/_spawner$/', '', $id) ?? $id;
            return ucwords(str_replace("_", " ", $mobName)) . " Spawner";
        }

        $customName = method_exists($item, "getCustomName") ? TextFormat::clean($item->getCustomName()) : "";
        if ($customName !== "") {
            return trim($customName);
        }

        return $item->getVanillaName();
    }
}
