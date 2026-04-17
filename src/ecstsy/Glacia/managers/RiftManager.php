<?php

declare(strict_types=1);

namespace ecstsy\Glacia\managers;

use ecstsy\Glacia\entity\RiftEntity;
use ecstsy\Glacia\entity\RiftWalkerEntity;
use ecstsy\Glacia\Loader;
use pocketmine\block\BlockTypeIds;
use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\scheduler\Task;
use pocketmine\Server;
use pocketmine\world\format\Chunk;
use pocketmine\world\Position;

final class RiftManager {

    /** @var RiftEntity[] keyed by entity id */
    public static array $rifts = [];

    private const MAX_ACTIVE_RIFTS = 1;

    private static int $pendingSpawns = 0;


    public static function canReserveSpawnSlot(): bool {
        return (count(self::$rifts) + self::$pendingSpawns) < self::MAX_ACTIVE_RIFTS;
    }

    private static function reserveSpawnSlot(): bool {
        if (!self::canReserveSpawnSlot()) return false;
        self::$pendingSpawns++;
        Loader::getInstance()->getLogger()->info("RiftManager: reserved spawn slot (pending=" . self::$pendingSpawns . ").");
        return true;
    }
    private static function releaseSpawnSlot(): void {
        if (self::$pendingSpawns > 0) {
            self::$pendingSpawns--;
            Loader::getInstance()->getLogger()->info("RiftManager: released spawn slot (pending=" . self::$pendingSpawns . ").");
        }
    }

    public static function requestSpawn(callable $onSuccess, ?callable $onFailure = null): void {
        if (!self::reserveSpawnSlot()) {
            if ($onFailure) $onFailure("max_rifts");
            return;
        }

        $internalFailure = function (?string $reason = null) use ($onFailure): void {
            self::releaseSpawnSlot();
            if ($onFailure) {
                $onFailure($reason);
            }
        };

        $internalSuccess = function (Position $position) use ($onSuccess, $internalFailure): void {
            if (!self::canReserveSpawnSlot()) {
                $internalFailure("capacity_lost");
                return;
            }

            $entity = self::spawnEndCrystal($position);
            if ($entity !== null) {
                self::releaseSpawnSlot();
                if ($onSuccess) $onSuccess($position, $entity);
                return;
            }

            $internalFailure("spawn_failed");
        };

        self::getRandomPosition($internalSuccess, $internalFailure);
    }

    public static function scheduleRifts(): void {
        Loader::getInstance()->getScheduler()->scheduleRepeatingTask(new class() extends Task {
            public function onRun(): void {
                RiftManager::spawnRift();
            }
        }, mt_rand(3600, 7200) * 20);
    }

    public static function spawnRift(): void {
        if (count(self::$rifts) >= self::MAX_ACTIVE_RIFTS) {
            Loader::getInstance()->getLogger()->info("RiftManager: maximum active rifts reached, skipping spawn.");
            return;
        }

        self::getRandomPosition(function (Position $position) {
            $players = Server::getInstance()->getOnlinePlayers();

            foreach ($players as $player) {
                $player->sendMessage("§l§b[!] §r§7A Rift has appeared at §bX: " . $position->getFloorX() . " Y: " . $position->getFloorY() . " Z: " . $position->getFloorZ() . "§7! Seek it out for great rewards!");
            }

            self::spawnEndCrystal($position);
        });
    }

