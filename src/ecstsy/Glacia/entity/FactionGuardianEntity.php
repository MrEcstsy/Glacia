<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\factions\guardian\FactionGuardianManager;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Location;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class FactionGuardianEntity extends Entity
{
    public const NETWORK_ID = EntityIds::GHAST;

    private string $factionId = "";
    private float $anchorX = 0.0;
    private float $anchorY = 0.0;
    private float $anchorZ = 0.0;
    private float $angleOffset = 0.0;

    public function getName(): string
    {
        return "Faction Guardian";
    }

    public static function getNetworkTypeId(): string
    {
        return self::NETWORK_ID;
    }

    public function canSaveWithChunk(): bool
    {
        return false;
    }

    public function setGuardianData(string $factionId, float $anchorX, float $anchorY, float $anchorZ): void
    {
        $this->factionId = $factionId;
        $this->anchorX = $anchorX;
        $this->anchorY = $anchorY;
        $this->anchorZ = $anchorZ;
        $this->angleOffset = lcg_value() * M_PI * 2.0;
        $this->refreshNameTag();
    }

    public function getFactionId(): string
    {
        return $this->factionId;
    }

    protected function initEntity(CompoundTag $nbt): void
    {
        parent::initEntity($nbt);

        $this->factionId = $nbt->getString("guardianFactionId", $this->factionId);
        $this->anchorX = $nbt->getFloat("guardianAnchorX", (float) $this->location->x);
        $this->anchorY = $nbt->getFloat("guardianAnchorY", (float) $this->location->y);
        $this->anchorZ = $nbt->getFloat("guardianAnchorZ", (float) $this->location->z);
        $this->angleOffset = $nbt->getFloat("guardianAngleOffset", lcg_value() * M_PI * 2.0);

        $this->setNameTagVisible(true);
        $this->setNameTagAlwaysVisible(true);
        $this->setNoClientPredictions(true);
        $this->setScale(FactionGuardianManager::getEntityScale());
        $this->refreshNameTag();
    }

    protected function getInitialSizeInfo(): EntitySizeInfo
    {
        return new EntitySizeInfo(2.2, 2.2);
    }

    protected function getInitialDragMultiplier(): float
    {
        return 0.0;
    }

    protected function getInitialGravity(): float
    {
        return 0.0;
    }

    public function onUpdate(int $currentTick): bool
    {
        if ($this->factionId === "" || !FactionGuardianManager::shouldGuardianEntityRemain($this->factionId, $this->getPosition())) {
            $this->flagForDespawn();
            return false;
        }

        if (($currentTick % FactionGuardianManager::getOrbitUpdateTicks()) === 0) {
            $this->orbit($currentTick);
        }

        return parent::onUpdate($currentTick);
    }

    public function attack(EntityDamageEvent $source): void
    {
        $source->cancel();
    }

    public function spawnTo(Player $player): void
    {
        if (!FactionGuardianManager::shouldDisplayTo($player, $this->factionId)) {
            return;
        }

        parent::spawnTo($player);
    }

    public function saveNBT(): CompoundTag
    {
        $nbt = parent::saveNBT();
        $nbt->setString("guardianFactionId", $this->factionId);
        $nbt->setFloat("guardianAnchorX", $this->anchorX);
        $nbt->setFloat("guardianAnchorY", $this->anchorY);
        $nbt->setFloat("guardianAnchorZ", $this->anchorZ);
        $nbt->setFloat("guardianAngleOffset", $this->angleOffset);
        return $nbt;
    }

    private function orbit(int $currentTick): void
    {
        $radius = FactionGuardianManager::getOrbitRadius();
        $speed = FactionGuardianManager::getOrbitSpeed();
        $angle = $this->angleOffset + ($currentTick * $speed);
        $x = $this->anchorX + cos($angle) * $radius;
        $z = $this->anchorZ + sin($angle) * $radius;
        $y = $this->anchorY + FactionGuardianManager::getOrbitHeight() + sin($angle * 2.0) * 1.25;
        $yaw = rad2deg($angle) + 90.0;

        $this->teleport(new Location($x, $y, $z, $this->getWorld(), $yaw, 0.0));
    }

    private function refreshNameTag(): void
    {
        $faction = $this->factionId !== "" ? Loader::getFactionManager()->getFactionById($this->factionId) : null;
        $name = $faction?->getName() ?? "Unknown";
        $this->setNameTag(C::colorize("&r&b&l" . $name . " Guardian\n&r&7Faction Core"));
    }
}
