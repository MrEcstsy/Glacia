<?php

declare(strict_types=1);

namespace ecstsy\Glacia\blocks\tiles;

use ecstsy\Glacia\blocks\CustomSpawner;
use ecstsy\Glacia\entity\EntityManager;
use ecstsy\Glacia\entity\SpawnerEntity;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\items\GlaciaSpawnerItem;
use ecstsy\Glacia\server\factions\claims\ClaimManager;
use ecstsy\Glacia\server\spawners\SpawnerData;
use ecstsy\Glacia\server\spawners\SpawnerWorthManager;
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

    private int $spawnDelay = 0;
    private int $minSpawnDelay = 200;
    private int $maxSpawnDelay = 800;
    private int $spawnRange = 5;
    private int $requiredPlayerRange = 16;
    private int $stackCount = 1;
    private int $maxStack = 10;
    private int $recordedFactionWorth = 0;

    protected int $legacyEntityTypeId = 0;
    protected string $entityTypeId = ":";

    private ?TaskHandler $handler = null;

    public function __construct(World $world, Vector3 $pos)
    {
        parent::__construct($world, $pos);

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
            $this->handler->cancel();
            return;
        }

        if (!$this->canUpdate()) {
            return;
        }

        if (--$this->spawnDelay > 0) {
            return;
        }

        $baseDelayTicks = $this->minSpawnDelay; 
        $this->spawnDelay = (int) ceil($baseDelayTicks / 20); 

        $range = $this->spawnRange;
        $blockPos = $this->getPosition();
        $stack = max(1, $this->stackCount);

        $spawnCount = 0;
        for ($i = 0; $i < $stack; $i++) {
            $spawnCount += random_int(1, 4);
        }

        for ($i = 0; $i < $spawnCount; $i++) {
            $spawnPos = $blockPos->getWorld()->getSafeSpawn(
                $blockPos->add(mt_rand(-$range, $range), 0, mt_rand(-$range, $range))
            );

            $nearest = $this->findNearestEntity($this->getEntityTypeId());
            if ($nearest !== null) {
                $nearest->setStack($nearest->getStack() + 1);
                continue;
            }

            $nbt = (new CompoundTag())->setInt("stack", 1);
            $entity = EntityManager::getInstance()->getEntityFor(
                $this->getEntityTypeId(),
                Location::fromObject($spawnPos, $blockPos->world),
                $nbt
            );
            $entity->spawnToAll();
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

        $legacyIdTag = $nbt->getTag(self::TAG_LEGACY_ENTITY_TYPE_ID);

        if ($legacyIdTag instanceof IntTag) {
            $this->setLegacyEntityId($legacyIdTag->getValue());
        } else {
            $this->setEntityId($nbt->getString(self::TAG_ENTITY_TYPE_ID, ":"));
        }

        $this->stackCount = max(1, (int)$nbt->getInt(self::TAG_SPAWNER_STACK, 1));
        $this->maxStack = max(1, (int)$nbt->getInt(self::TAG_SPAWNER_MAX_STACK, 10));

        $world = $this->getPosition()->getWorld();
        $world->scheduleDelayedBlockUpdate($this->getPosition(), 1);
        $this->refreshFactionWorthContribution();
    }

    public function canUpdate(): Bool
    {
        return (
            $this->entityTypeId !== ":" and
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
    }

    public function getStackCount(): int { return $this->stackCount; }
    public function getMaxStack(): int { return $this->maxStack; }
    public function getEntityTypeId(): string { return $this->entityTypeId; }
    public function getLegacyEntityId(): int { return $this->legacyEntityTypeId; }
    public function getDelay(): int { return $this->minSpawnDelay; }
    public function getMinDelay(): int { return $this->minSpawnDelay; }
    public function getMaxDelay(): int { return $this->maxSpawnDelay; }

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
        $this->stackCount = max(1, min($count, $this->maxStack));
        $this->refreshFactionWorthContribution();
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
        $this->setStackCount($this->stackCount + $delta);
    }

    public function refreshFactionWorthContribution(): void
    {
        if ($this->closed) {
            return;
        }

        $claim = ClaimManager::getInstance()->getClaimAtPosition($this->getPosition());
        $faction = $claim?->getFaction();
        $currentWorth = $faction !== null ? ($this->getSpawnerValue() * $this->getStackCount()) : 0;

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
        if ($count <= 0) return 0;

        $inv = $player->getInventory();
        $needed = $count;
        $added = 0;
        $max = $this->getMaxStack();
        $remainingCapacity = $max - $this->stackCount;
        if ($remainingCapacity <= 0) return 0;

        $needed = min($needed, $remainingCapacity);

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

        return $added;
    }

    public function removeFromStackToPlayer(Player $player, int $count, int $minRemaining = 1): int
    {
        if ($count <= 0) return 0;

        $available = max(0, $this->stackCount - $minRemaining);
        $toRemove = min($available, $count);
        if ($toRemove <= 0) return 0;

        $removed = 0;
        $spawnerItemPrototype = GlaciaSpawnerItem::MONSTER_SPAWNER()->asItem();
        GlaciaSpawnerItem::setSpawnerEntityId($spawnerItemPrototype, $this->legacyEntityTypeId);

        $inv = $player->getInventory();

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
            'delay' => $this->minSpawnDelay,
            'baseCount'=> 1,
        ];
    }
}
