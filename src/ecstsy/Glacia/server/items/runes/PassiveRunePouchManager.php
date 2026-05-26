<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\items\runes;

use ecstsy\MartianUtilities\utils\ItemUtils;
use pocketmine\item\Item;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;

final class PassiveRunePouchManager
{
    public const FREE_SLOTS = 3;
    public const MAX_SLOTS = 9;

    private const GLACIA_ITEM_ID = "passive_rune_pouch";
    private const TAG_POUCH_ID = "pouch_id";
    private const TAG_UNLOCKED_SLOTS = "unlocked_slots";
    private const TAG_ITEMS = "stored_items";

    /** @var array<int, int> unlocked slot count => next unlock cost */
    private const UNLOCK_COSTS = [
        4 => 250000,
        5 => 500000,
        6 => 1000000,
        7 => 2500000,
        8 => 5000000,
        9 => 10000000,
    ];

    public function initializePouchItem(Item $item): Item
    {
        if (!$this->isPouchItem($item)) {
            return $item;
        }

        $root = $item->getNamedTag();
        $glacia = $root->getCompoundTag("Glacia") ?? new CompoundTag();
        if ($glacia->getString(self::TAG_POUCH_ID, "") === "") {
            $glacia->setString(self::TAG_POUCH_ID, bin2hex(random_bytes(8)));
        }
        if (!$glacia->getTag(self::TAG_UNLOCKED_SLOTS)) {
            $glacia->setInt(self::TAG_UNLOCKED_SLOTS, self::FREE_SLOTS);
        }
        if (!($glacia->getTag(self::TAG_ITEMS) instanceof CompoundTag)) {
            $glacia->setTag(self::TAG_ITEMS, new CompoundTag());
        }

        $root->setTag("Glacia", $glacia);
        $item->setNamedTag($root);
        return $item;
    }

    public function isPouchItem(Item $item): bool
    {
        $glacia = $item->getNamedTag()->getCompoundTag("Glacia");
        return strtolower($glacia?->getString("glaciaItem", "") ?? "") === self::GLACIA_ITEM_ID;
    }

    public function getPouchId(Item $item): string
    {
        return $item->getNamedTag()->getCompoundTag("Glacia")?->getString(self::TAG_POUCH_ID, "") ?? "";
    }

    public function getUnlockedSlots(Item $item): int
    {
        if (!$this->isPouchItem($item)) {
            return self::FREE_SLOTS;
        }

        $item = $this->initializePouchItem(clone $item);
        $glacia = $item->getNamedTag()->getCompoundTag("Glacia");
        return max(self::FREE_SLOTS, min(self::MAX_SLOTS, (int) ($glacia?->getInt(self::TAG_UNLOCKED_SLOTS, self::FREE_SLOTS) ?? self::FREE_SLOTS)));
    }

    public function getNextUnlockCost(Item $item): ?int
    {
        $nextUnlocked = $this->getUnlockedSlots($item) + 1;
        return self::UNLOCK_COSTS[$nextUnlocked] ?? null;
    }

    public function unlockNextSlot(Player $player, Item $item): ?Item
    {
        if (!$this->isPouchItem($item)) {
            return null;
        }

        $cost = $this->getNextUnlockCost($item);
        if ($cost === null) {
            return null;
        }

        $session = \ecstsy\Glacia\Loader::getPlayerManager()->getSession($player);
        if ($session === null || $session->getBalance() < $cost) {
            return null;
        }

        $session->removeBalance($cost);
        $item = $this->initializePouchItem(clone $item);
        $root = $item->getNamedTag();
        $glacia = $root->getCompoundTag("Glacia") ?? new CompoundTag();
        $glacia->setInt(self::TAG_UNLOCKED_SLOTS, min(self::MAX_SLOTS, $this->getUnlockedSlots($item) + 1));
        $root->setTag("Glacia", $glacia);
        $item->setNamedTag($root);
        return $this->refreshPouchMeta($item);
    }

