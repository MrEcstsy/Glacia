<?php

declare(strict_types=1);

namespace ecstsy\Glacia\blocks\tiles;

use ecstsy\Glacia\blocks\CustomSpawner;
use ecstsy\Glacia\entity\EntityManager;
use ecstsy\Glacia\entity\SpawnerEntity;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\items\GlaciaItemFactory;
use ecstsy\Glacia\server\items\GlaciaSpawnerItem;
use ecstsy\Glacia\server\factions\claims\ClaimManager;
use ecstsy\Glacia\server\spawners\SpawnerData;
use ecstsy\Glacia\server\spawners\SpawnerWorthManager;
use ecstsy\Glacia\server\zones\ZoneManager;
use ecstsy\Glacia\server\zones\ZoneMobManager;
use ecstsy\Glacia\server\watchdogs\ChunkWatchdogManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\block\tile\Spawnable;
use pocketmine\data\bedrock\LegacyEntityIdToStringIdMap;
use pocketmine\entity\Location;
use pocketmine\item\StringToItemParser;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\TaskHandler;
use pocketmine\world\particle\MobSpawnParticle;
use pocketmine\world\World;

class SpawnerTile extends Spawnable
{
    protected const TAG_LEGACY_ENTITY_TYPE_ID = "EntityId";
    protected const TAG_ENTITY_TYPE_ID = "EntityIdentifier";

    protected const TAG_SPAWN_DELAY = "Delay";
    protected const TAG_MIN_SPAWN_DELAY = "MinSpawnDelay";
    protected const TAG_MAX_SPAWN_DELAY = "MaxSpawnDelay";

    protected const TAG_SPAWN_RANGE = "SpawnRange";
    protected const TAG_REQUIRED_PLAYER_RANGE = "RequiredPlayerRange";

    protected const TAG_SPAWNER_VALUE = "SpawnerValue";
    protected const TAG_SPAWNER_STACK = "SpawnerStack";
    protected const TAG_SPAWNER_MAX_STACK = "SpawnerMaxStack";
    protected const TAG_SPAWNER_AUGMENTS = "SpawnerAugmentCount";
    protected const TAG_SPAWNER_MODE = "SpawnerMode";
    protected const TAG_ZONE_ID = "ZoneSpawnerZoneId";

    public const MODE_FARM = "farm";
    public const MODE_ZONE = "zone";

    private int $spawnDelay = 0;
    private int $minSpawnDelay = 200;
    private int $maxSpawnDelay = 800;
    private int $spawnRange = 5;
    private int $requiredPlayerRange = 16;
    private int $stackCount = 1;
    private int $maxStack = 10;
    private int $augmentCount = 0;
    private int $recordedFactionWorth = 0;
    private string $mode = self::MODE_FARM;
    private string $zoneId = "";

    protected int $legacyEntityTypeId = 0;
    protected string $entityTypeId = ":";

    private ?TaskHandler $handler = null;

    public function __construct(World $world, Vector3 $pos)
    {
        parent::__construct($world, $pos);

        // New tiles skip readSaveData until chunk load/item NBT merge; Delay=0 would underflow (--0) and spawn on first tick.
        $this->spawnDelay = max(1, (int) ceil($this->minSpawnDelay / 20));

        $this->handler = Loader::getInstance()->getScheduler()->scheduleRepeatingTask(
            new ClosureTask(
                function() {
                    if ($this->canUpdate()) $this->onUpdate();
                }
            ), 20
        );
    }

