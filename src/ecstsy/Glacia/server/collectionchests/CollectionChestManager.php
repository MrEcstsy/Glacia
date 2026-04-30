<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\collectionchests;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\economy\SellHandler;
use ecstsy\Glacia\server\quests\QuestInstance;
use ecstsy\Glacia\utils\StorageUtils;
use ecstsy\Glacia\utils\screens\collectionchests\CollectionChestScreen;
use ecstsy\MartianUtilities\utils\ItemUtils;
use pocketmine\block\Chest as ChestBlock;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat as C;
use pocketmine\world\Position;

final class CollectionChestManager {

    /** @var array<string, array<string, mixed>> */
    private array $entries = [];
    /** @var array<string, string> */
    private array $chunkIndex = [];
    private string $file;
    private bool $dirty = false;

    public function __construct()
    {
        $this->file = Loader::getInstance()->getDataFolder() . "collection_chests.json";
        $this->load();
    }

    public function save(): void
    {
        $this->persist();
    }

    public function registerChest(Position $position, string $factionId): bool
    {
        $chunkKey = $this->chunkKeyFromPosition($position);
        if (isset($this->chunkIndex[$chunkKey])) {
            return false;
        }

        $positionKey = $this->positionKey($position);
        $this->entries[$positionKey] = [
            "world" => $position->getWorld()->getFolderName(),
            "x" => $position->getFloorX(),
            "y" => $position->getFloorY(),
            "z" => $position->getFloorZ(),
            "faction_id" => $factionId,
            "items" => [],
            "xp" => 0
        ];
        $this->chunkIndex[$chunkKey] = $positionKey;
        $this->persist();
        CollectionChestScreen::refreshFor($position);

        return true;
    }

    public function removeChest(Position $position): ?array
    {
        $positionKey = $this->positionKey($position);
        $entry = $this->entries[$positionKey] ?? null;
        if ($entry === null) {
            return null;
        }

        unset($this->entries[$positionKey], $this->chunkIndex[$this->chunkKeyFromPosition($position)]);
        $this->persist();
        CollectionChestScreen::refreshFor($position);

        return $entry;
    }

    public function removeAllForFaction(string $factionId): void
    {
        $factionId = trim($factionId);
        if ($factionId === "") {
            return;
        }

        $changed = false;
        foreach ($this->entries as $positionKey => $entry) {
            if ((string) ($entry["faction_id"] ?? "") !== $factionId) {
                continue;
            }

            unset($this->chunkIndex[$this->chunkKeyFromEntry($entry)], $this->entries[$positionKey]);
            $changed = true;
        }

        if ($changed) {
            $this->persist();
        }
    }

    public function getChestAt(Position $position): ?array
    {
        $entry = $this->entries[$this->positionKey($position)] ?? null;
        if ($entry === null) {
            return null;
        }

        if (!$this->validateEntry($entry)) {
            $this->cleanupEntry($entry);
            return null;
        }

        return $entry;
    }

    public function getChunkChestAt(Position $position): ?array
    {
        $chunkKey = $this->chunkKeyFromPosition($position);
        $positionKey = $this->chunkIndex[$chunkKey] ?? null;
        if ($positionKey === null) {
            return null;
        }

        $entry = $this->entries[$positionKey] ?? null;
        if ($entry === null) {
            unset($this->chunkIndex[$chunkKey]);
            $this->persist();
            return null;
        }

        if (!$this->validateEntry($entry)) {
            $this->cleanupEntry($entry);
            return null;
        }

        return $entry;
    }

    public function addDropsToChunk(Position $deathPosition, array $drops, int $xp): bool
    {
        $entry = $this->getChunkChestAt($deathPosition);
        if ($entry === null) {
            return false;
        }

        $positionKey = $this->positionKeyFromEntry($entry);

        foreach ($drops as $drop) {
            if (!$drop instanceof Item || $drop->isNull() || $drop->getCount() <= 0) {
                continue;
            }

            $this->storeItemByKey($positionKey, $drop);
        }

        if ($xp > 0) {
            $this->entries[$positionKey]["xp"] = (int) $this->entries[$positionKey]["xp"] + $xp;
        }

        $this->persist();
        CollectionChestScreen::refreshFor($this->positionFromEntry($this->entries[$positionKey]));
        return true;
    }

