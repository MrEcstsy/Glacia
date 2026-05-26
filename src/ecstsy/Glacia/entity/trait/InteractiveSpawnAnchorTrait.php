<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\trait;

use pocketmine\entity\Location;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;

trait InteractiveSpawnAnchorTrait
{
    private const TAG_ANCHOR_X = "glacia_anchor_x";
    private const TAG_ANCHOR_Y = "glacia_anchor_y";
    private const TAG_ANCHOR_Z = "glacia_anchor_z";
    private const TAG_ANCHOR_YAW = "glacia_anchor_yaw";
    private const TAG_ANCHOR_PITCH = "glacia_anchor_pitch";

    private float $anchorX = 0.0;
    private float $anchorY = 0.0;
    private float $anchorZ = 0.0;
    private float $anchorYaw = 0.0;
    private float $anchorPitch = 0.0;
    private bool $anchorInitialized = false;

    protected function loadInteractiveSpawnAnchor(CompoundTag $nbt): void
    {
        if ($nbt->getTag(self::TAG_ANCHOR_X) !== null) {
            $this->anchorX = $nbt->getFloat(self::TAG_ANCHOR_X);
            $this->anchorY = $nbt->getFloat(self::TAG_ANCHOR_Y);
            $this->anchorZ = $nbt->getFloat(self::TAG_ANCHOR_Z);
            $this->anchorYaw = $nbt->getFloat(self::TAG_ANCHOR_YAW);
            $this->anchorPitch = $nbt->getFloat(self::TAG_ANCHOR_PITCH);
            $this->anchorInitialized = true;
            return;
        }

        $this->captureInteractiveSpawnAnchor();
    }

    protected function saveInteractiveSpawnAnchor(CompoundTag $nbt): CompoundTag
    {
        if (!$this->anchorInitialized) {
            $this->captureInteractiveSpawnAnchor();
        }

        $nbt->setFloat(self::TAG_ANCHOR_X, $this->anchorX);
        $nbt->setFloat(self::TAG_ANCHOR_Y, $this->anchorY);
        $nbt->setFloat(self::TAG_ANCHOR_Z, $this->anchorZ);
        $nbt->setFloat(self::TAG_ANCHOR_YAW, $this->anchorYaw);
        $nbt->setFloat(self::TAG_ANCHOR_PITCH, $this->anchorPitch);
        return $nbt;
    }

    protected function captureInteractiveSpawnAnchor(): void
    {
        $this->anchorX = $this->location->x;
        $this->anchorY = $this->location->y;
        $this->anchorZ = $this->location->z;
        $this->anchorYaw = $this->location->yaw;
        $this->anchorPitch = $this->location->pitch;
        $this->anchorInitialized = true;
    }

    public function hasMovementUpdate(): bool
    {
        return false;
    }

    protected function tryChangeMovement(): void
    {
    }

    protected function lockInteractiveSpawnToAnchor(): void
    {
        $this->setHasGravity(false);
        $this->setMotion(Vector3::zero());

        if (!$this->anchorInitialized) {
            $this->captureInteractiveSpawnAnchor();
        }

        $pos = $this->getPosition();
        $dx = abs($pos->x - $this->anchorX);
        $dy = abs($pos->y - $this->anchorY);
        $dz = abs($pos->z - $this->anchorZ);
        if ($dx > 0.001 || $dy > 0.001 || $dz > 0.001) {
            $this->teleport($this->getInteractiveSpawnAnchorLocation());
        }

        $this->onGround = true;
    }

    protected function getInteractiveSpawnAnchorLocation(): Location
    {
        return new Location(
            $this->anchorX,
            $this->anchorY,
            $this->anchorZ,
            $this->getWorld(),
            $this->anchorYaw,
            $this->anchorPitch
        );
    }

    public function shiftAnchorY(float $delta): void
    {
        if (!$this->anchorInitialized) {
            $this->captureInteractiveSpawnAnchor();
        }

        $this->anchorY += $delta;
        $this->teleport($this->getInteractiveSpawnAnchorLocation());
        $this->syncLabelHologramPositionIfPresent();
    }

    public function relocateAnchorTo(\pocketmine\player\Player $player, float $yOffset = 0.0): void
    {
        $isCrystal = $this instanceof \ecstsy\Glacia\entity\InteractiveCrystalEntity;
        $location = \ecstsy\Glacia\server\spawn\InteractiveSpawnPlacement::atPlayerFeet($player, $isCrystal, $yOffset);
        $this->anchorX = $location->x;
        $this->anchorY = $location->y;
        $this->anchorZ = $location->z;
        $this->anchorYaw = $location->yaw;
        $this->anchorPitch = $location->pitch;
        $this->anchorInitialized = true;
        $this->teleport($location);
        $this->syncLabelHologramPositionIfPresent();
    }

    private function syncLabelHologramPositionIfPresent(): void
    {
        if (method_exists($this, 'syncLabelHologramPosition')) {
            $this->syncLabelHologramPosition();
        }
    }
}
