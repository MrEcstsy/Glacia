<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\player\GlaciaPlayer;
use ecstsy\MartianUtilities\utils\ItemUtils;
use ecstsy\MartianUtilities\utils\screens\BaseScreen;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\DeterministicInvMenuTransaction;
use muqsit\invmenu\type\InvMenuTypeIds;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\inventory\Inventory;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\scheduler\ClosureTask;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class CollectScreen extends BaseScreen
{
    private InvMenu $menu;

    private const BACK_BUTTON_SLOT = 48;
    private const NEXT_BUTTON_SLOT = 50;
    private const ITEMS_PER_PAGE = 45;

    public function __construct(Player $player, int $currentPage = 1)
    {
        $this->menu = InvMenu::create(InvMenuTypeIds::TYPE_DOUBLE_CHEST);
        $this->menu->setName(C::colorize("&r&8Collection"));

        $inventory = $this->menu->getInventory();
        $this->fillBottomRowWithGlass($inventory);

        $session = Loader::getPlayerManager()->getSession($player);
        $items = $session->getItemsFromCollection();
        $totalItems = count($items);

        $this->populateItems($inventory, $items, $currentPage, $totalItems);
        $this->addNavigationButtons($inventory, $currentPage, $totalItems);

        $this->menu->setListener(InvMenu::readonly(function (DeterministicInvMenuTransaction $transaction) use ($player, &$currentPage, $inventory, $session) {
            $slot = $transaction->getAction()->getSlot();
            $items = $session->getItemsFromCollection();
            $totalItems = count($items);

            if ($slot === self::BACK_BUTTON_SLOT && $currentPage > 1) {
                $currentPage--;
                $this->updateMenu($inventory, $player, $currentPage, $totalItems);
            }

            if ($slot === self::NEXT_BUTTON_SLOT && ($currentPage - 1) * self::ITEMS_PER_PAGE + self::ITEMS_PER_PAGE < $totalItems) {
                $currentPage++;
                $this->updateMenu($inventory, $player, $currentPage, $totalItems);
            }

            if ($slot >= 0 && $slot < self::ITEMS_PER_PAGE) {
                $this->handleItemClick($player, $inventory, $session, $items, $currentPage, $slot);
            }
        }));
    }

    public static function display(Player $player): void 
    {
        $screen = new self($player);
        $screen->menu->send($player);
    }

    private function fillBottomRowWithGlass(Inventory $inventory): void
    {
        for ($i = 45; $i <= 53; $i++) {
            $color = ($i % 2 === 0) ? DyeColor::BLACK() : DyeColor::GRAY();
            $glass = VanillaBlocks::STAINED_GLASS_PANE()->setColor($color)->asItem()->setCustomName(" ");
            $inventory->setItem($i, $glass);
        }
    }

    private function populateItems(Inventory $inventory, array $items, int $currentPage, int $totalItems): void
    {
        $startIndex = ($currentPage - 1) * self::ITEMS_PER_PAGE;
        $endIndex = min($startIndex + self::ITEMS_PER_PAGE, $totalItems);

        for ($i = $startIndex; $i < $endIndex; $i++) {
            $item = $this->decodeCollectionItem((string) $items[$i], $i);
            if ($item === null) {
                $item = VanillaItems::BARRIER()
                    ->setCustomName(C::colorize("&r&cCorrupted Collection Item"))
                    ->setLore([
                        C::colorize("&r&7This stored item could not be decoded."),
                        C::colorize("&r&7Click to remove it from /collect."),
                    ]);
            } elseif (!$item->equals(VanillaBlocks::STAINED_GLASS_PANE()->asItem()) && !$item->equals(VanillaItems::ARROW())) {
                $item->setLore(array_merge($item->getLore(), [C::colorize("&r&l&6Click to claim")]));
            }
            $inventory->setItem($i - $startIndex, $item);
        }
    }

    private function addNavigationButtons(Inventory $inventory, int $currentPage, int $totalItems): void
    {
        $inventory->setItem(
            self::BACK_BUTTON_SLOT,
            $currentPage > 1
                ? VanillaItems::ARROW()->setCustomName(C::colorize("&r&7Back"))
                : VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::BLACK())->asItem()->setCustomName(" ")
        );

        $inventory->setItem(
            self::NEXT_BUTTON_SLOT,
            ($currentPage * self::ITEMS_PER_PAGE < $totalItems)
                ? VanillaItems::ARROW()->setCustomName(C::colorize("&r&7Next"))
                : VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::BLACK())->asItem()->setCustomName(" ")
        );
    }

    private function handleItemClick(Player $player, Inventory $inventory, GlaciaPlayer $session, array $items, int $currentPage, int $slot): void
    {
        $itemIndex = ($currentPage - 1) * self::ITEMS_PER_PAGE + $slot;
        if (isset($items[$itemIndex])) {
            $encodedItem = (string) $items[$itemIndex];
            $clickedItem = $this->decodeCollectionItem((string) $items[$itemIndex], $itemIndex);
            if ($clickedItem === null) {
                $session->removeEncodedItemFromCollection($encodedItem);
                $player->sendMessage(C::colorize("&r&eRemoved a corrupted item from your collection."));
                $updatedItems = $session->getItemsFromCollection();
                $updatedTotal = count($updatedItems);
                $maxPage = max(1, (int) ceil(max(1, $updatedTotal) / self::ITEMS_PER_PAGE));
                $currentPage = min($currentPage, $maxPage);
                $this->updateMenu($inventory, $player, $currentPage, $updatedTotal);
                return;
            }

            if (!$player->getInventory()->canAddItem($clickedItem)) {
                $player->sendToastNotification(
                    C::colorize(Loader::SERVER_PREFIX),
                    C::colorize("&r&7Clear space in your inventory to collect this!")
                );
                return;
            }

            Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(
                function() use ($player, $inventory, $session, $clickedItem, $currentPage): void {
                    if (!$player->isConnected()) {
                        return;
                    }

                    $player->getInventory()->addItem(clone $clickedItem);
                    $session->removeItemFromCollection($clickedItem);
                    $updatedItems = $session->getItemsFromCollection();
                    $updatedTotal = count($updatedItems);
                    $maxPage = max(1, (int) ceil(max(1, $updatedTotal) / self::ITEMS_PER_PAGE));
                    $this->updateMenu($inventory, $player, min($currentPage, $maxPage), $updatedTotal);
                }
            ), 1);
        }
    }

    private function updateMenu(Inventory $inventory, Player $player, int $currentPage, int $totalItems): void
    {
        for ($i = 0; $i < self::ITEMS_PER_PAGE; $i++) {
            $inventory->setItem($i, VanillaItems::AIR());
        }

        $session = Loader::getPlayerManager()->getSession($player);
        $items = $session->getItemsFromCollection();

        $this->populateItems($inventory, $items, $currentPage, $totalItems);
        $this->addNavigationButtons($inventory, $currentPage, $totalItems);
    }

    private function decodeCollectionItem(string $encoded, int $index): ?Item
    {
        try {
            $item = ItemUtils::decodeItem($encoded);
            if ($item->isNull()) {
                return null;
            }

            $safeItem = $this->ensureClientSafe($item);
            if ($safeItem === null) {
                Loader::getInstance()->getLogger()->warning("[Collect] Collection item at index {$index} is not safe to send to the client and will be treated as corrupted.");
                return null;
            }

            return $safeItem;
        } catch (\Throwable $e) {
            Loader::getInstance()->getLogger()->warning("[Collect] Failed to decode collection item at index {$index}: " . $e->getMessage());
            return null;
        }
    }

    private function ensureClientSafe(Item $item): ?Item
    {
        if (method_exists(ItemUtils::class, "ensureClientSafe")) {
            try {
                /** @var ?Item $safeItem */
                $safeItem = ItemUtils::ensureClientSafe($item);
                return $safeItem;
            } catch (\Throwable) {
                // Fall through to the local compatibility path below.
            }
        }

        try {
            $safeItem = clone $item;
            if ($safeItem->isNull()) {
                return null;
            }

            $count = $safeItem->getCount();
            if ($count <= 0) {
                $count = 1;
            }

            $maxCount = max(1, min(255, $safeItem->getMaxStackSize()));
            if ($count > $maxCount) {
                $count = $maxCount;
            }

            $safeItem->setCount($count);
            TypeConverter::getInstance()->coreItemStackToNet($safeItem);
            return $safeItem;
        } catch (\Throwable) {
            return null;
        }
    }
}
