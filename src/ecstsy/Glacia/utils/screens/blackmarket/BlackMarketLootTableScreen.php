<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\blackmarket;

use ecstsy\Glacia\server\blackmarket\BlackMarketAuctionManager;
use ecstsy\MartianUtilities\utils\screens\BaseScreen;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\DeterministicInvMenuTransaction;
use muqsit\invmenu\type\InvMenuTypeIds;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class BlackMarketLootTableScreen extends BaseScreen {

    private const ITEMS_PER_PAGE = 45;

    private InvMenu $menu;
    private int $page = 1;

    public function __construct(private Player $viewer)
    {
        $this->menu = InvMenu::create(InvMenuTypeIds::TYPE_DOUBLE_CHEST);
        $this->menu->setName(C::colorize("&r&8BAH Loot Table"));
        $this->menu->setListener(InvMenu::readonly(function(DeterministicInvMenuTransaction $transaction): void {
            $slot = $transaction->getAction()->getSlot();
            $total = count($this->getEntries());

            if ($slot === 45 && $this->page > 1) {
                $this->page--;
                $this->build();
                return;
            }

            if ($slot === 49) {
                $transaction->getPlayer()->removeCurrentWindow();
                $transaction->then(function() use ($transaction): void {
                    BlackMarketScreen::display($transaction->getPlayer());
                });
                return;
            }

            if ($slot === 53 && $this->page * self::ITEMS_PER_PAGE < $total) {
                $this->page++;
                $this->build();
            }
        }));

        $this->build();
    }

    public function getMenu(): InvMenu
    {
        return $this->menu;
    }

    public static function display(Player $player): void
    {
        (new self($player))->getMenu()->send($player);
    }

    private function build(): void
    {
        $inv = $this->menu->getInventory();
        $inv->clearAll();

        foreach ($this->getPageEntries() as $slot => $entry) {
            $item = clone $entry["item"];
            $lore = $item->getLore();
            $lore[] = "";
            $lore[] = C::colorize("&r&eChance: &f" . $entry["chance"]);
            $lore[] = C::colorize("&r&eStarting Bid: &f$" . number_format((int) $entry["starting_bid"]));
            $item->setLore($lore);
            $inv->setItem($slot, $item);
        }

        for ($slot = 45; $slot <= 53; $slot++) {
            $inv->setItem($slot, VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::BLACK())->asItem()->setCustomName(" "));
        }

        if ($this->page > 1) {
            $prev = VanillaItems::PAPER();
            $prev->setCustomName(C::colorize("&r&ePrevious Page"));
            $inv->setItem(45, $prev);
        }

        $back = VanillaBlocks::CHEST()->asItem();
        $back->setCustomName(C::colorize("&r&eBack"));
        $back->setLore([C::colorize("&r&7Click to return to the black market.")]);
        $inv->setItem(49, $back);

        if ($this->page * self::ITEMS_PER_PAGE < count($this->getEntries())) {
            $next = VanillaItems::PAPER();
            $next->setCustomName(C::colorize("&r&eNext Page"));
            $inv->setItem(53, $next);
        }
    }

    private function getEntries(): array
    {
        return BlackMarketAuctionManager::getInstance()->getPoolEntries($this->viewer);
    }

    private function getPageEntries(): array
    {
        return array_slice($this->getEntries(), ($this->page - 1) * self::ITEMS_PER_PAGE, self::ITEMS_PER_PAGE);
    }
}
