<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\factions;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\blocks\tiles\SpawnerTile;
use ecstsy\Glacia\server\factions\claims\ClaimManager;
use ecstsy\Glacia\server\factions\member\FactionMember;
use ecstsy\Glacia\utils\QueryStmts;
use pocketmine\Server;
use pocketmine\utils\TextFormat as C;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class Faction {

    /** @var FactionMember[] */
    private array $members = [];
    private int $spawnerWorth = 0;

    public function __construct(
        private string $factionId,
        private string $name,
        private string $description,
        private string $leaderUuid,
        private string $leaderName,
        private int $maxMembers,
        private int $basePowerCap
    )
    {
        $this->loadMembers();
    }

    public function getFactionId(): string {
        return $this->factionId;
    }

    public function getName(): string {
        return $this->name;
    }

    public function setName(string $name): void {
        $this->name = $name;
        $this->updateDb();
    }

    public function getDescription(): string {
        return $this->description;
    }
    
    public function setDescription(string $desc): void {
        $this->description = $desc;
        $this->updateDb();
    }

    public function getLeaderUuid(): string {
        return $this->leaderUuid;
    }

    public function setLeader(UuidInterface $uuid, string $name): void {
        $this->leaderUuid = $uuid->toString();
        $this->leaderName = $name;
        $this->updateDb();
    }

    public function getLeaderName(): string {
        return $this->leaderName;
    }

    public function getMaxMembers(): int {
        return $this->maxMembers;
    }

    public function setMaxMembers(int $max): void {
        $this->maxMembers = $max;
        $this->updateDb();
    }

    public function getOnlineMembers(): array {
        $online = [];
        $pm = Loader::getPlayerManager();
        
        foreach (Loader::getInstance()->getServer()->getOnlinePlayers() as $player) {
            $session = $pm->getSession($player);
            if ($session !== null && $session->getFactionId() === $this->factionId) {
                $online[] = $player;
            }
        }

        return $online;
    }

    public function getClaims(): array {
        $claimManager = ClaimManager::getInstance();
        return $claimManager->getClaimsForFaction($this->factionId);
    }

    public function getClaimCount(): int {
        return count($this->getClaims());
    }

    public function getSpawnerWorth(): int
    {
        return max(0, $this->spawnerWorth);
    }

    public function getFactionValue(): int
    {
        return $this->getSpawnerWorth();
    }

    public function setSpawnerWorth(int $worth): void
    {
        $this->spawnerWorth = max(0, $worth);
        $this->refreshScoreboards();
    }

    public function adjustSpawnerWorth(int $delta): void
    {
        $this->spawnerWorth = max(0, $this->spawnerWorth + $delta);
        $this->refreshScoreboards();
    }

    public function recalculateSpawnerWorth(): void
    {
        $total = 0;

        foreach ($this->getClaims() as $claim) {
            $world = $claim->getWorld();
            if ($world === null) {
                continue;
            }

            if (!$world->isChunkLoaded($claim->getChunkX(), $claim->getChunkZ())) {
                $world->loadChunk($claim->getChunkX(), $claim->getChunkZ());
            }

            $chunk = $world->getChunk($claim->getChunkX(), $claim->getChunkZ());
            if ($chunk === null) {
                continue;
            }

            foreach ($chunk->getTiles() as $tile) {
                if ($tile instanceof SpawnerTile) {
                    $total += $tile->getSpawnerValue() * $tile->getStackCount();
                    $tile->refreshFactionWorthContribution();
                }
            }
        }

        $this->spawnerWorth = max(0, $total);
        $this->refreshScoreboards();
    }

    public function isOverclaimable(): bool {
        return $this->getClaimCount() > $this->getFactionPower();
    }

    public function notifyOverclaimed(): void {
        $message = C::colorize("&r&cYour faction power is too low to hold all of your claims. Some land can now be overclaimed.");
        foreach ($this->getOnlineMembers() as $member) {
            $member->sendMessage($message);
        }
    }

    public function transferLeadership(string $factionId, UuidInterface $newLeaderUuid): void {
        if ($factionId !== $this->factionId) {
            return;
        }

        $oldLeaderUuid = $this->leaderUuid;
        $newLeader = Loader::getPlayerManager()->getSessionByUuid($newLeaderUuid);

        $this->setLeader($newLeaderUuid, $newLeader?->getUsername() ?? "Unknown");
        $this->setMemberRole($this->factionId, Uuid::fromString($oldLeaderUuid), "officer");
        $this->setMemberRole($this->factionId, $newLeaderUuid, "leader");
        $this->normalizeLeaderMembers($newLeaderUuid->toString());
    }


    private function loadMembers(): void {
        Loader::getDatabase()->executeSelect(QueryStmts::FACTION_MEMBERS_SELECT_BY_FACTION, [
            "faction_id" => $this->factionId
        ], function(array $rows): void {
            // Avoid wiping in-memory members before the first DB write wins (async select can
            // finish with 0 rows while addMember() has already appended the leader).
            if ($rows === []) {
                return;
            }
            $this->members = [];
            foreach ($rows as $row) {
                $this->members[] = new FactionMember(
                    Uuid::fromString($row['member_uuid']),
                    $row['role_key'],
                    (int)($row['joined_at'] ?? time())
                );
            }
            $this->normalizeLeaderMembers($this->leaderUuid);
        });
    }

    /** @return FactionMember[] */
    public function getMembers(): array {
        return $this->members;
    }

    private function findMemberIndexByUuid(UuidInterface $uuid): ?int {
        foreach ($this->members as $i => $member) {
            if ($member->getUuid()->toString() === $uuid->toString()) return $i;
        }
        return null;
    }

    public function addMember(string $factionId, UuidInterface $memberUuid, string $roleKey = "recruit"): void {
        $joinedAt = time();

        $idx = $this->findMemberIndexByUuid($memberUuid);
        if ($idx !== null) {
            if ($this->members[$idx]->getRole() !== $roleKey) {
                Loader::getDatabase()->executeChange(QueryStmts::FACTION_MEMBERS_UPDATE, [
                    "faction_id" => $this->factionId,
                    "member_uuid" => $memberUuid->toString(),
                    "role_key" => $roleKey,
                    "joined_at" => $this->members[$idx]->getJoinedAt(),
                ]);
                $this->members[$idx]->setRole($roleKey);
            }
            return;
        }

        Loader::getDatabase()->executeChange(QueryStmts::FACTION_MEMBERS_CREATE, [
            "faction_id" => $this->factionId,
            "member_uuid" => $memberUuid->toString(),
            "role_key" => $roleKey,
            "joined_at" => $joinedAt,
        ]);

        $this->members[] = new FactionMember($memberUuid, $roleKey, $joinedAt);

        $session = Loader::getPlayerManager()->getSessionByUuid($memberUuid);
        if ($session !== null) {
            $session->setFactionId($this->factionId);
            $session->setFactionRole($roleKey);
        }

        $this->refreshScoreboards();
    }

    public function kickMember(string $factionId, UuidInterface $memberUuid): void {
        Loader::getDatabase()->executeChange(QueryStmts::FACTION_MEMBERS_DELETE, [
            "faction_id"   => $this->factionId,
            "member_uuid"  => $memberUuid->toString(),
        ]);

        $idx = $this->findMemberIndexByUuid($memberUuid);
        if ($idx !== null) array_splice($this->members, $idx, 1);

        $session = Loader::getPlayerManager()->getSessionByUuid($memberUuid);
        if ($session !== null) {
            $session->setFactionId(null);
            $session->setFactionRole(null);
        }

        $this->refreshScoreboards();
    }

    public function setMemberRole(string $factionId, UuidInterface $memberUuid, string $roleKey): void {
        $idx = $this->findMemberIndexByUuid($memberUuid);
        if ($idx === null) {
            return;
        }

        Loader::getDatabase()->executeChange(QueryStmts::FACTION_MEMBERS_UPDATE, [
            "faction_id" => $this->factionId,
            "member_uuid" => $memberUuid->toString(),
            "role_key" => $roleKey,
            "joined_at" => $this->members[$idx]->getJoinedAt(),
        ]);

        $this->members[$idx]->setRole($roleKey);

        $session = Loader::getPlayerManager()->getSessionByUuid($memberUuid);
        if ($session !== null) {
            $session->setFactionRole($roleKey);
        }

        if ($roleKey === "leader") {
            $leaderName = $session?->getUsername()
                ?? Server::getInstance()->getPlayerByUUID($memberUuid)?->getName()
                ?? $this->leaderName;
            $this->setLeader($memberUuid, $leaderName);
            $this->normalizeLeaderMembers($memberUuid->toString());
        }

        $this->refreshScoreboards();
    }

    public function getFactionPower(): int {
        $pm = Loader::getPlayerManager();
        $total = 0;

        foreach ($this->members as $member) {
            $uuid = $member->getUuid();
            $session = $pm->getSessionByUuid($uuid);
            if ($session === null) {
                continue;
            }
            $power = (int) $session->getPower();
            $total += $power;
        }

        $cap = $this->getMaxPower();
        return min($total, $cap);
    }

    public function getMaxPower(): int {
        return $this->basePowerCap;
    }

    public function setMaxPower(int $power): void {
        $this->basePowerCap = $power;
        $this->updateDb();
    }

    public function getMaxClaims(): int {
        $powerPerClaim = 25;
        return intdiv($this->getFactionPower(), $powerPerClaim);
    }

    private function updateDb(): void {
        Loader::getDatabase()->executeChange(QueryStmts::FACTIONS_UPDATE, [
            "faction_id" => $this->factionId,
            "name" => $this->name,
            "description" => $this->description,
            "leader_uuid" => $this->leaderUuid,
            "leader_name" => $this->leaderName,
            "max_members" => $this->maxMembers,
            "base_power_cap" => $this->basePowerCap
        ]);
    }

    public function refreshScoreboards(): void
    {
        foreach ($this->getOnlineMembers() as $member) {
            \ecstsy\Glacia\server\scoreboard\ScoreboardHelper::updateFactionLines($member);
        }
    }

    private function normalizeLeaderMembers(string $leaderUuid): void
    {
        foreach ($this->members as $member) {
            $memberUuid = $member->getUuid()->toString();
            if ($memberUuid === $leaderUuid || $member->getRole() !== "leader") {
                continue;
            }

            Loader::getDatabase()->executeChange(QueryStmts::FACTION_MEMBERS_UPDATE, [
                "faction_id" => $this->factionId,
                "member_uuid" => $memberUuid,
                "role_key" => "officer",
                "joined_at" => $member->getJoinedAt(),
            ]);
            $member->setRole("officer");

            $session = Loader::getPlayerManager()->getSessionByUuid($member->getUuid());
            if ($session !== null) {
                $session->setFactionRole("officer");
            }
        }
    }
}
