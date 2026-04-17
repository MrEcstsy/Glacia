<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\armor;

final class StickyEnchant {

    public static function getProtectionPercent(int $level): float {
        return min(100.0, $level * 12.5);
    }

    public static function reducesProcChance(float $baseChance, int $level): float {
        $reduction = self::getProtectionPercent($level) / 100;
        return max(0.0, $baseChance * (1 - $reduction));
    }
}