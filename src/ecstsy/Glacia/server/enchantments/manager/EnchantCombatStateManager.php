<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\manager;

use ecstsy\Glacia\server\enchantments\essence\EnchantEssenceManager;
use pocketmine\player\Player;

final class EnchantCombatStateManager
{
    /** @var array<string, int> */
    private static array $potionDisabledUntil = [];
    /** @var array<string, int> */
    private static array $petDisabledUntil = [];
    /** @var array<string, int> */
    private static array $customEnchantDisabledUntil = [];
    /** @var array<string, int> */
    private static array $procBonusUntil = [];
    /** @var array<string, int> */
    private static array $procBonusAmount = [];
    /** @var array<string, int> */
    private static array $plagueWeakenedUntil = [];
    /** @var array<string, int> */
    private static array $siegebreakerUntil = [];
    /** @var array<string, int> */
    private static array $flourishActiveUntil = [];
    /** @var array<string, array<string, int>> */
    private static array $recentAttackers = [];

    public static function disablePotions(Player $player, int $seconds): void
    {
        self::$potionDisabledUntil[$player->getUniqueId()->toString()] = time() + max(1, $seconds);
    }

    public static function arePotionsDisabled(Player $player): bool
    {
        return self::isActive(self::$potionDisabledUntil, $player);
    }

    public static function disablePets(Player $player, int $seconds): void
    {
        self::$petDisabledUntil[$player->getUniqueId()->toString()] = time() + max(1, $seconds);
    }

    public static function arePetsDisabled(Player $player): bool
    {
        return self::isActive(self::$petDisabledUntil, $player);
    }

    public static function disableCustomEnchants(Player $player, int $seconds): void
    {
        self::$customEnchantDisabledUntil[$player->getUniqueId()->toString()] = time() + max(1, $seconds);
    }

    public static function areCustomEnchantsDisabled(Player $player): bool
    {
        return self::isActive(self::$customEnchantDisabledUntil, $player);
    }

    public static function grantProcBonus(Player $player, int $seconds, int $bonusPercent = 5): void
    {
        $key = $player->getUniqueId()->toString();
        self::$procBonusUntil[$key] = time() + max(1, $seconds);
        self::$procBonusAmount[$key] = max(0, $bonusPercent);
    }

    public static function getProcBonus(Player $player): int
    {
        $key = $player->getUniqueId()->toString();
        if (!self::isActive(self::$procBonusUntil, $player)) {
            unset(self::$procBonusAmount[$key]);
            return 0;
        }

        return max(0, (int) (self::$procBonusAmount[$key] ?? 5));
    }

    public static function applyPlagueWeaken(Player $player, int $seconds): void
    {
        self::$plagueWeakenedUntil[$player->getUniqueId()->toString()] = time() + max(1, $seconds);
    }

    public static function isPlagueWeakened(Player $player): bool
    {
        return self::isActive(self::$plagueWeakenedUntil, $player);
    }

    public static function activateSiegebreaker(Player $player, int $seconds): void
    {
        self::$siegebreakerUntil[$player->getUniqueId()->toString()] = time() + max(1, $seconds);
    }

    public static function isSiegebreakerActive(Player $player): bool
    {
        return self::isActive(self::$siegebreakerUntil, $player);
    }

    public static function registerIncomingAttacker(Player $victim, Player $attacker, int $windowSeconds = 6): void
    {
        $victimKey = $victim->getUniqueId()->toString();
        $attackerKey = $attacker->getUniqueId()->toString();
        self::$recentAttackers[$victimKey][$attackerKey] = time() + max(1, $windowSeconds);
        self::$flourishActiveUntil[$victimKey] = time() + max(1, $windowSeconds);
    }

    public static function getFlourishReductionPercent(Player $victim): float
    {
        $key = $victim->getUniqueId()->toString();
        if (!self::isActive(self::$flourishActiveUntil, $victim)) {
            return 0.0;
        }

        $now = time();
        $activeAttackers = 0;
        foreach ((self::$recentAttackers[$key] ?? []) as $attackerKey => $expiresAt) {
            if ($expiresAt > $now) {
                $activeAttackers++;
            } else {
                unset(self::$recentAttackers[$key][$attackerKey]);
            }
        }

        $base = min(70.0, $activeAttackers * 7.0);
        $perExtra = (float) EnchantEssenceManager::resolveArmorValue($victim, "flourish", "extra-reduction-percent-per-additional-attacker", 0.0);
        if ($perExtra > 0.0 && $activeAttackers >= 2) {
            $base += ($activeAttackers - 1) * $perExtra;
        }

        return min(70.0, $base);
    }

    private static function isActive(array &$store, Player $player): bool
    {
        $key = $player->getUniqueId()->toString();
        $expiresAt = $store[$key] ?? 0;
        if ($expiresAt <= time()) {
            unset($store[$key]);
            return false;
        }

        return true;
    }

    public static function countRecentAttackers(Player $victim): int
    {
        $key = $victim->getUniqueId()->toString();
        $now = time();
        $n = 0;
        foreach ((self::$recentAttackers[$key] ?? []) as $ak => $expiresAt) {
            if ($expiresAt > $now) {
                $n++;
            } else {
                unset(self::$recentAttackers[$key][$ak]);
            }
        }
        if ((self::$recentAttackers[$key] ?? []) === []) {
            unset(self::$recentAttackers[$key]);
        }
        return $n;
    }

}
