<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\factions;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\factions\claims\ClaimManager;
use ecstsy\Glacia\server\factions\member\FactionMember;
use ecstsy\Glacia\utils\QueryStmts;
use pocketmine\Server;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class Faction {

    /** @var FactionMember[] */
    private array $members = [];

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

    public function isOverclaimable(): bool {
        return $this->getClaimCount() > $this->getFactionPower();
    }

    public function notifyOverclaimed(): void {
        $message = "§cWarning: Your faction's power is too low to hold all your claims! Some land can be overclaimed!";
        foreach ($this->getOnlineMembers() as $member) {
            $member->sendMessage($message);
        }
    }

    public function transferLeadership(string $factionId, UuidInterface $newLeaderUuid): void {
        $faction = FactionManager::getInstance()->getFaction($factionId);
        if ($faction === null) return;

        $oldLeaderUuid = $faction->getLeaderUuid();
        $newLeader    = Loader::getPlayerManager()->getSessionByUuid($newLeaderUuid);

        $faction->setLeader($newLeaderUuid, $newLeader?->getUsername() ?? "Unknown");

        $this->setMemberRole($factionId, Uuid::fromString($oldLeaderUuid), "officer");
        $this->setMemberRole($factionId, $newLeaderUuid, "leader");
    }


    private function loadMembers(): void {
        Loader::getDatabase()->executeSelect(QueryStmts::FACTION_MEMBERS_SELECT_BY_FACTION, [
            "faction_id" => $this->factionId
        ], function(array $rows): void {
            $this->members = [];
            foreach ($rows as $row) {
                $this->members[] = new FactionMember(
                    Uuid::fromString($row['member_uuid']),
                    $row['role_key'],
                    (int)($row['joined_at'] ?? time())
                );
            }
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
        $idx = $this->findMemberIndexByUuid($memberUuid);
        if ($idx !== null) {
            if ($this->members[$idx]->getRole() !== $roleKey) {
                Loader::getDatabase()->executeChange(QueryStmts::FACTION_MEMBERS_UPDATE, [
                    "faction_id" => $factionId,
                    "member_uuid" => $memberUuid->toString(),
                    "role_key" => $roleKey,
                ]);
                $this->members[$idx]->setRole($roleKey);
            }
            return;
        }

        Loader::getDatabase()->executeChange(QueryStmts::FACTION_MEMBERS_CREATE, [
            "faction_id" => $factionId,
            "member_uuid" => $memberUuid->toString(),
            "role_key" => $roleKey,
            "joined_at" => time(),
        ]);

        $this->members[] = new FactionMember($memberUuid, $roleKey, time());

        $session = Loader::getPlayerManager()->getSessionByUuid($memberUuid);
        if ($session !== null) {
            $session->setFactionId($factionId);
            $session->setFactionRole($roleKey);
        }
    }

    public function kickMember(string $factionId, UuidInterface $memberUuid): void {
        Loader::getDatabase()->executeChange(QueryStmts::FACTION_MEMBERS_DELETE, [
            "faction_id"   => $factionId,
            "member_uuid"  => $memberUuid->toString(),
        ]);

        $idx = $this->findMemberIndexByUuid($memberUuid);
        if ($idx !== null) array_splice($this->members, $idx, 1);

        $session = Loader::getPlayerManager()->getSessionByUuid($memberUuid);
        if ($session !== null) {
            $session->setFactionId(null);
            $session->setFactionRole(null);
        }
    }

    public function setMemberRole(string $factionId, UuidInterface $memberUuid, string $roleKey): void {
        Loader::getDatabase()->executeChange(QueryStmts::FACTION_MEMBERS_UPDATE, [
            "faction_id" => $factionId,
            "member_uuid" => $memberUuid->toString(),
            "role_key" => $roleKey,
        ]);

        $idx = $this->findMemberIndexByUuid($memberUuid);
        if ($idx !== null) {
            $this->members[$idx]->setRole($roleKey);
        }

        $session = Loader::getPlayerManager()->getSessionByUuid($memberUuid);
        if ($session !== null) {
            $session->setFactionRole($roleKey);
        }
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
}