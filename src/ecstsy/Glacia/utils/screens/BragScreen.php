<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens;

use ecstsy\Glacia\managers\BragTracker;
use muqsit\invmenu\InvMenu;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\utils\MobHeadType;
use pocketmine\block\VanillaBlocks;
use pocketmine\inventory\Inventory;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class BragScreen
{
    private InvMenu $menu;

    /**
     * @param array{
     *     hotbar: Item[],
     *     inventory: Item[],
     *     helmet: Item,
     *     chestplate: Item,
     *     leggings: Item,
     *     boots: Item,
     *     offhand: Item,
     *     level: int
     * } $snapshot
     */
    private function __construct(private string $ownerName, private array $snapshot)
    {
        $this->menu = InvMenu::create(InvMenu::TYPE_DOUBLE_CHEST);
        $this->menu->setName(C::colorize("&r&8{$this->ownerName}'s Brag"));
        $inv = $this->menu->getInventory();

        $pane = VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::GRAY())->asItem()->setCustomName(" ");
        $this->fillRange($inv, 0, 8, $pane);
        $this->fillRange($inv, 9, 17, $pane);

        $inv->setItem(0, $this->createPlayerSkull());
        $inv->setItem(1, $this->createXpBottle());
        $inv->setItem(3, clone $this->snapshot["helmet"]);
        $inv->setItem(4, clone $this->snapshot["chestplate"]);
        $inv->setItem(5, clone $this->snapshot["leggings"]);
        $inv->setItem(6, clone $this->snapshot["boots"]);
        $inv->setItem(8, clone $this->snapshot["offhand"]);

        foreach ($this->snapshot["inventory"] as $index => $item) {
            $inv->setItem(18 + $index, clone $item);
        }

        foreach ($this->snapshot["hotbar"] as $index => $item) {
            $inv->setItem(45 + $index, clone $item);
        }

        $this->menu->setListener(InvMenu::readonly());
    }

    private function createPlayerSkull(): Item
    {
        return VanillaBlocks::MOB_HEAD()
            ->setMobHeadType(MobHeadType::PLAYER())
            ->asItem()
            ->setCustomName(C::colorize("&r&d{$this->ownerName}"));
    }

    private function createXpBottle(): Item
    {
        return VanillaItems::EXPERIENCE_BOTTLE()
            ->setCustomName(C::colorize("&r&e" . $this->snapshot["level"] . " Enchantment Levels"));
    }

    private function fillRange(Inventory $inv, int $start, int $end, Item $item): void
    {
        for ($i = $start; $i <= $end; ++$i) {
            $inv->setItem($i, clone $item);
        }
    }

    public static function display(Player $viewer, string $targetName): void
    {
        $snapshot = BragTracker::getLastInventoryBrag($targetName);
        if ($snapshot === null) {
            $viewer->sendMessage(C::colorize("&r&cThat player has not bragged recently."));
            return;
        }

        (new self($targetName, $snapshot))->menu->send($viewer);
    }
}