    public function onUpdate(): void {
        if ($this->closed) {
            $this->handler?->cancel();
            return;
        }

        if (!$this->canUpdate()) {
            return;
        }

        if (--$this->spawnDelay > 0) {
            return;
        }

        if ($this->isZoneSpawner()) {
            $this->spawnDelay = max(1, ZoneMobManager::getSpawnIntervalSeconds());
            ZoneMobManager::tickSpawner($this->getPosition(), $this->zoneId);
            return;
        }

        $baseDelayTicks = $this->minSpawnDelay; 
        $this->spawnDelay = (int) ceil($baseDelayTicks / 20); 

        $range = $this->spawnRange;
        $blockPos = $this->getPosition();
        $stack = max(1, $this->stackCount);

        $spawnCount = 0;
        $spawnMultiplier = max(1, ChunkWatchdogManager::getSpawnerSpawnMultiplier($this->getPosition()));
        for ($i = 0; $i < $stack; $i++) {
            $spawnCount += random_int(1, 4);
        }
        $spawnCount *= $spawnMultiplier;

        for ($i = 0; $i < $spawnCount; $i++) {
            $nearest = $this->findNearestEntity($this->getEntityTypeId());
            if ($nearest !== null) {
                $nearest->setStack($nearest->getStack() + ($spawnCount - $i));
                break;
            }

            $spawnPos = $blockPos->getWorld()->getSafeSpawn(
                $blockPos->add(mt_rand(-$range, $range), 0, mt_rand(-$range, $range))
            );

            $nbt = (new CompoundTag())->setInt("stack", 1);
            $entity = EntityManager::getInstance()->getEntityFor(
                $this->getEntityTypeId(),
                Location::fromObject($spawnPos, $blockPos->world),
                $nbt
            );
            $entity->spawnToAll();
            break;
        }

        $blockPos->getWorld()->addParticle($blockPos, new MobSpawnParticle(2, 2));
    }

    public function readSaveData(CompoundTag $nbt): void
    {
        $this->spawnDelay = $nbt->getShort(self::TAG_SPAWN_DELAY, 0);
        $this->minSpawnDelay = $nbt->getShort(self::TAG_MIN_SPAWN_DELAY, 200);
        $this->maxSpawnDelay = $nbt->getShort(self::TAG_MAX_SPAWN_DELAY, 800);
        $this->spawnRange = $nbt->getShort(self::TAG_SPAWN_RANGE, 5);
        $this->requiredPlayerRange = $nbt->getShort(self::TAG_REQUIRED_PLAYER_RANGE, 16);
        $this->legacyEntityTypeId = $nbt->getShort(self::TAG_LEGACY_ENTITY_TYPE_ID, 0);
        $this->mode = $nbt->getString(self::TAG_SPAWNER_MODE, self::MODE_FARM);
        $this->zoneId = strtolower($nbt->getString(self::TAG_ZONE_ID, ""));

        $legacyIdTag = $nbt->getTag(self::TAG_LEGACY_ENTITY_TYPE_ID);

        if ($legacyIdTag instanceof IntTag) {
            $this->setLegacyEntityId($legacyIdTag->getValue());
        } else {
            $this->setEntityId($nbt->getString(self::TAG_ENTITY_TYPE_ID, ":"));
        }

        $this->stackCount = max(1, (int)$nbt->getInt(self::TAG_SPAWNER_STACK, 1));
        $this->maxStack = max(1, (int)$nbt->getInt(self::TAG_SPAWNER_MAX_STACK, 10));
        $this->augmentCount = max(0, (int) $nbt->getInt(self::TAG_SPAWNER_AUGMENTS, 0));
        if ($this->isZoneSpawner()) {
            $this->stackCount = 1;
            $this->maxStack = 1;
            $this->augmentCount = 0;
            if ($this->spawnDelay <= 0) {
                $this->spawnDelay = max(1, ZoneMobManager::getSpawnIntervalSeconds());
            }
        } elseif ($this->spawnDelay <= 0) {
            $this->spawnDelay = max(1, (int) ceil($this->minSpawnDelay / 20));
        }
        $this->augmentCount = min($this->augmentCount, $this->getMaxAugmentCount());

        $world = $this->getPosition()->getWorld();
        $world->scheduleDelayedBlockUpdate($this->getPosition(), 1);
        $this->refreshFactionWorthContribution();
    }

    public function canUpdate(): Bool
    {
        if ($this->isZoneSpawner()) {
            $zone = ZoneManager::getZone($this->zoneId);
            return $zone !== null && ZoneMobManager::getZoneConfig($this->zoneId) !== null;
        }

        return $this->entityTypeId !== ":" && (
            ChunkWatchdogManager::hasRavengerBypass($this->getPosition()) ||
            $this->getPosition()->getWorld()->getNearestEntity($this->getPosition(), $this->requiredPlayerRange, Player::class) !== null
        );
    }

