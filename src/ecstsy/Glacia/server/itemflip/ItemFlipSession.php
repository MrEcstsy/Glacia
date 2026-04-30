<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\itemflip;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\screens\itemflip\ItemFlipScreen;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use muqsit\invmenu\transaction\InvMenuTransaction;
use muqsit\invmenu\transaction\InvMenuTransactionResult;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\TaskHandler;
use pocketmine\utils\TextFormat as C;

final class ItemFlipSession {

    public const LEFT_OFFER_SLOTS = [0, 1, 2, 3, 9, 10, 11, 12, 18, 19, 20, 21, 27, 28, 29, 30, 36, 37, 38, 39];
    public const RIGHT_OFFER_SLOTS = [5, 6, 7, 8, 14, 15, 16, 17, 23, 24, 25, 26, 32, 33, 34, 35, 41, 42, 43, 44];
    public const DIVIDER_SLOTS = [4, 13, 22, 31, 40];
    public const ROLL_SLOTS = [45, 46, 47, 48, 49, 50, 51, 52, 53];
    public const COLOR_SELECTOR_SLOTS = [40, 45, 46, 47, 48, 50, 51, 52];

    private const READY_SLOT = 53;
    private const CENTER_SLOT = 49;
    private const COUNTDOWN_SECONDS = 3;
    private const ROLL_STEPS = 36;
    private const ROLL_INTERVAL_TICKS = 3;

    /** @var array<string, array<int, Item|null>> */
    private array $offers = [];
    /** @var array<string, string> */
    private array $selectedColor = [];
    /** @var array<string, bool> */
    private array $ready = [];
    /** @var array<string, ItemFlipScreen> */
    private array $screens = [];
    /** @var array<int, Item> */
    private array $rollingRow = [];
    private ?TaskHandler $countdownTask = null;
    private ?TaskHandler $rollTask = null;
    private int $countdownLeft = 0;
    private int $rollTick = 0;
    private bool $countingDown = false;
    private bool $rolling = false;
    private bool $finished = false;
    private bool $closed = false;
    private ?string $winnerKey = null;

    /** @var array<string, array{label:string,code:string}> */
    public const CHOICES = [
        "red" => ["label" => "Red", "code" => "&c"],
        "orange" => ["label" => "Orange", "code" => "&6"],
        "yellow" => ["label" => "Yellow", "code" => "&e"],
        "lime" => ["label" => "Lime", "code" => "&a"],
        "cyan" => ["label" => "Cyan", "code" => "&b"],
        "light_blue" => ["label" => "Light Blue", "code" => "&9"],
        "purple" => ["label" => "Purple", "code" => "&5"],
        "pink" => ["label" => "Pink", "code" => "&d"],
    ];

    public function __construct(
        private Player $playerA,
        private Player $playerB
    ) {
        $a = $this->playerA->getUniqueId()->toString();
        $b = $this->playerB->getUniqueId()->toString();
        $blank = array_fill(0, count(self::LEFT_OFFER_SLOTS), null);
        $this->offers[$a] = $blank;
        $this->offers[$b] = $blank;
        $this->ready[$a] = false;
        $this->ready[$b] = false;
    }

    public function open(): void
    {
        $screenA = new ItemFlipScreen($this, $this->playerA, $this->playerB);
        $screenB = new ItemFlipScreen($this, $this->playerB, $this->playerA);
        $this->screens[$this->playerA->getUniqueId()->toString()] = $screenA;
        $this->screens[$this->playerB->getUniqueId()->toString()] = $screenB;

        foreach (self::ROLL_SLOTS as $slot) {
            $this->rollingRow[$slot] = $this->makeNeutralPane();
        }

        $screenA->send();
        $screenB->send();
        $this->refresh();
    }

    public function getPlayerA(): Player { return $this->playerA; }
    public function getPlayerB(): Player { return $this->playerB; }
    public function isRolling(): bool { return $this->rolling; }
    public function isFinished(): bool { return $this->finished; }
    public function isCountingDown(): bool { return $this->countingDown; }
    public function getCountdownLeft(): int { return $this->countdownLeft; }
    public function getWinnerKey(): ?string { return $this->winnerKey; }
    public function getReadySlot(): int { return self::READY_SLOT; }
    public function getCenterSlot(): int { return self::CENTER_SLOT; }

    public function handleOfferTransaction(Player $player, int $slot, InvMenuTransaction $transaction): InvMenuTransactionResult
    {
        if ($this->closed || $this->rolling || $this->finished) {
            return $transaction->discard();
        }

        if ($this->countingDown) {
            $player->sendMessage(C::colorize("&r&cWait until the countdown finishes."));
            return $transaction->discard();
        }

        $index = array_search($slot, $this->getOwnOfferSlots($player), true);
        if ($index === false) {
            return $transaction->discard();
        }

        $uid = $player->getUniqueId()->toString();
        $incoming = clone $transaction->getIn();
        $this->offers[$uid][$index] = $incoming->isNull() ? null : $incoming;
        $this->resetReadyState();

        return $transaction->continue()->then(function() : void {
            $this->refresh();
        });
    }

