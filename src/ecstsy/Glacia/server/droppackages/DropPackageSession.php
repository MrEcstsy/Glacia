<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\droppackages;

use pocketmine\item\Item;

final class DropPackageSession {

    public const PHASE_SELECTING = 0;
    public const PHASE_ANIMATING = 1;
    public const PHASE_READY_TO_REVEAL = 2;
    public const PHASE_COMPLETE = 3;

    /** @var array<int, true> */
    private array $selectedSlots = [];

    /** @var array<int, true> */
    private array $revealedRewards = [];

    /** @var array<int, string> */
    private array $slotRarities = [];

    /** @var array<int, Item> */
    private array $slotRewards = [];

    private int $phase = self::PHASE_SELECTING;
    private bool $locked = false;
    private bool $granted = false;

    public function __construct(
        private readonly string $packageType,
        private readonly string $displayName,
        private readonly string $title,
        private readonly int $pickAmount = 5
    ){}

    public function getPackageType(): string{
        return $this->packageType;
    }

    public function getDisplayName(): string{
        return $this->displayName;
    }

    public function getTitle(): string{
        return $this->title;
    }

    public function getPickAmount(): int{
        return $this->pickAmount;
    }

    public function getPhase(): int{
        return $this->phase;
    }

    public function setPhase(int $phase): void{
        $this->phase = $phase;
    }

    public function isSelecting(): bool{
        return $this->phase === self::PHASE_SELECTING;
    }

    public function isAnimating(): bool{
        return $this->phase === self::PHASE_ANIMATING;
    }

    public function isReadyToReveal(): bool{
        return $this->phase === self::PHASE_READY_TO_REVEAL;
    }

    public function isComplete(): bool{
        return $this->phase === self::PHASE_COMPLETE;
    }

    public function lockSelections(): void{
        $this->locked = true;
    }

    public function isLocked(): bool{
        return $this->locked;
    }

    public function addSelection(int $slot): void{
        if($this->locked){
            return;
        }

        $this->selectedSlots[$slot] = true;
    }

    public function removeSelection(int $slot): void{
        if($this->locked){
            return;
        }

        unset($this->selectedSlots[$slot]);
    }

    public function isSelected(int $slot): bool{
        return isset($this->selectedSlots[$slot]);
    }

    public function getSelectedCount(): int{
        return count($this->selectedSlots);
    }

    /**
     * @return int[]
     */
    public function getSelectedSlots(): array{
        return array_keys($this->selectedSlots);
    }

    public function needsAutoComplete(): bool{
        return $this->getSelectedCount() < $this->pickAmount;
    }

    public function markRewardRevealed(int $slot): void{
        $this->revealedRewards[$slot] = true;
    }

    public function isRewardRevealed(int $slot): bool{
        return isset($this->revealedRewards[$slot]);
    }

    public function getRevealedCount(): int{
        return count($this->revealedRewards);
    }

    public function areAllSelectedRewardsRevealed(): bool{
        return $this->getRevealedCount() >= $this->pickAmount;
    }

    public function setSlotRarity(int $slot, string $rarity): void{
        $this->slotRarities[$slot] = strtolower($rarity);
    }

    public function getSlotRarity(int $slot): ?string{
        return $this->slotRarities[$slot] ?? null;
    }

    public function setSlotReward(int $slot, Item $item): void{
        $this->slotRewards[$slot] = clone $item;
    }

    public function getSlotReward(int $slot): ?Item{
        return isset($this->slotRewards[$slot]) ? clone $this->slotRewards[$slot] : null;
    }

    public function hasGrantedRewards(): bool{
        return $this->granted;
    }

    public function markRewardsGranted(): void{
        $this->granted = true;
    }
}