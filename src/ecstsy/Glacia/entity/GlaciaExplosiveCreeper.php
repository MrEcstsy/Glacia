<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity;

use ecstsy\Glacia\entity\mobs\CustomCreeper;
use ecstsy\Glacia\entity\mobs\GlaciaLightningStrike;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\customtnt\CustomTNTIds;
use ecstsy\Glacia\server\customtnt\CustomTNTPattern;
use ecstsy\Glacia\server\customtnt\LuckyTNTHelper;
use ecstsy\Glacia\server\customtnt\TNTBlastShape;
use ecstsy\Glacia\server\customtnt\TacticalTNTHelper;
use ecstsy\Glacia\server\cannon\CannonRulesConfig;
use ecstsy\Glacia\server\cannon\WaterVulnerableExplosionBypass;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\Location;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityPreExplodeEvent;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\server\Server;
use pocketmine\utils\TextFormat as C;
use pocketmine\world\Explosion;
use pocketmine\world\Position;
use pocketmine\world\World;
use pocketmine\world\sound\ExplodeSound;
use Ramsey\Uuid\Uuid;

final class GlaciaExplosiveCreeper extends CustomCreeper {

    public const TAG_GLACIA_EXPLOSIVE_TYPE = "glacia_explosive_type";
    public const TAG_OWNER_UUID = "owner_uuid";
    public const TAG_BLOCK_BREAKING = "block_breaking";
    public const TAG_RAID_SOURCE_FACTION = "glacia_raid_source_faction";
    public const TAG_CUSTOM_DISPLAY_NAME = "glacia_custom_display_name";
    public const TAG_TARGET_UUID = "glacia_target_uuid";
    public const TAG_TEMPEST_CHARGE = "glacia_tempest_charge";
    public const TAG_TEMPEST_DAMAGE = "glacia_tempest_damage";
    public const TAG_TEMPEST_DAMAGE_RADIUS = "glacia_tempest_damage_radius";
    public const TAG_TEMPEST_TRIGGER_RADIUS = "glacia_tempest_trigger_radius";
    public const TAG_TEMPEST_CHASE_SPEED = "glacia_tempest_chase_speed";
    public const TAG_TEMPEST_MAX_CHASE_TICKS = "glacia_tempest_max_chase_ticks";
    public const TAG_TEMPEST_ARM_TICKS = "glacia_tempest_arm_ticks";
    public const TAG_TEMPEST_TARGET_ENTITY_ID = "glacia_tempest_target_entity_id";
    public const TAG_TEMPEST_TARGET_NAME = "glacia_tempest_target_name";