    public function handleButtonClick(Player $player, int $slot): void
    {
        if ($this->closed || $this->rolling || $this->finished) {
            return;
        }

        if ($slot === self::READY_SLOT) {
            $this->toggleReady($player);
            return;
        }

        $selectorIndex = array_search($slot, self::COLOR_SELECTOR_SLOTS, true);
        if ($selectorIndex === false) {
            return;
        }

        $colorKey = array_keys(self::CHOICES)[$selectorIndex] ?? null;
        if ($colorKey !== null) {
            $this->chooseColor($player, $colorKey);
        }
    }

    public function chooseColor(Player $player, string $colorKey): void
    {
        if ($this->closed || $this->countingDown || $this->rolling || $this->finished) {
            return;
        }
        if (!isset(self::CHOICES[$colorKey])) {
            return;
        }

        $playerKey = $player->getUniqueId()->toString();
        $otherKey = $this->getPartner($player)->getUniqueId()->toString();
        if (($this->selectedColor[$otherKey] ?? null) === $colorKey) {
            $player->sendMessage(C::colorize("&r&cThat color is already taken by your opponent."));
            return;
        }

        $this->selectedColor[$playerKey] = $colorKey;
        $this->resetReadyState();
        $this->refresh();
    }

    public function toggleReady(Player $player): void
    {
        if ($this->closed || $this->rolling || $this->finished) {
            return;
        }

        if ($this->countingDown) {
            $player->sendMessage(C::colorize("&r&eCountdown already started."));
            return;
        }

        $uid = $player->getUniqueId()->toString();
        if (!isset($this->selectedColor[$uid])) {
            $player->sendMessage(C::colorize("&r&cPick your wool color first."));
            return;
        }
        if (!$this->hasAnyOfferedItems($player)) {
            $player->sendMessage(C::colorize("&r&cAdd at least one item before readying up."));
            return;
        }

        $this->ready[$uid] = !($this->ready[$uid] ?? false);
        $player->sendMessage(C::colorize(($this->ready[$uid] ?? false) ? "&r&aReady locked in." : "&r&eReady removed."));        
        $this->refresh();
        $this->recheckCountdownState();
    }

    public function getChosenColor(Player $player): ?string
    {
        return $this->selectedColor[$player->getUniqueId()->toString()] ?? null;
    }

    public function getPartnerChosenColor(Player $player): ?string
    {
        return $this->selectedColor[$this->getPartner($player)->getUniqueId()->toString()] ?? null;
    }

    public function getSelectorOwnerName(string $colorKey): ?string
    {
        foreach ([$this->playerA, $this->playerB] as $player) {
            if (($this->selectedColor[$player->getUniqueId()->toString()] ?? null) === $colorKey) {
                return $player->getName();
            }
        }

        return null;
    }

    public function isViewerReady(Player $viewer): bool
    {
        return $this->ready[$viewer->getUniqueId()->toString()] ?? false;
    }

    public function isOpponentReady(Player $viewer): bool
    {
        return $this->ready[$this->getPartner($viewer)->getUniqueId()->toString()] ?? false;
    }

    public function getReadyCount(): int
    {
        $count = 0;
        foreach ($this->ready as $ready) {
            if ($ready) {
                ++$count;
            }
        }

        return $count;
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
    public function getOpponentOfferItems(Player $viewer): array
    {
        return $this->offers[$this->getPartner($viewer)->getUniqueId()->toString()] ?? array_fill(0, count(self::LEFT_OFFER_SLOTS), null);
    }

    /**
     * @return list<int>
     */
    public function getOwnOfferSlots(Player $viewer): array
    {
        return $viewer->getUniqueId()->toString() === $this->playerA->getUniqueId()->toString()
            ? self::LEFT_OFFER_SLOTS
            : self::RIGHT_OFFER_SLOTS;
    }

    /**
     * @return list<int>
     */
    public function getOpponentOfferSlots(Player $viewer): array
    {
        return $viewer->getUniqueId()->toString() === $this->playerA->getUniqueId()->toString()
            ? self::RIGHT_OFFER_SLOTS
            : self::LEFT_OFFER_SLOTS;
    }

    public function getRollItem(int $slot): Item
    {
        return clone ($this->rollingRow[$slot] ?? $this->makeNeutralPane());
    }

    public function onClosed(Player $player): void
    {
        if ($this->closed) {
            return;
        }

        if ($this->countingDown || $this->rolling || $this->finished) {
            return;
        }

        $this->cancel($player->getName() . " closed the Item Flip.");
    }

    public function cancel(string $reason): void
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;
        $this->stopCountdownTask();
        $this->stopRollTask();
        $this->refundCreditsToBoth();
        $this->returnOffersToOwners();

        foreach ([$this->playerA, $this->playerB] as $player) {
            if ($player->isOnline()) {
                $player->sendMessage(C::colorize("&r&c" . $reason));
            }
        }

        foreach ($this->screens as $screen) {
            $screen->closeForViewer();
        }
        ItemFlipManager::unregisterSession($this);
    }

