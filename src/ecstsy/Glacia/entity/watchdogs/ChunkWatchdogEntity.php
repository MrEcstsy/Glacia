<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\watchdogs;

use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Location;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

abstract class ChunkWatchdogEntity extends Entity
{
    protected string $watchdogId = "";
    protected string $watchdogType = "";
    protected int $expiresAt = 0;

    public function canSaveWithChunk(): bool
    {
        return false;
    }

    protected function initEntity(CompoundTag $nbt): void
    {
        parent::initEntity($nbt);
        $this->watchdogId = $nbt->getString("watchdogId", "");
        $this->watchdogType = $nbt->getString("watchdogType", "");
        $this->expiresAt = $nbt->getInt("watchdogExpiresAt", 0);
        $this->setNameTagVisible(true);
        $this->setNameTagAlwaysVisible(true);
        $this->setNoClientPredictions(true);
        $this->refreshNameTag();
    }

    protected function getInitialSizeInfo(): EntitySizeInfo
    {
        return new EntitySizeInfo(1.9, 0.9);
    }

    protected function getInitialDragMultiplier(): float
    {
        return 0.0;
    }

    protected function getInitialGravity(): float
    {
        return 0.0;
    }

    public function attack(EntityDamageEvent $source): void
    {
        $source->cancel();
    }

    public function onUpdate(int $currentTick): bool
    {
        $this->setMotion($this->getMotion()->multiply(0.0));
        return parent::onUpdate($currentTick);
    }

    public function setWatchdogData(string $watchdogId, string $watchdogType, int $expiresAt): void
    {
        $this->watchdogId = $watchdogId;
        $this->watchdogType = $watchdogType;
        $this->expiresAt = $expiresAt;
        $this->refreshNameTag();
    }

    public function getWatchdogId(): string
    {
        return $this->watchdogId;
    }

    public function getWatchdogType(): string
    {
        return $this->watchdogType;
    }

    public function getExpiresAt(): int
    {
        return $this->expiresAt;
    }

    public function saveNBT(): CompoundTag
    {
        $nbt = parent::saveNBT();
        $nbt->setString("watchdogId", $this->watchdogId);
        $nbt->setString("watchdogType", $this->watchdogType);
        $nbt->setInt("watchdogExpiresAt", $this->expiresAt);
        return $nbt;
    }

    protected function refreshNameTag(): void
    {
        $remaining = max(0, $this->expiresAt - time());
        $days = intdiv($remaining, 86400);
        $hours = intdiv($remaining % 86400, 3600);
        $this->setNameTag(C::colorize(
            "&r&l" . $this->getDisplayName() .
            "\n&r&7Tap to manage" .
            "\n&r&7" . ($days > 0 ? "{$days}d {$hours}h" : max(0, $hours) . "h")
        ));
    }

    abstract protected function getDisplayName(): string;
}
