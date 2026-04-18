<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\auctionhouse;

use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\screens\BaseScreen;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\DeterministicInvMenuTransaction;
use muqsit\invmenu\type\InvMenuTypeIds;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class AuctionHouseManageScreen extends BaseScreen {

    private InvMenu $menu;
    private int $page = 1;
    private const ITEMS_PER_PAGE = 45;

    public function __construct(private Player $viewer)
    {
        $this->menu = InvMenu::create(InvMenuTypeIds::TYPE_DOUBLE_CHEST);
        $this->menu->setName(C::colorize("&r&8Your Auctions"));
        $this->menu->setListener(InvMenu::readonly(function(DeterministicInvMenuTransaction $transaction): void {
            $slot = $transaction->getAction()->getSlot();
            $player = $transaction->getPlayer();

            if ($slot === 46) {
                (new AuctionHouseCollectionScreen($player))->display($player);
                return;
            }
            if ($slot === 49) {
                (new AuctionHouseScreen($player))->display($player);
                return;
            }
            if ($slot === 48 && $this->page > 1) {
                $this->page--;
                $this->build();
                return;
            }
            if ($slot === 50 && $this->page * self::ITEMS_PER_PAGE < count(Loader::getAuctionHouseManager()->getListingsForSeller($player->getUniqueId()->toString()))) {
                $this->page++;
                $this->build();
                return;
            }
            if ($slot >= 0 && $slot < self::ITEMS_PER_PAGE) {
                $listing = $this->getPageListings()[$slot] ?? null;
                if ($listing === null) {
                    return;
                }

                $result = Loader::getAuctionHouseManager()->cancelListing($player, (string) $listing["listing_id"]);
                $player->sendMessage(C::colorize($result === "" ? "&r&aListing cancelled and moved to your collection bin." : "&r&c" . $result));
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

        foreach ($this->getPageListings() as $slot => $listing) {
            $inv->setItem($slot, AuctionHouseScreen::buildListingItem($listing, true));
        }

        for ($i = 45; $i <= 53; $i++) {
            $inv->setItem($i, VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::BLACK())->asItem()->setCustomName(" "));
        }

        $collection = VanillaBlocks::ENDER_CHEST()->asItem();
        $collection->setCustomName(C::colorize("&r&eCollection Bin"));
        $collection->setLore([
            C::colorize("&r&7Click here to view and collect all of the"),
            C::colorize("&r&7items you have cancelled or have expired."),
            "",
            C::colorize("&r&e" . Loader::getAuctionHouseManager()->getCollectionCount($this->viewer->getUniqueId()->toString()) . " Item(s)")
        ]);
        $inv->setItem(46, $collection);

        if ($this->page > 1) {
            $prev = VanillaItems::PAPER();
            $prev->setCustomName(C::colorize("&r&ePrevious Page"));
            $inv->setItem(48, $prev);
        }

        $back = VanillaBlocks::CHEST()->asItem();
        $back->setCustomName(C::colorize("&r&eBack"));
        $back->setLore([C::colorize("&r&7Click to return to the auction house.")]);
        $inv->setItem(49, $back);

        if ($this->page * self::ITEMS_PER_PAGE < count(Loader::getAuctionHouseManager()->getListingsForSeller($this->viewer->getUniqueId()->toString()))) {
            $next = VanillaItems::PAPER();
            $next->setCustomName(C::colorize("&r&eNext Page"));
            $inv->setItem(50, $next);
        }

        $tutorial = VanillaItems::BOOK();
        $tutorial->setCustomName(C::colorize("&r&eTutorial"));
        $tutorial->setLore([
            C::colorize("&r&7These are your current listings, all of"),
            C::colorize("&r&7the items you currently have listed on"),
            C::colorize("&r&7the auction house are displayed here."),
            "",
            C::colorize("&r&7You can cancel and view your listing's"),
            C::colorize("&r&7expire time here.")
        ]);
        $inv->setItem(53, $tutorial);
    }

    private function getPageListings(): array
    {
        return array_slice(Loader::getAuctionHouseManager()->getListingsForSeller($this->viewer->getUniqueId()->toString()), ($this->page - 1) * self::ITEMS_PER_PAGE, self::ITEMS_PER_PAGE, false);
    }
}