    private function recheckCountdownState(): void
    {
        if ($this->closed || $this->rolling || $this->finished || $this->countingDown) {
            return;
        }

        $aKey = $this->playerA->getUniqueId()->toString();
        $bKey = $this->playerB->getUniqueId()->toString();
        if (($this->ready[$aKey] ?? false) && ($this->ready[$bKey] ?? false)) {
            $this->startCountdown();
        }
    }

    private function startCountdown(): void
    {
        if ($this->closed || $this->rolling || $this->finished || $this->countingDown) {
            return;
        }

        $this->countingDown = true;
        $this->countdownLeft = self::COUNTDOWN_SECONDS;
        $this->refresh();

        $this->countdownTask = Loader::getInstance()->getScheduler()->scheduleRepeatingTask(new ClosureTask(function(): void {
            if ($this->closed || $this->rolling || $this->finished) {
                $this->stopCountdownTask();
                return;
            }

            $aKey = $this->playerA->getUniqueId()->toString();
            $bKey = $this->playerB->getUniqueId()->toString();
            if (!(($this->ready[$aKey] ?? false) && ($this->ready[$bKey] ?? false))) {
                $this->stopCountdownTask();
                $this->refresh();
                return;
            }

            $this->countdownLeft--;
            if ($this->countdownLeft <= 0) {
                $this->stopCountdownTask();
                $this->startRoll();
                return;
            }

            $this->refresh();
        }), 20);
    }

    private function startRoll(): void
    {
        if ($this->closed || $this->rolling || $this->finished) {
            return;
        }

        $aColor = $this->selectedColor[$this->playerA->getUniqueId()->toString()] ?? null;
        $bColor = $this->selectedColor[$this->playerB->getUniqueId()->toString()] ?? null;
        if ($aColor === null || $bColor === null) {
            $this->cancel("Item Flip cancelled because a player is missing a color.");
            return;
        }

        $this->rolling = true;
        $this->rollTick = 0;
        foreach (self::ROLL_SLOTS as $slot) {
            $this->rollingRow[$slot] = $this->makeColorPane(mt_rand(0, 1) === 0 ? $aColor : $bColor);
        }
        $this->refresh();

        $this->rollTask = Loader::getInstance()->getScheduler()->scheduleRepeatingTask(new ClosureTask(function() use ($aColor, $bColor): void {
            if ($this->closed) {
                $this->stopRollTask();
                return;
            }

            $this->rollTick++;

            for ($i = count(self::ROLL_SLOTS) - 1; $i > 0; $i--) {
                $this->rollingRow[self::ROLL_SLOTS[$i]] = clone $this->rollingRow[self::ROLL_SLOTS[$i - 1]];
            }
            $this->rollingRow[self::ROLL_SLOTS[0]] = $this->makeColorPane(mt_rand(0, 1) === 0 ? $aColor : $bColor);

            if ($this->rollTick >= self::ROLL_STEPS) {
                $winnerIsA = mt_rand(0, 1) === 0;
                $this->winnerKey = $winnerIsA ? $this->playerA->getUniqueId()->toString() : $this->playerB->getUniqueId()->toString();
                $winnerColor = $winnerIsA ? $aColor : $bColor;
                $this->rollingRow[self::CENTER_SLOT] = $this->makeColorPane($winnerColor);
                $this->finishRoll();
                return;
            }

            $this->refreshRollRow();
        }), self::ROLL_INTERVAL_TICKS);
    }

