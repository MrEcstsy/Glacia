<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\factions\claims;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\factions\guardian\FactionGuardianManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\Glacia\utils\StorageUtils;
use ecstsy\Glacia\utils\QueryStmts;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\Position;
use pocketmine\world\World;

final class ClaimManager
{
    use SingletonTrait;

    /** @var FactionClaim[] */
    private array $claims = [];
    /** @var array<string, array{type:string, created_at:int, disconnected_since:int}> */
    private array $claimMeta = [];
    /** @var array<string, mixed> */
    private array $settings = [];
    private string $metaPath = "";

    public function __construct() {
        self::setInstance($this);
        $this->metaPath = Loader::getInstance()->getDataFolder() . "faction_claim_meta.json";
        $this->loadSettings();
        $this->loadMeta();
        $this->loadClaims();
        Loader::getInstance()->getScheduler()->scheduleRepeatingTask(new ClosureTask(function(): void {
            $this->cleanupDisconnectedNormalClaims();
        }), 20 * max(60, $this->getDisconnectedClaimCleanupIntervalSeconds()));
    }

    public function loadSettings(): void {
        $misc = GeneralUtils::getConfiguration(Loader::getInstance(), "misc.yml");
        $settings = (array) ($misc?->get("faction-core-claims", []) ?? []);
        $this->settings = [
            "normal-require-adjacent" => (bool) ($settings["normal-require-adjacent"] ?? true),
            "normal-disconnect-expiry-seconds" => max(3600, (int) ($settings["normal-disconnect-expiry-seconds"] ?? 86400)),
            "normal-disconnect-cleanup-interval-seconds" => max(60, (int) ($settings["normal-disconnect-cleanup-interval-seconds"] ?? 300)),
            "premium" => (array) ($settings["premium"] ?? []),
            "raid" => (array) ($settings["raid"] ?? []),
            "breach" => (array) ($settings["breach"] ?? []),
        ];
    }

    private function loadClaims(): void {
        Loader::getDatabase()->executeSelect(QueryStmts::FACTION_CLAIM_SELECT, [], function(array $rows): void {
            foreach ($rows as $row) {
                $key = $this->makeKey($row["world_name"], (int) $row["chunkX"], (int) $row["chunkZ"]);
                $type = $this->getMetaType($key);
                $this->claims[$key] = new FactionClaim(
                    $row["faction_id"],
                    $row["chunkX"],
                    $row["chunkZ"],
                    $row["world_name"],
                    $type
                );
            }
            $this->ensureCoreClaimsForLoadedFactions();
        });
    }

    private function loadMeta(): void {
        $decoded = StorageUtils::readJsonFile($this->metaPath, "faction claim metadata");
        if (!is_array($decoded)) {
            return;
        }

        foreach ($decoded as $key => $meta) {
            if (!is_string($key) || !is_array($meta)) {
                continue;
            }

            $this->claimMeta[$key] = [
                "type" => FactionClaim::normalizeType((string) ($meta["type"] ?? FactionClaim::TYPE_NORMAL)),
                "created_at" => max(0, (int) ($meta["created_at"] ?? time())),
                "disconnected_since" => max(0, (int) ($meta["disconnected_since"] ?? 0)),
            ];
        }
    }

    public function saveMeta(): void {
        if ($this->metaPath === "") {
            return;
        }

        StorageUtils::writeJsonFile($this->metaPath, $this->claimMeta, "faction claim metadata");
    }

    private function ensureCoreClaimsForLoadedFactions(): void {
        $hasCore = [];
        foreach ($this->claims as $claim) {
            if ($claim->isCore()) {
                $hasCore[$claim->getFactionId()] = true;
            }
        }

        $changed = false;
        foreach ($this->claims as $claim) {
            $factionId = $claim->getFactionId();
            if (isset($hasCore[$factionId])) {
                continue;
            }

            $claim->setClaimType(FactionClaim::TYPE_CORE);
            $key = $this->makeKey($claim->getWorldName(), $claim->getChunkX(), $claim->getChunkZ());
            $this->claimMeta[$key] = [
                "type" => FactionClaim::TYPE_CORE,
                "created_at" => (int) (($this->claimMeta[$key] ?? [])["created_at"] ?? time()),
                "disconnected_since" => 0,
            ];
            $hasCore[$factionId] = true;
            $changed = true;
        }

        if ($changed) {
            $this->saveMeta();
        }
    }

