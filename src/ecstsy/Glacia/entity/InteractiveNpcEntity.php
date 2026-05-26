<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity;

use ecstsy\Glacia\entity\trait\InteractiveSpawnAnchorTrait;
use ecstsy\Glacia\entity\trait\InteractiveSpawnHologramTrait;
use ecstsy\Glacia\server\spawn\NpcSkinRepository;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Human;
use pocketmine\entity\Location;
use pocketmine\entity\Skin;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\network\mcpe\protocol\MovePlayerPacket;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class InteractiveNpcEntity extends Human implements InteractiveSpawnEntity
{
    use InteractiveSpawnAnchorTrait;
    use InteractiveSpawnHologramTrait;

    private const TAG_ACTION = "glacia_action";
    private const TAG_LABEL = "glacia_label";
    private const TAG_FACE_VIEWERS = "glacia_face_viewers";

    private string $interactAction = "";
    private string $displayLabel = "";
    private bool $faceViewers = true;

    public function __construct(Location $location, Skin $skin, ?CompoundTag $nbt = null)
    {
        parent::__construct($location, $skin, $nbt);
    }

    public static function fromNbt(Location $location, CompoundTag $nbt): self
    {
        try {
            $skin = Human::parseSkinNBT($nbt);
        } catch (\Throwable) {
            $skin = NpcSkinRepository::createFallbackSkin();
        }

        return new self($location, $skin, $nbt);
    }

    protected function initEntity(CompoundTag $nbt): void
    {
        parent::initEntity($nbt);

        $this->interactAction = trim($nbt->getString(self::TAG_ACTION, ""));
        $this->displayLabel = trim($nbt->getString(self::TAG_LABEL, $this->getNameTag()));
        $this->faceViewers = $nbt->getByte(self::TAG_FACE_VIEWERS, 1) === 1;

        $this->setNoClientPredictions(true);
        $this->setHasGravity(false);
        $this->loadInteractiveSpawnAnchor($nbt);
        $this->loadInteractiveSpawnHologram($nbt);
        $this->syncLabelHologram();
    }

    public function saveNBT(): CompoundTag
    {
        $nbt = parent::saveNBT();
        $nbt->setString(self::TAG_ACTION, $this->interactAction);
        $nbt->setString(self::TAG_LABEL, $this->displayLabel);
        $nbt->setByte(self::TAG_FACE_VIEWERS, $this->faceViewers ? 1 : 0);
        $nbt = $this->saveInteractiveSpawnAnchor($nbt);
        return $this->saveInteractiveSpawnHologram($nbt);
    }

    protected function getInitialSizeInfo(): EntitySizeInfo
    {
        return new EntitySizeInfo(1.8, 0.6, 1.62);
    }

    public function onUpdate(int $currentTick): bool
    {
        if ($this->faceViewers && ($currentTick % 5) === 0) {
            $this->syncViewerFacing();
        }

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

    public function getName(): string
    {
        return $this->displayLabel !== "" ? $this->displayLabel : "Interactive NPC";
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
        return 2.5;
    }

    public function setFaceViewers(bool $faceViewers): void
    {
        $this->faceViewers = $faceViewers;
    }

    public function shouldFaceViewers(): bool
    {
        return $this->faceViewers;
    }

    protected function sendSpawnPacket(Player $player): void
    {
        parent::sendSpawnPacket($player);
        $this->sendSkin([$player]);
        $this->syncNpcMovementTo($player, $this->location->pitch, $this->location->yaw, MovePlayerPacket::MODE_TELEPORT);
    }

    protected function broadcastMovement(bool $teleport = false): void
    {
        $mode = $teleport ? MovePlayerPacket::MODE_TELEPORT : MovePlayerPacket::MODE_NORMAL;
        NetworkBroadcastUtils::broadcastPackets($this->hasSpawned, [
            MovePlayerPacket::simple(
                $this->getId(),
                $this->getOffsetPosition($this->location),
                $this->location->pitch,
                $this->location->yaw,
                $this->location->yaw,
                $mode,
                $this->onGround,
                0,
                0
            ),
        ]);
    }

    private function syncViewerFacing(): void
    {
        $position = $this->getPosition();
        foreach ($this->getViewers() as $viewer) {
            $dx = $viewer->getPosition()->x - $position->x;
            $dz = $viewer->getPosition()->z - $position->z;
            $horizontal = sqrt(($dx * $dx) + ($dz * $dz));
            if ($horizontal <= 0.001) {
                continue;
            }

            $dy = $viewer->getEyePos()->y - ($position->y + $this->getEyeHeight());
            $yaw = rad2deg(atan2(-$dx, $dz));
            $pitch = rad2deg(-atan2($dy, $horizontal));

            $this->syncNpcMovementTo($viewer, $pitch, $yaw, MovePlayerPacket::MODE_NORMAL);
        }
    }

    private function syncNpcMovementTo(Player $viewer, float $pitch, float $yaw, int $mode): void
    {
        $viewer->getNetworkSession()->sendDataPacket(MovePlayerPacket::simple(
            $this->getId(),
            $this->getOffsetPosition($this->location),
            $pitch,
            $yaw,
            $yaw,
            $mode,
            true,
            0,
            0
        ));
    }

}