    /**
     * @return array<int, Item>
     */
    public function getStoredContents(Item $item): array
    {
        if (!$this->isPouchItem($item)) {
            return [];
        }

        $item = $this->initializePouchItem(clone $item);
        $glacia = $item->getNamedTag()->getCompoundTag("Glacia");
        $itemsTag = $glacia?->getCompoundTag(self::TAG_ITEMS);
        if (!$itemsTag instanceof CompoundTag) {
            return [];
        }

        $decoded = [];
        foreach ($itemsTag->getValue() as $slot => $tag) {
            $slot = (int) $slot;
            if ($slot < 0 || $slot >= self::MAX_SLOTS) {
                continue;
            }

            $itemData = $itemsTag->getString((string) $slot, "");
            if ($itemData === "") {
                continue;
            }

            try {
                $stored = ItemUtils::decodeItem($itemData);
            } catch (\Throwable) {
                continue;
            }

            if (!$this->isAllowedRuneItem($stored)) {
                continue;
            }

            $decoded[$slot] = $stored;
        }

        ksort($decoded);
        return $decoded;
    }

    /**
     * @return list<Item>
     */
    public function getActiveContents(Player $player): array
    {
        $slot = $this->findFirstPouchSlot($player);
        if ($slot === null) {
            return [];
        }

        return array_values($this->getStoredContents($player->getInventory()->getItem($slot)));
    }

    /**
     * @param array<int, Item> $contents
     */
    public function saveContents(Item $item, array $contents): Item
    {
        if (!$this->isPouchItem($item)) {
            return $item;
        }

        $item = $this->initializePouchItem(clone $item);
        $unlocked = $this->getUnlockedSlots($item);
        $encoded = new CompoundTag();

        foreach ($contents as $slot => $stored) {
            $slot = (int) $slot;
            if ($slot < 0 || $slot >= $unlocked || !$stored instanceof Item || $stored->isNull() || !$this->isAllowedRuneItem($stored)) {
                continue;
            }

            $encoded->setString((string) $slot, ItemUtils::encodeItem($stored));
        }

        $root = $item->getNamedTag();
        $glacia = $root->getCompoundTag("Glacia") ?? new CompoundTag();
        $glacia->setTag(self::TAG_ITEMS, $encoded);
        $root->setTag("Glacia", $glacia);
        $item->setNamedTag($root);
        return $this->refreshPouchMeta($item);
    }

    public function isAllowedRuneItem(Item $item): bool
    {
        if ($item->isNull()) {
            return false;
        }

        $glacia = $item->getNamedTag()->getCompoundTag("Glacia");
        if ($glacia === null) {
            return false;
        }

        if (strtolower($glacia->getString("rune_mode", "")) !== "passive") {
            return false;
        }

        $glaciaItem = strtolower($glacia->getString("glaciaItem", ""));
        return $glaciaItem === "rune" || $glaciaItem === "pet_duration_rune";
    }

    public function findFirstPouchSlot(Player $player): ?int
    {
        foreach ($player->getInventory()->getContents() as $slot => $item) {
            if ($this->isPouchItem($item)) {
                return (int) $slot;
            }
        }

        return null;
    }

    public function findPouchSlotById(Player $player, string $pouchId): ?int
    {
        if ($pouchId === "") {
            return null;
        }

        foreach ($player->getInventory()->getContents() as $slot => $item) {
            if ($this->isPouchItem($item) && $this->getPouchId($item) === $pouchId) {
                return (int) $slot;
            }
        }

        return null;
    }

    public function refreshPouchMeta(Item $item): Item
    {
        if (!$this->isPouchItem($item)) {
            return $item;
        }

        $unlocked = $this->getUnlockedSlots($item);
        $stored = count($this->getStoredContents($item));
        $item->setCustomName(\pocketmine\utils\TextFormat::colorize("&r&l&aPassive Rune Pouch &8[&f{$unlocked}&7/&f" . self::MAX_SLOTS . "&8]"));
        $item->setLore([
            \pocketmine\utils\TextFormat::colorize("&r&7Stores passive runes inside this pouch item."),
            \pocketmine\utils\TextFormat::colorize("&r&7Only this specific pouch keeps these slots."),
            \pocketmine\utils\TextFormat::colorize("&r&7If you lose the pouch, you lose the runes in it."),
            "",
            \pocketmine\utils\TextFormat::colorize("&r&fStored Runes: &a{$stored}"),
            \pocketmine\utils\TextFormat::colorize("&r&fUnlocked Slots: &a{$unlocked}&7/&f" . self::MAX_SLOTS),
            \pocketmine\utils\TextFormat::colorize("&r&eRight-click to open."),
        ]);
        return $item;
    }

    public function resetSeasonState(): void
    {
        // Pouch state now lives on the pouch item itself.
    }
}
