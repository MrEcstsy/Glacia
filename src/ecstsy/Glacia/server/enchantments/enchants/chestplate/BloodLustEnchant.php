<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\chestplate;

use ecstsy\Glacia\Loader;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;

final class BloodLustEnchant {

    public static function trigger(Player $owner, int $level): void {
        $heal = 0.5 + (0.5 * min(6, $level));

        Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(
            function() use ($owner, $heal): void {
                if (!$owner->isAlive()) {
                    return;
                }

                $owner->setHealth(min($owner->getMaxHealth(), $owner->getHealth() + $heal));
            }
        ), 1);
    }
}