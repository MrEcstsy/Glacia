<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\regions;

use pocketmine\math\Vector3;
use pocketmine\utils\SingletonTrait;

final class RegionManager {
    use SingletonTrait;

    public function __construct()
    {
        self::setInstance($this);
    }

    /** @var Region[] */
    private array $regions = [];

    public function addRegion(Region $region): void {
        $this->regions[$region->getName()] = $region;
    }

    public function getRegionsAt(Vector3 $position): array {
        $found = [];
        foreach ($this->regions as $region) {
            if ($region->isWithinBounds($position)) {
                $found[] = $region;
            }
        }
        return $found;
    }

    public function getFirstRegionAt(Vector3 $position): ?Region {
        foreach ($this->regions as $region) {
            if ($region->isWithinBounds($position)) {
                return $region;
            }
        }
        return null;
    }

    public function getRegionByName(string $name): ?Region {
        return $this->regions[$name] ?? null;
    }

    public function getRegionAt(Vector3 $position): ?Region {
        foreach ($this->regions as $region) {
            if ($region->contains($position)) {
                return $region;
            }
        }
        return null;
    }

    public function getRegionAtChunk(int $chunkX, int $chunkZ, string $world): ?Region {
        $pos = new Vector3(($chunkX << 4) + 8, 64, ($chunkZ << 4) + 8);
        foreach ($this->regions as $region) {
            if ($region->getWorld() === $world && $region->isWithinBounds($pos)) {
                return $region;
            }
        }
        return null;
    }
}