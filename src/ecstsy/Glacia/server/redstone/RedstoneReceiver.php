<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\redstone;

use pocketmine\block\Block;

interface RedstoneReceiver
{
    public function onRedstonePowerChange(Block $block, int $power): void;
}