    /**
     * Finds a suitable spawn position and calls $onFound(Position).
     * If it cannot find one instantly it may (optionally) schedule a small chunk population
     * and retry once after a short delay while the pending slot remains reserved.
     *
     * @param callable $onFound function(Position $pos)
     * @param callable|null $onNotFound function(string $reason = null)
     * @param bool $allowPopulationRetry whether to allow a short delayed population+retry (default true)
     */
    public static function getRandomPosition(callable $onFound, ?callable $onNotFound = null, bool $allowPopulationRetry = true): void {
        $level = Server::getInstance()->getWorldManager()->getDefaultWorld();

        // tuning
        $minPlayerRadius = 16;
        $maxPlayerRadius = 250;
        $perPlayerAttempts = 40;   // try this many positions per player
        $localSpiralRadius = 120;  // used by shoreline search
        $globalAttempts = 60;
        $globalNearbyRadius = 48;

        // helper that tries to check/rescue a candidate by resolving surface Y (shorelines, shallow water)
        $tryCandidate = function(int $x, int $z) use ($level, $onFound, $localSpiralRadius): ?Position {
            $y = self::resolveSurfaceY($level, $x, $z);
            if ($y === null) return null;
            $candidate = new Position($x, $y, $z, $level);
            if (self::isValidSpawnPosition($candidate)) {
                return $candidate;
            }

            // if it's liquid-ish but resolvable, try small nearby land
            if (self::isOnlyLiquidFailure($candidate)) {
                $land = self::findNearestLandFrom($candidate, $localSpiralRadius);
                if ($land !== null) return $land;
            }

            return null;
        };

        // 1) Try around online players first (shuffle to distribute)
        $players = array_values(Server::getInstance()->getOnlinePlayers());
        if (!empty($players)) {
            shuffle($players);
            foreach ($players as $player) {
                $px = (int)$player->getPosition()->getX();
                $pz = (int)$player->getPosition()->getZ();

                for ($i = 0; $i < $perPlayerAttempts; $i++) {
                    $angle = mt_rand(0, 31415) / 10000.0;
                    $dist  = mt_rand($minPlayerRadius, $maxPlayerRadius);
                    $x = (int)floor($px + cos($angle) * $dist);
                    $z = (int)floor($pz + sin($angle) * $dist);

                    $chunkX = $x >> 4;
                    $chunkZ = $z >> 4;
                    if (!$level->isChunkLoaded($chunkX, $chunkZ)) {
                        continue; // skip unloaded chunk for instant attempt
                    }

                    $found = $tryCandidate($x, $z);
                    if ($found !== null) {
                        $onFound($found);
                        return;
                    }
                }
            }
        }

        // 2) Global random fallback (loaded-chunk-only quick attempts)
        $attempt = 0;
        while ($attempt < $globalAttempts) {
            $attempt++;
            $maxDistance = 1000;
            $radiusAvoid = 250;

            $x = mt_rand(-$maxDistance, $maxDistance);
            $z = mt_rand(-$maxDistance, $maxDistance);

            if (sqrt($x * $x + $z * $z) <= $radiusAvoid) {
                $x = ($x < 0 ? -1 : 1) * ($radiusAvoid + mt_rand(0, $maxDistance - $radiusAvoid));
                $z = ($z < 0 ? -1 : 1) * ($radiusAvoid + mt_rand(0, $maxDistance - $radiusAvoid));
            }

            $chunkX = $x >> 4;
            $chunkZ = $z >> 4;
            if (!$level->isChunkLoaded($chunkX, $chunkZ)) {
                continue;
            }

            $found = $tryCandidate($x, $z);
            if ($found !== null) {
                $onFound($found);
                return;
            }
        }

        // 3) Last quick try around world spawn
        $spawn = $level->getSpawnLocation();
        $sx = (int)$spawn->x;
        $sz = (int)$spawn->z;
        $sy = $level->getHighestBlockAt($sx, $sz);
        $fallback = new Position($sx, $sy, $sz, $level);
        $land = self::findNearestLandFrom($fallback, 128) ?? self::findNearbySafePosition($fallback, 64);
        if ($land !== null && self::isValidSpawnPosition($land)) {
            $onFound($land);
            return;
        }

        // 4) If allowed, schedule a controlled population of a small set of nearby chunks and retry once
        if ($allowPopulationRetry) {
            // gather candidate unloaded chunk coords near players (or spawn if no players)
            $candidateChunks = [];
            if (!empty($players)) {
                // use first player we sampled earlier (randomized order)
                $center = $players[0]->getPosition();
                $cx = (int)$center->x;
                $cz = (int)$center->z;
            } else {
                $center = $level->getSpawnLocation();
                $cx = (int)$center->x;
                $cz = (int)$center->z;
            }

            // collect up to N unloaded chunks within radius to populate
            $chunkSearchRadius = 8; // chunks (8 => ~128 blocks)
            $maxToPopulate = 6;
            for ($dx = -$chunkSearchRadius; $dx <= $chunkSearchRadius && count($candidateChunks) < $maxToPopulate; $dx++) {
                for ($dz = -$chunkSearchRadius; $dz <= $chunkSearchRadius && count($candidateChunks) < $maxToPopulate; $dz++) {
                    $x = $cx + $dx * 16;
                    $z = $cz + $dz * 16;
                    $chunkX = $x >> 4;
                    $chunkZ = $z >> 4;
                    $key = $chunkX . ":" . $chunkZ;
                    if (isset($candidateChunks[$key])) continue;
                    if (!$level->isChunkLoaded($chunkX, $chunkZ)) {
                        $candidateChunks[$key] = [$chunkX, $chunkZ];
                    }
                }
            }

            if (!empty($candidateChunks)) {
                Loader::getInstance()->getLogger()->info("RiftManager: no instant spot — populating " . count($candidateChunks) . " nearby chunks and retrying shortly.");
                // order population for each collected chunk (best-effort)
                foreach ($candidateChunks as [$chunkX, $chunkZ]) {
                    $level->orderChunkPopulation($chunkX, $chunkZ, null);
                }

                // schedule a single delayed retry after a small delay (40 ticks ~ 2s)
                $scheduler = Loader::getInstance()->getScheduler();
                $scheduler->scheduleDelayedTask(new class($onFound, $onNotFound) extends Task {
                    private $onFound;
                    private $onNotFound;
                    public function __construct($onFound, $onNotFound) {
                        $this->onFound = $onFound;
                        $this->onNotFound = $onNotFound;
                    }
                    public function onRun(): void {
                        // retry search but disallow further population retries (prevent loops)
                        RiftManager::getRandomPosition($this->onFound, $this->onNotFound, false);
                    }
                }, 40);

                return;
            }
        }

        // final failure: tell caller so pending slot can be released
        if ($onNotFound !== null) {
            $onNotFound("no_valid_spot");
        }
    }

