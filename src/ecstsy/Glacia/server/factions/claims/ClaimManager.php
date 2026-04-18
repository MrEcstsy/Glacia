<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\factions\claims;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\QueryStmts;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\Position;
use pocketmine\world\World;

final class ClaimManager
{
    use SingletonTrait;

    /** @var FactionClaim[] */
    private array $claims = [];

    public function __construct() {
        self::setInstance($this);
        $this->loadClaims();
    }

    private function loadClaims(): void {
        Loader::getDatabase()->executeSelect(QueryStmts::FACTION_CLAIM_SELECT, [], function(array $rows): void {
            foreach ($rows as $row) {
                $key = $this->makeKey($row["world_name"], (int) $row["chunkX"], (int) $row["chunkZ"]);
                $this->claims[$key] = new FactionClaim(
                    $row["faction_id"],
                    $row["chunkX"],
                    $row["chunkZ"],
                    $row["world_name"]
                );
            }
        });
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

    public function createClaim(string $factionId, World $world, int $chunkX, int $chunkZ): FactionClaim {
        $worldName = $world->getFolderName();
        $key = $this->makeKey($worldName, $chunkX, $chunkZ);

        if (isset($this->claims[$key])) {
            throw new \RuntimeException("Chunk {$chunkX},{$chunkZ} in {$worldName} is already claimed!");
        }

        Loader::getDatabase()->executeInsert(QueryStmts::FACTION_CLAIM_CREATE, [
            "world_name" => $worldName,
            "chunkX"     => $chunkX,
            "chunkZ"     => $chunkZ,
            "faction_id" => $factionId,
        ]);

        $claim = new FactionClaim($factionId, $chunkX, $chunkZ, $worldName);
        $this->claims[$key] = $claim;
        Loader::getFactionManager()->recalculateSpawnerWorths();
        return $claim;
    }

    public function deleteClaim(FactionClaim $claim): void {
        $key = $this->makeKey($claim->getWorldName(), $claim->getChunkX(), $claim->getChunkZ());
        if (isset($this->claims[$key])) {
            unset($this->claims[$key]);
        }

        Loader::getDatabase()->executeChange(QueryStmts::FACTION_CLAIM_DELETE, [
            "world_name" => $claim->getWorldName(),
            "chunkX"     => $claim->getChunkX(),
            "chunkZ"     => $claim->getChunkZ(),
        ]);

        Loader::getFactionManager()->recalculateSpawnerWorths();
    }

    public function deleteAllForFaction(string $factionId): void {
        foreach ($this->claims as $key => $claim) {
            if ($claim->getFactionId() === $factionId) {
                unset($this->claims[$key]);
            }

        }

        Loader::getDatabase()->executeChange(QueryStmts::FACTION_CLAIM_DELETE_FACTION, [
            "faction_id" => $factionId,
        ]);

        Loader::getFactionManager()->recalculateSpawnerWorths();
    }

    private function makeKey(string $world, int $x, int $z): string {
        return "{$world}:{$x}:{$z}";
    }
}
