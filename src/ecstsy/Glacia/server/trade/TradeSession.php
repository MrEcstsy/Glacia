<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\trade;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\screens\trade\TradeScreen;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use muqsit\invmenu\transaction\InvMenuTransaction;
use muqsit\invmenu\transaction\InvMenuTransactionResult;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\TaskHandler;
use pocketmine\utils\TextFormat as C;

final class TradeSession {

    // Sender (player A) always uses left; receiver (player B) always uses right.
    public const LEFT_OFFER_SLOTS = [1, 2, 3, 9, 10, 11, 12, 18, 19, 20, 21, 27, 28, 29, 30, 36, 37, 38, 39, 45, 46, 47, 48];
    public const RIGHT_OFFER_SLOTS = [5, 6, 7, 14, 15, 16, 17, 23, 24, 25, 26, 32, 33, 34, 35, 41, 42, 43, 44, 50, 51, 52, 53];
    public const DIVIDER_SLOTS = [4, 13, 22, 31, 40];
    public const READY_SLOT = 49;

    /** @var array<string, array<int, Item|null>> */
    private array $offers = [];
    /** Phase 1: both players locked in to negotiate */
    /** @var array<string, bool> */
    private array $ready = [];
    /** After countdown: tap wool again to lock the trade */
    /** @var array<string, bool> */
    private array $finalConfirm = [];

    /** @var array<string, TradeScreen> */
    private array $screens = [];
    private bool $closed = false;
    private bool $finalizing = false;

    private bool $inCountdown = false;
    private int $countdownLeft = 0;
    /** True between countdown end and successful finalize */
    private bool $awaitFinalConfirm = false;
    private ?TaskHandler $countdownTask = null;

    public function __construct(private Player $playerA, private Player $playerB)
    {
        $a = $this->playerA->getUniqueId()->toString();
        $b = $this->playerB->getUniqueId()->toString();
        $fill = array_fill(0, count(self::LEFT_OFFER_SLOTS), null);
        $this->offers[$a] = $fill;
        $this->offers[$b] = $fill;
        $this->ready[$a] = false;
        $this->ready[$b] = false;
        $this->finalConfirm[$a] = false;
        $this->finalConfirm[$b] = false;
    }

    public function open(): void
    {
        $screenA = new TradeScreen($this, $this->playerA, $this->playerB);
        $screenB = new TradeScreen($this, $this->playerB, $this->playerA);
        $this->screens[$this->playerA->getUniqueId()->toString()] = $screenA;
        $this->screens[$this->playerB->getUniqueId()->toString()] = $screenB;
        $screenA->send();
        $screenB->send();

        $this->playerA->sendMessage(C::colorize("&r&aTrading with &f{$this->playerB->getName()}&a."));
        $this->playerB->sendMessage(C::colorize("&r&aTrading with &f{$this->playerA->getName()}&a."));
    }

    public function getPlayerA(): Player { return $this->playerA; }
    public function getPlayerB(): Player { return $this->playerB; }

    public function handleOfferTransaction(Player $player, int $slot, InvMenuTransaction $transaction): InvMenuTransactionResult
    {
        if ($this->closed || $this->finalizing) {
            return $transaction->discard();
        }

        $action = $transaction->getAction();
        if ($action->getSlot() !== $slot) {
            return $transaction->discard();
        }

        if ($this->inCountdown) {
            $player->sendMessage(C::colorize("&r&cWait until the countdown finishes."));
            return $transaction->discard();
        }

        $uid = $player->getUniqueId()->toString();
        $ownSlots = $this->getOwnOfferSlots($player);
        $index = array_search($slot, $ownSlots, true);
        if ($index === false) {
            return $transaction->discard();
        }

        if (!$this->inCountdown && $this->awaitFinalConfirm) {
            $player->sendMessage(C::colorize("&r&eOffer changed — restart ready & countdown."));
        }

        $incoming = clone $transaction->getIn();
        $this->offers[$uid][$index] = $incoming->isNull() ? null : $incoming;
        $this->resetNegotiationState();
        return $transaction->continue()->then(function(Player $player): void {
            $this->refresh();
        });
    }

