<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\factions\relations;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\Glacia\utils\QueryStmts;
use pocketmine\utils\SingletonTrait;

final class RelationManager {
    use SingletonTrait;

    /** @var array<string, array<string, string>> */
    private array $relations = [];

    public function __construct() {
        self::setInstance($this);
        $this->load();
    }

    private function load(): void {
        Loader::getDatabase()->executeSelect(QueryStmts::FACTION_RELATIONS_SELECT, [], function(array $rows): void {
            foreach ($rows as $row) {
                $this->relations[$row["faction_id"]][$row["other_id"]] = $row["relation"];
            }
        });
    }

    public function getRelation(string $f1, string $f2): string {
        return $this->relations[$f1][$f2] ?? "neutral";
    }

    public function setRelation(string $a, string $b, string $relation): void {
        $alreadyExists = isset($this->relations[$a][$b]);
        $this->relations[$a][$b] = $relation;

        $params = [
            "faction_id" => $a,
            "other_id" => $b,
            "relation" => $relation,
        ];

        $stmt = $alreadyExists ? QueryStmts::FACTION_RELATIONS_UPDATE : QueryStmts::FACTION_RELATIONS_CREATE;

        Loader::getDatabase()->executeChange($stmt, $params);
        $this->refreshNametagsFor($a, $b);
    }

    public function clearRelation(string $f1, string $f2): void {
        unset($this->relations[$f1][$f2]);

        Loader::getDatabase()->executeChange(QueryStmts::FACTION_RELATIONS_DELETE, [
            "faction_id" => $f1, 
            "other_id" => $f2
        ]);

        $this->refreshNametagsFor($f1, $f2);
    }

    public function getRelationsFor(string $factionId, ?string $filter = null, bool $bidirectional = false): array {
        $direct = $this->relations[$factionId] ?? [];

        $result = [];

        foreach ($direct as $otherId => $relation) {
            if ($filter === null || $relation === $filter) {
                $result[$otherId] = $relation;
            }
        }

        if ($bidirectional) {
            foreach ($this->relations as $otherFactionId => $relations) {
                if (isset($relations[$factionId])) {
                    $rel = $relations[$factionId];
                    if ($filter === null || $rel === $filter) {
                        $result[$otherFactionId] = $rel;
                    }
                }
            }
        }

        return $result;
    }

    private function refreshNametagsFor(string $factionId1, string $factionId2): void {
        $pm = Loader::getPlayerManager();
        foreach ($pm->getSessions() as $session) {
            $targetFaction = $session->getFactionId();
            if ($targetFaction === $factionId1 || $targetFaction === $factionId2) {
                $targetPlayer = $session->getPlayer();
                if ($targetPlayer !== null && $targetPlayer->isOnline()) {
                    foreach ($pm->getSessions() as $viewerSession) {
                        $viewer = $viewerSession->getPlayer();
                        if ($viewer !== null && $viewer->isOnline()) {
                            GlaciaUtils::updatePlayerNametag($targetPlayer, $viewer);
                        }
                    }
                }
            }
        }
    }

    public function resetSeasonState(): void
    {
        $this->relations = [];
    }
}
