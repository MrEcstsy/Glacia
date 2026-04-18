<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\spawners;

use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\GeneralUtils;

final class SpawnerWorthManager {

    /** @var array<string, int> */
    private static array $worths = [];

    public static function loadFromConfig(): void
    {
        self::$worths = [];

        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "spawners.yml");
        $entries = $config?->get("worths", []);
        if (!is_array($entries)) {
            return;
        }

        foreach ($entries as $entityId => $worth) {
            $normalized = self::normalizeEntityId((string) $entityId);
            if ($normalized === "" || !is_numeric($worth)) {
                continue;
            }

            self::$worths[$normalized] = max(0, (int) $worth);
        }
    }

    public static function getWorth(string $entityTypeId): int
    {
        return self::$worths[self::normalizeEntityId($entityTypeId)] ?? 0;
    }

    private static function normalizeEntityId(string $entityTypeId): string
    {
        $entityTypeId = strtolower(trim($entityTypeId));
        if ($entityTypeId === "") {
            return "";
        }

        if (!str_starts_with($entityTypeId, "minecraft:")) {
            $entityTypeId = "minecraft:" . $entityTypeId;
        }

        if ($entityTypeId === "minecraft:snowman") {
            return "minecraft:snow_golem";
        }

        return $entityTypeId;
    }
}