    /**
     * Try to resolve a sensible surface Y for x/z.
     * Returns integer Y or null if nothing usable found.
     * This tries getHighestBlockAt(), then if that top is liquid, scans down a few blocks for nearest non-liquid solid surface.
     */
    protected static function resolveSurfaceY($level, int $x, int $z): ?int {
        $yTop = $level->getHighestBlockAt($x, $z);
        if ($yTop <= 0) return null;

        // If the top is not liquid, return it
        $topBlock = $level->getBlockAt($x, $yTop, $z);
        $liquidIds = [BlockTypeIds::WATER, BlockTypeIds::LAVA];
        if (!in_array($topBlock->getTypeId(), $liquidIds, true)) {
            return $yTop;
        }

        // Otherwise scan downward up to N blocks to try to find a non-liquid solid (useful on shallow water)
        $maxDown = 6;
        for ($dy = 1; $dy <= $maxDown; $dy++) {
            $y = $yTop - $dy;
            if ($y <= 1) break;
            $b = $level->getBlockAt($x, $y, $z);
            if (!$b->isTransparent() && !in_array($b->getTypeId(), $liquidIds, true) && $b->isSolid()) {
                // ensure at least two blocks of headroom above that block
                $above1 = $level->getBlockAt($x, $y + 1, $z);
                $above2 = $level->getBlockAt($x, $y + 2, $z);
                if ($above1->isTransparent() && $above2->isTransparent()) {
                    return $y + 1; // spawn on top of this block
                }
            }
        }

        return null;
    }


    protected static function findNearestLandFrom(Position $origin, int $radius): ?Position {
        $level = $origin->getWorld();
        $ox = $origin->getFloorX();
        $oz = $origin->getFloorZ();

        // We'll keep track of the nearest candidate by squared distance so we return the closest found
        $best = null;
        $bestDistSq = PHP_INT_MAX;

        for ($r = 0; $r <= $radius; $r++) {
            // ring edges first keep cost low; but check entire square at small radii
            for ($dx = -$r; $dx <= $r; $dx++) {
                for ($dz = -$r; $dz <= $r; $dz++) {
                    if (max(abs($dx), abs($dz)) !== $r) continue;

                    $x = $ox + $dx;
                    $z = $oz + $dz;

                    $chunkX = $x >> 4;
                    $chunkZ = $z >> 4;
                    if (!$level->isChunkLoaded($chunkX, $chunkZ)) {
                        continue;
                    }

                    $y = $level->getHighestBlockAt($x, $z);
                    $candidate = new Position($x, $y, $z, $level);

                    if (!self::isValidSpawnPosition($candidate)) continue;

                    $distSq = ($ox - $x) * ($ox - $x) + ($oz - $z) * ($oz - $z);
                    if ($distSq < $bestDistSq) {
                        $bestDistSq = $distSq;
                        $best = $candidate;
                    }
                }
            }

            // if we found something in this ring, return the best — it's the closest
            if ($best !== null) {
                return $best;
            }
        }

        return null;
    }

