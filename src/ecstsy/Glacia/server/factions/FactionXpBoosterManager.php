<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\factions;

use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\player\Player;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\TextFormat as C;

final class FactionXpBoosterManager {
    use SingletonTrait;

    /**
     * @var array<string, array{multiplier: float, expires_at: int, activated_by: string}>
     */
    private array $boosters = [];

    public function __construct()
    {
        self::setInstance($this);
    }

    public function activate(Faction $faction, float $multiplier, int $durationSeconds, Player $activatedBy): void
    {
        $expiresAt = time() + $durationSeconds;
        $this->boosters[$faction->getFactionId()] = [
            "multiplier" => $multiplier,
            "expires_at" => $expiresAt,
            "activated_by" => $activatedBy->getName()
        ];

        $durationText = GeneralUtils::translateTime($durationSeconds);
        $message = C::colorize(
            "&r&bFaction XP Booster activated! &r&7(" .
            "&f" . $faction->getName() . "&7, &bx" . rtrim(rtrim(number_format($multiplier, 2), "0"), ".") .
            " &7for &f" . $durationText . "&7)"
        );

        foreach ($faction->getOnlineMembers() as $member) {
            $member->sendMessage($message);
        }
    }

    public function hasActiveBooster(Faction $faction): bool
    {
        return $this->getActiveBooster($faction) !== null;
    }

    /**
     * @return array{multiplier: float, expires_at: int, activated_by: string}|null
     */
    public function getActiveBooster(Faction $faction): ?array
    {
        $data = $this->boosters[$faction->getFactionId()] ?? null;
        if ($data === null) {
            return null;
        }

        if ($data["expires_at"] <= time()) {
            unset($this->boosters[$faction->getFactionId()]);
            return null;
        }

        return $data;
    }

    public function getMultiplier(Faction $faction): float
    {
        return $this->getActiveBooster($faction)["multiplier"] ?? 1.0;
    }

    public function getRemainingTime(Faction $faction): int
    {
        $data = $this->getActiveBooster($faction);
        if ($data === null) {
            return 0;
        }

        return max(0, $data["expires_at"] - time());
    }

    public function tick(): void
    {
        foreach ($this->boosters as $factionId => $data) {
            if ($data["expires_at"] > time()) {
                continue;
            }

            unset($this->boosters[$factionId]);

            $faction = Loader::getFactionManager()->getFactionById($factionId);
            if ($faction === null) {
                continue;
            }

            foreach ($faction->getOnlineMembers() as $member) {
                $member->sendMessage(C::colorize("&r&cYour faction XP booster has expired."));
            }
        }
    }
}
