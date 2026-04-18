<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\auctionhouse;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\player\GlaciaPlayer;
use ecstsy\Glacia\utils\QueryStmts;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianUtilities\utils\ItemUtils;
use pocketmine\item\Item;
use pocketmine\player\Player;
use Ramsey\Uuid\Uuid;

final class AuctionHouseManager {

    /** @var array<string, array<string, mixed>> */
    private array $listings = [];

    /** @var array<string, array<string, mixed>> */
    private array $collection = [];

    public function __construct()
    {
        $this->load();
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
        return count($this->getCollectionEntries($ownerUuid));
    }

    public function getListing(string $listingId): ?array
    {
        return $this->listings[$listingId] ?? null;
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
        $listing = [
            "listing_id" => $listingId,
            "seller_uuid" => $seller->getUniqueId()->toString(),
            "seller_name" => $seller->getName(),
            "item_data" => ItemUtils::encodeItem($item),
            "price" => $price,
            "created_at" => $now,
            "expires_at" => $now + $this->getListingDuration()
        ];

        $this->listings[$listingId] = $listing;

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

        $item = ItemUtils::decodeItem((string) $listing["item_data"]);
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

        $item = ItemUtils::decodeItem((string) $listing["item_data"]);
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

        $item = ItemUtils::decodeItem((string) $entry["item_data"]);
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
                $this->addToCollection((string) $listing["seller_uuid"], ItemUtils::decodeItem((string) $listing["item_data"]), "admin_return");
            }

            $this->deleteListing((string) $listing["listing_id"]);
            $removed++;
        }

        return $removed;
    }

    public function tick(): void
    {
        $now = time();
        foreach ($this->listings as $listingId => $listing) {
            if ((int) $listing["expires_at"] > $now) {
                continue;
            }

            $this->addToCollection((string) $listing["seller_uuid"], ItemUtils::decodeItem((string) $listing["item_data"]), "expired");
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
        foreach (Loader::getPlayerManager()->getSessions() as $session) {
            if ($session->getUuid()->toString() === $uuid) {
                return $session;
            }
        }

        return null;
    }
}