    public function getDisplayEntries(Position $position): array
    {
        $entry = $this->getChestAt($position);
        if ($entry === null) {
            return [];
        }

        $positionKey = $this->positionKeyFromEntry($entry);
        $display = [];

        foreach (($entry["items"] ?? []) as $fingerprint => $stored) {
            $item = $this->decodeStoredItem($positionKey, (string) $fingerprint, (string) ($stored["item_data"] ?? ""));
            if ($item === null) {
                continue;
            }

            $count = (int) ($stored["count"] ?? 0);
            $sellPrice = SellHandler::getSellPrice($item->getTypeId());

            $item->setCount(min($item->getMaxStackSize(), max(1, $count)));
            if ($item->hasCustomName()) {
                $item->setCustomName(C::RESET . $item->getCustomName());
            }

            $lore = $item->getLore();
            $lore[] = C::RESET;
            $lore[] = C::colorize("&r&8------------------------");
            $lore[] = C::colorize("&r&7Stored: &f" . number_format($count));
            if ($sellPrice !== null) {
                $lore[] = C::colorize("&r&7Sell Value: &a$" . number_format($sellPrice * $count, 2));
            }
            $lore[] = C::colorize("&r&eClick to withdraw.");
            $lore[] = C::colorize("&r&8------------------------");
            $item->setLore($lore);

            $display[] = [
                "fingerprint" => $fingerprint,
                "count" => $count,
                "item" => $item
            ];
        }

        $this->syncIfDirty($position);

        usort($display, static fn(array $a, array $b): int => strcmp($a["item"]->getName(), $b["item"]->getName()));
        return $display;
    }

    public function getStoredXp(Position $position): int
    {
        return (int) (($this->getChestAt($position)["xp"] ?? 0) ?: 0);
    }

    public function claimStoredXp(Position $position, Player $player): int
    {
        $entry = $this->getChestAt($position);
        if ($entry === null) {
            return 0;
        }

        $positionKey = $this->positionKeyFromEntry($entry);
        $amount = (int) ($this->entries[$positionKey]["xp"] ?? 0);
        if ($amount <= 0) {
            return 0;
        }

        $this->entries[$positionKey]["xp"] = 0;
        $this->persist();
        CollectionChestScreen::refreshFor($position);

        $player->getXpManager()->addXp($amount);
        return $amount;
    }

    public function withdrawEntry(Position $position, string $fingerprint): array
    {
        $entry = $this->getChestAt($position);
        if ($entry === null) {
            return [];
        }

        $positionKey = $this->positionKeyFromEntry($entry);
        $stored = $this->entries[$positionKey]["items"][$fingerprint] ?? null;
        if ($stored === null) {
            return [];
        }

        unset($this->entries[$positionKey]["items"][$fingerprint]);
        $this->persist();
        CollectionChestScreen::refreshFor($position);

        $template = $this->decodeStoredItem($positionKey, $fingerprint, (string) ($stored["item_data"] ?? ""));
        if ($template === null) {
            $this->syncIfDirty($position);
            return [];
        }

        $remaining = (int) $stored["count"];
        $items = [];

        while ($remaining > 0) {
            $stack = clone $template;
            $stackSize = min($stack->getMaxStackSize(), $remaining);
            $stack->setCount($stackSize);
            $items[] = $stack;
            $remaining -= $stackSize;
        }

        return $items;
    }

    public function sellAll(Position $position, Player $player): array
    {
        $entry = $this->getChestAt($position);
        if ($entry === null) {
            return ["items" => 0, "money" => 0.0];
        }

        $positionKey = $this->positionKeyFromEntry($entry);
        $session = Loader::getPlayerManager()->getSession($player);
        $multiplier = $session?->getSellMultiplier() ?? 1.0;
        $factionMultiplier = 1.0;
        $factionId = (string) ($entry["faction_id"] ?? "");

        if ($factionId !== "") {
            $factionMultiplier = Loader::getFactionUpgradeManager()->getCollectionSellMultiplier(
                Loader::getFactionManager()->getFaction($factionId)
            );
        }

        $multiplier *= $factionMultiplier;
        $totalEarned = 0.0;
        $soldItems = 0;

        foreach (($this->entries[$positionKey]["items"] ?? []) as $fingerprint => $stored) {
            $item = $this->decodeStoredItem($positionKey, (string) $fingerprint, (string) ($stored["item_data"] ?? ""));
            if ($item === null) {
                continue;
            }

            $count = (int) ($stored["count"] ?? 0);
            $sellPrice = SellHandler::getSellPrice($item->getTypeId());
            if ($sellPrice === null || $count <= 0) {
                continue;
            }

            $soldItems += $count;
            $totalEarned += round(($sellPrice * $count) * $multiplier, 2);
            QuestInstance::onSell($player, $item, $count);
            unset($this->entries[$positionKey]["items"][$fingerprint]);
        }

        if ($totalEarned > 0) {
            $session?->addBalance($totalEarned);
            $this->persist();
            CollectionChestScreen::refreshFor($position);
        } else {
            $this->syncIfDirty($position);
        }

        return [
            "items" => $soldItems,
            "money" => round($totalEarned, 2),
            "multiplier" => $multiplier
        ];
    }

