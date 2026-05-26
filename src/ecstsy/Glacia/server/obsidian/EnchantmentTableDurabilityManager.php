<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\obsidian;

use ecstsy\Glacia\Loader;
use pocketmine\block\Block;
use pocketmine\block\EnchantingTable;

final class EnchantmentTableDurabilityManager {
    private const KEY_REMAIN = "ench_table_remain";
    /** @deprecated Legacy migrated to remain */
    private const KEY_HP_LEGACY = "ench_table_hp_legacy";

    private const FLOAT_EPS = 1e-6;

    public static function isTrackedTable(Block $block): bool {
        return $block instanceof EnchantingTable;
    }

    public static function getMaxHp(): int {
        return EnchantmentTableExplosionConfig::displayLayers();
    }

    public static function getHp(Block $block): int {
        if (!self::isTrackedTable($block)) {
            return 0;
        }

        $layers = EnchantmentTableExplosionConfig::displayLayers();
        $remain = self::getRemain($block);

        if ($remain <= self::FLOAT_EPS) {
            return 0;
        }

        return max(1, (int) ceil($layers * $remain - self::FLOAT_EPS));
    }

    public static function getRemain(Block $block): float {
        $data = Loader::getBlockData()->get($block);
        if (!is_array($data)) {
            return 1.0;
        }

        if (isset($data[self::KEY_REMAIN])) {
            $v = $data[self::KEY_REMAIN];
            if (is_float($v) || is_int($v)) {
                return max(0.0, min(1.0, (float) $v));
            }
        }

        if (isset($data[self::KEY_HP_LEGACY]) && is_numeric($data[self::KEY_HP_LEGACY])) {
            $layers = EnchantmentTableExplosionConfig::displayLayers();

            return max(0.0, min(1.0, (float) (((int) $data[self::KEY_HP_LEGACY]) / $layers)));
        }

        return 1.0;
    }

    public static function applyExplosionWear(Block $block, float $fractionOfFullDurability): bool {
        if (!self::isTrackedTable($block)) {
            return false;
        }

        $wear = max(0.0, min(1.0, $fractionOfFullDurability));

        $remain = self::getRemain($block) - $wear;
        if ($remain <= self::FLOAT_EPS) {
            self::clear($block);

            return true;
        }

        $existing = Loader::getBlockData()->get($block);
        $merged = is_array($existing) ? $existing : [];
        unset($merged[self::KEY_HP_LEGACY]);
        $merged[self::KEY_REMAIN] = $remain;

        Loader::getBlockData()->set($block, $merged);

        return false;
    }

    public static function clear(Block $block): void {
        Loader::getBlockData()->remove($block);
    }
}
