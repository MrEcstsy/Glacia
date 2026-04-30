<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\trade;

use ecstsy\Glacia\server\trade\TradeSession;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\transaction\InvMenuTransaction;
use muqsit\invmenu\transaction\InvMenuTransactionResult;
use muqsit\invmenu\type\InvMenuTypeIds;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\inventory\Inventory;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class TradeScreen {

    private InvMenu $menu;

    public function __construct(
        private TradeSession $session,
        private Player $viewer,
        private Player $partner
    ) {
        $this->menu = InvMenu::create(InvMenuTypeIds::TYPE_DOUBLE_CHEST);
        $this->menu->setName(C::colorize("&r&8Trading: &f" . $this->partner->getName()));
        $this->menu->setListener(function(InvMenuTransaction $transaction): InvMenuTransactionResult {
            $slot = $transaction->getAction()->getSlot();
            $player = $transaction->getPlayer();
            if ($slot === TradeSession::READY_SLOT) {
                $this->session->handleReadySlotClick($player);
                return $transaction->discard();
            }

            if (in_array($slot, $this->session->getOwnOfferSlots($player), true)) {
                return $this->session->handleOfferTransaction($player, $slot, $transaction);
            }

            return $transaction->discard();
        });

        $this->menu->setInventoryCloseListener(function(Player $player, Inventory $inventory): void {
            $this->session->onClosedBy($player);
        });

        $this->render();
    }

    public function send(): void
    {
        $this->menu->send($this->viewer);
    }

    public function closeForViewer(): void
    {
        if ($this->viewer->getCurrentWindow() === $this->menu->getInventory()) {
            $this->viewer->removeCurrentWindow();
        }
    }

    public function render(): void
    {
        $inventory = $this->menu->getInventory();
        $inventory->clearAll();

        foreach (range(0, 53) as $slot) {
            $filler = VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::BLACK())->asItem()->setCustomName(C::colorize("&r "));
            $inventory->setItem($slot, $filler);
        }

        foreach ($this->session->getOwnOfferSlots($this->viewer) as $slot) {
            $inventory->setItem($slot, VanillaItems::AIR());
        }
        foreach ($this->session->getPartnerOfferSlots($this->viewer) as $slot) {
            $inventory->setItem($slot, VanillaItems::AIR());
        }

        $own = $this->session->getOwnOfferItems($this->viewer);
        foreach ($this->session->getOwnOfferSlots($this->viewer) as $i => $slot) {
            $item = $own[$i] ?? null;
            if ($item !== null) {
                $inventory->setItem($slot, clone $item);
            }
        }

        $partnerItems = $this->session->getPartnerOfferItems($this->viewer);
        foreach ($this->session->getPartnerOfferSlots($this->viewer) as $i => $slot) {
            $item = $partnerItems[$i] ?? null;
            if ($item !== null) {
                $inventory->setItem($slot, clone $item);
            }
        }

        $ownInfo = VanillaItems::PAPER();
        $ownInfo->setCustomName(C::colorize("&r&aYour Side"));
        $ownInfo->setLore([
            C::colorize("&r&7Drag and drop items into"),
            C::colorize("&r&7your offer slots."),
            C::colorize("&r&7Take items back by moving"),
            C::colorize("&r&7them out of your side."),
        ]);
        $inventory->setItem(0, $ownInfo);

        $partnerInfo = VanillaItems::PAPER();
        $partnerInfo->setCustomName(C::colorize("&r&b" . $this->partner->getName() . "'s Side"));
        $inventory->setItem(8, $partnerInfo);

        $phase = $this->session->getPhaseReadyCount();
        if ($this->session->isAwaitFinalConfirm()) {
            $dye = DyeColor::YELLOW();
            $custom = "&r&e&l★ CONFIRM";
            $lines = [
                C::colorize("&r&7You tapped: &f" . ($this->session->hasConfirmedFinal($this->viewer) ? "&aYes" : "&cNo")),
                C::colorize("&r&7Partner tapped: &f" . ($this->session->hasPartnerConfirmedFinal($this->viewer) ? "&aYes" : "&cNo")),
            ];
            $extra = [
                C::colorize("&r&eBoth players tap again to"),
                C::colorize("&r&eexecute the trade."),
            ];
        } elseif ($this->session->isInCountdown()) {
            $dye = DyeColor::LIME();
            $custom = "&r&b&l3… 2… 1…";
            $countdownStack = max(1, min(3, $this->session->getCountdownLeft()));
            $lines = [
                C::colorize("&r&aFirst-stage agreement sealed."),
                C::colorize("&r&7Countdown: &f" . $this->session->getCountdownLeft() . "s"),
            ];
            $extra = [C::colorize("&r&cDo not change your offer.")];
        } else {
            $dye = match ($phase) {
                0 => DyeColor::RED(),
                1 => DyeColor::ORANGE(),
                default => DyeColor::LIME(),
            };
            $custom = match ($phase) {
                0 => "&r&c&lNot ready",
                1 => "&r&6&lOne player ready",
                default => "&r&a&lBoth players ready…",
            };
            $lines = [
                C::colorize("&r&7You locked in: &f" . ($this->session->isViewerPhaseReady($this->viewer) ? "&aYes" : "&cNo")),
                C::colorize("&r&7Partner locked in: &f" . ($this->session->isPartnerPhaseReady($this->viewer) ? "&aYes" : "&cNo")),
            ];
            $extra = [
                C::colorize(($phase >= 2) ? "&r&aStarting countdown..." : "&r&7Tap when your offer looks right."),
            ];
        }

        $ready = VanillaBlocks::WOOL()->setColor($dye)->asItem();
        if (isset($countdownStack)) {
            $ready->setCount($countdownStack);
        }
        $ready->setCustomName(C::colorize($custom));
        $ready->setLore([...$lines, ...$extra, C::colorize("&r&8────────")]);
        $inventory->setItem(TradeSession::READY_SLOT, $ready);

        $divider = VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::GRAY())->asItem()->setCustomName(C::colorize("&r&8Trade Divider"));
        foreach (TradeSession::DIVIDER_SLOTS as $slot) {
            $inventory->setItem($slot, clone $divider);
        }
    }
}

