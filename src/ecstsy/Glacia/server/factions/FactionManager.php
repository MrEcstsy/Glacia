<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\factions;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\factions\claims\ClaimManager;
use ecstsy\Glacia\server\factions\relations\RelationManager;
use ecstsy\Glacia\utils\QueryStmts;
use pocketmine\Server;
use pocketmine\utils\SingletonTrait;
use Ramsey\Uuid\Uuid;

final class FactionManager {
    use SingletonTrait;

    /** @var Faction[] */
    private array $factions = [];

    public function __construct() {
        self::setInstance($this);
        $this->loadFactions();
    }

    private function loadFactions(): void {
        Loader::getDatabase()->executeSelect(QueryStmts::FACTIONS_SELECT, [], function(array $rows): void {
            foreach ($rows as $row) {
                $this->factions[$row["faction_id"]] = new Faction(
                    $row["faction_id"],
                    $row["name"],
                    $row["description"],
                    $row["leader_uuid"],
                    $row["leader_name"],
                    $row["max_members"],
                    $row["base_power_cap"]
                );
            }
        });

        
    }

    public function getFaction(string $id): ?Faction {
        return $this->factions[$id] ?? null;
    }

    /**
     * @return Faction[]
     */
    public function getAllFactions(): array {
        return $this->factions;
    }

    public function createFaction(string $id, string $name, string $description, string $leaderUuid, string $leaderName, array $rolePermissions = [], int $maxMembers = 5): ?Faction {
        foreach($this->factions as $existing) {
            if (strtolower($existing->getName()) === strtolower($name)) {
                return null;
            }
        }

        Loader::getDatabase()->executeInsert(QueryStmts::FACTIONS_CREATE, [
            "faction_id" => $id,
            "name" => $name,
            "description" => $description,
            "leader_uuid" => $leaderUuid,
            "leader_name" => $leaderName,
            "max_members" => $maxMembers,
            "base_power_cap" => 2500
        ]);

        $faction = new Faction(
            $id,
            $name,
            $description,
            $leaderUuid,
            $leaderName,
            $maxMembers,
            2500
        );

        $this->factions[$id] = $faction;

        $faction->addMember($id, Uuid::fromString($leaderUuid), "leader");
        Loader::getFactionUpgradeManager()->ensureFaction($faction);
        return $faction;
    }

    public function getFactionByName(string $name): ?Faction {
        foreach ($this->factions as $faction) {
            if (strtolower($faction->getName()) === strtolower($name)) {
                return $faction;
            }
        }
        return null;
    }

    public function getFactionById(string $id): ?Faction {
        return $this->factions[$id] ?? null;
    }

    public function deleteFaction(string $id): void {
        unset($this->factions[$id]);

        Loader::getDatabase()->executeChange(QueryStmts::FACTIONS_DELETE, [
            "faction_id" => $id
        ]);
    }

    public function disbandFaction(string $id): ?Faction
    {
        $faction = $this->getFaction($id);
        if ($faction === null) {
            return null;
        }

        foreach (Loader::getPlayerManager()->getSessions() as $session) {
            if ($session !== null && $session->getFactionId() === $id) {
                $session->joinFaction(null, null);
            }
        }

        $relations = RelationManager::getInstance()->getRelationsFor($id, null, true);
        foreach (array_keys($relations) as $otherId) {
            RelationManager::getInstance()->clearRelation($id, $otherId);
            RelationManager::getInstance()->clearRelation($otherId, $id);
        }

        ClaimManager::getInstance()->deleteAllForFaction($id);

        $db = Loader::getDatabase();
        $db->executeChange(QueryStmts::FACTION_HOME_DELETE, ["faction_id" => $id]);
        $db->executeChange(QueryStmts::FACTION_ROLES_DELETE, ["faction_id" => $id]);

        foreach ($faction->getMembers() as $member) {
            $db->executeChange(QueryStmts::FACTION_MEMBERS_DELETE, [
                "faction_id" => $id,
                "member_uuid" => $member->getUuid()->toString()
            ]);
        }

        Loader::getFactionUpgradeManager()->removeFaction($id);
        $this->deleteFaction($id);
        return $faction;
    }

    public function recalculateSpawnerWorths(): void
    {
        foreach ($this->factions as $faction) {
            $faction->setSpawnerWorth(0);
        }

        foreach ($this->factions as $faction) {
            $faction->recalculateSpawnerWorth();
        }
    }
}
