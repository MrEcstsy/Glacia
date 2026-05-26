<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\trait;

use ecstsy\Glacia\entity\FloatingTextEntity;
use pocketmine\entity\Location;
use pocketmine\nbt\tag\CompoundTag;

trait InteractiveSpawnHologramTrait
{
    private const TAG_HOLOGRAM_Y = "glacia_hologram_y";

    private ?FloatingTextEntity $labelHologram = null;
    private float $hologramYOffset = 0.0;
    private bool $hologramYOffsetInitialized = false;

    protected function loadInteractiveSpawnHologram(CompoundTag $nbt): void
    {
        if ($nbt->getTag(self::TAG_HOLOGRAM_Y) !== null) {
            $this->hologramYOffset = $nbt->getFloat(self::TAG_HOLOGRAM_Y);
            $this->hologramYOffsetInitialized = true;
            return;
        }

        $this->hologramYOffset = $this->getDefaultLabelHologramYOffset();
        $this->hologramYOffsetInitialized = true;
    }

    protected function saveInteractiveSpawnHologram(CompoundTag $nbt): CompoundTag
    {
        if (!$this->hologramYOffsetInitialized) {
            $this->hologramYOffset = $this->getDefaultLabelHologramYOffset();
        }

        $nbt->setFloat(self::TAG_HOLOGRAM_Y, $this->hologramYOffset);
        return $nbt;
    }

    protected function getDefaultLabelHologramYOffset(): float
    {
        return 2.35;
    }

    protected function getLabelHologramYOffset(): float
    {
        if (!$this->hologramYOffsetInitialized) {
            $this->hologramYOffset = $this->getDefaultLabelHologramYOffset();
            $this->hologramYOffsetInitialized = true;
        }

        return $this->hologramYOffset;
    }

    public function getHologramYOffset(): float
    {
        return $this->getLabelHologramYOffset();
    }

    public function shiftHologramYOffset(float $delta): void
    {
        $this->getLabelHologramYOffset();
        $this->hologramYOffset += $delta;
        $this->syncLabelHologramPosition();
    }

    protected function syncLabelHologram(): void
    {
        $label = $this->getDisplayLabel();
        if ($label === "") {
            $this->removeLabelHologram();
            return;
        }

        $this->hideEntityNameTag();

        if ($this->labelHologram === null || $this->labelHologram->isClosed()) {
            $this->spawnLabelHologram($label);
            return;
        }

        $this->labelHologram->setText($label);
        $this->syncLabelHologramPosition();
    }

    protected function syncLabelHologramPosition(): void
    {
        if ($this->labelHologram === null || $this->labelHologram->isClosed()) {
            return;
        }

        $anchor = $this->getInteractiveSpawnAnchorLocation();
        $this->labelHologram->teleport(new Location(
            $anchor->x,
            $anchor->y + $this->getLabelHologramYOffset(),
            $anchor->z,
            $anchor->getWorld(),
            0.0,
            0.0
        ));
    }

    protected function removeLabelHologram(): void
    {
        if ($this->labelHologram !== null && !$this->labelHologram->isClosed()) {
            $this->labelHologram->flagForDespawn();
        }
        $this->labelHologram = null;
    }

    protected function onDispose(): void
    {
        $this->removeLabelHologram();
        parent::onDispose();
    }

    private function spawnLabelHologram(string $label): void
    {
        $this->removeLabelHologram();

        $anchor = $this->getInteractiveSpawnAnchorLocation();
        $this->labelHologram = new FloatingTextEntity(new Location(
            $anchor->x,
            $anchor->y + $this->getLabelHologramYOffset(),
            $anchor->z,
            $anchor->getWorld(),
            0.0,
            0.0
        ));
        $this->labelHologram->setText($label);
        $this->labelHologram->spawnToAll();
    }

    private function hideEntityNameTag(): void
    {
        $this->setNameTag("");
        $this->setNameTagVisible(false);
        $this->setNameTagAlwaysVisible(false);
    }
}