    protected function writeSaveData(CompoundTag $nbt): void
    {
        $nbt->setString(self::TAG_ENTITY_TYPE_ID, $this->entityTypeId);
        $nbt->setShort(self::TAG_SPAWN_DELAY, $this->spawnDelay);
        $nbt->setShort(self::TAG_MIN_SPAWN_DELAY, $this->minSpawnDelay);
        $nbt->setShort(self::TAG_MAX_SPAWN_DELAY, $this->maxSpawnDelay);
        $nbt->setShort(self::TAG_SPAWN_RANGE, $this->spawnRange);
        $nbt->setShort(self::TAG_REQUIRED_PLAYER_RANGE, $this->requiredPlayerRange);
        $nbt->setInt(self::TAG_SPAWNER_VALUE, $this->getSpawnerValue());
        $nbt->setInt(self::TAG_SPAWNER_STACK, $this->stackCount);
        $nbt->setInt(self::TAG_SPAWNER_MAX_STACK, $this->maxStack);
        $nbt->setInt(self::TAG_SPAWNER_AUGMENTS, $this->augmentCount);
        $nbt->setString(self::TAG_SPAWNER_MODE, $this->mode);
        $nbt->setString(self::TAG_ZONE_ID, $this->zoneId);
    }

    public function getStackCount(): int { return $this->stackCount; }
    public function getMaxStack(): int { return $this->maxStack + ChunkWatchdogManager::getSpawnerMaxStackBonus($this->getPosition()); }
    public function getAugmentCount(): int { return $this->augmentCount; }
    public function getEntityTypeId(): string { return $this->entityTypeId; }
    public function getLegacyEntityId(): int { return $this->legacyEntityTypeId; }
    public function getDelay(): int { return $this->minSpawnDelay; }
    public function getMinDelay(): int { return $this->minSpawnDelay; }
    public function getMaxDelay(): int { return $this->maxSpawnDelay; }
    public function getMode(): string { return $this->mode; }
    public function getZoneId(): string { return $this->zoneId; }
    public function isZoneSpawner(): bool { return $this->mode === self::MODE_ZONE; }

    public function getMaxAugmentCount(): int
    {
        return $this->isZoneSpawner() ? 0 : $this->stackCount;
    }

    public function getAugmentBonusPercent(): int
    {
        return $this->augmentCount * 25;
    }

    public function getAugmentWorthBonus(): int
    {
        if ($this->isZoneSpawner() || $this->augmentCount <= 0) {
            return 0;
        }

        return (int) round($this->getSpawnerValue() * 0.25 * $this->augmentCount);
    }

    public function getFactionWorthContribution(): int
    {
        if ($this->isZoneSpawner()) {
            return 0;
        }

        return max(0, ($this->getSpawnerValue() * $this->stackCount) + $this->getAugmentWorthBonus());
    }

    public function getEstimatedSpawnMinPerCycle(): int
    {
        return max(1, $this->stackCount);
    }

    public function getEstimatedSpawnMaxPerCycle(): int
    {
        return max(1, $this->stackCount) * 4;
    }

    public function getEstimatedSpawnAveragePerCycle(): float
    {
        return max(1, $this->stackCount) * 2.5;
    }

    public function getEstimatedCyclesPerMinute(): float
    {
        return 60.0 / max(0.05, $this->minSpawnDelay / 20.0);
    }

    public function getEstimatedSpawnMinPerMinute(): int
    {
        return (int) floor($this->getEstimatedSpawnMinPerCycle() * $this->getEstimatedCyclesPerMinute());
    }

    public function getEstimatedSpawnMaxPerMinute(): int
    {
        return (int) ceil($this->getEstimatedSpawnMaxPerCycle() * $this->getEstimatedCyclesPerMinute());
    }

    public function getEstimatedSpawnAveragePerMinute(): float
    {
        return $this->getEstimatedSpawnAveragePerCycle() * $this->getEstimatedCyclesPerMinute();
    }

