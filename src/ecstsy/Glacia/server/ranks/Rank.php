<?php
declare(strict_types=1);

namespace ecstsy\Glacia\server\ranks;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\ranks\RankManager;
use ecstsy\Glacia\utils\QueryStmts;

final class Rank {

    private array $ownPerms;

    public function __construct(
        private string $key,
        private string $displayName,
        private ?string $parentKey,
        private string $chatFormat,
        private string $nametagFormat,
        array $ownPerms
    ){
        $this->ownPerms = $ownPerms;
    }

    public function getKey(): string { return $this->key; }
    public function getDisplayName(): string { return $this->displayName; }
    public function getParentKey(): ?string { return $this->parentKey; }
    public function getChatFormat(): string { return $this->chatFormat; }
    public function getNametagFormat(): string { return $this->nametagFormat; }
    public function getOwnPermissions(): array { return array_values(array_unique($this->ownPerms)); }

    public function addOwnPermission(string $perm): void {
        if (!in_array($perm, $this->ownPerms, true)) {
            $this->ownPerms[] = $perm;
        }
    }

    public function removeOwnPermission(string $perm): void {
        $this->ownPerms = array_values(array_filter(
            $this->ownPerms,
            static fn(string $existing): bool => $existing !== $perm
        ));
    }

    public function setDisplayName(string $displayName): void {
        $this->displayName = $displayName;
        $this->updateDb();
    }

    /**
     * @return string[] 
     */
    public function getPermissions(): array {
        $seen = [];
        return $this->collectPermissions($seen);
    }

    private function collectPermissions(array &$seen): array {
        if (in_array($this->key, $seen, true)) {
            return [];
        }
        $seen[] = $this->key;
        $perms = $this->ownPerms;
        if ($this->parentKey !== null) {
            $parent = RankManager::getInstance()->getRank($this->parentKey);
            if ($parent !== null) {
                $perms = array_merge($perms, $parent->collectPermissions($seen));
            }
        }
        return array_unique($perms);
    }

    public function setChatFormat(string $format): void {
        $this->chatFormat = $format;
        $this->updateDb();
    }

    public function setNametagFormat(string $format): void {
        $this->nametagFormat = $format;
        $this->updateDb();
    }

    public function setParentKey(?string $parentRank): void {
        $this->parentKey = $parentRank;
        $this->updateDb();
    }

    private function updateDb(): void {
        Loader::getDatabase()->executeChange(QueryStmts::PLAYER_RANK_UPDATE, [
            "rank_key" => $this->key,
            "display_name" => $this->displayName,
            "parent_rank" => $this->parentKey,
            "chat_format" => $this->chatFormat,
            "nametag_format" => $this->nametagFormat
        ]);
    }
}
