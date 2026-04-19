<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\items\pets;

use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\player\Player;

final class PetManager
{
    /** @var array<string, int> */
    private static array $gargoyleExpiries = [];

    public static function getCooldown(string $petKey, int $fallback): int
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "pets.yml");
        $raw = $config?->getNested("pets.{$petKey}.cooldown", "{$fallback}s");
        return max(1, GeneralUtils::parseTimeString((string) $raw));
    }

    public static function getDuration(string $petKey, string $path, int $fallback): int
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "pets.yml");
        $raw = $config?->getNested("pets.{$petKey}.{$path}", "{$fallback}s");
        return max(1, GeneralUtils::parseTimeString((string) $raw));
    }

    public static function getInt(string $petKey, string $path, int $fallback): int
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "pets.yml");
        return max(0, (int) ($config?->getNested("pets.{$petKey}.{$path}", $fallback) ?? $fallback));
    }

    public static function activateShatterfang(Player $player, int $durationSeconds): void
    {
        self::$gargoyleExpiries[$player->getUniqueId()->toString()] = time() + $durationSeconds;
    }

    public static function isShatterfangActive(Player $player): bool
    {
        $expiry = self::$gargoyleExpiries[$player->getUniqueId()->toString()] ?? 0;
        if ($expiry <= time()) {
            unset(self::$gargoyleExpiries[$player->getUniqueId()->toString()]);
            return false;
        }

        return true;
    }
}
