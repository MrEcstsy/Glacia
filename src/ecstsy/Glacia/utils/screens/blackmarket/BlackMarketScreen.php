<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\blackmarket;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\tasks\BlackMarketCenterUpdateTask;
use ecstsy\Glacia\utils\inventory\CustomSizedInvMenu;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianUtilities\utils\screens\BaseScreen;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\DeterministicInvMenuTransaction;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\scheduler\TaskHandler;
use pocketmine\utils\TextFormat as C;

final class BlackMarketScreen extends BaseScreen {

    private InvMenu $menu;
    private ?TaskHandler $updateTask = null;
    private const CENTER_SLOT = 4;

    public function __construct(private Player $viewer)
    {
        $this->menu = CustomSizedInvMenu::create(9);
        $this->menu->setName(C::colorize("&r&8Black Market Auction"));

        $panes = [1, 2, 3, 5, 6, 7];

        $inv = $this->menu->getInventory();

        $bahTable = VanillaItems::BOOK();
        $bahTable->setCustomName(c::colorize("&r&l&6Black Market Auction"));
        $bahTable->setLore([
            C::colorize("&r&7Every 30 minutes, a random rare item will be"),
            C::colorize("&r&7listed on the /bah for players to bid on."),
            "",
            C::colorize("&r&7The bid increments will increase as the"),
            C::colorize("&r&7max bid increases, with a &f10s anti-snipe"),
            C::colorize("&r&ftimer &7added each time a bid is placed."),
            "",
            C::colorize("&r&7The playerr with the highest bid at the"),
            C::colorize("&r&7end of the auction will recieve the item."),
            "",
            C::colorize("&r&7Click to preview the loot table.")
        ]);

        $bahHistory = VanillaItems::WRITABLE_BOOK();
        $bahHistory->setCustomName(C::colorize("&r&l&6Black Market History"));
        $bahHistory->setLore([
            C::colorize("&r&7Click to view the /bah history."),
        ]);

        $inv->setItem(0, $bahHistory);

        $inv->setItem(8, $bahTable);

        foreach ($panes as $slot) {
            $inv->setItem($slot, VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::BLACK())->asItem()->setCustomName(""));
        }

        $this->refreshCenterItem();

        $this->menu->setListener(InvMenu::readonly(function (DeterministicInvMenuTransaction $transaction): void {
            $slot = $transaction->getAction()->getSlot();
            $player = $transaction->getPlayer();

            switch ($slot) {
                case 0:
                    $player->sendMessage(C::colorize("&r&7History screen soon!"));
                    break;
                case 8:
                    $player->sendMessage(C::colorize("&r&7Table screen soon!"));
                    break;
                CASE self::CENTER_SLOT:
                    if(!Loader::getBlackMarketAuctionInstance()->isEnabled() || !Loader::getBlackMarketAuctionInstance()->hasStarted()){
                        $player->sendMessage(C::colorize("&r&cThere is no active auction right now."));
                        return;
                    }
    
                    $player->removeCurrentWindow();
                    $transaction->then(function() use ($player): void {
                        BlackMarketConfirmScreen::display($player);
                    });
                    break;
            }
        }));

        $this->menu->setInventoryCloseListener(function(Player $player): void {
            $this->stopUpdateTask();
        });
    }

    private function refreshCenterItem(): void {
        $this->menu->getInventory()->setItem(self::CENTER_SLOT, $this->getCenterItem());
    }

    private function getCenterItem(): Item {
        $bah = Loader::getBlackMarketAuctionInstance();

        if(!$bah->isEnabled() || !$bah->hasStarted()){
            return $this->getWaitingItem();
        }

        $auctionItem = $bah->getCurrentItem(); // change this if your method is named differently
        if($auctionItem === null){
            return $this->getWaitingItem();
        }

        $item = clone $auctionItem;

        $baseLore = $item->getLore();
        $extraLore = [];

        $currentBid = $bah->getCurrentBid();
        $startingBid = $bah->getStartingBid();
        $bidIncrement = $bah->getBidIncrement();
        $nextBidAmount = $bah->getNextBidAmount();
        $timeLeft = $bah->getAuctionTimeLeft();
        $hasBids = $bah->hasBids();
        $highestBidder = $bah->getHighestBidder();

        $extraLore[] = "";
        $extraLore[] = C::colorize("&r&f------------------------");
        $extraLore[] = "";

        $extraLore[] = C::colorize("&r&l&eCLICK &r&7item to bid &a$" . number_format($nextBidAmount) . "&7!");

        if ($hasBids) {
            $extraLore[] = C::colorize("&r&aCurrent Bid: &f$" . number_format($currentBid));
            $extraLore[] = C::colorize("&r&eHighest Bidder: &f" . ($highestBidder !== "" ? $highestBidder : "Unknown"));
        } else {
            $extraLore[] = C::colorize("&r&cNo bids currently placed.");
        }

        $extraLore[] = C::colorize("&r&eBid Increment: &f$" . number_format($bidIncrement));
        $extraLore[] = C::colorize("&r&eBidding ends in: &f" . GeneralUtils::translateTime($timeLeft));

        $extraLore[] = "";
        $extraLore[] = C::colorize("&r&f------------------------");

        $item->setLore(array_merge($baseLore, $extraLore));
        return $item;
    }

    private function getWaitingItem(): Item {
        $item = VanillaBlocks::BARRIER()->asItem();
        $item->setCustomName(C::colorize("&r&l&c???"));
        $item->setLore([
            C::colorize("&r&cNext Auction in " . GeneralUtils::translateTime(
                Loader::getBlackMarketAuctionInstance()->getRemaining()
            ))
        ]);
        return $item;
    }

    public function startUpdateTask(): void {
        $this->stopUpdateTask();

        $this->updateTask = Loader::getInstance()->getScheduler()->scheduleRepeatingTask(
            new BlackMarketCenterUpdateTask($this->viewer, $this),
            20
        );
    }

    public function stopUpdateTask(): void {
        if($this->updateTask !== null){
            $this->updateTask->cancel();
            $this->updateTask = null;
        }
    }

    public function updateCenterItem(): void {
        $this->refreshCenterItem();
    }

    public function getMenu(): InvMenu
    {
        return $this->menu;
    }

    public static function display(Player $player): void
    {
        $screen = new self($player);
        $screen->getMenu()->send($player);

        $screen->startUpdateTask();
    }
}
