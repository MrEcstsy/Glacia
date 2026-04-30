<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\manager;

use ecstsy\Glacia\entity\gkits\ForsakenChampion;
use pocketmine\entity\Entity;
use pocketmine\player\Player;

/**
 * Requires a number of successful hits (per attacker and target) before custom weapon enchant
 * procs on {@see EnchantEventRegistry} {@code entity_damage_by_entity} can roll.
 */
final class WeaponProcGate
{
    /** @var array<string, array{hits: int, required: int}> */
    private static array $state = [];

    public static function clearAttacker(Player $player): void
    {
        $prefix = $player->getUniqueId()->toString() . '|';
        foreach (array_keys(self::$state) as $k) {
            if (str_starts_with($k, $prefix)) {
                unset(self::$state[$k]);
            }
        }
    }

    public static function clearForVictim(Entity $victim): void
    {
        $suffix = '|' . self::victimKey($victim);
        foreach (array_keys(self::$state) as $k) {
            if (str_ends_with($k, $suffix)) {
                unset(self::$state[$k]);
            }
        }
    }

    /**
     * Call once per hit when the weapon has at least one custom enchant that uses
     * {@code entity_damage_by_entity}. Returns whether those handlers may run.
     */
    public static function recordAndAllowWeaponProcs(Player|ForsakenChampion $attacker, Entity $victim): bool
    {
        if (!EnchantTuningManager::isWeaponProcGateEnabled()) {
            return true;
        }
        $min = EnchantTuningManager::getWeaponProcGateMinHits();
        $max = EnchantTuningManager::getWeaponProcGateMaxHits();
        if ($min <= 0) {
            return true;
        }
        if ($max < $min) {
            $max = $min;
        }

        $k = self::pairKey($attacker, $victim);
        if (!isset(self::$state[$k])) {
            self::$state[$k] = [
                'hits' => 0,
                'required' => mt_rand($min, $max),
            ];
        }
        self::$state[$k]['hits']++;

        return self::$state[$k]['hits'] >= self::$state[$k]['required'];
    }

    private static function pairKey(Player|ForsakenChampion $attacker, Entity $victim): string
    {
        return self::attackerKey($attacker) . '|' . self::victimKey($victim);
    }

    private static function attackerKey(Player|ForsakenChampion $attacker): string
    {
        if ($attacker instanceof Player) {
            return $attacker->getUniqueId()->toString();
        }

        return 'fc:' . $attacker->getId();
    }

    private static function victimKey(Entity $victim): string
    {
        if ($victim instanceof Player) {
            return 'p:' . $victim->getUniqueId()->toString();
        }

        return 'e:' . $victim->getId();
    }
}
