<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\manager;

use Closure;
use pocketmine\player\Player;

final class MirrorbrandProcManager
{
    /**
     * @var array<string, array{
     *     enchantId: string,
     *     callback: Closure(Player, Player): bool
     * }>
     */
    private static array $latestByAttacker = [];

    public static function record(Player $attacker, string $enchantId, Closure $callback): void
    {
        self::$latestByAttacker[$attacker->getUniqueId()->toString()] = [
            "enchantId" => strtolower($enchantId),
            "callback" => $callback,
        ];
    }

    /**
     * @return array{enchantId: string, callback: Closure(Player, Player): bool}|null
     */
    public static function getLatest(Player $attacker): ?array
    {
        return self::$latestByAttacker[$attacker->getUniqueId()->toString()] ?? null;
    }

    public static function clearPlayer(Player $player): void
    {
        unset(self::$latestByAttacker[$player->getUniqueId()->toString()]);
    }
}
