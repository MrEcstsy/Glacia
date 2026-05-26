<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\factions\claims;

use ecstsy\Glacia\server\factions\Faction;
use ecstsy\Glacia\server\factions\FactionManager;
use pocketmine\Server;
use pocketmine\world\Position;
use pocketmine\world\World;

final class FactionClaim {
    public const TYPE_NORMAL = "normal";
    public const TYPE_CORE = "core";
    public const TYPE_RAID = "raid";
    public const TYPE_PREMIUM = "premium";

    public function __construct(
        private string $factionId,
        private int $chunkX,
        private int $chunkZ,
        private string $worldName,
        private string $claimType = self::TYPE_NORMAL
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

    public function getClaimType(): string {
        return $this->claimType;
    }

    public function setClaimType(string $claimType): void {
        $this->claimType = self::normalizeType($claimType);
    }

    public function isCore(): bool {
        return $this->claimType === self::TYPE_CORE;
    }

    public function isRaid(): bool {
        return $this->claimType === self::TYPE_RAID;
    }

    public function isPremium(): bool {
        return $this->claimType === self::TYPE_PREMIUM;
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

    public static function normalizeType(string $claimType): string {
        return match (strtolower(trim($claimType))) {
            self::TYPE_CORE => self::TYPE_CORE,
            self::TYPE_RAID => self::TYPE_RAID,
            self::TYPE_PREMIUM => self::TYPE_PREMIUM,
            default => self::TYPE_NORMAL,
        };
    }
}