    public function getClaim(string $worldName, int $chunkX, int $chunkZ): ?FactionClaim {
        $key = $this->makeKey($worldName, $chunkX, $chunkZ);
        return $this->claims[$key] ?? null;
    }

    public function getClaimAtPosition(Position $pos): ?FactionClaim {
        $worldName = $pos->getWorld()->getFolderName();
        $chunkX = $pos->getFloorX() >> 4;
        $chunkZ = $pos->getFloorZ() >> 4;
        return $this->getClaim($worldName, $chunkX, $chunkZ);
    }

    /**
     * @return FactionClaim[]
     */
    public function getClaimsForFaction(string $factionId): array {
        $out = [];
        foreach ($this->claims as $claim) {
            if ($claim->getFactionId() === $factionId) {
                $out[] = $claim;
            }
        }
        return $out;
    }

    public function getClaimCountForFactionByType(string $factionId, string $type): int {
        $type = FactionClaim::normalizeType($type);
        $count = 0;
        foreach ($this->getClaimsForFaction($factionId) as $claim) {
            if ($claim->getClaimType() === $type) {
                ++$count;
            }
        }
        return $count;
    }

    public function getCoreClaimForFaction(string $factionId): ?FactionClaim {
        foreach ($this->getClaimsForFaction($factionId) as $claim) {
            if ($claim->isCore()) {
                return $claim;
            }
        }
        return null;
    }

