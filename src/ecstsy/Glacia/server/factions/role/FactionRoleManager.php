<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\factions\role;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\FactionPermissions;
use ecstsy\Glacia\utils\QueryStmts;
use pocketmine\utils\SingletonTrait;

final class FactionRoleManager {
    use SingletonTrait;

    /** @var array<string, array<string, FactionRole>> */
    private array $roles = []; 

    public function __construct() {
        self::setInstance($this);
        $this->load();
    }

    private function load(): void {
        Loader::getDatabase()->executeSelect(QueryStmts::FACTION_ROLES_SELECT, [], function(array $rows): void {
            foreach($rows as $r) {
                $perms = [];
                foreach(FactionPermissions::getAllPermissions() as $perm) {
                    if ($perm === FactionPermissions::VIEW_AUDIT_LOG) {
                        $perms[$perm] = !empty((int) ($r["view_audit_log"] ?? 0));
                        continue;
                    }
                    $col = str_replace('-', '_', $perm);
                    $perms[$perm] = (bool)$r["can_{$col}"] ?? false;
                }
                $this->roles[$r["faction_id"]][$r["role_key"]] =
                    new FactionRole($r["faction_id"], $r["role_key"], $perms);
            }
        });
    }

    public function getRole(string $factionId, string $roleKey): ?FactionRole {
        return $this->roles[$factionId][$roleKey] ?? null;
    }

    /** @return FactionRole[] */
    public function getAllRoles(string $factionId): array {
        return $this->roles[$factionId] ?? [];
    }

    public function createRole(string $factionId, string $roleKey, array $defaults): FactionRole {
        Loader::getDatabase()->executeInsert(QueryStmts::FACTION_ROLES_CREATE, [
            "faction_id" => $factionId,
            "role_key" => $roleKey,
            "kill_mobs" => (int)($defaults[FactionPermissions::KILL_MOBS] ?? 0),
            "open_containers" => (int)($defaults[FactionPermissions::OPEN_CONTAINERS] ?? 0),
            "interact_doors" => (int)($defaults[FactionPermissions::OPEN_DOORS] ?? 0),
            "pickup" => (int)($defaults[FactionPermissions::PICKUP] ?? 0),
            "break" => (int)($defaults[FactionPermissions::BREAK] ?? 0),
            "place" => (int)($defaults[FactionPermissions::PLACE] ?? 0),
            "use_buckets" => (int)($defaults[FactionPermissions::USE_BUCKETS] ?? 0),
            "change_permissions" => (int)($defaults[FactionPermissions::EDIT_PERMISSIONS] ?? 0),
            "demote_users" => (int)($defaults[FactionPermissions::DEMOTE_USERS] ?? 0),
            "promote_users" => (int)($defaults[FactionPermissions::PROMOTE_USERS] ?? 0),
            "kick_users" => (int)($defaults[FactionPermissions::KICK_USERS] ?? 0),
            "use_redstone" => (int)($defaults[FactionPermissions::USE_REDSTONE] ?? 0),
            "rename_faction" => (int)($defaults[FactionPermissions::RENAME_FACTION] ?? 0),
            "faction_home" => (int)($defaults[FactionPermissions::FACTION_HOME] ?? 0),
            "break_spawners" => (int)($defaults[FactionPermissions::BREAK_SPAWNERS] ?? 0),
            "change_settings" => (int)($defaults[FactionPermissions::EDIT_SETTINGS] ?? 0),
            "manage_warps" => (int)($defaults[FactionPermissions::MANAGE_WARPS] ?? 0),
            "change_description" => (int)($defaults[FactionPermissions::EDIT_SIGNS] ?? 0),
            "invite_players" => (int)($defaults[FactionPermissions::INVITE_USERS] ?? 0),
            "view_audit_log" => (int)($defaults[FactionPermissions::VIEW_AUDIT_LOG] ?? 0),
        ]);

        $role = new FactionRole($factionId, $roleKey, $defaults);
        
        $this->roles[$factionId][$roleKey] = $role;
        return $role;
    }
}
