<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\armor;

use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;

final class StickyEnchant {

    public static function getProtectionPercent(int $level): float {
        $cap = EnchantTuningManager::getFloat("sticky", "max-protection-percent-cap", 100.0);
        $fallbackProtection = $level * EnchantTuningManager::getFloat("sticky", "protection-percent-per-level", 12.5);
        return min($cap, max(0.0, EnchantTuningManager::getLevelFloat("sticky", "protection-percent-by-level", $level, $fallbackProtection)));
    }

    public static function reducesProcChance(float $baseChance, int $level): float {
        $reduction = self::getProtectionPercent($level) / 100;
        return max(0.0, $baseChance * (1 - $reduction));
    }
}
