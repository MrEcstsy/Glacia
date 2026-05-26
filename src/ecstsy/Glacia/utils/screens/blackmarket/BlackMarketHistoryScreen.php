<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\blackmarket;

use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianUtilities\utils\ItemUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
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

final class BlackMarketHistoryScreen extends BaseScreen {

    private const ITEMS_PER_PAGE = 45;

    private InvMenu $menu;
    private int $page = 1;

    public function __construct(private Player $viewer)
    {
        $this->menu = InvMenu::create(InvMenuTypeIds::TYPE_DOUBLE_CHEST);
        $this->menu->setName(C::colorize("&r&8BAH History"));
        $this->menu->setListener(InvMenu::readonly(function(DeterministicInvMenuTransaction $transaction): void {
            $slot = $transaction->getAction()->getSlot();
            $total = count($this->getHistory());

            if ($slot === 45 && $this->page > 1) {
                PlayerUtils::playSound($transaction->getPlayer(), "random.click");
                $this->page--;
                $this->build();
                return;
            }

            if ($slot === 49) {
                PlayerUtils::playSound($transaction->getPlayer(), "random.click");
                $transaction->getPlayer()->removeCurrentWindow();
                $transaction->then(function() use ($transaction): void {
                    BlackMarketScreen::display($transaction->getPlayer());
                });
                return;
            }

            if ($slot === 53 && $this->page * self::ITEMS_PER_PAGE < $total) {
                PlayerUtils::playSound($transaction->getPlayer(), "random.click");
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
        PlayerUtils::playSound($player, "random.click", 1.0, 1.05);
    }

    private function build(): void
    {
        $inv = $this->menu->getInventory();
        $inv->clearAll();

        $entries = $this->getPageEntries();
        if ($entries === []) {
            $empty = VanillaBlocks::BARRIER()->asItem();
            $empty->setCustomName(C::colorize("&r&cNo Auction History"));
            $empty->setLore([
                C::colorize("&r&7The black market history is empty right now."),
            ]);
            $inv->setItem(22, $empty);
        } else {
            foreach ($entries as $slot => $entry) {
                $inv->setItem($slot, $this->createHistoryItem($entry));
            }
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

        if ($this->page * self::ITEMS_PER_PAGE < count($this->getHistory())) {
            $next = VanillaItems::PAPER();
            $next->setCustomName(C::colorize("&r&eNext Page"));
            $inv->setItem(53, $next);
        }
    }

    private function createHistoryItem(array $entry): Item
    {
        $itemData = $entry["item_data"] ?? null;
        $item = is_string($itemData) && $itemData !== "" ? ItemUtils::decodeItem($itemData) : VanillaBlocks::BARRIER()->asItem();

        if ($item->getCustomName() === "" && isset($entry["name"]) && $entry["name"] !== "") {
            $item->setCustomName((string) $entry["name"]);
        }

        $sold = ((int) ($entry["sold"] ?? 0)) === 1;
        $winner = (string) ($entry["winner"] ?? "");
        $finalBid = (int) ($entry["final_bid"] ?? 0);
        $startingBid = (int) ($entry["starting_bid"] ?? 0);
        $endedAt = (int) ($entry["ended_at"] ?? time());

        $lore = $item->getLore();
        $lore[] = "";
        $lore[] = C::colorize("&r&f------------------------");
        $lore[] = C::colorize($sold ? "&r&aStatus: &fSold" : "&r&cStatus: &fNo Bids");
        if ($sold) {
            $lore[] = C::colorize("&r&eWinner: &f" . $winner);
            $lore[] = C::colorize("&r&eFinal Bid: &f$" . number_format($finalBid));
        }
        $lore[] = C::colorize("&r&eStarting Bid: &f$" . number_format($startingBid));
        $lore[] = C::colorize("&r&eEnded: &f" . date("M j, Y g:i A", $endedAt));
        $lore[] = C::colorize("&r&7" . GeneralUtils::translateTime(max(0, time() - $endedAt)) . " ago");
        $lore[] = C::colorize("&r&f------------------------");
        $item->setLore($lore);

        return $item;
    }

    private function getHistory(): array
    {
        return Loader::getBlackMarketAuctionInstance()->getHistory();
    }

    private function getPageEntries(): array
    {
        return array_slice($this->getHistory(), ($this->page - 1) * self::ITEMS_PER_PAGE, self::ITEMS_PER_PAGE);
    }
}
