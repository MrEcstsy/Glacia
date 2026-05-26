<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\manager;

final class EnchantProcResult
{
    /**
     * @param array<string, float|int|bool|string> $chanceBreakdown
     */
    public function __construct(
        public readonly string $enchantId,
        public readonly int $level,
        public readonly bool $success,
        public readonly string $reason,
        public readonly float $baseChance,
        public readonly float $finalChance,
        public readonly float $roll,
        public readonly int $cooldownSeconds,
        public readonly int $cooldownRemainingSeconds,
        public readonly int $missesBefore,
        public readonly int $missesAfter,
        public readonly array $chanceBreakdown = []
    ) {
    }

    public function blockedByCooldown(): bool
    {
        return $this->reason === "cooldown";
    }
}
