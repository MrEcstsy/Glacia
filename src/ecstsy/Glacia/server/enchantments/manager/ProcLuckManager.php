<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\manager;

use pocketmine\entity\Living;
use pocketmine\player\Player;

final class ProcLuckManager {

    /** @var array<string, array<string, int>> */
    private static array $failStacks = [];

    public static function roll(Living $player, string $key, int $baseChance, int $luckPerMiss = 2, int $maxBonus = 25): bool
    {

        if ($player instanceof Player) {
            $uuid = $player->getUniqueId()->toString();
            $misses = self::$failStacks[$uuid][$key] ?? 0;
            $effectiveChance = min(100, $baseChance + min($maxBonus, $misses * $luckPerMiss));

            if (mt_rand(1, 100) <= $effectiveChance) {
                self::$failStacks[$uuid][$key] = 0;
                return true;
            }

            self::$failStacks[$uuid][$key] = $misses + 1;
            
        }
        return false;
    }

    public static function reset(Living $player, string $key): void
    {
        if ($player instanceof Player) {
            $uuid = $player->getUniqueId()->toString();
            self::$failStacks[$uuid][$key] = 0;
        }

        if ((self::$failStacks[$uuid] ?? []) === []) {
            unset(self::$failStacks[$uuid]);
        }
    }
}
