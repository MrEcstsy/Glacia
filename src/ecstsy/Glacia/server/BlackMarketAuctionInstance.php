<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\blackmarket\BlackMarketAuctionManager;
use ecstsy\Glacia\utils\QueryStmts;
use ecstsy\Glacia\utils\StorageUtils;
use ecstsy\MartianUtilities\utils\ItemUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\TextFormat;

final class BlackMarketAuctionInstance {
    use SingletonTrait;

    private bool $enabled = false;
    private int $nextAuctionTime = 0;
    private int $currentBid = 0;
    private int $startingBid = 0;
    private int $endTime = 0;
    private string $highestBidder = "";
    private string $listingName = "";
    private ?Item $currentItem = null;
    private array $history = [];

    public function __construct() {
        self::setInstance($this);
        $this->load();
    }

    private function load(): void {
        $db = Loader::getDatabase();
        $db->executeSelect(QueryStmts::SERVER_GLACIA_SELECT, [], function(array $rows): void {
            foreach ($rows as $row) {
                switch ($row['key'] ?? '') {
                    case GlaciaServerKeys::BAH_ACTIVE:
                        $this->enabled = (bool) $row['value'];
                        break;

                    case GlaciaServerKeys::BAH_NEXT_AUCTION:
                        $this->nextAuctionTime = (int) $row['value'];
                        break;

                    case GlaciaServerKeys::BAH_HISTORY:
                        $this->history = StorageUtils::decodeJsonString((string) $row['value'], "black market auction history") ?? [];
                        break;
                }
            }

            if ($this->nextAuctionTime <= 0) {
                $this->startCountdown(1800);
            }
        });
    }

    public function isEnabled(): bool {
        return $this->enabled;
    }

    public function hasStarted(): bool {
        return $this->enabled && $this->currentItem !== null && $this->endTime > time();
    }

    public function isCountingDown(): bool {
        return $this->enabled && $this->currentItem === null && $this->nextAuctionTime > time();
    }

    public function tick(): void {
        if (!$this->enabled) {
            $this->startCountdown(BlackMarketAuctionManager::getInstance()->getInterval());
            return;
        }

        if ($this->currentItem !== null && $this->endTime > 0 && $this->endTime <= time()) {
            $this->finishAuction();
            $this->startCountdown(BlackMarketAuctionManager::getInstance()->getInterval());
            return;
        }

        if ($this->currentItem !== null && $this->endTime > time()) {
            return;
        }

        if ($this->currentItem === null && $this->nextAuctionTime > 0 && time() >= $this->nextAuctionTime) {
            $this->startAuction();
            return;
        }

        if ($this->currentItem === null && $this->nextAuctionTime <= 0) {
            $this->startCountdown(BlackMarketAuctionManager::getInstance()->getInterval());
        }
    }

    public function startAuction(): void {
        $listing = BlackMarketAuctionManager::getInstance()->rollListing();
        if ($listing === null) {
            $this->startCountdown(60);
            return;
        }

        $this->currentBid = 0;
        $this->startingBid = $listing->getStartingBid();
        $this->endTime = time() + BlackMarketAuctionManager::getInstance()->getAuctionDuration();
        $this->highestBidder = "";
        $this->listingName = self::sanitizeText($listing->getName());
        $this->currentItem = $listing->getItem();
        $this->nextAuctionTime = 0;

        $this->save(GlaciaServerKeys::BAH_ACTIVE, "1");
        $this->save(GlaciaServerKeys::BAH_NEXT_AUCTION, "0");

        $message = "&r&l&6BAH &r&8» &eA new item is up for auction: {$this->listingName} &7(Starting Bid: &a$" . number_format($this->startingBid) . "&7)";
        BlackMarketAuctionManager::getInstance()->messageAll(TextFormat::colorize(self::sanitizeText($message)));
    }

