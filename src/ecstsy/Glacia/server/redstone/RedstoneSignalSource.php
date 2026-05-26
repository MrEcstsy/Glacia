<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\redstone;

use pocketmine\block\Block;

interface RedstoneSignalSource
{
    public function getSignalPower(Block $block): int;
}
