<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\lootboxes;

use pocketmine\player\Player;

final class LootboxAnimationFactory
{
    /**
     * @param array<string, mixed> $definition
     */
    public static function display(Player $viewer, array $definition): void
    {
        $type = strtolower(trim((string) ($definition["animation-type"] ?? ($definition["animation"]["type"] ?? "classic"))));
        $type = match ($type) {
            "chest", "world", "chest-world", "spawned-chest" => "chest",
            default => "classic",
        };

        if ($type === "chest") {
            $screen = new LootboxChestAnimationScreen($viewer, $definition);
            if ($screen->display($viewer)) {
                return;
            }
        }

        (new LootboxAnimationScreen($viewer, $definition))->display($viewer);
    }
}