    public function finishAuction(): void {
        $winner = $this->highestBidder;
        $finalBid = $this->currentBid;
        $listingName = $this->listingName;
        $item = $this->currentItem !== null ? clone $this->currentItem : null;

        if ($item !== null && $winner !== "") {
            $this->addHistoryEntry($listingName, $item, $winner, $finalBid, $this->startingBid, true);
            BlackMarketAuctionManager::getInstance()->giveAuctionToWinner($winner, $item, $finalBid);

            $message = "&r&l&6BAH &r&8» &e{$winner} &ewon {$listingName} &efor &a$" . number_format($finalBid) . " &7(Starting Bid: &a$" . number_format($this->startingBid) . "&7)";
            BlackMarketAuctionManager::getInstance()->messageAll(TextFormat::colorize(self::sanitizeText($message)));
        } else {
            if ($item !== null || $listingName !== "") {
                $this->addHistoryEntry($listingName, $item, "", 0, $this->startingBid, false);
            }

            $message = "&r&l&6BAH &r&8» &eThe Black Market Auction ended with no bids.";
            BlackMarketAuctionManager::getInstance()->messageAll(TextFormat::colorize(self::sanitizeText($message)));
        }

        $this->currentBid = 0;
        $this->startingBid = 0;
        $this->endTime = 0;
        $this->highestBidder = "";
        $this->listingName = "";
        $this->currentItem = null;
    }

    public function getNextAuctionTime(): int {
        return $this->nextAuctionTime;
    }

    public function getRemaining(): int {
        if ($this->hasStarted()) {
            return $this->getAuctionTimeLeft();
        }

        return max(0, $this->nextAuctionTime - time());
    }

    public function startCountdown(int $durationSeconds): void {
        if ($durationSeconds <= 0) {
            return;
        }

        $this->enabled = true;
        $this->nextAuctionTime = time() + $durationSeconds;

        $this->currentBid = 0;
        $this->startingBid = 0;
        $this->endTime = 0;
        $this->highestBidder = "";
        $this->listingName = "";
        $this->currentItem = null;

        $this->save(GlaciaServerKeys::BAH_ACTIVE, "1");
        $this->save(GlaciaServerKeys::BAH_NEXT_AUCTION, (string) $this->nextAuctionTime);
    }

    public function disable(): void {
        $this->enabled = false;
        $this->nextAuctionTime = 0;

        $this->currentBid = 0;
        $this->startingBid = 0;
        $this->endTime = 0;
        $this->highestBidder = "";
        $this->listingName = "";
        $this->currentItem = null;

        $this->save(GlaciaServerKeys::BAH_ACTIVE, "0");
        $this->save(GlaciaServerKeys::BAH_NEXT_AUCTION, "0");
    }

    private function save(string $key, string $value): void {
        Loader::getDatabase()->executeChange(QueryStmts::SERVER_GLACIA_CREATE, [
            'key' => $key,
            'value' => $value
        ]);
    }

    public function setCurrentBid(int $bid): void {
        $this->currentBid = max(0, $bid);
    }

    public function getCurrentBid(): int {
        return $this->currentBid;
    }

    public function setStartingBid(int $bid): void {
        $this->startingBid = max(0, $bid);
    }

    public function getStartingBid(): int {
        return $this->startingBid;
    }

    public function setEndTime(int $time): void {
        $this->endTime = max(0, $time);
    }

    public function getEndTime(): int {
        return $this->endTime;
    }

    public function getAuctionTimeLeft(): int {
        return max(0, $this->endTime - time());
    }

    public function setHighestBidder(string $name): void {
        $this->highestBidder = $name;
    }

    public function getHighestBidder(): string {
        return $this->highestBidder;
    }

    public function setListingName(string $name): void {
        $this->listingName = self::sanitizeText($name);
    }

    public function getListingName(): string {
        return $this->listingName;
    }

    public function setCurrentItem(?Item $item): void {
        $this->currentItem = $item !== null ? clone $item : null;
    }

    public function getCurrentItem(): ?Item {
        return $this->currentItem !== null ? clone $this->currentItem : null;
    }

    public function hasActiveAuction(): bool {
        return $this->enabled && $this->currentItem !== null && $this->endTime > time();
    }

    public function getNextBidAmount(): int {
        if ($this->currentBid <= 0) {
            return $this->startingBid;
        }

        return $this->currentBid + $this->getBidIncrement();
    }

    public function getBidIncrement(): int {
        return BlackMarketAuctionManager::getInstance()->getBidIncrement($this->currentBid > 0 ? $this->currentBid : $this->startingBid);
    }

    public function hasBids(): bool {
        return $this->currentBid > 0;
    }

