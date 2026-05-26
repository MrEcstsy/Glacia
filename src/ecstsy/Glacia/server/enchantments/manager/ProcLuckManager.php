<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\manager;

use ecstsy\Glacia\server\enchantments\CustomEnchantments;
use ecstsy\Glacia\server\enchantments\RuneswellAfterProc;
use ecstsy\Glacia\server\enchantments\RuneswellProcBonus;
use ecstsy\Glacia\server\items\runes\RuneManager;
use pocketmine\entity\Living;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class ProcLuckManager {

    /** @var array<string, array<string, int>> */
    private static array $failStacks = [];
    /** @var list<string> */
    private const DEFAULT_CAPPED_WEAPON_PROCS = [
        "armageddon",
        "blind",
        "brotherhood",
        "cleave",
        "confusion",
        "demonicfinisher",
        "disarmor",
        "doublestrike",
        "execute",
        "fear",
        "hexlock",
        "holy",
        "iceaspect",
        "insomnia",
        "jeopardy",
        "leviathan",
        "lifesteal",
        "pellet",
        "phantom",
        "rage",
        "siegebreaker",
        "silence",
        "slowness",
        "thunderfamine",
        "triggerhappy",
        "vampire",
        "vampiricdevour",
        "virus",
    ];

    public static function roll(Living $player, string $key, int|float $baseChance, int $luckPerMiss = 2, int|float $maxBonus = 25): bool
    {
        $preview = self::preview($player, $key, $baseChance, $luckPerMiss, $maxBonus);
        return (bool) (self::rollDetailedFromPreview($player, $preview)["success"] ?? false);
    }

    /**
     * @return array<string, float|int|bool|string>
     */
    public static function preview(Living $player, string $key, int|float $baseChance, int $luckPerMiss = 2, int|float $maxBonus = 25): array
    {
        $key = strtolower($key);
        $inputChance = max(0.0, min(100.0, (float) $baseChance));
        $additiveBaseChance = EnchantTuningManager::getAdditiveBaseChancePercent($key);
        $baseChanceWithAdditive = max(0.0, min(100.0, $inputChance + $additiveBaseChance));
        $cappedWeaponProc = EnchantTuningManager::usesLegacyWeaponProcCap($key) && self::isCappedWeaponProc($key);
        $baseChanceApplied = $cappedWeaponProc
            ? min($baseChanceWithAdditive, EnchantTuningManager::getWeaponProcChanceCapPercent())
            : $baseChanceWithAdditive;

        $luckEnabled = EnchantTuningManager::isProcLuckEnabled($key, true);
        $luckPerMiss = $luckEnabled ? EnchantTuningManager::getProcLuckPerMiss($key, $luckPerMiss) : 0;
        $maxBonus = $luckEnabled ? EnchantTuningManager::getProcLuckMaxBonus($key, $maxBonus) : 0;

        $misses = 0;
        $failStackBonus = 0.0;
        $runeBonus = 0.0;
        $combatBonus = 0.0;
        $runeswellBonus = 0.0;
        if ($player instanceof Player) {
            $uuid = $player->getUniqueId()->toString();
            $misses = self::$failStacks[$uuid][$key] ?? 0;
            $failStackBonus = min((float) $maxBonus, $misses * $luckPerMiss);
            if (EnchantTuningManager::usesLegacyRuneBonus($key)) {
                $runeBonus = RuneManager::getGlobalProcChanceBonus($player);
            }
            if (EnchantTuningManager::usesLegacyCombatBonus($key)) {
                $combatBonus = EnchantCombatStateManager::getProcBonus($player);
            }
            if (EnchantTuningManager::usesLegacyRuneswellBonus($key)) {
                $runeswellBonus = RuneswellProcBonus::getForRoll($player, $key);
            }
        }

        $finalChance = min(
            100.0,
            $baseChanceApplied + $failStackBonus + $runeBonus + $combatBonus + $runeswellBonus
        );
        if ($cappedWeaponProc) {
            $finalChance = min($finalChance, EnchantTuningManager::getWeaponProcChanceCapPercent());
        }

        return [
            "key" => $key,
            "base_chance_input" => $inputChance,
            "additive_base_chance" => $additiveBaseChance,
            "base_chance" => $baseChanceApplied,
            "luck_enabled" => $luckEnabled,
            "luck_per_miss" => $luckPerMiss,
            "max_bonus" => (float) $maxBonus,
            "misses" => $misses,
            "fail_stack_bonus" => $failStackBonus,
            "rune_bonus" => $runeBonus,
            "combat_bonus" => $combatBonus,
            "runeswell_bonus" => $runeswellBonus,
            "final_chance" => $finalChance,
            "capped_weapon_proc" => $cappedWeaponProc,
        ];
    }

    /**
     * @return array<string, float|int|bool|string>
     */
    public static function rollDetailed(Living $player, string $key, int|float $baseChance, int $luckPerMiss = 2, int|float $maxBonus = 25): array
    {
        return self::rollDetailedFromPreview($player, self::preview($player, $key, $baseChance, $luckPerMiss, $maxBonus));
    }

    /**
     * @param array<string, float|int|bool|string> $preview
     * @return array<string, float|int|bool|string>
     */
    public static function rollDetailedFromPreview(Living $player, array $preview, ?float $forcedRoll = null): array
    {
        $key = strtolower((string) ($preview["key"] ?? ""));
        $chancePercent = max(0.0, min(100.0, (float) ($preview["final_chance"] ?? 0.0)));
        $missesBefore = (int) ($preview["misses"] ?? 0);
        $roll = $forcedRoll ?? ($chancePercent <= 0.0 ? 100.00 : (mt_rand(1, 10000) / 100.0));
        $success = $chancePercent > 0.0 && $roll <= $chancePercent;

        if (!$success) {
            if ($player instanceof Player) {
                $uuid = $player->getUniqueId()->toString();
                self::$failStacks[$uuid][$key] = $missesBefore + 1;
            }

            return [
                "success" => false,
                "reason" => "roll_failed",
                "roll" => $roll,
                "final_chance" => $chancePercent,
                "misses_before" => $missesBefore,
                "misses_after" => $missesBefore + 1,
            ];
        }

        if ($player instanceof Player) {
            if (EnchantTuningManager::usesLegacySoulGemCost($key) && !SoulGemSessionManager::tryConsumeForEnchant($player, $key)) {
                return [
                    "success" => false,
                    "reason" => "soul_gem_blocked",
                    "roll" => $roll,
                    "final_chance" => $chancePercent,
                    "misses_before" => $missesBefore,
                    "misses_after" => $missesBefore,
                ];
            }

            $uuid = $player->getUniqueId()->toString();
            self::$failStacks[$uuid][$key] = 0;
            RuneswellAfterProc::onProcSucceeded($player, $key);
            self::notifyProc($player, $key);
        }

        return [
            "success" => true,
            "reason" => "success",
            "roll" => $roll,
            "final_chance" => $chancePercent,
            "misses_before" => $missesBefore,
            "misses_after" => 0,
        ];
    }

    public static function rollFlat(float $chancePercent, ?Player $notifyPlayer = null, ?string $notifyKey = null): bool
    {
        $chancePercent = max(0.0, min(100.0, $chancePercent));
        if ($chancePercent <= 0.0) {
            return false;
        }
        if ($chancePercent >= 100.0) {
            if ($notifyPlayer !== null && $notifyKey !== null) {
                self::notifyProc($notifyPlayer, $notifyKey);
            }
            return true;
        }

        $success = mt_rand(1, 10000) <= (int) round($chancePercent * 100);
        if ($success && $notifyPlayer !== null && $notifyKey !== null) {
            self::notifyProc($notifyPlayer, $notifyKey);
        }
        return $success;
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

    private static function isCappedWeaponProc(string $key): bool
    {
        $configured = EnchantTuningManager::getBalanceStringList("capped-weapon-procs");
        $pool = $configured !== [] ? $configured : self::DEFAULT_CAPPED_WEAPON_PROCS;
        return in_array($key, $pool, true);
    }

    public static function notifyProc(Player $player, string $key, ?string $message = null): void
    {
        $debug = EnchantTuningManager::getSettingsArray("debug");
        if (!((bool) ($debug["proc-chat"] ?? false))) {
            return;
        }

        $displayName = self::getDisplayName($key);
        $player->sendMessage(C::colorize($message ?? "&8[&dProc&8] &r{$displayName} &7activated."));
    }

    private static function getDisplayName(string $key): string
    {
        $displayName = CustomEnchantments::getEnchantmentDisplayName($key, "&d");
        if (trim($displayName) !== "") {
            return $displayName;
        }

        return "&d" . ucwords(str_replace(["_", "-"], " ", $key));
    }
}
