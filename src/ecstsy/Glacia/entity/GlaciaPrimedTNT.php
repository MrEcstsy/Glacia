<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\customtnt\CustomTNTIds;
use ecstsy\Glacia\server\customtnt\CustomTNTPattern;
use ecstsy\Glacia\server\customtnt\LuckyTNTHelper;
use ecstsy\Glacia\server\customtnt\TacticalTNTHelper;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\Location;
use pocketmine\entity\object\PrimedTNT;
use pocketmine\event\entity\EntityExplodeEvent;
use pocketmine\event\entity\EntityPreExplodeEvent;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\world\Explosion;
use pocketmine\world\Position;
use pocketmine\world\World;

final class GlaciaPrimedTNT extends PrimedTNT {

    public const TAG_GLACIA_TNT_TYPE = "glacia_tnt_type";

    private string $tntType = "";

    public function __construct(Location $location, ?CompoundTag $nbt = null) {
        parent::__construct($location, $nbt);

        if ($nbt !== null) {
            $this->tntType = $nbt->getString(self::TAG_GLACIA_TNT_TYPE, "");
        }
    }

    protected function initEntity(CompoundTag $nbt): void {
        parent::initEntity($nbt);
        $this->tntType = $nbt->getString(self::TAG_GLACIA_TNT_TYPE, "");
    }

    public function saveNBT(): CompoundTag {
        $nbt = parent::saveNBT();
        $nbt->setString(self::TAG_GLACIA_TNT_TYPE, $this->tntType);
        return $nbt;
    }

    public function setTNTType(string $type): void {
        $this->tntType = $type;
    }

    public function getTNTType(): string {
        return $this->tntType;
    }

    public function explode(): void {
        if (Loader::getGraceInstance()->isEnabled()) {
            return;
        }

        $ev = new EntityPreExplodeEvent($this, 4.0);
        $ev->call();

        if ($ev->isCancelled()) {
            return;
        }

        $center = Position::fromObject(
            $this->getLocation()->add(0, $this->getSize()->getHeight() / 2, 0),
            $this->getWorld()
        );

        if ($this->tntType === CustomTNTIds::TACTICAL) {
            TacticalTNTHelper::affectSpawnersOnly($center, $ev->getRadius());
            return;
        }

        if ($this->tntType === CustomTNTIds::LUCKY && $ev->isBlockBreaking()) {
            LuckyTNTHelper::dropSpawnersInRadius($center, $ev->getRadius());
        }

        $explosion = new Explosion(
            $center,
            $ev->getRadius(),
            $this,
            $ev->getFireChance()
        );

        if ($ev->isBlockBreaking()) {
            $explosion->explodeA();
        }

        $explosion->explodeB();

        CustomTNTPattern::explodeExtra($this->tntType, $this, $ev->getRadius());
    }
}