<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\runes;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\items\runes\PassiveRunePouchManager;
use ecstsy\Glacia\utils\inventory\CustomSizedInvMenu;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\InvMenuTransaction;
use muqsit\invmenu\transaction\InvMenuTransactionResult;
use pocketmine\block\VanillaBlocks;
use pocketmine\inventory\Inventory;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class PassiveRunePouchScreen
{
    private InvMenu $menu;
    private PassiveRunePouchManager $manager;
    private string $pouchId;

    public function __construct(private Player $player, private int $pouchSlot)
    {
        $this->manager = Loader::getPassiveRunePouchManager();
        $pouch = $this->manager->initializePouchItem(clone $this->player->getInventory()->getItem($this->pouchSlot));
        $this->player->getInventory()->setItem($this->pouchSlot, $pouch);
        $this->pouchId = $this->manager->getPouchId($pouch);
        $this->menu = CustomSizedInvMenu::create(PassiveRunePouchManager::MAX_SLOTS);
        $this->menu->setListener(fn(InvMenuTransaction $transaction): InvMenuTransactionResult => $this->handleTransaction($transaction));
        $this->menu->setInventoryCloseListener(function(Player $player, Inventory $inventory): void {
            $this->saveInventory($player, $inventory);
        });
        $this->render();
    }

    public function display(): void
    {
        $this->player->sendMessage(C::colorize("&r&7Place passive runes in this pouch to keep them active while this pouch is in your inventory."));
        $this->player->sendMessage(C::colorize("&r&7Unlocks are saved on this specific pouch item only."));
        $this->menu->send($this->player);
    }

    private function render(): void
    {
        $pouch = $this->getCurrentPouchItem();
        if ($pouch === null) {
            return;
        }

        $inventory = $this->menu->getInventory();
        $inventory->clearAll();
        $unlocked = $this->manager->getUnlockedSlots($pouch);
        $this->menu->setName(C::colorize("&r&l&aPassive Rune Pouch &8[&f{$unlocked}&7/&f" . PassiveRunePouchManager::MAX_SLOTS . "&8]"));

        foreach ($this->manager->getStoredContents($pouch) as $slot => $item) {
            $inventory->setItem($slot, clone $item);
        }

        if ($unlocked < PassiveRunePouchManager::MAX_SLOTS) {
            $nextLockedSlot = $unlocked;
            foreach (range($unlocked, PassiveRunePouchManager::MAX_SLOTS - 1) as $slot) {
                $barrier = VanillaBlocks::BARRIER()->asItem();
                if ($slot === $nextLockedSlot) {
                    $cost = $this->manager->getNextUnlockCost($pouch);
                    $barrier->setCustomName(C::colorize("&r&cLocked Slot"));
                    $barrier->setLore([
                        C::colorize("&r&7Unlock the next passive rune slot."),
                        C::colorize("&r&7Cost: &a$" . number_format((int) $cost)),
                        C::colorize("&r&eClick to unlock."),
                    ]);
                } else {
                    $barrier->setCustomName(C::colorize("&r&8Locked Slot"));
                    $barrier->setLore([
                        C::colorize("&r&7Unlock earlier slots first."),
                    ]);
                }
                $inventory->setItem($slot, $barrier);
            }
        }
    }

    private function handleTransaction(InvMenuTransaction $transaction): InvMenuTransactionResult
    {
        $slot = $transaction->getAction()->getSlot();
        $player = $transaction->getPlayer();
        $pouch = $this->getCurrentPouchItem();
        if ($pouch === null) {
            $player->sendMessage(C::colorize("&r&cThat passive rune pouch is no longer in your inventory."));
            return $transaction->discard();
        }
        $unlocked = $this->manager->getUnlockedSlots($pouch);

        if ($slot >= $unlocked) {
            if ($slot === $unlocked && $this->manager->getNextUnlockCost($pouch) !== null) {
                $persistedPouch = $this->manager->saveContents($pouch, $this->collectUnlockedContents($this->menu->getInventory(), $unlocked));
                $updated = $this->manager->unlockNextSlot($player, $persistedPouch);
                if ($updated !== null) {
                    $this->replaceCurrentPouchItem($updated);
                    PlayerUtils::playSound($player, "random.levelup");
                    $player->sendMessage(C::colorize("&r&aUnlocked passive rune slot &f" . ($unlocked + 1) . "&a."));
                    $this->render();
                } else {
                    PlayerUtils::playSound($player, "note.bass");
                    $cost = (int) ($this->manager->getNextUnlockCost($pouch) ?? 0);
                    $player->sendMessage(C::colorize("&r&cYou need &f$" . number_format($cost) . " &cto unlock that slot."));
                }
            }

            return $transaction->discard();
        }

        $incoming = $transaction->getIn();
        if (!$incoming->isNull() && !$this->manager->isAllowedRuneItem($incoming)) {
            PlayerUtils::playSound($player, "note.bass");
            $player->sendMessage(C::colorize("&r&cOnly passive runes can go into your passive rune pouch."));
            return $transaction->discard();
        }

        return $transaction->continue();
    }

    private function saveInventory(Player $player, Inventory $inventory): void
    {
        $pouch = $this->getCurrentPouchItem();
        if ($pouch === null) {
            return;
        }

        $this->replaceCurrentPouchItem(
            $this->manager->saveContents(
                $pouch,
                $this->collectUnlockedContents($inventory, $this->manager->getUnlockedSlots($pouch))
            )
        );
    }

    /**
     * @return array<int, Item>
     */
    private function collectUnlockedContents(Inventory $inventory, int $unlocked): array
    {
        $contents = [];
        foreach (range(0, max(0, $unlocked - 1)) as $slot) {
            $item = $inventory->getItem($slot);
            if ($item->isNull() || !$this->manager->isAllowedRuneItem($item)) {
                continue;
            }

            $contents[$slot] = clone $item;
        }

        return $contents;
    }

    private function getCurrentPouchItem(): ?Item
    {
        $slot = $this->manager->findPouchSlotById($this->player, $this->pouchId);
        if ($slot === null) {
            return null;
        }

        $this->pouchSlot = $slot;
        return $this->player->getInventory()->getItem($slot);
    }

    private function replaceCurrentPouchItem(Item $item): void
    {
        $slot = $this->manager->findPouchSlotById($this->player, $this->pouchId);
        if ($slot === null) {
            return;
        }

        $this->pouchSlot = $slot;
        $this->player->getInventory()->setItem($slot, $item);
    }
}