    private function finishRoll(): void
    {
        $this->stopRollTask();
        $this->rolling = false;
        $this->finished = true;

        $winner = $this->winnerKey === $this->playerA->getUniqueId()->toString() ? $this->playerA : $this->playerB;
        $loser = $winner === $this->playerA ? $this->playerB : $this->playerA;
        $winnerSession = Loader::getPlayerManager()->getSession($winner);
        if ($winnerSession !== null) {
            foreach ($this->collectAllStakedItems() as $item) {
                $winnerSession->addItemToCollection($item);
            }
        }

        $winnerColor = $this->selectedColor[$winner->getUniqueId()->toString()] ?? "unknown";
        $winnerLabel = self::CHOICES[$winnerColor]["label"] ?? ucfirst($winnerColor);
        $winnerCode = self::CHOICES[$winnerColor]["code"] ?? "&f";

        if ($winner->isOnline()) {
            $winner->sendMessage(C::colorize("&r&aYou won the Item Flip with {$winnerCode}{$winnerLabel}&a."));
            $winner->sendMessage(C::colorize("&r&eAll winnings were sent to &f/collect&e."));
            PlayerUtils::playSound($winner, "random.levelup");
        }
        if ($loser->isOnline()) {
            $loser->sendMessage(C::colorize("&r&cYou lost the Item Flip. Winner: &f{$winner->getName()}&c."));
            PlayerUtils::playSound($loser, "note.bass");
        }

        $this->refresh();
        Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function(): void {
            if ($this->closed) {
                return;
            }

            $this->closed = true;
            foreach ($this->screens as $screen) {
                $screen->closeForViewer();
            }
            ItemFlipManager::unregisterSession($this);
        }), 60);
    }

    private function resetReadyState(): void
    {
        $this->stopCountdownTask();
        $this->ready[$this->playerA->getUniqueId()->toString()] = false;
        $this->ready[$this->playerB->getUniqueId()->toString()] = false;
    }

    private function stopCountdownTask(): void
    {
        if ($this->countdownTask !== null) {
            $this->countdownTask->cancel();
            $this->countdownTask = null;
        }
        $this->countingDown = false;
        $this->countdownLeft = 0;
    }

    private function stopRollTask(): void
    {
        if ($this->rollTask !== null) {
            $this->rollTask->cancel();
            $this->rollTask = null;
        }
    }

    private function refresh(): void
    {
        foreach ($this->screens as $screen) {
            $screen->render();
        }
    }

    private function refreshRollRow(): void
    {
        foreach ($this->screens as $screen) {
            $screen->renderRollRow();
        }
    }

    private function hasAnyOfferedItems(Player $player): bool
    {
        foreach ($this->offers[$player->getUniqueId()->toString()] ?? [] as $item) {
            if ($item instanceof Item && !$item->isNull()) {
                return true;
            }
        }

        return false;
    }

    private function returnOffersToOwners(): void
    {
        $this->returnOffersFor($this->playerA);
        $this->returnOffersFor($this->playerB);
    }

    private function returnOffersFor(Player $player): void
    {
        foreach ($this->offers[$player->getUniqueId()->toString()] ?? [] as $item) {
            if (!$item instanceof Item || $item->isNull()) {
                continue;
            }
            foreach ($player->getInventory()->addItem(clone $item) as $leftover) {
                $player->getWorld()->dropItem($player->getPosition(), $leftover);
            }
        }
    }

    private function refundCreditsToBoth(): void
    {
        $aSession = Loader::getPlayerManager()->getSession($this->playerA);
        $bSession = Loader::getPlayerManager()->getSession($this->playerB);
        $aSession?->addItemFlipCredits(1);
        $bSession?->addItemFlipCredits(1);
    }

    /**
     * @return list<Item>
     */
    private function collectAllStakedItems(): array
    {
        $items = [];
        foreach ($this->offers as $offerList) {
            foreach ($offerList as $item) {
                if ($item instanceof Item && !$item->isNull()) {
                    $items[] = clone $item;
                }
            }
        }

        return $items;
    }

    private function getPartner(Player $player): Player
    {
        return $player->getUniqueId()->toString() === $this->playerA->getUniqueId()->toString() ? $this->playerB : $this->playerA;
    }

    private function makeColorPane(string $colorKey): Item
    {
        $choice = self::CHOICES[$colorKey] ?? null;
        if ($choice === null) {
            return $this->makeNeutralPane();
        }

        $item = VanillaBlocks::WOOL()->setColor(self::toDyeColor($colorKey))->asItem();
        $item->setCustomName(C::colorize("&r" . $choice["code"] . "&l" . $choice["label"]));
        return $item;
    }

    private function makeNeutralPane(): Item
    {
        return VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::GRAY())->asItem()->setCustomName(C::colorize("&r "));
    }

    private static function toDyeColor(string $colorKey): DyeColor
    {
        return match ($colorKey) {
            "red" => DyeColor::RED(),
            "orange" => DyeColor::ORANGE(),
            "yellow" => DyeColor::YELLOW(),
            "lime" => DyeColor::LIME(),
            "cyan" => DyeColor::CYAN(),
            "light_blue" => DyeColor::LIGHT_BLUE(),
            "purple" => DyeColor::PURPLE(),
            "pink" => DyeColor::PINK(),
            default => DyeColor::GRAY(),
        };
    }
}