    public function restoreOverflow(Position $position, Item $item): void
    {
        $entry = $this->getChestAt($position);
        if ($entry === null) {
            return;
        }

        $this->storeItemByKey($this->positionKeyFromEntry($entry), $item);
        $this->persist();
        CollectionChestScreen::refreshFor($position);
    }

    private function storeItemByKey(string $positionKey, Item $item): void
    {
        $normalized = clone $item;
        $count = $normalized->getCount();
        $normalized->setCount(1);
        $fingerprint = ItemUtils::encodeItem($normalized);
        if (!is_string($fingerprint) || $fingerprint === "") {
            return;
        }

        if (!isset($this->entries[$positionKey]["items"][$fingerprint])) {
            $this->entries[$positionKey]["items"][$fingerprint] = [
                "item_data" => $fingerprint,
                "count" => 0
            ];
        }

        $this->entries[$positionKey]["items"][$fingerprint]["count"] = (int) $this->entries[$positionKey]["items"][$fingerprint]["count"] + $count;
        $this->dirty = true;
    }

    private function decodeStoredItem(string $positionKey, string $fingerprint, string $encoded): ?Item
    {
        try {
            return ItemUtils::decodeItem($encoded);
        } catch (\Throwable) {
            unset($this->entries[$positionKey]["items"][$fingerprint]);
            $this->dirty = true;
            return null;
        }
    }

    private function validateEntry(array $entry): bool
    {
        $world = Server::getInstance()->getWorldManager()->getWorldByName((string) $entry["world"]);
        if ($world === null) {
            return false;
        }

        $block = $world->getBlockAt((int) $entry["x"], (int) $entry["y"], (int) $entry["z"]);
        if (!$block instanceof ChestBlock) {
            return false;
        }

        return true;
    }

    private function cleanupEntry(array $entry): void
    {
        $positionKey = $this->positionKeyFromEntry($entry);
        unset($this->entries[$positionKey], $this->chunkIndex[$this->chunkKeyFromEntry($entry)]);
        $this->persist();
    }

    private function load(): void
    {
        if (!is_file($this->file)) {
            return;
        }

        $decoded = StorageUtils::readJsonFile($this->file, "collection chest state");
        if (!is_array($decoded)) {
            return;
        }

        foreach ($decoded as $entry) {
            if (!is_array($entry) || !isset($entry["world"], $entry["x"], $entry["y"], $entry["z"], $entry["faction_id"])) {
                continue;
            }

            $positionKey = $this->positionKeyFromEntry($entry);
            $this->entries[$positionKey] = [
                "world" => (string) $entry["world"],
                "x" => (int) $entry["x"],
                "y" => (int) $entry["y"],
                "z" => (int) $entry["z"],
                "faction_id" => (string) $entry["faction_id"],
                "items" => is_array($entry["items"] ?? null) ? $entry["items"] : [],
                "xp" => (int) ($entry["xp"] ?? 0)
            ];
            $this->chunkIndex[$this->chunkKeyFromEntry($this->entries[$positionKey])] = $positionKey;
        }
    }

    private function persist(): void
    {
        if (StorageUtils::writeJsonFile($this->file, array_values($this->entries), "collection chest state")) {
            $this->dirty = false;
        }
    }

    private function syncIfDirty(Position $position): void
    {
        if (!$this->dirty) {
            return;
        }

        $this->persist();
        CollectionChestScreen::refreshFor($position);
    }

    private function positionFromEntry(array $entry): Position
    {
        $world = Server::getInstance()->getWorldManager()->getWorldByName((string) $entry["world"]);
        if ($world === null) {
            throw new \RuntimeException("Collection chest world is not loaded.");
        }

        return new Position((int) $entry["x"], (int) $entry["y"], (int) $entry["z"], $world);
    }

    private function positionKey(Position $position): string
    {
        return $position->getWorld()->getFolderName() . ":" . $position->getFloorX() . ":" . $position->getFloorY() . ":" . $position->getFloorZ();
    }

    private function positionKeyFromEntry(array $entry): string
    {
        return $entry["world"] . ":" . (int) $entry["x"] . ":" . (int) $entry["y"] . ":" . (int) $entry["z"];
    }

    private function chunkKeyFromPosition(Position $position): string
    {
        return $position->getWorld()->getFolderName() . ":" . ($position->getFloorX() >> 4) . ":" . ($position->getFloorZ() >> 4);
    }

    private function chunkKeyFromEntry(array $entry): string
    {
        return $entry["world"] . ":" . ((int) $entry["x"] >> 4) . ":" . ((int) $entry["z"] >> 4);
    }
}
