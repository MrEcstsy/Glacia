<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\quests;

use ecstsy\Glacia\server\quests\types\KillQuest;
use ecstsy\Glacia\server\quests\types\MiningQuest;
use ecstsy\Glacia\server\quests\types\PlacingQuest;

final class QuestManager {

    /** @var array<string, object> */
    private static array $quests = [];

    public static function init(): void {
        self::$quests = [
            'kill' => new KillQuest(),
            'mining' => new MiningQuest(),
            'placing' => new PlacingQuest(),
        ];
    }

    public static function get(string $id): ?object {
        return self::$quests[$id] ?? null;
    }

    public static function getAll(): array {
        return self::$quests;
    }
}