    /**
     * Quick check to see if the only reason the candidate fails is that it's on/in liquid.
     */
    protected static function isOnlyLiquidFailure(Position $position): bool {
        $level = $position->getWorld();
        $x = $position->getFloorX();
        $y = $position->getFloorY();
        $z = $position->getFloorZ();

        $underY = max(0, $y - 1);
        $underBlock = $level->getBlockAt($x, $underY, $z);
        $hereBlock = $level->getBlockAt($x, $y, $z);

        $liquidIds = [
            BlockTypeIds::WATER,
            BlockTypeIds::LAVA,
        ];

        if (in_array($underBlock->getTypeId(), $liquidIds, true) || in_array($hereBlock->getTypeId(), $liquidIds, true)) {
            return true;
        }

        return false;
    }

    protected static function isValidSpawnPosition(Position $position): bool {
        $level = $position->getWorld();
        $x = $position->getFloorX();
        $y = $position->getFloorY();
        $z = $position->getFloorZ();

        if ($y <= 0) return false;

        $underY = max(0, $y - 1);
        $underBlock = $level->getBlockAt($x, $underY, $z);

        if (!$underBlock->isSolid()) {
            return false;
        }

        $liquidIds = [
            BlockTypeIds::WATER,
            BlockTypeIds::LAVA,
        ];
        if (in_array($underBlock->getTypeId(), $liquidIds, true)) {
            return false;
        }

        $hereBlock = $level->getBlockAt($x, $y, $z);
        if (in_array($hereBlock->getTypeId(), $liquidIds, true)) {
            return false;
        }

        $above1 = $level->getBlockAt($x, $y + 1, $z);
        $above2 = $level->getBlockAt($x, $y + 2, $z);
        if (!$above1->isTransparent() || !$above2->isTransparent()) {
            return false;
        }

        return true;
    }

    protected static function findNearbySafePosition(Position $origin, int $radius): ?Position {
        $level = $origin->getWorld();
        $ox = $origin->getFloorX();
        $oz = $origin->getFloorZ();

        for ($r = 1; $r <= $radius; $r++) {
            for ($dx = -$r; $dx <= $r; $dx++) {
                for ($dz = -$r; $dz <= $r; $dz++) {
                    if (max(abs($dx), abs($dz)) !== $r) continue;

                    $x = $ox + $dx;
                    $z = $oz + $dz;

                    $chunkX = $x >> 4;
                    $chunkZ = $z >> 4;

                    if (!$level->isChunkLoaded($chunkX, $chunkZ)) {
                        continue;
                    }

                    $y = $level->getHighestBlockAt($x, $z);
                    $candidate = new Position($x, $y, $z, $level);

                    if (self::isValidSpawnPosition($candidate)) {
                        return $candidate;
                    }
                }
            }
        }

        return null;
    }

    public static function spawnEndCrystal(Position $position): ?RiftEntity {
        $crystal = new RiftEntity(new Location($position->getFloorX() + 0.5, $position->getFloorY() + 1.0, $position->getFloorZ() + 0.5, $position->getWorld(), 50, 50));
        $crystal->spawnToAll();

        $eid = $crystal->getId();
        self::$rifts[$eid] = $crystal;

        self::spawnMobs($position);

        return $crystal;
    }

    public static function removeRift(int $entityId): void {
        if (isset(self::$rifts[$entityId])) {
            unset(self::$rifts[$entityId]);
            Loader::getInstance()->getLogger()->info("RiftManager: removed active rift {$entityId}");
        }
    }

    public static function spawnMobs(Position $position): void {
        $world = $position->getWorld();
        $mobClass = RiftWalkerEntity::class;

        if ($mobClass !== null) {
            for ($i = 0; $i < 5; $i++) {
                $tries = 0;
                do {
                    $mobX = $position->getFloorX() + mt_rand(-5, 5);
                    $mobZ = $position->getFloorZ() + mt_rand(-5, 5);
                    $mobY = $world->getHighestBlockAt($mobX, $mobZ) + 1;
                    $mobPos = new Position($mobX, $mobY, $mobZ, $world);
                    $tries++;
                } while ($tries < 6 && !self::isValidSpawnPosition($mobPos));

                if (!self::isValidSpawnPosition($mobPos)) {
                    continue;
                }

                $mobLocation = new Location($mobX + 0.5, $mobY, $mobZ + 0.5, $world, 0, 0);
                /** @var Entity $mob */
                $mob = new $mobClass($mobLocation);
                $mob->spawnToAll();
            }
        }
    }
}