    public function placeBid(Player $player): bool {
        if (!$this->isEnabled() || !$this->hasStarted() || $this->currentItem === null) {
            return false;
        }

        $session = PlayerManager::getInstance()->getSession($player);
        if ($session === null) {
            return false;
        }

        if ($this->highestBidder !== "" && strcasecmp($this->highestBidder, $player->getName()) === 0) {
            $player->sendMessage(TextFormat::colorize("&r&cYou are already the highest bidder."));
            return false;
        }

        $amount = $this->getNextBidAmount();
        if ($session->getBalance() < $amount) {
            $player->sendMessage(TextFormat::colorize("&r&cYou do not have enough money to bid &f$" . number_format($amount) . "&r&c."));
            return false;
        }

        $previousBid = $this->currentBid;
        $previousBidder = $this->highestBidder;
        if ($previousBidder !== "") {
            $previousPlayer = PlayerUtils::getPlayerByPrefix($previousBidder);
            if ($previousPlayer !== null && $previousPlayer->isOnline()) {
                $previousSession = PlayerManager::getInstance()->getSession($previousPlayer);
                if ($previousSession !== null) {
                    $previousSession->addBalance($previousBid);
                    $previousPlayer->sendMessage(TextFormat::colorize("&r&eYou have been outbid on the Black Market Auction."));
                }
            }
        }

        $session->removeBalance($amount);
        $this->currentBid = $amount;
        $this->highestBidder = $player->getName();

        $antiSnipe = BlackMarketAuctionManager::getInstance()->getAntiSnipeSeconds();
        $extended = false;
        if ($this->getAuctionTimeLeft() <= $antiSnipe) {
            $this->endTime += $antiSnipe;
            $extended = true;
        }

        $message = "&r&l&6BAH &r&8» &e{$player->getName()} &ebid &a$" . number_format($amount) . " &eon &f{$this->listingName}" . ($extended ? " &7(+{$antiSnipe}s anti-snipe)" : "");
        BlackMarketAuctionManager::getInstance()->messageAll(TextFormat::colorize(self::sanitizeText($message)));
        return true;
    }

    public function setNextAuctionTime(int $time): void {
        $this->nextAuctionTime = max(0, $time);
        $this->save(GlaciaServerKeys::BAH_ACTIVE, $this->enabled ? "1" : "0");
        $this->save(GlaciaServerKeys::BAH_NEXT_AUCTION, (string) $this->nextAuctionTime);
    }

    public function getHistory(): array {
        return $this->history;
    }

    public function clearHistory(): void {
        $this->history = [];
        $payload = StorageUtils::encodeJsonString([], "black market auction history");
        if ($payload !== null) {
            $this->save(GlaciaServerKeys::BAH_HISTORY, $payload);
        }
    }

    private function addHistoryEntry(string $listingName, ?Item $item, string $winner, int $finalBid, int $startingBid, bool $sold): void {
        $entry = [
            'name' => self::sanitizeText($listingName !== '' ? $listingName : ($item?->getName() ?? 'Unknown Item')),
            'item_data' => $item !== null ? ItemUtils::encodeItem($item) : null,
            'winner' => $winner,
            'final_bid' => $finalBid,
            'starting_bid' => $startingBid,
            'sold' => $sold ? 1 : 0,
            'ended_at' => time()
        ];

        array_unshift($this->history, $entry);
        $limit = BlackMarketAuctionManager::getInstance()->getHistoryLimit();
        if (count($this->history) > $limit) {
            $this->history = array_slice($this->history, 0, $limit);
        }

        $payload = StorageUtils::encodeJsonString($this->history, "black market auction history");
        if ($payload !== null) {
            $this->save(GlaciaServerKeys::BAH_HISTORY, $payload);
        }
    }

    private static function sanitizeText(string $text): string
    {
        if (preg_match('//u', $text) === 1) {
            return $text;
        }

        if (function_exists('iconv')) {
            $converted = iconv('UTF-8', 'UTF-8//IGNORE', $text);
            if (is_string($converted) && preg_match('//u', $converted) === 1) {
                return $converted;
            }
        }

        $converted = utf8_encode(utf8_decode($text));
        if (preg_match('//u', $converted) === 1) {
            return $converted;
        }

        return preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '', $text) ?? '';
    }
}
