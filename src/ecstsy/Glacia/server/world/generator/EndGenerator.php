<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\world\generator;

use ecstsy\Glacia\server\world\theme\DimensionThemeManager;
use pocketmine\block\VanillaBlocks;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use pocketmine\world\generator\Generator;
use pocketmine\world\generator\noise\Simplex;
use function abs;

final class EndGenerator extends Generator {

    public const SPAWN_PLATFORM_Y = 64;

    private Simplex $baseNoise;
    private Simplex $islandNoise;
    /** @var array<string, mixed> */
    private array $terrain;

    public function __construct(int $seed, string $preset) {
        parent::__construct($seed, $preset);
        // Adapted from MultiWorld's Ender generator profile for cleaner playable islands.
        $this->baseNoise = new Simplex($this->random, 4, 1 / 16, 1 / 128);
        $this->islandNoise = new Simplex($this->random, 1, 1, 1 / 1024);
        $this->terrain = DimensionThemeManager::getTerrainSettings($preset !== "" ? $preset : "astral_veil");
    }

    public function generateChunk(ChunkManager $world, int $chunkX, int $chunkZ): void {
        $this->random->setSeed(0xdeadbeef ^ ($chunkX << 8) ^ $chunkZ ^ $this->seed);
        $chunk = $world->getChunk($chunkX, $chunkZ) ?? throw new \InvalidArgumentException("Chunk $chunkX $chunkZ does not yet exist");

        $surface = DimensionThemeManager::resolveTerrainBlockStateId((string) ($this->terrain["surface-block"] ?? "end_stone"), VanillaBlocks::END_STONE()->getStateId());
        $fill = DimensionThemeManager::resolveTerrainBlockStateId((string) ($this->terrain["fill-block"] ?? "end_stone"), VanillaBlocks::END_STONE()->getStateId());
        $noise = $this->baseNoise->getFastNoise2D(Chunk::EDGE_LENGTH, Chunk::EDGE_LENGTH, 2, $chunkX * Chunk::EDGE_LENGTH, 0, $chunkZ * Chunk::EDGE_LENGTH);
        $islandNoise = $this->islandNoise->getFastNoise2D(Chunk::EDGE_LENGTH, Chunk::EDGE_LENGTH, 2, $chunkX * Chunk::EDGE_LENGTH, 0, $chunkZ * Chunk::EDGE_LENGTH);

        $baseX = $chunkX * Chunk::EDGE_LENGTH;
        $baseZ = $chunkZ * Chunk::EDGE_LENGTH;

        for ($x = 0; $x < Chunk::EDGE_LENGTH; ++$x) {
            for ($z = 0; $z < Chunk::EDGE_LENGTH; ++$z) {
                $worldX = $baseX + $x;
                $worldZ = $baseZ + $z;
                $isInMainIsland = (($worldX * $worldX) + ($worldZ * $worldZ)) <= ($this->getIslandRadius() * $this->getIslandRadius());
                $islandNoiseValue = abs($islandNoise[$x][$z] ?? 0.0);
                if (!$isInMainIsland && $islandNoiseValue < 0.5) {
                    continue;
                }

                $noiseValue = (int) abs(($noise[$x][$z] ?? 0.0) * $this->getNoiseSize()) + $this->getNoiseHalf();
                for ($y = -$noiseValue; $y < $noiseValue; ++$y) {
                    $chunk->setBlockStateId($x, $this->getBaseIslandHeight() + $y, $z, $y >= 0 ? $surface : $fill);
                }

                $distanceSquared = ($worldX * $worldX) + ($worldZ * $worldZ);
                if ($distanceSquared <= $this->getSpawnIslandRadius() * $this->getSpawnIslandRadius()) {
                    $topY = $this->getSpawnPlatformY() - 1;
                    for ($y = $this->getBaseIslandHeight() - 4; $y <= $topY; ++$y) {
                        $chunk->setBlockStateId($x, $y, $z, $surface);
                    }
                }
            }
        }
    }

    public function populateChunk(ChunkManager $world, int $chunkX, int $chunkZ): void {
        if ($chunkX !== 0 || $chunkZ !== 0) {
            return;
        }

        $chunk = $world->getChunk($chunkX, $chunkZ);
        if ($chunk === null) {
            return;
        }

        $platform = DimensionThemeManager::resolveTerrainBlockStateId((string) ($this->terrain["spawn-platform-block"] ?? "obsidian"), VanillaBlocks::OBSIDIAN()->getStateId());
        $surface = DimensionThemeManager::resolveTerrainBlockStateId((string) ($this->terrain["surface-block"] ?? "end_stone"), VanillaBlocks::END_STONE()->getStateId());
        $air = VanillaBlocks::AIR()->getStateId();

        for ($x = 0; $x < Chunk::EDGE_LENGTH; ++$x) {
            for ($z = 0; $z < Chunk::EDGE_LENGTH; ++$z) {
                $worldX = ($chunkX * Chunk::EDGE_LENGTH) + $x;
                $worldZ = ($chunkZ * Chunk::EDGE_LENGTH) + $z;
                $distanceSquared = ($worldX * $worldX) + ($worldZ * $worldZ);
                if ($distanceSquared > $this->getSpawnClearRadius() * $this->getSpawnClearRadius()) {
                    continue;
                }

                $chunk->setBlockStateId($x, $this->getSpawnPlatformY() - 1, $z, $surface);
                $chunk->setBlockStateId($x, $this->getSpawnPlatformY(), $z, $platform);
                for ($y = $this->getSpawnPlatformY() + 1; $y <= $this->getSpawnPlatformY() + 8; ++$y) {
                    $chunk->setBlockStateId($x, $y, $z, $air);
                }
            }
        }
    }

    private function getBaseIslandHeight() : int { return max(32, (int) ($this->terrain["base-island-height"] ?? 55)); }
    private function getNoiseSize() : int { return max(1, (int) ($this->terrain["noise-size"] ?? 12)); }
    private function getNoiseHalf() : int { return max(1, (int) ($this->terrain["noise-half"] ?? 6)); }
    private function getIslandRadius() : int { return max(32, (int) ($this->terrain["island-radius"] ?? 100)); }
    private function getSpawnIslandRadius() : int { return max(8, (int) ($this->terrain["spawn-island-radius"] ?? 24)); }
    private function getSpawnClearRadius() : int { return max(4, (int) ($this->terrain["spawn-clear-radius"] ?? 10)); }
    private function getSpawnPlatformY() : int { return max(40, (int) ($this->terrain["spawn-platform-y"] ?? 64)); }
}
