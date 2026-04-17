<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\economy;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\quests\QuestInstance;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\block\BlockTypeIds;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use pocketmine\world\particle\LavaParticle;

use function PHPSTORM_META\map;

final class SellHandler {

    public static array $sellPrices = [];

    public static function registerDefaults(): void {
        self::$sellPrices = [
            // FARMING
            ItemTypeIds::WHEAT => 2.5,
            ItemTypeIds::CARROT => 3,
            ItemTypeIds::POTATO => 3.25,
            ItemTypeIds::BEETROOT => 2.25,
            BlockTypeIds::SUGARCANE => 3.75,
            ItemTypeIds::MELON => 1,
            BlockTypeIds::PUMPKIN => 5.50,
            ItemTypeIds::COCOA_BEANS => 2,
            BlockTypeIds::NETHER_WART => 4.25,
            BlockTypeIds::CACTUS => 1.75,
            ItemTypeIds::WHEAT_SEEDS => 0.25,
            ItemTypeIds::MELON_SEEDS => 0.25,
            ItemTypeIds::PUMPKIN_SEEDS => 0.25,
            ItemTypeIds::BEETROOT_SEEDS => 0.25,
            ItemTypeIds::BAMBOO => 2,

            // MOB DROPS
            ItemTypeIds::ROTTEN_FLESH => 1.25,
            ItemTypeIds::BONE => 1.75,
            ItemTypeIds::STRING => 1.25,
            ItemTypeIds::SPIDER_EYE => 1.75,
            ItemTypeIds::GUNPOWDER => 3.25,
            ItemTypeIds::ENDER_PEARL => 6,
            ItemTypeIds::BLAZE_ROD => 7,
            ItemTypeIds::SLIMEBALL => 3.50,
            ItemTypeIds::GHAST_TEAR => 8.50,
            ItemTypeIds::MAGMA_CREAM => 5.25,

            // ORES
            ItemTypeIds::COAL => 2,
            ItemTypeIds::IRON_INGOT => 5.50,
            ItemTypeIds::GOLD_INGOT => 7.75,
            ItemTypeIds::REDSTONE_DUST => 1.25,
            ItemTypeIds::LAPIS_LAZULI => 2.25,
            ItemTypeIds::DIAMOND => 15,
            ItemTypeIds::EMERALD => 17.50,
            ItemTypeIds::COPPER_INGOT => 3.50,

            // Nature
            BlockTypeIds::OAK_WOOD => 1.25,
            BlockTypeIds::SPRUCE_WOOD => 1.25,
            BlockTypeIds::BIRCH_WOOD => 1.25,
            BlockTypeIds::JUNGLE_WOOD => 1.50,
            BlockTypeIds::ACACIA_WOOD => 1.50,
            BlockTypeIds::DARK_OAK_WOOD => 1.50,

            // Items
            ItemTypeIds::APPLE => 2,
            ItemTypeIds::STICK => 0.25,
            ItemTypeIds::LEATHER => 2.50,
            ItemTypeIds::FEATHER => 1,
            ItemTypeIds::EGG => 1.50,
            ItemTypeIds::INK_SAC => 2.25,
            ItemTypeIds::GLOW_INK_SAC => 3.75,
            BlockTypeIds::COBWEB => 2.50,
            ItemTypeIds::ARROW => 0.75,
            ItemTypeIds::PRISMARINE_CRYSTALS => 4.25,
            ItemTypeIds::PRISMARINE_SHARD => 4.25
        ];
    }

    public static function sellItem(Player $player, Item $item): bool {
        $count = $item->getCount();
        $id = $item->getTypeId();

        if (!isset(self::$sellPrices[$id])) {
            $player->sendMessage(C::colorize("&r&cThis item cannot be sold."));
            PlayerUtils::playSound($player, "note.bass");
            $pos = $player->getPosition()->add(0, 1.5, 0);
            $player->getWorld()->addParticle($pos, new LavaParticle());
            return false;
        }

        $session = Loader::getPlayerManager()->getSession($player);

        $basePrice = self::$sellPrices[$id] * $count;
        $multiplier = $session->getSellMultiplier() ?? 1.0;
        $finalPrice = round($basePrice * $multiplier, 2);

        $session->addBalance($finalPrice);
        $player->getInventory()->removeItem($item);

        $multiplierText = $multiplier > 1.0 ? " &r&7(x{$multiplier} Multiplier)" : "";
        $player->sendMessage(C::colorize("&r&aYou sold &f{$count}x &a" . $item->getVanillaName() . " &r&afor &b$" . number_format($finalPrice, 2) . "{$multiplierText}&r&a."));
        PlayerUtils::playSound($player, "random.orb");

        QuestInstance::onSell($player, $item, $count);

        return true;
    }

    public static function sellAll(Player $player): void {
        $inventory = $player->getInventory();
        $session = Loader::getPlayerManager()->getSession($player);
        $totalEarned = 0.0;
        $itemsSold = 0;

        $multiplier = $session->getSellMultiplier() ?? 1.0;
        $multiplierText = $multiplier > 1.0 ? " &r&7(x{$multiplier} Multiplier)" : "";

        foreach ($inventory->getContents() as $slot => $item) {
            $id = $item->getTypeId();

            if (!isset(self::$sellPrices[$id])) {
                continue; 
            }

            $count = $item->getCount();
            $price = self::$sellPrices[$id] * $count;
            $finalPrice = round($price * $multiplier, 2);

            $totalEarned += $finalPrice;
            $itemsSold += $count;

            QuestInstance::onSell($player, $item, $count);

            $inventory->setItem($slot, $item->setCount(0));
        }

        if ($totalEarned > 0) {
            $session->addBalance($totalEarned);
            $player->sendMessage(C::colorize("&r&aYou sold &f{$itemsSold} &aitems for &b$" . number_format($totalEarned, 2) . "{$multiplierText}&r&a."));
            PlayerUtils::playSound($player, "random.orb");
        } else {
            $player->sendMessage(C::colorize("&r&cYou have no items that can be sold."));
            PlayerUtils::playSound($player, "note.bass");
        }
    }
}