    public function hasAdjacentFactionClaim(string $factionId, World $world, int $chunkX, int $chunkZ): bool {
        $worldName = $world->getFolderName();
        foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dx, $dz]) {
            $claim = $this->getClaim($worldName, $chunkX + $dx, $chunkZ + $dz);
            if ($claim !== null && $claim->getFactionId() === $factionId) {
                return true;
            }
        }

        return false;
    }

    public function mustNormalClaimBeAdjacent(): bool {
        return (bool) ($this->settings["normal-require-adjacent"] ?? true);
    }

    public function getDisconnectedClaimExpirySeconds(): int
    {
        return max(3600, (int) ($this->settings["normal-disconnect-expiry-seconds"] ?? 86400));
    }

    public function getDisconnectedClaimCleanupIntervalSeconds(): int
    {
        return max(60, (int) ($this->settings["normal-disconnect-cleanup-interval-seconds"] ?? 300));
    }

    public function createClaim(string $factionId, World $world, int $chunkX, int $chunkZ, string $type = FactionClaim::TYPE_NORMAL): FactionClaim {
        $worldName = $world->getFolderName();
        $key = $this->makeKey($worldName, $chunkX, $chunkZ);
        $type = FactionClaim::normalizeType($type);

        if (isset($this->claims[$key])) {
            throw new \RuntimeException("Chunk {$chunkX},{$chunkZ} in {$worldName} is already claimed!");
        }

        Loader::getDatabase()->executeInsert(QueryStmts::FACTION_CLAIM_CREATE, [
            "world_name" => $worldName,
            "chunkX"     => $chunkX,
            "chunkZ"     => $chunkZ,
            "faction_id" => $factionId,
        ]);

        $this->claimMeta[$key] = [
            "type" => $type,
            "created_at" => time(),
            "disconnected_since" => 0,
        ];
        $this->saveMeta();

        $claim = new FactionClaim($factionId, $chunkX, $chunkZ, $worldName, $type);
        $this->claims[$key] = $claim;
        if ($claim->isCore()) {
            FactionGuardianManager::syncGuardianToCoreClaim($factionId, $world, $chunkX, $chunkZ);
        }
        Loader::getFactionManager()->recalculateSpawnerWorths();
        return $claim;
    }

    public function deleteClaim(FactionClaim $claim): void {
        $wasCore = $claim->isCore();
        $factionId = $claim->getFactionId();
        $key = $this->makeKey($claim->getWorldName(), $claim->getChunkX(), $claim->getChunkZ());
        if (isset($this->claims[$key])) {
            unset($this->claims[$key]);
        }
        unset($this->claimMeta[$key]);
        $this->saveMeta();

        Loader::getDatabase()->executeChange(QueryStmts::FACTION_CLAIM_DELETE, [
            "world_name" => $claim->getWorldName(),
            "chunkX"     => $claim->getChunkX(),
            "chunkZ"     => $claim->getChunkZ(),
        ]);

        if ($wasCore) {
            FactionGuardianManager::clearGuardianForFaction($factionId);
        }

        Loader::getFactionManager()->recalculateSpawnerWorths();
    }

    public function deleteAllForFaction(string $factionId): void {
        $keys = [];
        foreach ($this->claims as $key => $claim) {
            if ($claim->getFactionId() === $factionId) {
                $keys[] = $key;
            }
        }

        foreach ($keys as $key) {
            unset($this->claims[$key]);
            unset($this->claimMeta[$key]);
        }
        $this->saveMeta();

        Loader::getDatabase()->executeChange(QueryStmts::FACTION_CLAIM_DELETE_FACTION, [
            "faction_id" => $factionId,
        ]);

        Loader::getFactionManager()->recalculateSpawnerWorths();
    }

    public function getPremiumClaimCost(string $factionId): float {
        $premium = (array) ($this->settings["premium"] ?? []);
        $base = max(0.0, (float) ($premium["base-cost"] ?? 1000000.0));
        $increment = max(0.0, (float) ($premium["increment-per-existing"] ?? 500000.0));
        return $base + ($increment * $this->getClaimCountForFactionByType($factionId, FactionClaim::TYPE_PREMIUM));
    }

    public function getPremiumClaimLimit(): int {
        $premium = (array) ($this->settings["premium"] ?? []);
        return max(0, (int) ($premium["limit-per-faction"] ?? 3));
    }

    public function isPremiumWildernessCandidate(World $world, int $chunkX, int $chunkZ): bool {
        $premium = (array) ($this->settings["premium"] ?? []);
        if (!(bool) ($premium["enabled"] ?? true)) {
            return false;
        }

        $worldName = $world->getFolderName();
        if ($this->getClaim($worldName, $chunkX, $chunkZ) !== null) {
            return false;
        }

        $region = Loader::getRegionManager()->getRegionAtChunk($chunkX, $chunkZ, $worldName);
        if ($region !== null) {
            return false;
        }

        $warzoneName = (string) ($premium["warzone-region"] ?? "Warzone");
        $adjacentDistance = max(1, (int) ($premium["adjacent-chunk-distance"] ?? 1));

        for ($dx = -$adjacentDistance; $dx <= $adjacentDistance; ++$dx) {
            for ($dz = -$adjacentDistance; $dz <= $adjacentDistance; ++$dz) {
                if ($dx === 0 && $dz === 0) {
                    continue;
                }

                $near = Loader::getRegionManager()->getRegionAtChunk($chunkX + $dx, $chunkZ + $dz, $worldName);
                if ($near !== null && strcasecmp($near->getName(), $warzoneName) === 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public function areRaidClaimsPermanent(): bool {
        $raid = (array) ($this->settings["raid"] ?? []);
        return (bool) ($raid["permanent"] ?? true);
    }

    public function getBreachCooldownSeconds(): int {
        $breach = (array) ($this->settings["breach"] ?? []);
        return max(0, (int) ($breach["cooldown-seconds"] ?? 28800));
    }

    public function getBreachMinimumY(): int {
        $breach = (array) ($this->settings["breach"] ?? []);
        return max(0, (int) ($breach["minimum-y"] ?? 20));
    }

    public function getBreachPercentForY(float $y): float {
        $breach = (array) ($this->settings["breach"] ?? []);
        $tiers = is_array($breach["tiers"] ?? null) ? $breach["tiers"] : [
            ["max-y" => 100, "percent" => 10],
            ["max-y" => 150, "percent" => 15],
            ["max-y" => 250, "percent" => 20],
            ["max-y" => 255, "percent" => 25],
        ];

        $lastPercent = 0.0;
        foreach ($tiers as $tier) {
            if (!is_array($tier)) {
                continue;
            }
            $lastPercent = max(0.0, (float) ($tier["percent"] ?? $lastPercent));
            if ($y <= (float) ($tier["max-y"] ?? 0)) {
                return max(0.0, (float) ($tier["percent"] ?? 0.0)) / 100.0;
            }
        }

        return $lastPercent / 100.0;
    }

    private function cleanupDisconnectedNormalClaims(): void
    {
        $expiry = $this->getDisconnectedClaimExpirySeconds();
        $now = time();
        $changedMeta = false;

        foreach ($this->groupClaimsByFaction() as $factionId => $claims) {
            $core = null;
            foreach ($claims as $claim) {
                if ($claim->isCore()) {
                    $core = $claim;
                    break;
                }
            }
            if ($core === null) {
                continue;
            }

            $reachable = $this->findReachableCoreConnectedKeys($factionId, $core);
            foreach ($claims as $claim) {
                if ($claim->getClaimType() !== FactionClaim::TYPE_NORMAL) {
                    continue;
                }

                $key = $this->makeKey($claim->getWorldName(), $claim->getChunkX(), $claim->getChunkZ());
                if (isset($reachable[$key])) {
                    if (((int) (($this->claimMeta[$key] ?? [])["disconnected_since"] ?? 0)) !== 0) {
                        $this->claimMeta[$key]["disconnected_since"] = 0;
                        $changedMeta = true;
                    }
                    continue;
                }

                $disconnectedSince = (int) (($this->claimMeta[$key] ?? [])["disconnected_since"] ?? 0);
                if ($disconnectedSince <= 0) {
                    $this->claimMeta[$key]["disconnected_since"] = $now;
                    $changedMeta = true;
                    continue;
                }

                if (($now - $disconnectedSince) < $expiry) {
                    continue;
                }

                $factionName = $claim->getFaction()?->getName() ?? $factionId;
                $worldName = $claim->getWorldName();
                $chunkX = $claim->getChunkX();
                $chunkZ = $claim->getChunkZ();
                $this->deleteClaim($claim);
                GlaciaUtils::logFactionAudit("Auto Unclaim", [
                    "faction" => $factionName,
                    "faction_id" => $factionId,
                    "world" => $worldName,
                    "chunk_x" => $chunkX,
                    "chunk_z" => $chunkZ,
                    "type" => FactionClaim::TYPE_NORMAL,
                    "reason" => "disconnected_from_core",
                ]);
            }
        }

        if ($changedMeta) {
            $this->saveMeta();
        }
    }

    /**
     * @return array<string, list<FactionClaim>>
     */
    private function groupClaimsByFaction(): array
    {
        $grouped = [];
        foreach ($this->claims as $claim) {
            $grouped[$claim->getFactionId()][] = $claim;
        }

        return $grouped;
    }

    /**
     * @return array<string, true>
     */
    private function findReachableCoreConnectedKeys(string $factionId, FactionClaim $core): array
    {
        $startKey = $this->makeKey($core->getWorldName(), $core->getChunkX(), $core->getChunkZ());
        $reachable = [$startKey => true];
        $queue = [[$core->getWorldName(), $core->getChunkX(), $core->getChunkZ()]];

        while ($queue !== []) {
            [$worldName, $chunkX, $chunkZ] = array_shift($queue);
            foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dx, $dz]) {
                $neighbor = $this->getClaim($worldName, $chunkX + $dx, $chunkZ + $dz);
                if ($neighbor === null || $neighbor->getFactionId() !== $factionId || $neighbor->isPremium() || $neighbor->isRaid()) {
                    continue;
                }

                $neighborKey = $this->makeKey($neighbor->getWorldName(), $neighbor->getChunkX(), $neighbor->getChunkZ());
                if (isset($reachable[$neighborKey])) {
                    continue;
                }

                $reachable[$neighborKey] = true;
                $queue[] = [$neighbor->getWorldName(), $neighbor->getChunkX(), $neighbor->getChunkZ()];
            }
        }

        return $reachable;
    }

    private function getMetaType(string $key): string {
        return FactionClaim::normalizeType((string) (($this->claimMeta[$key] ?? [])["type"] ?? FactionClaim::TYPE_NORMAL));
    }

    private function makeKey(string $world, int $x, int $z): string {
        return "{$world}:{$x}:{$z}";
    }

    public function resetSeasonState(): void
    {
        $this->claims = [];
        $this->claimMeta = [];
        $this->saveMeta();
    }
}
