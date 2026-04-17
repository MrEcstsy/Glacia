<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\blackmarket;

use pocketmine\item\Item;

final class BlackMarketAuctionListing {

    public function __construct(
        private string $key,
        private string $name,
        private Item $item,
        private int $startingBid
    ) {}

    public function getKey(): string {
        return $this->key;
    }

    public function getName(): string {
        return $this->name;
    }

    public function getItem(): Item {
        return clone $this->item;
    }

    public function getStartingBid(): int {
        return $this->startingBid;
    }
}