<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchanter;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\items\pets\PetManager;
use ecstsy\Glacia\server\items\GlaciaItemFactory;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\Item;
use pocketmine\player\Player;
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

    /** @return array<int, array{group: string, base_price:int, price: int}> */
    public static function getBooks(?Player $player = null): array
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "enchanter.yml");
        $prices = (array) $config->get("books", []);

        $books = [];
        foreach (self::BOOK_SLOTS as $group => $slot) {
            $basePrice = max(1, (int) ($prices[$group] ?? 0));
            $books[$slot] = [
                "group" => $group,
                "base_price" => $basePrice,
                "price" => self::getEffectivePrice($player, $basePrice),
            ];
        }

        ksort($books);
        return $books;
    }

    public static function getBookDataBySlot(int $slot, ?Player $player = null): ?array
    {
        return self::getBooks($player)[$slot] ?? null;
    }

    public static function createDisplayItem(string $group, int $price, ?Player $player = null): Item
    {
        $book = GlaciaItemFactory::randomEnchantBook($group);
        $display = VanillaBlocks::STAINED_GLASS_PANE()->setColor(self::BOOK_COLORS[$group])->asItem();
        $display->setCustomName($book->getCustomName());

        $lore = $book->getLore();
        $lore[] = "";
        $lore[] = C::colorize("&r&l&bCOST &r&f" . number_format($price) . " XP");
        $discount = $player !== null ? PetManager::getEnchanterDiscountPercent($player) : 0;
        if ($discount > 0) {
            $lore[] = C::colorize("&r&7Pet Bonus: &a-" . $discount . "%");
        }
        $display->setLore($lore);

        return $display;
    }

    public static function createRewardItem(string $group): Item
    {
        return GlaciaItemFactory::randomEnchantBook($group);
    }

    public static function getEffectivePrice(?Player $player, int $basePrice): int
    {
        $basePrice = max(1, $basePrice);
        if (!$player instanceof Player) {
            return $basePrice;
        }

        $discount = PetManager::getEnchanterDiscountPercent($player);
        if ($discount <= 0) {
            return $basePrice;
        }

        return max(0, (int) floor($basePrice * (1 - ($discount / 100))));
    }
}
