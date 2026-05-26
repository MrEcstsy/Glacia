<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity;

use ecstsy\Glacia\entity\trait\InteractiveSpawnAnchorTrait;
use ecstsy\Glacia\entity\trait\InteractiveSpawnHologramTrait;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class InteractiveCrystalEntity extends Living implements InteractiveSpawnEntity
{
    use InteractiveSpawnAnchorTrait;
    use InteractiveSpawnHologramTrait;

    private const TAG_ACTION = "glacia_action";
    private const TAG_LABEL = "glacia_label";

    private string $interactAction = "";
    private string $displayLabel = "";

    protected function initEntity(CompoundTag $nbt): void
    {
        parent::initEntity($nbt);

        $this->interactAction = trim($nbt->getString(self::TAG_ACTION, ""));
        $this->displayLabel = trim($nbt->getString(self::TAG_LABEL, $this->getNameTag()));

        $this->setNoClientPredictions(true);
        $this->setHasGravity(false);
        $this->setMaxHealth(1000);
        $this->setHealth(1000);
        $this->setScale(1.0);
        $this->loadInteractiveSpawnAnchor($nbt);
        $this->loadInteractiveSpawnHologram($nbt);
        $this->syncLabelHologram();
    }

    public function saveNBT(): CompoundTag
    {
        $nbt = parent::saveNBT();
        $nbt->setString(self::TAG_ACTION, $this->interactAction);
        $nbt->setString(self::TAG_LABEL, $this->displayLabel);
        $nbt = $this->saveInteractiveSpawnAnchor($nbt);
        return $this->saveInteractiveSpawnHologram($nbt);
    }

    protected function getInitialSizeInfo(): EntitySizeInfo
    {
        return new EntitySizeInfo(2.0, 1.0);
    }

    public static function getNetworkTypeId(): string
    {
        return EntityIds::ENDER_CRYSTAL;
    }

    public function onUpdate(int $currentTick): bool
    {
        $updated = parent::onUpdate($currentTick);
        $this->lockInteractiveSpawnToAnchor();
        $this->syncLabelHologramPosition();

        return $updated;
    }

    public function getInitialGravity(): float
    {
        return 0.0;
    }

    public function getInitialDragMultiplier(): float
    {
        return 0.0;
    }

    public function knockBack(float $x = 0, float $z = 0, float $force = 0, ?float $verticalLimit = 0): void
    {
    }

    public function getName(): string
    {
        return $this->displayLabel !== "" ? $this->displayLabel : "Interactive Crystal";
    }

    public function getInteractAction(): string
    {
        return $this->interactAction;
    }

    public function setInteractAction(string $action): void
    {
        $this->interactAction = trim($action);
    }

    public function getDisplayLabel(): string
    {
        return $this->displayLabel;
    }

    public function setDisplayLabel(string $label): void
    {
        $this->displayLabel = trim($label);
        $this->syncLabelHologram();
    }

    protected function getDefaultLabelHologramYOffset(): float
    {
        return 2.75;
    }
}
