<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\auctionhouse;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\player\GlaciaPlayer;
use ecstsy\Glacia\utils\QueryStmts;
use ecstsy\Glacia\utils\StorageUtils;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianUtilities\utils\ItemUtils;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use Ramsey\Uuid\Uuid;

final class AuctionHouseManager {

    /** @var array<string, array<string, mixed>> */
    private array $listings = [];

    /** @var array<string, array<string, mixed>> */
    private array $collection = [];
    /** @var array<string, string[]> */
    private array $watchlists = [];
    private string $watchlistFile;

    public function __construct()
    {
        $this->watchlistFile = Loader::getInstance()->getDataFolder() . "auction_watchlists.json";
        $this->load();
        $this->loadWatchlists();
    }

    private function load(): void
    {
        Loader::getDatabase()->executeSelect(QueryStmts::AUCTION_HOUSE_LISTINGS_SELECT, [], function(array $rows): void {
            foreach ($rows as $row) {
                $this->listings[$row["listing_id"]] = $row;
            }
        });

        Loader::getDatabase()->executeSelect(QueryStmts::AUCTION_HOUSE_COLLECTION_SELECT, [], function(array $rows): void {
            foreach ($rows as $row) {
                $this->collection[$row["entry_id"]] = $row;
            }
        });
    }

    public function getListings(): array
    {
        $listings = array_values($this->listings);
        usort($listings, static function(array $a, array $b): int {
            return ($b["created_at"] <=> $a["created_at"]) ?: strcmp((string) $a["listing_id"], (string) $b["listing_id"]);
        });
        return $listings;
    }

    public function getListingsForSeller(string $sellerUuid): array
    {
        return array_values(array_filter(
            $this->getListings(),
            static fn(array $listing): bool => $listing["seller_uuid"] === $sellerUuid
        ));
    }

    public function getCollectionEntries(string $ownerUuid): array
    {
        $entries = array_values(array_filter(
            $this->collection,
            static fn(array $entry): bool => $entry["owner_uuid"] === $ownerUuid
        ));

        usort($entries, static fn(array $a, array $b): int => ($b["created_at"] <=> $a["created_at"]));
        return $entries;
    }

    public function getCollectionCount(string $ownerUuid): int
    {
        return count(array_filter(
            $this->collection,
            static fn(array $entry): bool => $entry["owner_uuid"] === $ownerUuid
        ));
    }

    public function getListing(string $listingId): ?array
    {
        return $this->listings[$listingId] ?? null;
    }

    public function searchListings(string $query = "", ?int $minPrice = null, ?int $maxPrice = null, string $sort = "newest"): array
    {
        $query = strtolower(trim($query));
        $matches = array_filter($this->getListings(), static function(array $listing) use ($query, $minPrice, $maxPrice): bool{
            $price = (int) ($listing["price"] ?? 0);
            if ($minPrice !== null && $price < $minPrice) {
                return false;
            }
            if ($maxPrice !== null && $price > $maxPrice) {
                return false;
            }
            if ($query === "") {
                return true;
            }
            $seller = strtolower((string) ($listing["seller_name"] ?? ""));
            if (str_contains($seller, $query)) {
                return true;
            }
            $item = ItemUtils::decodeItem((string) $listing["item_data"]);
            if (str_contains(strtolower($item->getName()), $query)) {
                return true;
            }
            $custom = strtolower($item->getCustomName());
            return $custom !== "" && str_contains($custom, $query);
        });

        $matches = array_values($matches);
        usort($matches, static function(array $a, array $b) use ($sort): int{
            return match ($sort) {
                "price_low" => ((int) $a["price"] <=> (int) $b["price"]) ?: ((int) $b["created_at"] <=> (int) $a["created_at"]),
                "price_high" => ((int) $b["price"] <=> (int) $a["price"]) ?: ((int) $b["created_at"] <=> (int) $a["created_at"]),
                "ending_soon" => ((int) $a["expires_at"] <=> (int) $b["expires_at"]) ?: ((int) $b["created_at"] <=> (int) $a["created_at"]),
                default => ((int) $b["created_at"] <=> (int) $a["created_at"]) ?: strcmp((string) $a["listing_id"], (string) $b["listing_id"])
            };
        });
        return $matches;
    }

