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
    /** @var array<string, int> */
    private static array $ironcladDisarmCooldowns = [];
    /** @var array<string, int> */
    private static array $aegisExpiries = [];
    /** @var array<string, int> */
    private static array $pearlBlockExpiries = [];
    /** @var array<string, int> */
    private static array $gappleBlockExpiries = [];
    /** @var array<string, int> */
    private static array $enchantSurgeExpiries = [];
    /** @var array<string, int> */
    private static array $nearHideExpiries = [];
    /** @var array<string, int> */
    private static array $blastShieldExpiries = [];
    /** @var array<string, \pocketmine\item\Item[]> */
    private static array $pendingTamedPets = [];

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

    public static function triggerIroncladDisarm(Player $player): void
    {
        self::$ironcladDisarmCooldowns[$player->getUniqueId()->toString()] = time() + 3;
    }

    public static function consumeIroncladDisarm(Player $player): bool
    {
        $key = $player->getUniqueId()->toString();
        $expiry = self::$ironcladDisarmCooldowns[$key] ?? 0;
        if ($expiry <= time()) {
            unset(self::$ironcladDisarmCooldowns[$key]);
            return false;
        }

        unset(self::$ironcladDisarmCooldowns[$key]);
        return true;
    }

    public static function activateAegis(Player $player, int $durationSeconds): void
    {
        self::$aegisExpiries[$player->getUniqueId()->toString()] = time() + $durationSeconds;
    }

    public static function isAegisActive(Player $player): bool
    {
        return self::isTimedStateActive(self::$aegisExpiries, $player);
    }

    public static function blockPearls(Player $player, int $durationSeconds): void
    {
        self::$pearlBlockExpiries[$player->getUniqueId()->toString()] = time() + $durationSeconds;
    }

    public static function isPearlBlocked(Player $player): bool
    {
        return self::isTimedStateActive(self::$pearlBlockExpiries, $player);
    }

    public static function blockGapples(Player $player, int $durationSeconds): void
    {
        self::$gappleBlockExpiries[$player->getUniqueId()->toString()] = time() + $durationSeconds;
    }

    public static function isGappleBlocked(Player $player): bool
    {
        return self::isTimedStateActive(self::$gappleBlockExpiries, $player);
    }

    public static function activateEnchantSurge(Player $player, int $durationSeconds): void
    {
        self::$enchantSurgeExpiries[$player->getUniqueId()->toString()] = time() + $durationSeconds;
    }

    public static function getEnchantBonusPercent(Player $player): int
    {
        return self::isTimedStateActive(self::$enchantSurgeExpiries, $player) ? 5 : 0;
    }

    public static function hideFromNear(Player $player, int $durationSeconds): void
    {
        self::$nearHideExpiries[$player->getUniqueId()->toString()] = time() + $durationSeconds;
    }

    public static function isHiddenFromNear(Player $player): bool
    {
        return self::isTimedStateActive(self::$nearHideExpiries, $player);
    }

    public static function activateBlastShield(Player $player, int $durationSeconds): void
    {
        self::$blastShieldExpiries[$player->getUniqueId()->toString()] = time() + $durationSeconds;
    }

    public static function hasBlastShield(Player $player): bool
    {
        return self::isTimedStateActive(self::$blastShieldExpiries, $player);
    }

    /**
     * @param \pocketmine\item\Item[] $items
     */
    public static function queueTamedPets(Player $player, array $items): void
    {
        if ($items === []) {
            return;
        }

        $key = $player->getUniqueId()->toString();
        self::$pendingTamedPets[$key] = array_merge(self::$pendingTamedPets[$key] ?? [], $items);
    }

    /**
     * @return \pocketmine\item\Item[]
     */
    public static function claimQueuedTamedPets(Player $player): array
    {
        $key = $player->getUniqueId()->toString();
        $items = self::$pendingTamedPets[$key] ?? [];
        unset(self::$pendingTamedPets[$key]);
        return $items;
    }

    private static function isTimedStateActive(array &$store, Player $player): bool
    {
        $key = $player->getUniqueId()->toString();
        $expiry = $store[$key] ?? 0;
        if ($expiry <= time()) {
            unset($store[$key]);
            return false;
        }

        return true;
    }
}
