<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\manager;

use ecstsy\Glacia\server\enchantments\RuneswellAfterProc;
use ecstsy\Glacia\server\enchantments\RuneswellProcBonus;
use ecstsy\Glacia\server\items\runes\RuneManager;
use pocketmine\entity\Living;
use pocketmine\player\Player;

final class ProcLuckManager {

    /** @var array<string, array<string, int>> */
    private static array $failStacks = [];

    public static function roll(Living $player, string $key, int $baseChance, int $luckPerMiss = 2, int $maxBonus = 25): bool
    {
        $key = strtolower($key);
        $baseChance = EnchantTuningManager::getProcBaseChance($key, $baseChance);
        $luckEnabled = EnchantTuningManager::isProcLuckEnabled($key, true);
        $luckPerMiss = $luckEnabled ? EnchantTuningManager::getProcLuckPerMiss($key, $luckPerMiss) : 0;
        $maxBonus = $luckEnabled ? EnchantTuningManager::getProcLuckMaxBonus($key, $maxBonus) : 0;

        if ($player instanceof Player) {
            $uuid = $player->getUniqueId()->toString();
            $misses = self::$failStacks[$uuid][$key] ?? 0;
            $effectiveChance = min(
                100,
                $baseChance +
                min($maxBonus, $misses * $luckPerMiss) +
                RuneManager::getGlobalProcChanceBonus($player) +
                EnchantCombatStateManager::getProcBonus($player) +
                RuneswellProcBonus::getForRoll($player, $key)
            );

            if (mt_rand(1, 100) <= $effectiveChance) {
                self::$failStacks[$uuid][$key] = 0;
                RuneswellAfterProc::onProcSucceeded($player, $key);
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
            self::$failStacks[$uuid][strtolower($key)] = 0;
            if ((self::$failStacks[$uuid] ?? []) === []) {
                unset(self::$failStacks[$uuid]);
            }
        }
    }

    public static function clearPlayer(Player $player): void
    {
        unset(self::$failStacks[$player->getUniqueId()->toString()]);
    }
}
