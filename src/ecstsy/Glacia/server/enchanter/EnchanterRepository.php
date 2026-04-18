<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchanter;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\items\GlaciaItemFactory;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\Item;
use pocketmine\utils\TextFormat as C;

final class EnchanterRepository {

    private const BOOK_SLOTS = [
        "simple" => 11,
        "unique" => 12,
        "elite" => 13,
        "exotic" => 14,
        "legendary" => 15,
    ];

    private const BOOK_COLORS = [
        "simple" => DyeColor::WHITE,
        "unique" => DyeColor::LIME,
        "elite" => DyeColor::LIGHT_BLUE,
        "exotic" => DyeColor::YELLOW,
        "legendary" => DyeColor::ORANGE,
    ];

    /** @return array<int, array{group: string, price: int}> */
    public static function getBooks(): array
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "enchanter.yml");
        $prices = (array) $config->get("books", []);

        $books = [];
        foreach (self::BOOK_SLOTS as $group => $slot) {
            $price = max(1, (int) ($prices[$group] ?? 0));
            $books[$slot] = [
                "group" => $group,
                "price" => $price
            ];
        }

        ksort($books);
        return $books;
    }

    public static function getBookDataBySlot(int $slot): ?array
    {
        return self::getBooks()[$slot] ?? null;
    }

    public static function createDisplayItem(string $group, int $price): Item
    {
        $book = GlaciaItemFactory::randomEnchantBook($group);
        $display = VanillaBlocks::STAINED_GLASS_PANE()->setColor(self::BOOK_COLORS[$group])->asItem();
        $display->setCustomName($book->getCustomName());

        $lore = $book->getLore();
        $lore[] = "";
        $lore[] = C::colorize("&r&l&bCOST &r&f" . number_format($price) . " XP");
        $display->setLore($lore);

        return $display;
    }

    public static function createRewardItem(string $group): Item
    {
        return GlaciaItemFactory::randomEnchantBook($group);
    }
}