    public function getListingDuration(): int
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "auctionhouse.yml");
        return max(60, GeneralUtils::parseTimeString((string) $config?->get("listing-duration", "60m")));
    }

    public function getGuideLines(): array
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "auctionhouse.yml");
        $lines = (array) ($config?->get("guide-lines", []));
        $duration = GeneralUtils::translateTime($this->getListingDuration());
        return array_map(static fn(string $line): string => str_replace("{duration}", $duration, $line), $lines);
    }

    public function getSellFeePercent(): float
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "auctionhouse.yml");
        return max(0.0, (float) ($config?->get("sell-fee-percent", 1.0) ?? 1.0));
    }

    public function getSellFeePercentFor(Player $player): float
    {
        if ($player->hasPermission("glacia.ah.fee.0_75")) {
            return 0.75;
        }

        return $this->getSellFeePercent();
    }

    public function getMaxPrice(): int
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "auctionhouse.yml");
        return max(1, (int) ($config?->get("max-price", 9000000000) ?? 9000000000));
    }

    public function calculateSellFee(int $price, ?Player $player = null): int
    {
        if ($price <= 0) {
            return 0;
        }

        $percent = $player !== null ? $this->getSellFeePercentFor($player) : $this->getSellFeePercent();
        return max(1, (int) round($price * ($percent / 100), 0));
    }

    public function getMaxListingsFor(Player $player): int
    {
        if ($player->hasPermission("glacia.ah.listings.6")) {
            return 6;
        }

        if ($player->hasPermission("glacia.ah.listings.5")) {
            return 5;
        }

        if ($player->hasPermission("glacia.ah.listings.3")) {
            return 3;
        }

        if ($player->hasPermission("glacia.ah.listings.2")) {
            return 2;
        }

        return 1;
    }

    public function canCreateListing(Player $player): bool
    {
        return count($this->getListingsForSeller($player->getUniqueId()->toString())) < $this->getMaxListingsFor($player);
    }

    public function createListing(Player $seller, Item $item, int $price): array
    {
        $listingId = Uuid::uuid4()->toString();
        $now = time();
        $duration = $this->getListingDuration();
        $listing = [
            "listing_id" => $listingId,
            "seller_uuid" => $seller->getUniqueId()->toString(),
            "seller_name" => $seller->getName(),
            "item_data" => ItemUtils::encodeItem($item),
            "price" => $price,
            "created_at" => $now,
            "expires_at" => $now + $duration
        ];

        $this->listings[$listingId] = $listing;
        $this->notifyWatchers($listing);

        Loader::getDatabase()->executeInsert(QueryStmts::AUCTION_HOUSE_LISTINGS_CREATE, $listing);
        return $listing;
    }

    public function purchaseListing(Player $buyer, string $listingId): string
    {
        $listing = $this->getListing($listingId);
        if ($listing === null) {
            return "That listing no longer exists.";
        }

        if ($listing["seller_uuid"] === $buyer->getUniqueId()->toString()) {
            return "You cannot buy your own listing.";
        }

        $buyerSession = Loader::getPlayerManager()->getSession($buyer);
        if ($buyerSession === null) {
            return "Could not load your player session.";
        }

        $price = (float) $listing["price"];
        if ($buyerSession->getBalance() < $price) {
            return "You do not have enough money.";
        }

        $item = $this->decodeStoredItem((string) $listing["item_data"]);
        if (!$buyer->getInventory()->canAddItem($item)) {
            return "Clear some inventory space first.";
        }

        $sellerSession = $this->getSessionByUuid((string) $listing["seller_uuid"]);
        if ($sellerSession !== null) {
            $sellerSession->addBalance($price);

            $seller = $sellerSession->getPlayer();
            if ($seller !== null && $seller->isOnline()) {
                $seller->sendMessage(\pocketmine\utils\TextFormat::colorize(
                    "&r&aYour auction for &f" . $item->getName() . " &ahas sold to &f" . $buyer->getName() . " &afor &f$" . number_format($price) . "&a."
                ));
            }
        }

        $buyerSession->removeBalance($price);
        $buyer->getInventory()->addItem($item);

        $this->deleteListing($listingId);
        return "";
    }

    public function cancelListing(Player $player, string $listingId): string
    {
        $listing = $this->getListing($listingId);
        if ($listing === null) {
            return "That listing no longer exists.";
        }

        if ($listing["seller_uuid"] !== $player->getUniqueId()->toString()) {
            return "That listing does not belong to you.";
        }

        $item = $this->decodeStoredItem((string) $listing["item_data"]);
        $this->addToCollection((string) $listing["seller_uuid"], $item, "cancelled");
        $this->deleteListing($listingId);
        return "";
    }

    public function claimCollectionEntry(Player $player, string $entryId): string
    {
        $entry = $this->collection[$entryId] ?? null;
        if ($entry === null) {
            return "That collection item no longer exists.";
        }

        if ($entry["owner_uuid"] !== $player->getUniqueId()->toString()) {
            return "That collection item does not belong to you.";
        }

        $item = $this->decodeStoredItem((string) $entry["item_data"]);
        if (!$player->getInventory()->canAddItem($item)) {
            return "Clear some inventory space first.";
        }

        $player->getInventory()->addItem($item);
        $this->deleteCollectionEntry($entryId);
        return "";
    }

    public function addToCollection(string $ownerUuid, Item $item, string $reason = "returned"): void
    {
        $entry = [
            "entry_id" => Uuid::uuid4()->toString(),
            "owner_uuid" => $ownerUuid,
            "item_data" => ItemUtils::encodeItem($item),
            "reason" => $reason,
            "created_at" => time()
        ];

        $this->collection[$entry["entry_id"]] = $entry;
        Loader::getDatabase()->executeInsert(QueryStmts::AUCTION_HOUSE_COLLECTION_CREATE, $entry);
    }

    public function adminRemoveSellerListings(string $sellerName, bool $returnItems): int
    {
        $removed = 0;
        foreach ($this->getListings() as $listing) {
            if (strcasecmp((string) $listing["seller_name"], $sellerName) !== 0) {
                continue;
            }

            if ($returnItems) {
                $this->addToCollection((string) $listing["seller_uuid"], $this->decodeStoredItem((string) $listing["item_data"]), "admin_return");
            }

            $this->deleteListing((string) $listing["listing_id"]);
            $removed++;
        }

        return $removed;
    }

    public function adminRemoveListing(string $listingId, bool $returnItem): string
    {
        $listing = $this->getListing($listingId);
        if ($listing === null) {
            return "That listing no longer exists.";
        }
        if ($returnItem) {
            $this->addToCollection((string) $listing["seller_uuid"], $this->decodeStoredItem((string) $listing["item_data"]), "admin_refund");
        }
        $this->deleteListing($listingId);
        return "";
    }

    public function relistCollectionEntry(Player $player, string $entryId, int $price): string
    {
        $entry = $this->collection[$entryId] ?? null;
        if ($entry === null) {
            return "That collection entry does not exist.";
        }
        if ($entry["owner_uuid"] !== $player->getUniqueId()->toString()) {
            return "That collection entry does not belong to you.";
        }
        if ($price <= 0 || $price > $this->getMaxPrice()) {
            return "Enter a valid relist price.";
        }
        if (!$this->canCreateListing($player)) {
            return "You have reached your listing limit.";
        }
        $item = $this->decodeStoredItem((string) $entry["item_data"]);
        $this->createListing($player, $item, $price);
        $this->deleteCollectionEntry($entryId);
        return "";
    }

    public function addWatchKeyword(Player $player, string $keyword): bool
    {
        $keyword = strtolower(trim($keyword));
        if ($keyword === "") {
            return false;
        }
        $uuid = $player->getUniqueId()->toString();
        $existing = $this->watchlists[$uuid] ?? [];
        if (in_array($keyword, $existing, true)) {
            return false;
        }
        $existing[] = $keyword;
        $this->watchlists[$uuid] = array_values(array_unique($existing));
        $this->saveWatchlists();
        return true;
    }

    public function removeWatchKeyword(Player $player, string $keyword): bool
    {
        $keyword = strtolower(trim($keyword));
        $uuid = $player->getUniqueId()->toString();
        $existing = $this->watchlists[$uuid] ?? [];
        $filtered = array_values(array_filter($existing, static fn(string $row): bool => $row !== $keyword));
        if (count($filtered) === count($existing)) {
            return false;
        }
        $this->watchlists[$uuid] = $filtered;
        $this->saveWatchlists();
        return true;
    }

    public function clearWatchlist(Player $player): void
    {
        unset($this->watchlists[$player->getUniqueId()->toString()]);
        $this->saveWatchlists();
    }

    /**
     * @return string[]
     */
    public function getWatchlist(Player $player): array
    {
        return $this->watchlists[$player->getUniqueId()->toString()] ?? [];
    }

    public function tick(): void
    {
        $now = time();
        foreach ($this->listings as $listingId => $listing) {
            if ((int) $listing["expires_at"] > $now) {
                continue;
            }

            $this->addToCollection((string) $listing["seller_uuid"], $this->decodeStoredItem((string) $listing["item_data"]), "expired");
            $this->deleteListing($listingId);
        }
    }

    private function deleteListing(string $listingId): void
    {
        unset($this->listings[$listingId]);
        Loader::getDatabase()->executeChange(QueryStmts::AUCTION_HOUSE_LISTINGS_DELETE, ["listing_id" => $listingId]);
    }

    private function deleteCollectionEntry(string $entryId): void
    {
        unset($this->collection[$entryId]);
        Loader::getDatabase()->executeChange(QueryStmts::AUCTION_HOUSE_COLLECTION_DELETE, ["entry_id" => $entryId]);
    }

    private function getSessionByUuid(string $uuid): ?GlaciaPlayer
    {
        try {
            return Loader::getPlayerManager()->getSessionByUuid(Uuid::fromString($uuid));
        } catch (\Throwable) {
            return null;
        }
    }

    private function decodeStoredItem(string $encoded): Item
    {
        return ItemUtils::decodeItem($encoded);
    }

    private function notifyWatchers(array $listing): void
    {
        $item = ItemUtils::decodeItem((string) $listing["item_data"]);
        $needle = strtolower($item->getName() . " " . $item->getCustomName() . " " . ((string) ($listing["seller_name"] ?? "")));
        $price = (int) ($listing["price"] ?? 0);
        foreach (Loader::getInstance()->getServer()->getOnlinePlayers() as $online) {
            $keywords = $this->watchlists[$online->getUniqueId()->toString()] ?? [];
            foreach ($keywords as $keyword) {
                if ($keyword !== "" && str_contains($needle, $keyword)) {
                    $online->sendMessage(C::colorize(
                        "&r&d[AH Watch] &fNew match for '&e{$keyword}&f': &b" . $item->getName() . " &7for &a$" . number_format($price)
                    ));
                    break;
                }
            }
        }
    }

    private function loadWatchlists(): void
    {
        if (!file_exists($this->watchlistFile)) {
            return;
        }
        $decoded = StorageUtils::readJsonFile($this->watchlistFile, "auction watchlists");
        if (!is_array($decoded)) {
            return;
        }
        $rows = (array) ($decoded["watchlists"] ?? []);
        foreach ($rows as $uuid => $keywords) {
            if (!is_array($keywords)) {
                continue;
            }
            $this->watchlists[(string) $uuid] = array_values(array_filter(array_map("strval", $keywords), static fn(string $s): bool => trim($s) !== ""));
        }
    }

    private function saveWatchlists(): void
    {
        StorageUtils::writeJsonFile($this->watchlistFile, [
            "watchlists" => $this->watchlists
        ], "auction watchlists");
    }
}
