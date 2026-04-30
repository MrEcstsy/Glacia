<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\world\audit;

use ecstsy\Glacia\utils\AuditFileLogger;

final class WorldAuditLogger {

    /**
     * @param array<string, scalar|null> $data
     */
    public static function log(string $action, array $data): void {
        AuditFileLogger::staff("World " . $action, $data);
    }
}
