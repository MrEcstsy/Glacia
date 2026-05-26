<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils;

use pocketmine\player\Player;

final class PermissionTierUtils
{
    private function __construct()
    {
    }

    public static function getHighestIntPermission(Player $player, string $prefix, int $default = 0, int $maxScan = 100): int
    {
        $highest = $default;
        for ($i = 0; $i <= $maxScan; $i++) {
            if ($player->hasPermission($prefix . $i)) {
                $highest = max($highest, $i);
            }
        }

        return $highest;
    }
}
