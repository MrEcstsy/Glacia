<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\auctionhouse;

use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\ItemUtils;
use ecstsy\MartianUtilities\utils\screens\BaseScreen;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\DeterministicInvMenuTransaction;
use muqsit\invmenu\type\InvMenuTypeIds;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class AuctionHouseCollectionScreen extends BaseScreen {

    private InvMenu $menu;
    private int $page = 1;
    private const ITEMS_PER_PAGE = 45;

    public function __construct(private Player $viewer)
    {
        $this->menu = InvMenu::create(InvMenuTypeIds::TYPE_DOUBLE_CHEST);
        $this->menu->setName(C::colorize("&r&8Collection Bin"));
        $this->menu->setListener(InvMenu::readonly(function(DeterministicInvMenuTransaction $transaction): void {
            $slot = $transaction->getAction()->getSlot();
            $player = $transaction->getPlayer();

            if ($slot === 49) {
                (new AuctionHouseScreen($player))->display($player);
                return;
            }
            if ($slot === 48 && $this->page > 1) {
                $this->page--;
                $this->build();
                return;
            }
            if ($slot === 50 && $this->page * self::ITEMS_PER_PAGE < count(Loader::getAuctionHouseManager()->getCollectionEntries($player->getUniqueId()->toString()))) {
                $this->page++;
                $this->build();
                return;
            }
            if ($slot >= 0 && $slot < self::ITEMS_PER_PAGE) {
                $entry = $this->getPageEntries()[$slot] ?? null;
                if ($entry === null) {
                    return;
                }

                $result = Loader::getAuctionHouseManager()->claimCollectionEntry($player, (string) $entry["entry_id"]);
                $player->sendMessage(C::colorize($result === "" ? "&r&aCollected item from your collection bin." : "&r&c" . $result));
                $this->build();
            }
        }));

        $this->build();
    }

    public function display(Player $player): void
    {
        $this->menu->send($player);
    }

    private function build(): void
    {
        $inv = $this->menu->getInventory();
        $inv->clearAll();

        foreach ($this->getPageEntries() as $slot => $entry) {
            $item = ItemUtils::decodeItem((string) $entry["item_data"]);
            $lore = $item->getLore();
            $lore[] = "";
            $lore[] = C::colorize("&r&eSource: &f" . ucfirst((string) $entry["reason"]));
            $lore[] = C::colorize("&r&eClick to collect");
            $item->setLore($lore);
            $inv->setItem($slot, $item);
        }

        for ($i = 45; $i <= 53; $i++) {
            $inv->setItem($i, VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::BLACK())->asItem()->setCustomName(" "));
        }

        if ($this->page > 1) {
            $prev = VanillaItems::PAPER();
            $prev->setCustomName(C::colorize("&r&ePrevious Page"));
            $inv->setItem(48, $prev);
        }

        $back = VanillaBlocks::CHEST()->asItem();
        $back->setCustomName(C::colorize("&r&eBack"));
        $back->setLore([C::colorize("&r&7Click to return to the auction house.")]);
        $inv->setItem(49, $back);

        if ($this->page * self::ITEMS_PER_PAGE < count(Loader::getAuctionHouseManager()->getCollectionEntries($this->viewer->getUniqueId()->toString()))) {
            $next = VanillaItems::PAPER();
            $next->setCustomName(C::colorize("&r&eNext Page"));
            $inv->setItem(50, $next);
        }

        $info = VanillaItems::BOOK();
        $info->setCustomName(C::colorize("&r&eCollection Bin"));
        $info->setLore([
            C::colorize("&r&7Click here to collect all of the"),
            C::colorize("&r&7items you have cancelled or have expired."),
            "",
            C::colorize("&r&e" . Loader::getAuctionHouseManager()->getCollectionCount($this->viewer->getUniqueId()->toString()) . " Item(s)")
        ]);
        $inv->setItem(53, $info);
    }

    private function getPageEntries(): array
    {
        return array_slice(Loader::getAuctionHouseManager()->getCollectionEntries($this->viewer->getUniqueId()->toString()), ($this->page - 1) * self::ITEMS_PER_PAGE, self::ITEMS_PER_PAGE, false);
    }
}