    private function findNearestEntity(string $type): ?SpawnerEntity
    {
        $pos = $this->getBlock()->getPosition();
        $world = $pos->getWorld();
        $box = new AxisAlignedBB(
            $pos->x - 25, $pos->y - 25, $pos->z - 25,
            $pos->x + 25, $pos->y + 25, $pos->z + 25
        );

        foreach ($world->getNearbyEntities($box) as $entity) {
            if ($entity->isAlive() && $entity instanceof SpawnerEntity) {
                if ($entity::getNetworkTypeId() === $type) {
                    return $entity;
                }
            }
        }

        return null;
    }

    public function getSpawnerValue(): int
    {
        if ($this->isZoneSpawner()) {
            return 0;
        }

        return SpawnerWorthManager::getWorth($this->entityTypeId);
    }

    public function setLegacyEntityId(int $id): self
    {
        $this->legacyEntityTypeId = $id;
        $this->entityTypeId = LegacyEntityIdToStringIdMap::getInstance()->legacyToString($id) ?? ':';
        $this->refreshFactionWorthContribution();
        return $this;
    }

    public function setEntityId(string $id): void
    {
        $this->entityTypeId = $id;
        $this->legacyEntityTypeId = array_search(
            $this->entityTypeId,
            LegacyEntityIdToStringIdMap::getInstance()->getLegacyToStringMap()
        ) ?: $this->legacyEntityTypeId;

        $block = $this->getBlock();
        if ($block instanceof CustomSpawner) {
            $block->setLegacyEntityId($this->legacyEntityTypeId);
        }

        $this->refreshFactionWorthContribution();
    }

    public function setStackCount(int $count): void
    {
        if ($this->isZoneSpawner()) {
            $this->stackCount = 1;
            $this->augmentCount = 0;
            return;
        }

        $this->stackCount = max(1, min($count, $this->getMaxStack()));
        $this->augmentCount = min($this->augmentCount, $this->getMaxAugmentCount());
        $this->refreshFactionWorthContribution();
    }

    public function setMode(string $mode): void
    {
        $this->mode = $mode === self::MODE_ZONE ? self::MODE_ZONE : self::MODE_FARM;
        if ($this->isZoneSpawner()) {
            $this->stackCount = 1;
            $this->maxStack = 1;
            $this->augmentCount = 0;
            $this->spawnDelay = max(1, ZoneMobManager::getSpawnIntervalSeconds());
        }
        $this->refreshFactionWorthContribution();
    }

    public function addAugments(int $count): int
    {
        if ($this->isZoneSpawner() || $count <= 0) {
            return 0;
        }

        $capacity = $this->getMaxAugmentCount() - $this->augmentCount;
        if ($capacity <= 0) {
            return 0;
        }

        $added = min($count, $capacity);
        $this->augmentCount += $added;
        $this->refreshFactionWorthContribution();
        return $added;
    }

    public function removeAugments(int $count): int
    {
        if ($count <= 0 || $this->augmentCount <= 0) {
            return 0;
        }

        $removed = min($count, $this->augmentCount);
        $this->augmentCount -= $removed;
        $this->refreshFactionWorthContribution();
        return $removed;
    }

    public function setZoneId(string $zoneId): void
    {
        $this->zoneId = strtolower(trim($zoneId));
    }

    public function setDelay(int $delay): void
    {
        $this->minSpawnDelay = max(1, $delay);
        if ($this->maxSpawnDelay < $this->minSpawnDelay) {
            $this->maxSpawnDelay = $this->minSpawnDelay;
        }
    }

    public function setMaxDelay(int $delay): void
    {
        $this->maxSpawnDelay = max(1, $delay);
        if ($this->minSpawnDelay > $this->maxSpawnDelay) {
            $this->minSpawnDelay = $this->maxSpawnDelay;
        }
    }

    public function changeStackBy(int $delta): void
    {
        if ($this->isZoneSpawner()) {
            return;
        }
        $this->setStackCount($this->stackCount + $delta);
    }

    public function refreshFactionWorthContribution(): void
    {
        if ($this->closed) {
            return;
        }

        $claim = ClaimManager::getInstance()->getClaimAtPosition($this->getPosition());
        $faction = $claim?->getFaction();
        $currentWorth = $faction !== null ? $this->getFactionWorthContribution() : 0;

        if ($faction === null) {
            $this->recordedFactionWorth = 0;
            return;
        }

        $delta = $currentWorth - $this->recordedFactionWorth;
        if ($delta !== 0) {
            $faction->adjustSpawnerWorth($delta);
            $this->recordedFactionWorth = $currentWorth;
        }
    }

