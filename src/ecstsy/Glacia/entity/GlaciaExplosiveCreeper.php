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
    public const TAG_OWNER_UUID = "owner_uuid";
    public const TAG_BLOCK_BREAKING = "block_breaking";

    private string $explosiveType = "";
    private string $ownerUuid = "";
    private bool $blockBreakingEnabled = true;
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
            $this->ownerUuid = $nbt->getString(self::TAG_OWNER_UUID, "");
            $this->blockBreakingEnabled = $nbt->getByte(self::TAG_BLOCK_BREAKING, 1) === 1;
        }

        $this->updateNameTag();
    }

    protected function initEntity(CompoundTag $nbt): void {
        parent::initEntity($nbt);
        $this->explosiveType = $nbt->getString(self::TAG_GLACIA_EXPLOSIVE_TYPE, "");
        $this->ownerUuid = $nbt->getString(self::TAG_OWNER_UUID, "");
        $this->blockBreakingEnabled = $nbt->getByte(self::TAG_BLOCK_BREAKING, 1) === 1;
        $this->updateNameTag();
    }

    public function saveNBT(): CompoundTag {
        $nbt = parent::saveNBT();
        $nbt->setString(self::TAG_GLACIA_EXPLOSIVE_TYPE, $this->explosiveType);
        $nbt->setString(self::TAG_OWNER_UUID, $this->ownerUuid);
        $nbt->setByte(self::TAG_BLOCK_BREAKING, $this->blockBreakingEnabled ? 1 : 0);
        return $nbt;
    }

    public function setExplosiveType(string $type): void {
        $this->explosiveType = $type;
        $this->updateNameTag();
    }

    public function getExplosiveType(): string {
        return $this->explosiveType;
    }

    public function setOwnerUuid(string $uuid): void {
        $this->ownerUuid = $uuid;
    }

    public function getOwnerUuid(): string {
        return $this->ownerUuid;
    }

    public function setBlockBreakingEnabled(bool $enabled): void {
        $this->blockBreakingEnabled = $enabled;
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

    protected function updateNameTag(): void {
        $health = max(0, (int) ceil($this->getHealth()));
        $max = max(1, (int) ceil($this->getMaxHealth()));
        $hpColor = C::RED;
        $segments = 10;
        $filled = (int) floor(($health / $max) * $segments);
        $bar = str_repeat("❤", $filled) . C::GRAY . str_repeat("❤", max(0, $segments - $filled));

        $this->setNameTag($this->getDisplayNameForType() . C::colorize("\n&r{$hpColor}{$bar} &8(&f{$health}&7/&f{$max}&8)"));
        $this->setNameTagAlwaysVisible(true);
        $this->setNameTagVisible(true);
    }

    protected function entityBaseTick(int $tickDiff = 1): bool
    {
        $hasUpdate = parent::entityBaseTick($tickDiff);
        if (($this->ticksLived % 10) === 0) {
            $this->updateNameTag();
        }
        return $hasUpdate;
    }

    protected function explode(): void {
        if (Loader::getGraceInstance()->isEnabled()) {
            return;
        }

        $ev = new EntityPreExplodeEvent($this, 3.0);
        $ev->setBlockBreaking($this->blockBreakingEnabled);
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
