<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\cannon;

use pocketmine\block\Block;
use pocketmine\block\Fence;
use pocketmine\block\FenceGate;

/**
 * Surfaces cannon sand stacks may treat as footing (thin fence / gate tops).
 */
final class CannonSandSupport {

    /** Sand may stack on full block space above fences and fence gates. */
    public static function allowsSandStackedAbove(Block $surface): bool {
        return $surface instanceof Fence || $surface instanceof FenceGate;
    }
}
