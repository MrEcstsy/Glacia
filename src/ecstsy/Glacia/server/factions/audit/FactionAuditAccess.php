<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\factions\audit;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\player\GlaciaPlayer;
use ecstsy\Glacia\utils\FactionPermissions;

/**
 * Who may use /f audit: faction leader & officer always; other roles via view-audit-log on their role row.
 */
final class FactionAuditAccess {

    public static function canView(?GlaciaPlayer $session): bool {
        if ($session === null) {
            return false;
        }
        if ($session->getFactionId() === null || $session->getFactionRole() === null) {
            return false;
        }

        $rk = strtolower(trim((string) $session->getFactionRole()));
        if ($rk === "leader" || $rk === "officer") {
            return true;
        }

        $role = Loader::getFactionRoleManager()->getRole($session->getFactionId(), $rk);
        if ($role !== null && $role->hasPermission(FactionPermissions::VIEW_AUDIT_LOG)) {
            return true;
        }

        return false;
    }
}
