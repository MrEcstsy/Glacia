<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\mobs;

use pocketmine\entity\Entity;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Location;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\world\World;

final class GlaciaLightningStrike extends Entity {

    private int $lifeTicks = 0;

    public static function getNetworkTypeId(): string {
        return EntityIds::LIGHTNING_BOLT;
    }

    protected function getInitialSizeInfo(): EntitySizeInfo {
        return new EntitySizeInfo(0.0, 0.0);
    }

    public function __construct(Location $location, ?CompoundTag $nbt = null) {
        parent::__construct($location, $nbt);
        $this->setScale(1.0);
    }

    public function canSaveWithChunk(): bool {
        return false;
    }

    protected function initEntity(CompoundTag $nbt): void {
        parent::initEntity($nbt);
        $this->setNoClientPredictions();
    }

    public function entityBaseTick(int $tickDiff = 1): bool {
        $hasUpdate = parent::entityBaseTick($tickDiff);

        $this->lifeTicks += $tickDiff;

        if ($this->lifeTicks >= 8) {
            $this->flagForDespawn();
        }

        return $hasUpdate;
    }

    protected function applyGravity(): void {
        // no-op
    }

    protected function getInitialDragMultiplier(): float {
        return 0.0;
    }

    protected function getInitialGravity(): float {
        return 0.0;
    }
}