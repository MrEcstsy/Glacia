<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\shop;

use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use Vecnavium\FormsUI\SimpleForm;

final class ShopFormScreen {

    private const ITEMS_PER_PAGE = 8;

    /** @var array<string, string> */
    private const CATEGORIES = [
        "potions" => "Potion Shop",
        "raid" => "Raid Shop",
        "spawners" => "Spawners",
        "blocks" => "Building Blocks",
        "ores" => "Ore and Gems",
        "food" => "Food and Farming",
        "drops" => "Mob Drops",
        "special" => "Specialty Items",
        "brewing" => "Brewing",
        "wool" => "Wool",
        "glass" => "Glass",
        "base" => "Base Grind"
    ];

    public static function display(Player $player): void
    {
        $form = new SimpleForm(function(Player $player, mixed $data): void {
            if (!is_string($data) || !isset(self::CATEGORIES[$data])) {
                return;
            }

            self::displayCategory($player, $data);
        });

        $form->setTitle(C::colorize("&r&8Shop Categories"));
        $form->setContent(C::colorize("&r&7Choose a category."));

        foreach (self::CATEGORIES as $id => $name) {
            $form->addButton(C::colorize("&r&l&e{$name}\n&r&8Tap to browse"), label: $id);
        }

        $player->sendForm($form);
    }

    public static function displayCategory(Player $player, string $category, int $page = 0): void
    {
        $items = ShopRepository::getItemsForCategory($category);
        if ($items === []) {
            $player->sendMessage(C::colorize("&r&cThis shop category has no items configured."));
            return;
        }

        $maxPage = max(0, (int) ceil(count($items) / self::ITEMS_PER_PAGE) - 1);
        $page = max(0, min($maxPage, $page));
        $slice = array_slice($items, $page * self::ITEMS_PER_PAGE, self::ITEMS_PER_PAGE);

        $form = new SimpleForm(function(Player $player, mixed $data) use ($category, $items, $page): void {
            if ($data === "back") {
                self::display($player);
                return;
            }

            if ($data === "prev") {
                self::displayCategory($player, $category, $page - 1);
                return;
            }

            if ($data === "next") {
                self::displayCategory($player, $category, $page + 1);
                return;
            }

            if (!is_string($data) || !ctype_digit($data) || !isset($items[(int) $data])) {
                return;
            }

            ShopConfirmScreen::displayForm($player, $items[(int) $data]);
        });

        $title = self::CATEGORIES[$category] ?? ucwords(str_replace("_", " ", $category));
        $form->setTitle(C::colorize("&r&8{$title}"));
        $form->setContent(C::colorize("&r&7Page &f" . ($page + 1) . "&7/&f" . ($maxPage + 1)));

        foreach ($slice as $index => $itemData) {
            $globalIndex = ($page * self::ITEMS_PER_PAGE) + $index;
            $sell = $itemData["sell"] ?? null;
            $sellText = $sell !== null ? "$" . number_format((float) $sell, 2) : "Unavailable";
            $form->addButton(C::colorize(
                "&r&f" . (string) $itemData["name"] . "\n" .
                "&r&aBuy $" . number_format((float) $itemData["buy"], 2) . " &8| &cSell {$sellText}"
            ), label: (string) $globalIndex);
        }

        if ($page > 0) {
            $form->addButton(C::colorize("&r&bPrevious Page"), label: "prev");
        }

        if ($page < $maxPage) {
            $form->addButton(C::colorize("&r&bNext Page"), label: "next");
        }

        $form->addButton(C::colorize("&r&7Back"), label: "back");
        $player->sendForm($form);
    }
}
