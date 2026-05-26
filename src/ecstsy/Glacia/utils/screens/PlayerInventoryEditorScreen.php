<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens;

use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\InvMenuTransaction;
use muqsit\invmenu\transaction\InvMenuTransactionResult;
use muqsit\invmenu\type\InvMenuTypeIds;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\utils\MobHeadType;
use pocketmine\block\VanillaBlocks;
use pocketmine\inventory\Inventory;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class PlayerInventoryEditorScreen
{
    private const INFO_SLOTS = [0, 1, 2, 7, 9, 10, 11, 12, 13, 14, 15, 16, 17];
    private const HELMET_SLOT = 3;
    private const CHESTPLATE_SLOT = 4;
    private const LEGGINGS_SLOT = 5;
    private const BOOTS_SLOT = 6;
    private const OFFHAND_SLOT = 8;

    private InvMenu $menu;

    public function __construct(private Player $viewer, private Player $target)
    {
        $this->menu = InvMenu::create(InvMenuTypeIds::TYPE_DOUBLE_CHEST);
        $this->menu->setName(C::colorize("&r&8Editing Inventory: &f" . $this->target->getName()));
        $this->render();

        $this->menu->setListener(function(InvMenuTransaction $transaction): InvMenuTransactionResult {
            $slot = $transaction->getAction()->getSlot();
            if (in_array($slot, self::INFO_SLOTS, true)) {
                return $transaction->discard();
            }

            return $transaction->continue();
        });

        $this->menu->setInventoryCloseListener(function(Player $player, Inventory $inventory): void {
            if (!$this->target->isConnected()) {
                $player->sendMessage(C::colorize("&r&c" . $this->target->getName() . " went offline before the inventory could be saved."));
                return;
            }

            $this->saveToTarget($inventory);
            $player->sendMessage(C::colorize("&r&aSaved " . $this->target->getName() . "'s inventory."));
        });
    }

    public function display(): void
    {
        $this->menu->send($this->viewer);
    }

    private function render(): void
    {
        $inventory = $this->menu->getInventory();
        $inventory->clearAll();

        $pane = VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::GRAY())->asItem()->setCustomName(" ");
        foreach (self::INFO_SLOTS as $slot) {
            $inventory->setItem($slot, clone $pane);
        }

        $inventory->setItem(0, VanillaBlocks::MOB_HEAD()->setMobHeadType(MobHeadType::PLAYER())->asItem()->setCustomName(C::colorize("&r&d" . $this->target->getName())));
        $inventory->setItem(1, VanillaItems::EXPERIENCE_BOTTLE()->setCustomName(C::colorize("&r&e" . $this->target->getXpManager()->getXpLevel() . " Enchantment Levels")));

        $armor = $this->target->getArmorInventory();
        $inventory->setItem(self::HELMET_SLOT, clone $armor->getHelmet());
        $inventory->setItem(self::CHESTPLATE_SLOT, clone $armor->getChestplate());
        $inventory->setItem(self::LEGGINGS_SLOT, clone $armor->getLeggings());
        $inventory->setItem(self::BOOTS_SLOT, clone $armor->getBoots());
        $inventory->setItem(self::OFFHAND_SLOT, clone $this->target->getOffHandInventory()->getItem(0));

        foreach (range(9, 35) as $slot) {
            $inventory->setItem(18 + ($slot - 9), clone $this->target->getInventory()->getItem($slot));
        }

        foreach (range(0, 8) as $slot) {
            $inventory->setItem(45 + $slot, clone $this->target->getInventory()->getItem($slot));
        }
    }

    private function saveToTarget(Inventory $inventory): void
    {
        $targetInventory = $this->target->getInventory();
        foreach (range(9, 35) as $slot) {
            $targetInventory->setItem($slot, clone $inventory->getItem(18 + ($slot - 9)));
        }

        foreach (range(0, 8) as $slot) {
            $targetInventory->setItem($slot, clone $inventory->getItem(45 + $slot));
        }

        $armor = $this->target->getArmorInventory();
        $armor->setHelmet(clone $inventory->getItem(self::HELMET_SLOT));
        $armor->setChestplate(clone $inventory->getItem(self::CHESTPLATE_SLOT));
        $armor->setLeggings(clone $inventory->getItem(self::LEGGINGS_SLOT));
        $armor->setBoots(clone $inventory->getItem(self::BOOTS_SLOT));
        $this->target->getOffHandInventory()->setItem(0, clone $inventory->getItem(self::OFFHAND_SLOT));
    }
}
