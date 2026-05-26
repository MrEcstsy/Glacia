<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\staff;

use pocketmine\player\Player;

final class HiddenStaffTargeting
{
    public static function isUntargetable(Player $player): bool
    {
        return StaffModeManager::isInStaffMode($player) || VanishManager::isVanished($player);
    }
}
