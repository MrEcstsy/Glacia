<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity;

use ecstsy\Glacia\entity\mobs\CustomCreeper;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\customtnt\CustomTNTIds;
use ecstsy\Glacia\server\customtnt\CustomTNTPattern;
use ecstsy\Glacia\server\customtnt\LuckyTNTHelper;
use ecstsy\Glacia\server\customtnt\TacticalTNTHelper;
use ecstsy\Glacia\server\items\GlaciaItemFactory;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\Location;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\entity\EntityPreExplodeEvent;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\utils\TextFormat as C;
use pocketmine\world\Explosion;
use pocketmine\world\Position;
use pocketmine\world\World;

final class GlaciaExplosiveCreeper extends CustomCreeper {

    public const TAG_GLACIA_EXPLOSIVE_TYPE = "glacia_explosive_type";

    private string $explosiveType = "";
    private bool $ignited = false;

    public static function register(): void {
        EntityFactory::getInstance()->register(
            self::class,
            function(World $world, CompoundTag $nbt): self {
                return new self(EntityDataHelper::parseLocation($nbt, $world), $nbt);
            },
            [
                "GlaciaExplosiveCreeper",
                "minecraft:creeper"
            ]
        );
    }

    public function __construct(Location $location, ?CompoundTag $nbt = null) {
        parent::__construct($location, $nbt);

        if ($nbt !== null) {
            $this->explosiveType = $nbt->getString(self::TAG_GLACIA_EXPLOSIVE_TYPE, "");
        }

        $this->updateNameTag();
    }

    protected function initEntity(CompoundTag $nbt): void {
        parent::initEntity($nbt);
        $this->explosiveType = $nbt->getString(self::TAG_GLACIA_EXPLOSIVE_TYPE, "");
        $this->updateNameTag();
    }

    public function saveNBT(): CompoundTag {
        $nbt = parent::saveNBT();
        $nbt->setString(self::TAG_GLACIA_EXPLOSIVE_TYPE, $this->explosiveType);
        return $nbt;
    }

    public function setExplosiveType(string $type): void {
        $this->explosiveType = $type;
        $this->updateNameTag();
    }

    public function getExplosiveType(): string {
        return $this->explosiveType;
    }

    public function ignite(): void {
        if ($this->ignited || !$this->isAlive()) {
            return;
        }

        $this->ignited = true;

        // if your CustomCreeper already has proper ignition support, use that instead.
        // otherwise this is the simplest reliable route:
        $this->setHealth(0.0);
        $this->explode();
        $this->flagForDespawn();
    }

    public function shouldDropCegg(): bool {
        return !$this->ignited;
    }

    public function getCeggIdentifier(): ?string {
        return match ($this->explosiveType) {
            CustomTNTIds::GIGANTIC => "cegg_gigantic",
            CustomTNTIds::LETHAL => "cegg_lethal",
            CustomTNTIds::LUCKY => "cegg_lucky",
            CustomTNTIds::TACTICAL => "cegg_tactical",
            default => null
        };
    }

    public function getDisplayNameForType(): string {
        return match ($this->explosiveType) {
            CustomTNTIds::GIGANTIC => C::colorize("&r&bGigantic Creeper"),
            CustomTNTIds::LETHAL => C::colorize("&r&cLethal Creeper"),
            CustomTNTIds::LUCKY => C::colorize("&r&eLucky Creeper"),
            CustomTNTIds::TACTICAL => C::colorize("&r&dTactical Creeper"),
            default => C::colorize("&r&aCreeper")
        };
    }

    private function updateNameTag(): void {
        $this->setNameTag($this->getDisplayNameForType());
        $this->setNameTagAlwaysVisible(true);
        $this->setNameTagVisible(true);
    }

    protected function explode(): void {
        if (Loader::getGraceInstance()->isEnabled()) {
            return;
        }

        $ev = new EntityPreExplodeEvent($this, 3.0);
        $ev->call();

        if ($ev->isCancelled()) {
            return;
        }

        $center = Position::fromObject(
            $this->getLocation()->add(0, $this->getSize()->getHeight() / 2, 0),
            $this->getWorld()
        );

        if ($this->explosiveType === CustomTNTIds::TACTICAL) {
            TacticalTNTHelper::affectSpawnersOnly($center, $ev->getRadius());
            return;
        }

        if ($this->explosiveType === CustomTNTIds::LUCKY && $ev->isBlockBreaking()) {
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

        CustomTNTPattern::explodeExtra($this->explosiveType, $this, $ev->getRadius());
    }
}