<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\auctionhouse;

use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianUtilities\utils\ItemUtils;
use ecstsy\MartianUtilities\utils\screens\BaseScreen;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\DeterministicInvMenuTransaction;
use muqsit\invmenu\type\InvMenuTypeIds;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class AuctionHouseScreen extends BaseScreen {

    private InvMenu $menu;
    private int $page = 1;
    private const ITEMS_PER_PAGE = 45;

    public function __construct(private Player $viewer)
    {
        $this->menu = InvMenu::create(InvMenuTypeIds::TYPE_DOUBLE_CHEST);
        $this->menu->setName(C::colorize("&r&8Auction House"));
        $this->menu->setListener(InvMenu::readonly(function(DeterministicInvMenuTransaction $transaction): void {
            $slot = $transaction->getAction()->getSlot();
            $player = $transaction->getPlayer();

            if ($slot === 45) {
                $player->removeCurrentWindow();
                (new AuctionHouseManageScreen($player))->display($player);
                return;
            }

            if ($slot === 46) {
                $player->removeCurrentWindow();
                (new AuctionHouseCollectionScreen($player))->display($player);
                return;
            }

            if ($slot === 48 && $this->page > 1) {
                $this->page--;
                $this->build();
                return;
            }

            if ($slot === 49) {
                $this->build();
                return;
            }

            if ($slot === 50 && $this->page * self::ITEMS_PER_PAGE < count(Loader::getAuctionHouseManager()->getListings())) {
                $this->page++;
                $this->build();
                return;
            }

            if ($slot >= 0 && $slot < self::ITEMS_PER_PAGE) {
                $listing = $this->getPageListings()[$slot] ?? null;
                if ($listing === null) {
                    return;
                }

                $result = Loader::getAuctionHouseManager()->purchaseListing($player, (string) $listing["listing_id"]);
                if ($result !== "") {
                    $player->sendMessage(C::colorize("&r&c" . $result));
                } else {
                    $player->sendMessage(C::colorize("&r&aPurchase successful."));
                }
                $this->build();
            }
        }));

        $this->build();
    }

    public function display(Player $player): void
    {
        $this->menu->send($player);
    }

    public function getMenu(): InvMenu
    {
        return $this->menu;
    }

    private function build(): void
    {
        $inv = $this->menu->getInventory();
        $inv->clearAll();

        foreach ($this->getPageListings() as $slot => $listing) {
            $inv->setItem($slot, self::buildListingItem($listing));
        }

        for ($i = 45; $i <= 53; $i++) {
            $inv->setItem($i, VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::BLACK())->asItem()->setCustomName(" "));
        }

        $manage = VanillaItems::DIAMOND();
        $manage->setCustomName(C::colorize("&r&eYour Auctions"));
        $manage->setLore([C::colorize("&r&7View and manage your active listings.")]);
        $inv->setItem(45, $manage);

        $collection = VanillaBlocks::ENDER_CHEST()->asItem();
        $collection->setCustomName(C::colorize("&r&eCollection Bin"));
        $collection->setLore([
            C::colorize("&r&7Click here to view and collect all of the"),
            C::colorize("&r&7items you have cancelled or that have expired."),
            "",
            C::colorize("&r&e" . Loader::getAuctionHouseManager()->getCollectionCount($this->viewer->getUniqueId()->toString()) . " Item(s)")
        ]);
        $inv->setItem(46, $collection);

        if ($this->page > 1) {
            $prev = VanillaItems::PAPER();
            $prev->setCustomName(C::colorize("&r&ePrevious Page"));
            $inv->setItem(48, $prev);
        }

        $refresh = VanillaBlocks::CHEST()->asItem();
        $refresh->setCustomName(C::colorize("&r&eRefresh Auctions"));
        $refresh->setLore([C::colorize("&r&7Click to refresh the currently"), C::colorize("&r&7available auction listings.")]);
        $inv->setItem(49, $refresh);

        if ($this->page * self::ITEMS_PER_PAGE < count(Loader::getAuctionHouseManager()->getListings())) {
            $next = VanillaItems::PAPER();
            $next->setCustomName(C::colorize("&r&eNext Page"));
            $inv->setItem(50, $next);
        }

        $guide = VanillaItems::BOOK();
        $guide->setCustomName(C::colorize("&r&eGuide"));
        $guide->setLore(array_map([C::class, "colorize"], Loader::getAuctionHouseManager()->getGuideLines()));
        $inv->setItem(53, $guide);
    }

    private function getPageListings(): array
    {
        return array_slice(Loader::getAuctionHouseManager()->getListings(), ($this->page - 1) * self::ITEMS_PER_PAGE, self::ITEMS_PER_PAGE, false);
    }

    public static function buildListingItem(array $listing, bool $manageMode = false): Item
    {
        $item = ItemUtils::decodeItem((string) $listing["item_data"]);
        $lore = $item->getLore();
        $price = (int) $listing["price"];
        $count = max(1, $item->getCount());
        $perItem = $price / $count;

        $lore[] = "";
        $lore[] = C::colorize("&r&f------------------------");
        $lore[] = C::colorize($manageMode ? "&r&eClick item to cancel listing!" : "&r&eClick item to buy!");
        $lore[] = "";
        $price_text = C::colorize("&r&ePrice: &f$" . number_format($price));
        if ($count > 1) {
            $formattedPerItem = fmod($perItem, 1.0) === 0.0 ? number_format($perItem, 0) : number_format($perItem, 2);
            $price_text .= C::colorize(" &r&7($" . $formattedPerItem . " / item)");
        }
        $lore[] = $price_text;
        $lore[] = C::colorize("&r&eSeller: &f" . $listing["seller_name"]);
        if ($manageMode) {
            $remaining = max(0, ((int) $listing["expires_at"]) - time());
            $lore[] = C::colorize("&r&eExpires In: &f" . GeneralUtils::translateTime($remaining));
        }
        $lore[] = C::colorize("&r&f------------------------");
        $item->setLore($lore);

        return $item;
    }
}