    private string $explosiveType = "";
    private string $ownerUuid = "";
    private ?string $raidSourceFactionId = null;
    private bool $blockBreakingEnabled = true;
    private bool $ignited = false;
    private string $customDisplayName = "";
    private string $targetUuid = "";
    private bool $tempestCharge = false;
    private float $tempestDamage = 0.0;
    private float $tempestDamageRadius = 3.0;
    private float $tempestTriggerRadius = 2.6;
    private float $tempestChaseSpeed = 0.52;
    private int $tempestMaxChaseTicks = 100;
    private int $tempestArmTicks = 12;
    private int $tempestTargetEntityId = 0;
    private string $tempestTargetName = "";

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
            $sourceFaction = trim($nbt->getString(self::TAG_RAID_SOURCE_FACTION, ""));
            $this->raidSourceFactionId = $sourceFaction !== "" ? $sourceFaction : null;
            $this->blockBreakingEnabled = $nbt->getByte(self::TAG_BLOCK_BREAKING, 1) === 1;
            $this->customDisplayName = $nbt->getString(self::TAG_CUSTOM_DISPLAY_NAME, "");
            $this->targetUuid = $nbt->getString(self::TAG_TARGET_UUID, "");
            $this->tempestCharge = $nbt->getByte(self::TAG_TEMPEST_CHARGE, 0) === 1;
            $this->tempestDamage = $nbt->getFloat(self::TAG_TEMPEST_DAMAGE, 0.0);
            $this->tempestDamageRadius = $nbt->getFloat(self::TAG_TEMPEST_DAMAGE_RADIUS, 3.0);
            $this->tempestTriggerRadius = $nbt->getFloat(self::TAG_TEMPEST_TRIGGER_RADIUS, 2.6);
            $this->tempestChaseSpeed = $nbt->getFloat(self::TAG_TEMPEST_CHASE_SPEED, 0.52);
            $this->tempestMaxChaseTicks = $nbt->getInt(self::TAG_TEMPEST_MAX_CHASE_TICKS, 100);
            $this->tempestArmTicks = $nbt->getInt(self::TAG_TEMPEST_ARM_TICKS, 12);
            $this->tempestTargetEntityId = $nbt->getInt(self::TAG_TEMPEST_TARGET_ENTITY_ID, 0);
            $this->tempestTargetName = $nbt->getString(self::TAG_TEMPEST_TARGET_NAME, "");
        }

        $this->updateNameTag();
    }

    protected function initEntity(CompoundTag $nbt): void {
        parent::initEntity($nbt);
        $this->explosiveType = $nbt->getString(self::TAG_GLACIA_EXPLOSIVE_TYPE, "");
        $this->ownerUuid = $nbt->getString(self::TAG_OWNER_UUID, "");
        $sourceFaction = trim($nbt->getString(self::TAG_RAID_SOURCE_FACTION, ""));
        $this->raidSourceFactionId = $sourceFaction !== "" ? $sourceFaction : null;
        $this->blockBreakingEnabled = $nbt->getByte(self::TAG_BLOCK_BREAKING, 1) === 1;
        $this->customDisplayName = $nbt->getString(self::TAG_CUSTOM_DISPLAY_NAME, "");
        $this->targetUuid = $nbt->getString(self::TAG_TARGET_UUID, "");
        $this->tempestCharge = $nbt->getByte(self::TAG_TEMPEST_CHARGE, 0) === 1;
        $this->tempestDamage = $nbt->getFloat(self::TAG_TEMPEST_DAMAGE, 0.0);
        $this->tempestDamageRadius = $nbt->getFloat(self::TAG_TEMPEST_DAMAGE_RADIUS, 3.0);
        $this->tempestTriggerRadius = $nbt->getFloat(self::TAG_TEMPEST_TRIGGER_RADIUS, 2.6);
        $this->tempestChaseSpeed = $nbt->getFloat(self::TAG_TEMPEST_CHASE_SPEED, 0.52);
        $this->tempestMaxChaseTicks = $nbt->getInt(self::TAG_TEMPEST_MAX_CHASE_TICKS, 100);
        $this->tempestArmTicks = $nbt->getInt(self::TAG_TEMPEST_ARM_TICKS, 12);
        $this->tempestTargetEntityId = $nbt->getInt(self::TAG_TEMPEST_TARGET_ENTITY_ID, 0);
        $this->tempestTargetName = $nbt->getString(self::TAG_TEMPEST_TARGET_NAME, "");
        $this->updateNameTag();
    }

    public function saveNBT(): CompoundTag {
        $nbt = parent::saveNBT();
        $nbt->setString(self::TAG_GLACIA_EXPLOSIVE_TYPE, $this->explosiveType);
        $nbt->setString(self::TAG_OWNER_UUID, $this->ownerUuid);
        if ($this->raidSourceFactionId !== null) {
            $nbt->setString(self::TAG_RAID_SOURCE_FACTION, $this->raidSourceFactionId);
        }
        $nbt->setByte(self::TAG_BLOCK_BREAKING, $this->blockBreakingEnabled ? 1 : 0);
        if ($this->customDisplayName !== "") {
            $nbt->setString(self::TAG_CUSTOM_DISPLAY_NAME, $this->customDisplayName);
        }
        if ($this->targetUuid !== "") {
            $nbt->setString(self::TAG_TARGET_UUID, $this->targetUuid);
        }
        $nbt->setByte(self::TAG_TEMPEST_CHARGE, $this->tempestCharge ? 1 : 0);
        $nbt->setFloat(self::TAG_TEMPEST_DAMAGE, $this->tempestDamage);
        $nbt->setFloat(self::TAG_TEMPEST_DAMAGE_RADIUS, $this->tempestDamageRadius);
        $nbt->setFloat(self::TAG_TEMPEST_TRIGGER_RADIUS, $this->tempestTriggerRadius);
        $nbt->setFloat(self::TAG_TEMPEST_CHASE_SPEED, $this->tempestChaseSpeed);
        $nbt->setInt(self::TAG_TEMPEST_MAX_CHASE_TICKS, $this->tempestMaxChaseTicks);
        $nbt->setInt(self::TAG_TEMPEST_ARM_TICKS, $this->tempestArmTicks);
        $nbt->setInt(self::TAG_TEMPEST_TARGET_ENTITY_ID, $this->tempestTargetEntityId);
        if ($this->tempestTargetName !== "") {
            $nbt->setString(self::TAG_TEMPEST_TARGET_NAME, $this->tempestTargetName);
        }
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

    public function setRaidSourceFactionId(?string $factionId): void
    {
        $factionId = $factionId !== null ? trim($factionId) : "";
        $this->raidSourceFactionId = $factionId !== "" ? $factionId : null;
    }

    public function getRaidSourceFactionId(): ?string
    {
        return $this->raidSourceFactionId;
    }

    public function setBlockBreakingEnabled(bool $enabled): void {
        $this->blockBreakingEnabled = $enabled;
    }

    public function setCustomDisplayName(string $name): void
    {
        $this->customDisplayName = $name;
        $this->updateNameTag();
    }

    public function configureTempestCharge(
        string $targetUuid,
        float $damage,
        float $damageRadius = 3.25,
        float $triggerRadius = 2.75,
        float $chaseSpeed = 0.52,
        int $maxChaseTicks = 1200,
        int $armTicks = 12,
        int $targetEntityId = 0,
        string $targetName = ""
    ): void {
        $this->tempestCharge = true;
        $this->targetUuid = $targetUuid;
        $this->tempestDamage = $damage;
        $this->tempestDamageRadius = max(1.5, $damageRadius);
        $this->tempestTriggerRadius = max(1.0, min($this->tempestDamageRadius, $triggerRadius));
        $this->tempestChaseSpeed = max(0.18, $chaseSpeed);
        $this->tempestMaxChaseTicks = max(20, $maxChaseTicks);
        $this->tempestArmTicks = max(0, $armTicks);
        $this->tempestTargetEntityId = max(0, $targetEntityId);
        $this->tempestTargetName = trim($targetName);
        $this->setMaxHealth(max($this->getMaxHealth(), 18.0));
        $this->setHealth(max($this->getHealth(), 18.0));
    }

    public function playTempestChargeSpawnEffects(): void
    {
        if (!$this->tempestCharge || $this->isClosed()) {
            return;
        }

        $this->setOnFire(60);
        $this->spawnLightningVisual($this->getPosition());
        PlayerUtils::playSound($this, "ambient.weather.thunder", 1.0, 1.0, 16);
        PlayerUtils::playSound($this, "ambient.weather.lightning.impact", 1.0, 1.0, 16);
        PlayerUtils::playSound($this, "random.fuse", 1.0, 0.95, 10);
    }

    public function ignite(): void {
        if ($this->ignited || !$this->isAlive()) {
            return;
        }

        $this->ignited = true;

        $this->explode();
        $this->flagForDespawn();
    }

    public function shouldDropCegg(): bool {
        return !$this->ignited;
    }

    public function getDrops(): array
    {
        return $this->ignited ? [] : parent::getDrops();
    }

    public function getXpDropAmount(): int
    {
        return $this->ignited ? 0 : parent::getXpDropAmount();
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

    protected function syncNetworkData(EntityMetadataCollection $properties): void
    {
        parent::syncNetworkData($properties);

        if ($this->tempestCharge) {
            $properties->setGenericFlag(EntityMetadataFlags::POWERED, true);
        }
    }

    protected function updateNameTag(): void {
        $health = max(0, (int) ceil($this->getHealth()));
        $max = max(1, (int) ceil($this->getMaxHealth()));
        $hpColor = "&c";
        $segments = 10;
        $filled = (int) floor(($health / $max) * $segments);
        $bar = str_repeat("❤", $filled) . "&7" . str_repeat("❤", max(0, $segments - $filled));

        $display = $this->customDisplayName !== "" ? $this->customDisplayName : $this->getDisplayNameForType();
        $this->setNameTag($display . C::colorize("\n&r{$hpColor}{$bar} &8(&f{$health}&7/&f{$max}&8)"));
        $this->setNameTagAlwaysVisible(true);
        $this->setNameTagVisible(true);
    }

    protected function entityBaseTick(int $tickDiff = 1): bool
    {
        $hasUpdate = parent::entityBaseTick($tickDiff);
        if ($this->tempestCharge && !$this->ignited && $this->isAlive()) {
            $this->tickTempestCharge($tickDiff);
        }
        if (($this->ticksLived % 10) === 0) {
            $this->updateNameTag();
        }
        return $hasUpdate;
    }

    public function attack(EntityDamageEvent $source): void
    {
        if ($this->tempestCharge && !$this->ignited && $source instanceof EntityDamageByEntityEvent) {
            $source->cancel();
            $this->ignite();
            return;
        }

        parent::attack($source);
    }

    protected function explode(): void {
        if (!$this->tempestCharge && Loader::getGraceInstance()->isEnabled()) {
            return;
        }

        $center = Position::fromObject(
            $this->getLocation()->add(0, $this->getSize()->getHeight() / 2, 0),
            $this->getWorld()
        );

        if ($this->tempestCharge) {
            $this->detonateTempestCharge($center);
            return;
        }

        $ev = new EntityPreExplodeEvent($this, 3.0);
        $ev->setBlockBreaking($this->blockBreakingEnabled);
        $ev->call();

        if ($ev->isCancelled()) {
            return;
        }

        $aquaticBlocked = $ev->isBlockBreaking() && GlaciaPrimedTNT::shouldSuppressAquaticExplosionBlockBreak($this, $center);
        if ($aquaticBlocked) {
            $ev->setBlockBreaking(false);
        }

        if (CannonRulesConfig::suppressExplosionFullyAt($center)) {
            return;
        }

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

        $aquaticSuppressedBlocks = !$ev->isBlockBreaking()
            && GlaciaPrimedTNT::shouldSuppressAquaticExplosionBlockBreak($this, $center);

        if ($ev->isBlockBreaking()) {
            if (TNTBlastShape::usesAxisShapedBlast($this->explosiveType)) {
                TNTBlastShape::collectIntoExplosion($explosion, $center, $this, $this->explosiveType);
            } else {
                $explosion->explodeA();
            }
        } elseif ($aquaticSuppressedBlocks) {
            WaterVulnerableExplosionBypass::mergeSphereIntoExplosion($explosion, $center, $ev->getRadius());
        }

        $explosion->explodeB();

        CustomTNTPattern::explodeExtra($this->explosiveType, $this, $ev->getRadius());
    }

    private function tickTempestCharge(int $tickDiff): void
    {
        $target = $this->resolveTempestTarget();
        if (!$target instanceof Player || !$target->isAlive() || $target->isClosed() || $target->getWorld() !== $this->getWorld()) {
            if ($this->ticksLived >= $this->tempestMaxChaseTicks) {
                $this->ignited = true;
                $this->flagForDespawn();
            }
            return;
        }

        $distanceSquared = $this->getPosition()->distanceSquared($target->getPosition());
        $effectiveTriggerRadius = $this->ticksLived >= ($this->tempestArmTicks + 20)
            ? ($this->tempestTriggerRadius + 1.15)
            : $this->tempestTriggerRadius;
        if ($this->ticksLived >= $this->tempestArmTicks && $distanceSquared <= ($effectiveTriggerRadius ** 2)) {
            $this->ignite();
            return;
        }

        if ($this->ticksLived >= $this->tempestMaxChaseTicks) {
            $this->ignite();
            return;
        }

        $this->chaseTempestTarget($target);

        if (($this->ticksLived % 10) === 0) {
            $this->spawnLightningVisual($this->getPosition());
        }
    }

    private function chaseTempestTarget(Player $target): void
    {
        $targetPos = $target->getPosition();
        $dx = $targetPos->x - $this->location->x;
        $dz = $targetPos->z - $this->location->z;
        $distance = sqrt(($dx * $dx) + ($dz * $dz));
        if ($distance < 0.001) {
            return;
        }

        $speed = $this->tempestChaseSpeed;
        $stepX = ($dx / $distance) * $speed;
        $stepZ = ($dz / $distance) * $speed;
        $nextY = $this->location->y;

        $targetDeltaY = $targetPos->y - $this->location->y;
        if (abs($targetDeltaY) > 0.35) {
            $nextY += max(-0.28, min(0.28, $targetDeltaY * 0.18));
        }

        $next = new Vector3(
            $this->location->x + $stepX,
            $nextY,
            $this->location->z + $stepZ
        );
        $this->setPosition($next);
        $this->setMotion(new Vector3($stepX, 0.0, $stepZ));
        $this->location->yaw = rad2deg(atan2(-$dx, $dz));
        $this->location->pitch = 0.0;
    }

    private function detonateTempestCharge(Position $center): void
    {
        $this->spawnLightningVisual($center);
        $this->getWorld()->addSound($center, new ExplodeSound());
        PlayerUtils::playSound($this, "ambient.weather.thunder", 1.0, 1.0, 18);
        PlayerUtils::playSound($this, "ambient.weather.lightning.impact", 1.0, 1.0, 18);
        PlayerUtils::playSound($this, "random.explode", 1.0, 1.0, 18);

        $owner = $this->resolvePlayerByUuid($this->ownerUuid);
        $target = $this->resolveTempestTarget();
        $box = new AxisAlignedBB(
            $center->x - $this->tempestDamageRadius,
            $center->y - $this->tempestDamageRadius,
            $center->z - $this->tempestDamageRadius,
            $center->x + $this->tempestDamageRadius,
            $center->y + $this->tempestDamageRadius,
            $center->z + $this->tempestDamageRadius
        );

        foreach ($this->getWorld()->getNearbyEntities($box) as $entity) {
            if (!$entity instanceof Player || !$entity->isAlive() || $entity->isClosed()) {
                continue;
            }

            $distance = $entity->getPosition()->distance($center);
            if ($distance > $this->tempestDamageRadius) {
                continue;
            }

            if ($owner instanceof Player) {
                if ($entity === $owner || GlaciaUtils::isAlly($owner, $entity)) {
                    continue;
                }
            } elseif ($target instanceof Player && $entity !== $target) {
                continue;
            }

            $scaledDamage = max(
                1.0,
                $this->tempestDamage * (1.0 - min(0.72, $distance / max(0.1, $this->tempestDamageRadius)))
            );
            $event = new EntityDamageByEntityEvent(
                $owner instanceof Player ? $owner : $this,
                $entity,
                EntityDamageEvent::CAUSE_ENTITY_EXPLOSION,
                $scaledDamage
            );
            $entity->attack($event);

            if ($event->isCancelled()) {
                continue;
            }

            $dx = $entity->getPosition()->x - $center->x;
            $dz = $entity->getPosition()->z - $center->z;
            $entity->knockBack($dx, $dz, 0.58);
        }
    }

    private function spawnLightningVisual(Position $position): void
    {
        $lightning = new GlaciaLightningStrike(new Location(
            $position->x + 0.5,
            $position->y,
            $position->z + 0.5,
            $position->getWorld(),
            0.0,
            0.0
        ));
        $lightning->spawnToAll();
    }

    private function resolvePlayerByUuid(string $uuid): ?Player
    {
        if ($uuid === "") {
            return null;
        }

        try {
            return Server::getInstance()->getPlayerByUUID(Uuid::fromString($uuid));
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveTempestTarget(): ?Player
    {
        if ($this->tempestTargetEntityId > 0) {
            $entity = $this->getWorld()->getEntity($this->tempestTargetEntityId);
            if ($entity instanceof Player && $entity->isAlive() && !$entity->isClosed()) {
                return $entity;
            }
        }

        if ($this->tempestTargetName !== "") {
            $player = Server::getInstance()->getPlayerExact($this->tempestTargetName);
            if ($player instanceof Player && $player->isAlive() && !$player->isClosed()) {
                $this->tempestTargetEntityId = $player->getId();
                return $player;
            }
        }

        $player = $this->resolvePlayerByUuid($this->targetUuid);
        if ($player instanceof Player && $player->isAlive() && !$player->isClosed()) {
            $this->tempestTargetEntityId = $player->getId();
            $this->tempestTargetName = $player->getName();
            return $player;
        }

        return null;
    }
}
