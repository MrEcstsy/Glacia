<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\manager;

use ecstsy\Glacia\Loader;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\TaskHandler;

final class AngelicHealingManager {

    /** @var array<int, TaskHandler> */
    private static array $active = [];

    /** @var array<int, int> */
    private static array $cooldowns = [];

    public static function hasActive(Player $player): bool {
        return isset(self::$active[$player->getId()]);
    }

    public static function isOnCooldown(Player $player): bool {
        $id = $player->getId();
        return isset(self::$cooldowns[$id]) && self::$cooldowns[$id] > time();
    }

    public static function start(Player $player, int $level): void {
        $id = $player->getId();

        if (self::hasActive($player) || self::isOnCooldown($player)) {
            return;
        }

        $healAmount = match (true) {
            $level >= 4 => 1,
            $level >= 2 => 0.5,
            default => 1
        };

        $maxTicks = 20 * 1.5; // 1.5 seconds
        $interval = 20; // every second
        $elapsed = 0;

        self::$cooldowns[$id] = time() + 8;

        $handler = Loader::getInstance()->getScheduler()->scheduleRepeatingTask(
            new ClosureTask(function() use ($player, $id, $healAmount, $maxTicks, &$elapsed, &$handler): void {
                if (
                    !$player->isOnline() ||
                    $player->isClosed() ||
                    !$player->isAlive()
                ) {
                    unset(self::$active[$id]);
                    $handler?->cancel();
                    return;
                }

                if ($elapsed >= $maxTicks) {
                    unset(self::$active[$id]);
                    $handler?->cancel();
                    return;
                }

                $player->setHealth(min(
                    $player->getMaxHealth(),
                    $player->getHealth() + $healAmount
                ));

                $elapsed += 20;
            }),
            $interval
        );

        self::$active[$id] = $handler;
    }

    public static function clear(Player $player): void {
        $id = $player->getId();

        if (isset(self::$active[$id])) {
            self::$active[$id]->cancel();
            unset(self::$active[$id]);
        }
    }

    public static function clearPlayer(Player $player): void
    {
        $id = $player->getId();
        self::clear($player);
        unset(self::$cooldowns[$id]);
    }
}