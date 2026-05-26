<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\floatingtext;

use pocketmine\player\Player;
use Ramsey\Uuid\UuidInterface;

final class FloatingTextRemoveMode
{
    private const DURATION_SECONDS = 120;

    /** @var array<string, float> */
    private static array $activeUntil = [];

    public static function enable(Player $player): void
    {
        self::$activeUntil[$player->getUniqueId()->toString()] = microtime(true) + self::DURATION_SECONDS;
    }

    public static function disable(Player $player): void
    {
        unset(self::$activeUntil[$player->getUniqueId()->toString()]);
    }

    public static function isActive(Player $player): bool
    {
        $key = $player->getUniqueId()->toString();
        $until = self::$activeUntil[$key] ?? 0.0;
        if ($until <= microtime(true)) {
            unset(self::$activeUntil[$key]);
            return false;
        }

        return true;
    }

    public static function onQuit(UuidInterface $uuid): void
    {
        unset(self::$activeUntil[$uuid->toString()]);
    }
}
