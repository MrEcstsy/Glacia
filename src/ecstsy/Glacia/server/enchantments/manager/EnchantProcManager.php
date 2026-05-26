<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\manager;

use pocketmine\entity\Living;

final class EnchantProcManager
{
    public static function attempt(
        Living $owner,
        string $enchantId,
        int $level,
        float $baseChance,
        int $cooldownSeconds = 0,
        ?callable $cooldownRemainingResolver = null,
        int $luckPerMiss = 2,
        float $maxBonus = 25.0
    ): EnchantProcResult {
        $preview = ProcLuckManager::preview($owner, $enchantId, $baseChance, $luckPerMiss, $maxBonus);
        $cooldownRemaining = max(0, (int) ($cooldownRemainingResolver?->__invoke() ?? 0));
        $roll = self::rollForChance((float) ($preview["final_chance"] ?? 0.0));

        if ($cooldownRemaining > 0) {
            $result = new EnchantProcResult(
                enchantId: $enchantId,
                level: $level,
                success: false,
                reason: "cooldown",
                baseChance: (float) ($preview["base_chance"] ?? $baseChance),
                finalChance: (float) ($preview["final_chance"] ?? $baseChance),
                roll: $roll,
                cooldownSeconds: max(0, $cooldownSeconds),
                cooldownRemainingSeconds: $cooldownRemaining,
                missesBefore: (int) ($preview["misses"] ?? 0),
                missesAfter: (int) ($preview["misses"] ?? 0),
                chanceBreakdown: $preview
            );
            EnchantDebugManager::logProc($owner, $result);
            return $result;
        }

        $resolved = ProcLuckManager::rollDetailedFromPreview($owner, $preview, $roll);
        $result = new EnchantProcResult(
            enchantId: $enchantId,
            level: $level,
            success: (bool) ($resolved["success"] ?? false),
            reason: (string) ($resolved["reason"] ?? "unknown"),
            baseChance: (float) ($preview["base_chance"] ?? $baseChance),
            finalChance: (float) ($resolved["final_chance"] ?? $preview["final_chance"] ?? $baseChance),
            roll: (float) ($resolved["roll"] ?? $roll),
            cooldownSeconds: max(0, $cooldownSeconds),
            cooldownRemainingSeconds: 0,
            missesBefore: (int) ($resolved["misses_before"] ?? $preview["misses"] ?? 0),
            missesAfter: (int) ($resolved["misses_after"] ?? $preview["misses"] ?? 0),
            chanceBreakdown: $preview
        );
        EnchantDebugManager::logProc($owner, $result);
        return $result;
    }

    public static function applyCooldownIfNeeded(EnchantProcResult $result, ?callable $cooldownApplier): void
    {
        if (!$result->success || $result->cooldownSeconds <= 0 || $cooldownApplier === null) {
            return;
        }

        $cooldownApplier($result->cooldownSeconds);
    }

    /**
     * @param array<string, scalar|null> $values
     */
    public static function debugAppliedValues(Living $owner, EnchantProcResult $result, array $values): void
    {
        EnchantDebugManager::logAppliedValues($owner, $result, $values);
    }

    private static function rollForChance(float $chancePercent): float
    {
        if ($chancePercent <= 0.0) {
            return 100.00;
        }
        if ($chancePercent >= 100.0) {
            return 0.01;
        }

        return mt_rand(1, 10000) / 100.0;
    }
}
