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

    public static function secondsRemaining(Player $player): int
    {
        $id = $player->getId();
        return max(0, (self::$cooldowns[$id] ?? 0) - time());
    }

    public static function start(Player $player, int $level, float $healAmount, int $durationTicks, int $intervalTicks, int $cooldownSeconds): void {
        $id = $player->getId();

        if (
            self::hasActive($player) ||
            self::isOnCooldown($player) ||
            AdvancedEnchantState::isAnatomyBlocked($player) ||
            EnchantCombatStateManager::areCustomEnchantsDisabled($player)
        ) {
            return;
        }

        $healAmount = max(0.0, $healAmount);
        $maxTicks = max(1, $durationTicks);
        $interval = max(1, $intervalTicks);
        $elapsed = 0;

        self::$cooldowns[$id] = time() + max(0, $cooldownSeconds);

        $handler = Loader::getInstance()->getScheduler()->scheduleRepeatingTask(
            new ClosureTask(function() use ($player, $id, $level, $healAmount, $maxTicks, $interval, &$elapsed, &$handler): void {
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
                if (
                    AdvancedEnchantState::isAnatomyBlocked($player) ||
                    EnchantCombatStateManager::areCustomEnchantsDisabled($player)
                ) {
                    unset(self::$active[$id]);
                    $handler?->cancel();
                    return;
                }

                $player->setHealth(min(
                    $player->getMaxHealth(),
                    $player->getHealth() + $healAmount
                ));
                EnchantDebugManager::logPassiveValues($player, "angelic", $level, [
                    "pulse_heal" => $healAmount,
                    "health_after" => $player->getHealth(),
                ]);

                $elapsed += $interval;
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
