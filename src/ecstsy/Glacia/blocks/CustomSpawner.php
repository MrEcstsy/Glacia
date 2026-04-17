<?php

declare(strict_types=1);

namespace ecstsy\Glacia\blocks;

use ecstsy\Glacia\blocks\tiles\SpawnerTile;
use ecstsy\Glacia\server\items\GlaciaSpawnerItem;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\block\Block;
use pocketmine\data\bedrock\LegacyEntityIdToStringIdMap;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use pocketmine\block\MonsterSpawner;

class CustomSpawner extends MonsterSpawner
{

    protected string $entityTypeId = ':';
    protected int $legacyEntityId = 0;

    public function getMaxStackSize() : int { return 64; }

    public function isAffectedBySilkTouch() : bool { return true; }

    public function place(BlockTransaction $tx, Item $item, Block $replace, Block $clicked, Int $face, Vector3 $click, ?Player $player = null) : Bool {
        $this->setLegacyEntityId($item->getNamedTag()->getInt('SpawnerEntityId', 0));
        return parent::place($tx, $item, $replace, $clicked, $face, $click, $player);
    }

    public function setLegacyEntityId(int $id) : self {
        $this->entityTypeId = LegacyEntityIdToStringIdMap::getInstance()->legacyToString($this->legacyEntityId = $id) ?? ':';
        return $this;
    }    

    public function getLegacyEntityId(): int {
        return $this->legacyEntityId;
    }

    public function getSilkTouchDrops(Item $item): array {
        return [
            StringToItemParser::getInstance()->parse("52:" . $this->legacyEntityId)
                ?? GlaciaSpawnerItem::MONSTER_SPAWNER()
        ];
    }

    public function getEntityId(): string {
        return $this->entityTypeId;
    }

    public function getEntityType(): string {
        return $this->entityTypeId;
    }

    public function setEntityId(string $id): self {
        $this->entityTypeId = $id;
        $this->legacyEntityId = GlaciaUtils::convertNameToMobId($id)?? 90;
        return $this;
    }

    public function onScheduledUpdate() : Void{
        $tile = $this->position->getWorld()->getTile($this->position);

        if ($tile instanceof SpawnerTile and $tile->onUpdate()) $this->position->getWorld()->scheduleDelayedBlockUpdate($this->position, 1);
    }

    public function readStateFromWorld() : Block{
        parent::readStateFromWorld();
    
        $tile = $this->position->getWorld()->getTile($this->position);
    
        if ($tile instanceof SpawnerTile) {
            $this->entityTypeId = $tile->getEntityTypeId();
            $this->legacyEntityId = $tile->getLegacyEntityId();
        } 
    
        return $this;
    }

    public function writeStateToWorld() : Void {
        parent::writeStateToWorld();
    
        $tile = $this->position->getWorld()->getTile($this->position);
    
        if ($tile instanceof SpawnerTile) {
            if ($tile->getEntityTypeId() === ':') {
                $tile->setLegacyEntityId($this->legacyEntityId);
            }
        }
    }
}