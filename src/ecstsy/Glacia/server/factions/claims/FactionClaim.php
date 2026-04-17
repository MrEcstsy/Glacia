<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\factions\claims;

use ecstsy\Glacia\server\factions\Faction;
use ecstsy\Glacia\server\factions\FactionManager;
use pocketmine\Server;
use pocketmine\world\Position;
use pocketmine\world\World;

final class FactionClaim {

    public function __construct(
        private string $factionId,
        private int $chunkX,
        private int $chunkZ,
        private string $worldName
    ){}

    public function getFactionId(): string {
        return $this->factionId;
    }

    public function getChunkX(): int {
        return $this->chunkX;
    }

    public function getChunkZ(): int {
        return $this->chunkZ;
    }

    public function getWorldName(): string {
        return $this->worldName;
    }

    public function getWorld(): ?World {
        return Server::getInstance()->getWorldManager()->getWorldByName($this->worldName);
    }

    public function containsPosition(Position $pos): bool {
        $w = $pos->getWorld()->getFolderName();
        $cx = $pos->getFloorX() >> 4;
        $cz = $pos->getFloorZ() >> 4;
        return $w === $this->worldName && $cx === $this->chunkX && $cz === $this->chunkZ;
    }

    public function getFaction(): ?Faction{
        return FactionManager::getInstance()->getFaction($this->factionId);
    }
}