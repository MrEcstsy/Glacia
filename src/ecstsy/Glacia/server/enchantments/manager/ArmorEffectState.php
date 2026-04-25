<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\manager;

final class ArmorEffectState {

    /**
     * @return array<string, array{0:string,1:callable(int):int}>
     */
    public static function mappings(): array
    {
        return [
            "aquatic" => [["water_breathing", fn(int $level): int => 0]],
            "gears" => [["speed", fn(int $level): int => max(0, min(3, $level - 1))]],
            "glowing" => [["night_vision", fn(int $level): int => 0]],
            "bounce" => [["jump_boost", fn(int $level): int => max(0, min(3, $level - 1))]],
            "lavabound" => [["fire_resistance", fn(int $level): int => 0]],
            "overload" => [["health_boost", fn(int $level): int => max(0, min(3, $level - 1))]],
            "drunk" => [
                ["strength", fn(int $level): int => max(0, min(2, $level - 1))],
                ["slowness", fn(int $level): int => max(0, min(1, $level - 1))],
                ["mining_fatigue", fn(int $level): int => max(0, min(1, $level - 1))]
            ],
        ];
    }
}
