<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\hats;

use pocketmine\entity\Entity;
use pocketmine\item\ItemBlock;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\RemoveActorPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityLink;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\FloatMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\IntMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\LongMetadataProperty;
use pocketmine\network\mcpe\protocol\types\entity\PropertySyncData;
use pocketmine\network\mcpe\protocol\types\entity\Vec3MetadataProperty;
use pocketmine\player\Player;

final class RenderedBlockHat {

    private readonly int $actorId;
    private int $blockRuntimeId;
    private ItemBlock $sourceItem;

    public function __construct(
        private HatPlayer $owner,
        ItemBlock $block
    ) {
        $this->actorId = Entity::nextRuntimeId();
        $this->setBlock($block);
    }

    public function setBlock(ItemBlock $block): void
    {
        $this->sourceItem = clone $block;
        $this->sourceItem->setCount(1);
        $this->blockRuntimeId = TypeConverter::getInstance()->getBlockTranslator()->internalIdToNetworkId($block->getBlock()->getStateId());
    }

    public function getSourceItem(): ItemBlock
    {
        return clone $this->sourceItem;
    }

    public function sendTo(Player ...$players): void
    {
        if ($players === []) {
            return;
        }

        $flags = 1 << EntityMetadataFlags::NO_AI;
        $packet = AddActorPacket::create(
            $this->actorId,
            $this->actorId,
            EntityIds::FALLING_BLOCK,
            $this->owner->getPosition()->asVector3(),
            null,
            0.0,
            0.0,
            0.0,
            0.0,
            [],
            [
                EntityMetadataProperties::FLAGS => new LongMetadataProperty($flags),
                EntityMetadataProperties::VARIANT => new IntMetadataProperty($this->blockRuntimeId),
                EntityMetadataProperties::SCALE => new FloatMetadataProperty(0.85),
                EntityMetadataProperties::BOUNDING_BOX_WIDTH => new FloatMetadataProperty(0.0),
                EntityMetadataProperties::BOUNDING_BOX_HEIGHT => new FloatMetadataProperty(0.0),
                EntityMetadataProperties::RIDER_SEAT_POSITION => new Vec3MetadataProperty(new Vector3(0.0, 0.55, 0.0))
            ],
            new PropertySyncData([], []),
            [
                new EntityLink($this->owner->getId(), $this->actorId, EntityLink::TYPE_PASSENGER, false, false, 0.0)
            ]
        );

        foreach ($players as $player) {
            $player->getNetworkSession()->sendDataPacket(clone $packet);
        }
    }

    public function removeFrom(Player ...$players): void
    {
        if ($players === []) {
            return;
        }

        $packet = RemoveActorPacket::create($this->actorId);
        foreach ($players as $player) {
            $player->getNetworkSession()->sendDataPacket(clone $packet);
        }
    }
}