    public function close(): void
    {
        if ($this->recordedFactionWorth > 0) {
            $claim = ClaimManager::getInstance()->getClaimAtPosition($this->getPosition());
            $claim?->getFaction()?->adjustSpawnerWorth(-$this->recordedFactionWorth);
            $this->recordedFactionWorth = 0;
        }

        parent::close();
    }

    public function addToStackFromPlayerInventory(Player $player, int $count): int
    {
        if ($this->isZoneSpawner()) {
            return 0;
        }

        if ($count <= 0) return 0;

        $inv = $player->getInventory();
        $needed = $count;
        $added = 0;
        $max = $this->getMaxStack();
        $remainingCapacity = $max - $this->stackCount;
        if ($remainingCapacity <= 0) return 0;

        $needed = min($needed, $remainingCapacity);

        $startingStack = $this->stackCount;
        foreach ($inv->getContents(true) as $slot => $item) {
            if ($item->isNull()) continue;
            $spawnerId = GlaciaSpawnerItem::getSpawnerEntityId($item);
            if ($spawnerId === 0) continue;
            if ($spawnerId !== $this->legacyEntityTypeId) continue;

            $available = $item->getCount();
            $toTake = min($available, $needed);

            $remove = clone $item;
            $remove->setCount($toTake);
            $inv->removeItem($remove);

            $this->stackCount += $toTake;
            $added += $toTake;
            $needed -= $toTake;

            if ($needed <= 0 || $this->stackCount >= $max) break;
        }

        if ($this->stackCount !== $startingStack) {
            $this->setStackCount($this->stackCount);
        }

        return $added;
    }

    public function removeFromStackToPlayer(Player $player, int $count, int $minRemaining = 1): int
    {
        if ($this->isZoneSpawner()) {
            return 0;
        }

        if ($count <= 0) return 0;

        $available = max(0, $this->stackCount - $minRemaining);
        $toRemove = min($available, $count);
        if ($toRemove <= 0) return 0;

        $removed = 0;
        $spawnerItemPrototype = GlaciaSpawnerItem::MONSTER_SPAWNER()->asItem();
        GlaciaSpawnerItem::setSpawnerEntityId($spawnerItemPrototype, $this->legacyEntityTypeId);

        $inv = $player->getInventory();

        $startingStack = $this->stackCount;
        for ($i = 0; $i < $toRemove; $i++) {
            $shortName = GlaciaUtils::convertMobIdToName($this->legacyEntityTypeId);
            $item = StringToItemParser::getInstance()->parse($shortName . "_spawner");
            $item->setCount(1);
            $left = $inv->addItem($item);
            if (!empty($left)) {
                $player->getWorld()->dropItem($player->getPosition(), $item);
            }
            $removed++;
            $this->stackCount--;
        }

        $augmentRemoved = $this->removeAugments(min($this->augmentCount, $removed));
        for ($i = 0; $i < $augmentRemoved; $i++) {
            $augmentItem = GlaciaItemFactory::create("spawner_augment");
            $left = $inv->addItem($augmentItem);
            if (!empty($left)) {
                $player->getWorld()->dropItem($player->getPosition(), $augmentItem);
            }
        }

        if ($this->stackCount !== $startingStack) {
            $this->setStackCount($this->stackCount);
        }

        return $removed;
    }

    protected function addAdditionalSpawnData(CompoundTag $nbt) : void
    {
        $nbt->setString(self::TAG_ENTITY_TYPE_ID, $this->entityTypeId);
        $nbt->setInt(self::TAG_LEGACY_ENTITY_TYPE_ID, $this->legacyEntityTypeId);
    }

    public function getStackInfo(): array
    {
        return [
            'entityId' => $this->getEntityTypeId(),
            'stack' => $this->getStackCount(),
            'max' => $this->getMaxStack(),
            'augments' => $this->getAugmentCount(),
            'delay' => $this->minSpawnDelay,
            'baseCount'=> 1,
            'mode' => $this->mode,
            'zoneId' => $this->zoneId,
        ];
    }
}
