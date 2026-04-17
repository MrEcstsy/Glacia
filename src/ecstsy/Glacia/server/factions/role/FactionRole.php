<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\factions\role;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\FactionPermissions;
use ecstsy\Glacia\utils\QueryStmts;

final class FactionRole
{
    public function __construct(
        private string $factionId,
        private string $roleKey,
        private array $perms,
    )
    {
        
    }

    public function getRoleKey(): string {
        return $this->roleKey;
    }

    public function hasPermission(string $perm): bool {
        return !empty($this->perms[$perm]);
    }

    public function setPermission(string $perm, bool $value): void {
        $this->perms[$perm] = $value;
        Loader::getDatabase()->executeChange(QueryStmts::FACTION_ROLES_UPDATE, [
            "faction_id" => $this->factionId,
            "role_key" => $this->roleKey,
            "kill_mobs" => (int)($this->perms[FactionPermissions::KILL_MOBS] ?? 0),
            "open_containers" => (int)($this->perms[FactionPermissions::OPEN_CONTAINERS] ?? 0),
            "interact_doors" => (int)($this->perms[FactionPermissions::OPEN_DOORS] ?? 0),
            "pickup" => (int)($this->perms[FactionPermissions::PICKUP] ?? 0),
            "break" => (int)($this->perms[FactionPermissions::BREAK] ?? 0),
            "place" => (int)($this->perms[FactionPermissions::PLACE] ?? 0),
            "use_buckets" => (int)($this->perms[FactionPermissions::USE_BUCKETS] ?? 0),
            "change_permissions" => (int)($this->perms[FactionPermissions::EDIT_PERMISSIONS] ?? 0),
            "demote_users" => (int)($this->perms[FactionPermissions::DEMOTE_USERS] ?? 0),
            "promote_users" => (int)($this->perms[FactionPermissions::PROMOTE_USERS] ?? 0),
            "kick_users" => (int)($this->perms[FactionPermissions::KICK_USERS] ?? 0),
            "use_redstone" => (int)($this->perms[FactionPermissions::USE_REDSTONE] ?? 0),
            "rename_faction" => (int)($this->perms[FactionPermissions::RENAME_FACTION] ?? 0),
            "faction_home" => (int)($this->perms[FactionPermissions::FACTION_HOME] ?? 0),
            "break_spawners" => (int)($this->perms[FactionPermissions::BREAK_SPAWNERS] ?? 0),
            "change_settings" => (int)($this->perms[FactionPermissions::EDIT_SETTINGS] ?? 0),
            "manage_warps" => (int)($this->perms[FactionPermissions::MANAGE_WARPS] ?? 0),
            "change_description" => (int)($this->perms[FactionPermissions::EDIT_SIGNS] ?? 0),
            "invite_players" => (int)($this->perms[FactionPermissions::INVITE_USERS] ?? 0),
        ]);
    }

    public function toRow(): array {
        return array_merge([
            "faction_id" => $this->factionId,
            "role_key"   => $this->roleKey,
        ], $this->perms);
    }
}