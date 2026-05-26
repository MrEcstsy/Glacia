<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\economy;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\quests\QuestInstance;
use ecstsy\Glacia\server\factions\guardian\FactionGuardianManager;
use ecstsy\Glacia\server\items\CustomMobItemManager;
use ecstsy\Glacia\server\items\runes\RuneManager;
use ecstsy\Glacia\server\watchdogs\ChunkWatchdogManager;
use ecstsy\Glacia\server\factions\contribution\FactionContributionManager;
use ecstsy\Glacia\server\world\events\WorldEventManager;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use pocketmine\utils\TextFormat;
use pocketmine\world\particle\LavaParticle;

final class SellHandler {

    public static array $sellPrices = [];
    private const ZONE_ORE_MULTIPLIER_TAG = "zone_ore_sell_multiplier";

    public static function registerDefaults(): void {
        self::loadFromConfig();
    }

    public static function loadFromConfig(): void {
        self::$sellPrices = [];

        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "sell.yml");
        $priceSections = $config?->get("sell-prices", []);

        if (!is_array($priceSections)) {
            Loader::getInstance()->getLogger()->warning("sell.yml is missing a valid 'sell-prices' section.");
            return;
        }

        foreach ($priceSections as $section => $entries) {
            if (!is_array($entries)) {
                Loader::getInstance()->getLogger()->warning("Sell section '$section' is not a valid map.");
                continue;
            }

            foreach ($entries as $itemId => $price) {
                self::registerSellEntry((string) $section, (string) $itemId, $price);
            }
        }
    }

    private static function registerSellEntry(string $section, string $itemId, mixed $price): void {
        if (!is_numeric($price)) {
            Loader::getInstance()->getLogger()->warning("Sell price for '$itemId' in section '$section' is not numeric.");
            return;
        }

        $item = StringToItemParser::getInstance()->parse($itemId);
        if ($item === null) {
            Loader::getInstance()->getLogger()->warning("Could not parse sell item '$itemId' in section '$section'.");
            return;
        }

        self::$sellPrices[$item->getTypeId()] = round((float) $price, 2);
    }

    public static function sellItem(Player $player, Item $item): bool {
        if (FactionGuardianManager::isGuardianEgg($item)) {
            $player->sendMessage(C::colorize("&r&cChunk Guardian eggs cannot be sold."));
            PlayerUtils::playSound($player, "note.bass");
            return false;
        }

        $count = $item->getCount();
        $id = $item->getTypeId();
        $questPrice = QuestInstance::getSellPrice($item);
        $customPrice = CustomMobItemManager::getSellPrice($item);

        if ($questPrice === null && $customPrice === null && !isset(self::$sellPrices[$id])) {
            $player->sendMessage(C::colorize("&r&cThis item cannot be sold."));
            PlayerUtils::playSound($player, "note.bass");
            $pos = $player->getPosition()->add(0, 1.5, 0);
            $player->getWorld()->addParticle($pos, new LavaParticle());
            return false;
        }

        $session = Loader::getPlayerManager()->getSession($player);

        $basePrice = ($questPrice ?? $customPrice ?? self::$sellPrices[$id]) * $count;
        $basePrice *= self::getZoneOreSellMultiplier($item);
        $basePrice *= ChunkWatchdogManager::getDropSellBonus($item);
        $factionSellBuff = FactionContributionManager::getBuffMultiplierForPlayer($player, "sell-multiplier", 1.0);
        $outpostMultiplier = Loader::getCaptureManager()->getOutpostTypeMultiplierForPlayer($player, "economy", 1.0);
        $multiplier = ($session->getSellMultiplier() ?? 1.0) * RuneManager::getSellMultiplier($player) * $factionSellBuff * WorldEventManager::getSellMultiplier($player) * $outpostMultiplier * ChunkWatchdogManager::getCurrentChunkSellMultiplier($player);
        $finalPrice = round($basePrice * $multiplier, 2);

        $session->addBalance($finalPrice);
        $player->getInventory()->removeItem($item);

        $multiplierText = $multiplier > 1.0 ? " &r&7(x{$multiplier} Multiplier)" : "";
        $display = $questPrice !== null
            ? (TextFormat::clean($item->getCustomName()) !== "" ? TextFormat::clean($item->getCustomName()) : $item->getVanillaName())
            : ($customPrice !== null ? CustomMobItemManager::getSellDisplayName($item) : $item->getVanillaName());
        $player->sendMessage(C::colorize("&r&aYou sold &f{$count}x &a" . $display . " &r&afor &b$" . number_format($finalPrice, 2) . "{$multiplierText}&r&a."));
        PlayerUtils::playSound($player, "random.orb");

        QuestInstance::onSell($player, $item, $count);
        FactionContributionManager::addSellContribution($player, $finalPrice);
        Loader::getQuestBoardManager()->recordProgress($player, "sell_value", (int) round($finalPrice));

        return true;
    }

    public static function sellAll(Player $player): void {
        $inventory = $player->getInventory();
        $session = Loader::getPlayerManager()->getSession($player);
        $totalEarned = 0.0;
        $itemsSold = 0;

        $factionSellBuff = FactionContributionManager::getBuffMultiplierForPlayer($player, "sell-multiplier", 1.0);
        $outpostMultiplier = Loader::getCaptureManager()->getOutpostTypeMultiplierForPlayer($player, "economy", 1.0);
        $multiplier = ($session->getSellMultiplier() ?? 1.0) * RuneManager::getSellMultiplier($player) * $factionSellBuff * WorldEventManager::getSellMultiplier($player) * $outpostMultiplier * ChunkWatchdogManager::getCurrentChunkSellMultiplier($player);
        $multiplierText = $multiplier > 1.0 ? " &r&7(x{$multiplier} Multiplier)" : "";

        foreach ($inventory->getContents() as $slot => $item) {
            if (FactionGuardianManager::isGuardianEgg($item)) {
                continue;
            }

            $id = $item->getTypeId();
            $questPrice = QuestInstance::getSellPrice($item);
            $customPrice = CustomMobItemManager::getSellPrice($item);

            if ($questPrice === null && $customPrice === null && !isset(self::$sellPrices[$id])) {
                continue;
            }

            $count = $item->getCount();
            $price = ($questPrice ?? $customPrice ?? self::$sellPrices[$id]) * $count * self::getZoneOreSellMultiplier($item) * ChunkWatchdogManager::getDropSellBonus($item);
            $finalPrice = round($price * $multiplier, 2);

            $totalEarned += $finalPrice;
            $itemsSold += $count;

            QuestInstance::onSell($player, $item, $count);

            $inventory->setItem($slot, $item->setCount(0));
        }

        if ($totalEarned > 0) {
            $session->addBalance($totalEarned);
            FactionContributionManager::addSellContribution($player, $totalEarned);
            Loader::getQuestBoardManager()->recordProgress($player, "sell_value", (int) round($totalEarned));
            $player->sendMessage(C::colorize("&r&aYou sold &f{$itemsSold} &aitems for &b$" . number_format($totalEarned, 2) . "{$multiplierText}&r&a."));
            PlayerUtils::playSound($player, "random.orb");
        } else {
            $player->sendMessage(C::colorize("&r&cYou have no items that can be sold."));
            PlayerUtils::playSound($player, "note.bass");
        }
    }

    public static function getBuyPrice(int $id): ?float {
        $sellPrice = self::getSellPrice($id);
        if ($sellPrice === null) {
            return null;
        }

        return round($sellPrice * 3, 2);
    }

    public static function getSellPrice(int $id): ?float {
        return self::$sellPrices[$id] ?? null;
    }

    public static function getSellPriceForItem(Item $item): ?float
    {
        $questPrice = QuestInstance::getSellPrice($item);
        if ($questPrice !== null) {
            return $questPrice;
        }

        $customPrice = CustomMobItemManager::getSellPrice($item);
        if ($customPrice !== null) {
            return $customPrice;
        }

        return self::getSellPrice($item->getTypeId());
    }

    private static function getZoneOreSellMultiplier(Item $item): float
    {
        $glacia = $item->getNamedTag()->getCompoundTag("Glacia");
        if ($glacia === null) {
            return 1.0;
        }

        return max(1.0, (float) $glacia->getFloat(self::ZONE_ORE_MULTIPLIER_TAG, 1.0));
    }
}
