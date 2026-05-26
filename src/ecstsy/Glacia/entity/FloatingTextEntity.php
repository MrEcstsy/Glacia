<?php

namespace ecstsy\Glacia\entity;

use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\utils\TextFormat as C;

class FloatingTextEntity extends Entity {

    public const NETWORK_ID = EntityIds::PLAYER;

    protected string $text = "";

    protected function initEntity(CompoundTag $nbt): void {
        $this->setNameTagVisible(true);
        $this->setNameTagAlwaysVisible(true);
        $this->setNoClientPredictions(true);
        $this->setInvisible(false);

        if ($this->text !== "") {
            $this->setNameTag(C::colorize($this->text));
        }

        parent::initEntity($nbt);
    }

    public function setText(string $text): void {
        if ($this->text === $text) {
            return;
        }
        $this->text = $text;
        $this->setNameTag($text === "" ? "" : C::colorize($text));
    }

    public function getText(): string {
        return $this->text;
    }

    public function getName(): string {
        return "FloatingTextEntity";
    }

    protected function getInitialSizeInfo(): EntitySizeInfo {
        return new EntitySizeInfo(0.5, 0.0, 0.0, 0.0);
    }

    public function onUpdate(int $currentTick): bool {
        return parent::onUpdate($currentTick);
    }

    public function getInitialDragMultiplier(): float {
        return 0.0;
    }

    public function getInitialGravity(): float {
        return 0.0;
    }

    public static function getNetworkTypeId(): string {
        return self::NETWORK_ID;
    }

    public function saveNBT(): CompoundTag {
        return CompoundTag::create();
    }
}