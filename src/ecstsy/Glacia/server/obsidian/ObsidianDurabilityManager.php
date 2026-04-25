<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\obsidian;

use ecstsy\Glacia\Loader;
use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;

final class ObsidianDurabilityManager
{
    private const KEY_HP = "obsidian_hp";
    private const DEFAULT_HP = 5;

    public static function isTrackedObsidian(Block $block): bool
    {
        return $block->getTypeId() === VanillaBlocks::OBSIDIAN()->getTypeId();
    }

    public static function getMaxHp(): int
    {
        return self::DEFAULT_HP;
    }

    public static function getHp(Block $block): int
    {
        if (!self::isTrackedObsidian($block)) {
            return 0;
        }

        $data = Loader::getBlockData()->get($block);
        if (!is_array($data)) {
            return self::DEFAULT_HP;
        }

        return max(1, (int) ($data[self::KEY_HP] ?? self::DEFAULT_HP));
    }

    public static function damage(Block $block, int $amount = 1): int
    {
        if (!self::isTrackedObsidian($block)) {
            return 0;
        }

        $remaining = self::getHp($block) - max(1, $amount);
        if ($remaining <= 0) {
            self::clear($block);
            return 0;
        }

        Loader::getBlockData()->set($block, [
            self::KEY_HP => $remaining
        ]);

        return $remaining;
    }

    public static function clear(Block $block): void
    {
        Loader::getBlockData()->remove($block);
    }
}