    public function handleReadySlotClick(Player $player): void
    {
        if ($this->closed || $this->finalizing) {
            return;
        }

        if ($this->inCountdown) {
            $player->sendMessage(C::colorize("&r&eHold on — countdown in progress."));
            return;
        }

        $uid = $player->getUniqueId()->toString();

        if ($this->awaitFinalConfirm) {
            if ($this->finalConfirm[$uid] ?? false) {
                $player->sendMessage(C::colorize("&r&aYou already confirmed this trade."));
                return;
            }

            $this->finalConfirm[$uid] = true;
            PlayerUtils::playSound($player, "note.pling", 1, 1.25);
            $player->sendMessage(C::colorize("&r&aConfirmation registered. Waiting for partner."));

            $this->broadcastFinalConfirmTitles();

            if (($this->finalConfirm[$this->playerA->getUniqueId()->toString()] ?? false) &&
                ($this->finalConfirm[$this->playerB->getUniqueId()->toString()] ?? false)) {
                $this->finalize();
                return;
            }

            $this->refresh();
            return;
        }

        $this->ready[$uid] = !($this->ready[$uid] ?? false);
        $player->sendMessage(C::colorize(($this->ready[$uid] ?? false) ? "&r&aMarked as ready." : "&r&eNo longer marked ready."));
        PlayerUtils::playSound($player, "note.bass", 1, ($this->ready[$uid] ?? false) ? 1.05 : 0.75);
        $this->refresh();

        $aReady = $this->ready[$this->playerA->getUniqueId()->toString()] ?? false;
        $bReady = $this->ready[$this->playerB->getUniqueId()->toString()] ?? false;

        if ($aReady && $bReady) {
            Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function(): void {
                if ($this->closed || $this->finalizing || $this->inCountdown || $this->awaitFinalConfirm) {
                    return;
                }
                if (!($this->ready[$this->playerA->getUniqueId()->toString()] ?? false) ||
                    !($this->ready[$this->playerB->getUniqueId()->toString()] ?? false)) {
                    return;
                }
                $this->beginTradeCountdown();
            }), 1);
        }
    }

    /** How many players are phase-1 ready (0–2). */
    public function getPhaseReadyCount(): int
    {
        return $this->countsTrue($this->ready);
    }

    public function isInCountdown(): bool
    {
        return $this->inCountdown;
    }

    public function getCountdownLeft(): int
    {
        return $this->countdownLeft;
    }

    public function isAwaitFinalConfirm(): bool
    {
        return $this->awaitFinalConfirm;
    }

    /** Phase 1 readiness for toolbar wool name */
    public function isViewerPhaseReady(Player $viewer): bool
    {
        return $this->ready[$viewer->getUniqueId()->toString()] ?? false;
    }

    public function isPartnerPhaseReady(Player $viewer): bool
    {
        return $this->ready[$this->getPartner($viewer)->getUniqueId()->toString()] ?? false;
    }

    public function hasConfirmedFinal(Player $viewer): bool
    {
        return $this->finalConfirm[$viewer->getUniqueId()->toString()] ?? false;
    }

    public function hasPartnerConfirmedFinal(Player $viewer): bool
    {
        return $this->finalConfirm[$this->getPartner($viewer)->getUniqueId()->toString()] ?? false;
    }

    public function cancel(string $reason = "Trade cancelled."): void
    {
        if ($this->closed) {
            return;
        }
        $this->closed = true;
        $this->stopCountdownTask();

        $this->returnOffersToOwners();
        foreach ([$this->playerA, $this->playerB] as $player) {
            if ($player->isOnline()) {
                $player->sendMessage(C::colorize("&r&c" . $reason));
            }
        }

        foreach ($this->screens as $screen) {
            $screen->closeForViewer();
        }
        TradeManager::unregisterSession($this);
    }

    public function onClosedBy(Player $player): void
    {
        if ($this->closed || $this->finalizing) {
            return;
        }
        $this->cancel($player->getName() . " closed the trade.");
    }

    /**
     * @return list<Item|null>
     */
    public function getOwnOfferItems(Player $viewer): array
    {
        return $this->offers[$viewer->getUniqueId()->toString()] ?? array_fill(0, count(self::LEFT_OFFER_SLOTS), null);
    }

    /**
     * @return list<Item|null>
     */
    public function getPartnerOfferItems(Player $viewer): array
    {
        $partner = $this->getPartner($viewer);
        return $this->offers[$partner->getUniqueId()->toString()] ?? array_fill(0, count(self::LEFT_OFFER_SLOTS), null);
    }

    /**
     * @return list<int>
     */
    public function getOwnOfferSlots(Player $viewer): array
    {
        return $viewer->getUniqueId()->toString() === $this->playerA->getUniqueId()->toString() ? self::LEFT_OFFER_SLOTS : self::RIGHT_OFFER_SLOTS;
    }

    /**
     * @return list<int>
     */
    public function getPartnerOfferSlots(Player $viewer): array
    {
        return $viewer->getUniqueId()->toString() === $this->playerA->getUniqueId()->toString() ? self::RIGHT_OFFER_SLOTS : self::LEFT_OFFER_SLOTS;
    }

    private function finalize(): void
    {
        if ($this->closed || $this->finalizing) {
            return;
        }
        $this->finalizing = true;
        $this->stopCountdownTask();
        $offerA = array_values(array_filter($this->offers[$this->playerA->getUniqueId()->toString()] ?? [], static fn($item): bool => $item instanceof Item));
        $offerB = array_values(array_filter($this->offers[$this->playerB->getUniqueId()->toString()] ?? [], static fn($item): bool => $item instanceof Item));

        foreach ($offerA as $item) {
            foreach ($this->playerB->getInventory()->addItem($item) as $leftover) {
                $this->playerB->getWorld()->dropItem($this->playerB->getPosition(), $leftover);
            }
        }
        foreach ($offerB as $item) {
            foreach ($this->playerA->getInventory()->addItem($item) as $leftover) {
                $this->playerA->getWorld()->dropItem($this->playerA->getPosition(), $leftover);
            }
        }

        $this->closed = true;
        $this->playerA->sendMessage(C::colorize("&r&aTrade completed successfully."));
        $this->playerB->sendMessage(C::colorize("&r&aTrade completed successfully."));
        PlayerUtils::playSound($this->playerA, "random.levelup", 1, 1);
        PlayerUtils::playSound($this->playerB, "random.levelup", 1, 1);

        foreach ([$this->playerA, $this->playerB] as $p) {
            $p->sendTitle(C::colorize("&a✔ Trade complete"), "");
        }

        foreach ($this->screens as $screen) {
            $screen->closeForViewer();
        }
        TradeManager::unregisterSession($this);
    }

    private function returnOffersToOwners(): void
    {
        $aKey = $this->playerA->getUniqueId()->toString();
        foreach ($this->offers[$aKey] ?? [] as $item) {
            if (!$item instanceof Item) {
                continue;
            }
            foreach ($this->playerA->getInventory()->addItem($item) as $leftover) {
                $this->playerA->getWorld()->dropItem($this->playerA->getPosition(), $leftover);
            }
        }
        $bKey = $this->playerB->getUniqueId()->toString();
        foreach ($this->offers[$bKey] ?? [] as $item) {
            if (!$item instanceof Item) {
                continue;
            }
            foreach ($this->playerB->getInventory()->addItem($item) as $leftover) {
                $this->playerB->getWorld()->dropItem($this->playerB->getPosition(), $leftover);
            }
        }
    }

    private function resetNegotiationState(): void
    {
        $this->stopCountdownTask();
        $this->ready[$this->playerA->getUniqueId()->toString()] = false;
        $this->ready[$this->playerB->getUniqueId()->toString()] = false;
        $this->finalConfirm[$this->playerA->getUniqueId()->toString()] = false;
        $this->finalConfirm[$this->playerB->getUniqueId()->toString()] = false;
        $this->inCountdown = false;
        $this->countdownLeft = 0;
        $this->awaitFinalConfirm = false;
    }

    private function beginTradeCountdown(): void
    {
        if ($this->closed || $this->finalizing || $this->inCountdown) {
            return;
        }

        $this->inCountdown = true;
        $this->countdownLeft = 3;
        // Show "3" immediately, then 2 → 1 on the next whole seconds.
        $this->broadcastCountPulse(3);
        $this->refresh();

        $step = 0;
        $plugin = Loader::getInstance()->getScheduler();
        $this->countdownTask = $plugin->scheduleRepeatingTask(new ClosureTask(function() use (&$step): void {
            if ($this->closed || $this->finalizing) {
                $this->stopCountdownTask();
                return;
            }
            ++$step;
            if ($step === 1) {
                $this->countdownLeft = 2;
                $this->broadcastCountPulse(2);
                $this->refresh();
            } elseif ($step === 2) {
                $this->countdownLeft = 1;
                $this->broadcastCountPulse(1);
                $this->refresh();
            } elseif ($step >= 3) {
                $this->stopCountdownTask();
                $this->finishCountdownAndOpenConfirm();
            }
        }), 20);
    }

    private function finishCountdownAndOpenConfirm(): void
    {
        if ($this->closed || $this->finalizing) {
            return;
        }

        $this->inCountdown = false;
        $this->countdownLeft = 0;
        foreach ([$this->playerA, $this->playerB] as $p) {
            if ($p->isOnline()) {
                $p->sendTitle(C::colorize("&fConfirm"), C::colorize("&eTap the wool — both players"));
                PlayerUtils::playSound($p, "note.pling", 1, 1.35);
                $p->sendMessage(C::colorize("&r&eFinal step: tap the wool at the bottom edge to confirm the trade."));
            }
        }

        $this->ready[$this->playerA->getUniqueId()->toString()] = false;
        $this->ready[$this->playerB->getUniqueId()->toString()] = false;
        foreach ([$this->playerA, $this->playerB] as $p) {
            $this->finalConfirm[$p->getUniqueId()->toString()] = false;
        }
        $this->awaitFinalConfirm = true;
        $this->refresh();
    }

    private function broadcastCountPulse(int $n): void
    {
        foreach ([$this->playerA, $this->playerB] as $p) {
            if (!$p->isOnline()) {
                continue;
            }
            PlayerUtils::playSound($p, "random.click", 1, 1.05 + ($n * 0.05));
            $p->sendTitle(C::colorize("&f" . $n), C::colorize("&7Get ready"));
        }
    }

    private function broadcastFinalConfirmTitles(): void
    {
        $n = $this->countsTrue($this->finalConfirm);
        foreach ([$this->playerA, $this->playerB] as $p) {
            if (!$p->isOnline()) {
                continue;
            }
            PlayerUtils::playSound($p, "note.pling", 1, $n >= 2 ? 2.0 : 1.05);
            $p->sendTitle(C::colorize("&fConfirm "), C::colorize("&e{$n}&7/&f2"));
        }
    }

    private function stopCountdownTask(): void
    {
        if ($this->countdownTask !== null) {
            $this->countdownTask->cancel();
            $this->countdownTask = null;
        }
        $this->inCountdown = false;
        $this->countdownLeft = 0;
    }

    /**
     * @param array<string, bool> $map
     */
    private function countsTrue(array $map): int
    {
        $n = 0;
        foreach ($map as $v) {
            if ($v) {
                ++$n;
            }
        }
        return $n;
    }

    private function refresh(): void
    {
        foreach ($this->screens as $screen) {
            $screen->render();
        }
    }

    private function getPartner(Player $viewer): Player
    {
        return $viewer->getUniqueId()->toString() === $this->playerA->getUniqueId()->toString() ? $this->playerB : $this->playerA;
    }
}
