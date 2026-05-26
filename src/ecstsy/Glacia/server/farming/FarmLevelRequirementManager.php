<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\farming;

use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

/**
 * Loads {@see FarmLevelRequirementManager::loadFromConfig} from {@code levels.yml} under {@code farming-requirements}.
 */
final class FarmLevelRequirementManager {

    private static bool $enabled = true;
    private static bool $ignoreCreative = true;
    private static int $defaultLevel = 1;
    /** @var array<string, int> */
    private static array $cropLevels = [];

    public static function loadFromConfig(): void
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "levels.yml");
        $section = (array) ($config?->get("farming-requirements", []) ?? []);

        self::$enabled = (bool) ($section["enabled"] ?? true);
        self::$ignoreCreative = (bool) ($section["ignore-creative"] ?? true);
        self::$defaultLevel = max(1, (int) ($section["default-level"] ?? 1));

        self::$cropLevels = [];
        foreach ((array) ($section["crops"] ?? []) as $key => $value) {
            $k = strtolower(trim((string) $key));
            if ($k === "") {
                continue;
            }
            self::$cropLevels[$k] = max(1, (int) $value);
        }
    }

    public static function isEnabled(): bool
    {
        return self::$enabled;
    }

    public static function getDefaultLevel(): int
    {
        return self::$defaultLevel;
    }

    public static function getRequiredLevelForFamily(string $family): int
    {
        $k = strtolower(trim($family));
        return max(1, self::$cropLevels[$k] ?? self::$defaultLevel);
    }

    public static function meetsRequirement(Player $player, string $family): bool
    {
        if (!self::$enabled) {
            return true;
        }
        if ($player->hasPermission("glacia.farming.bypass") || $player->hasPermission("glacia.admin")) {
            return true;
        }
        if (self::$ignoreCreative && !$player->hasFiniteResources()) {
            return true;
        }

        $session = Loader::getPlayerManager()->getSession($player);
        $level = $session?->getLevel() ?? 1;

        return $level >= self::getRequiredLevelForFamily($family);
    }

    /**
     * @return array<string, int>
     */
    public static function getAllCropLevels(): array
    {
        return self::$cropLevels;
    }

    public static function notifyRequirementFailed(Player $player, string $family): void
    {
        $need = self::getRequiredLevelForFamily($family);
        $have = Loader::getPlayerManager()->getSession($player)?->getLevel() ?? 1;
        $player->sendMessage(C::colorize(
            "&r&cRequires farming level &f" . $need . " &cfor &f" . $family . "&c. You are level &f" . $have . "&c."
        ));
        PlayerUtils::playSound($player, "note.bass");
    }
}
