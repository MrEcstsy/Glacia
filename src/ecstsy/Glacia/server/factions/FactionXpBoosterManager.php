<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\factions;

use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\player\Player;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\TextFormat as C;
use xenialdan\apibossbar\BarColor;
use xenialdan\apibossbar\BossBar;

final class FactionXpBoosterManager {
    use SingletonTrait;

    /**
     * @var array<string, array{multiplier: float, started_at: int, expires_at: int, activated_by: string}>
     */
    private array $boosters = [];
    /** @var array<string, BossBar> */
    private array $bossBars = [];

    public function __construct()
    {
        self::setInstance($this);
    }

    public function activate(Faction $faction, float $multiplier, int $durationSeconds, Player $activatedBy): void
    {
        $startedAt = time();
        $expiresAt = time() + $durationSeconds;
        $this->boosters[$faction->getFactionId()] = [
            "multiplier" => $multiplier,
            "started_at" => $startedAt,
            "expires_at" => $expiresAt,
            "activated_by" => $activatedBy->getName()
        ];

        $this->getBossBar($faction)->addPlayers($faction->getOnlineMembers());
        $this->refreshBossBar($faction, $this->boosters[$faction->getFactionId()]);

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
     * @return array{multiplier: float, started_at: int, expires_at: int, activated_by: string}|null
     */
    public function getActiveBooster(Faction $faction): ?array
    {
        $data = $this->boosters[$faction->getFactionId()] ?? null;
        if ($data === null) {
            return null;
        }

        if ($data["expires_at"] <= time()) {
            unset($this->boosters[$faction->getFactionId()]);
            $this->hideBossBar($faction->getFactionId());
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
                $faction = Loader::getFactionManager()->getFactionById($factionId);
                if ($faction !== null) {
                    $this->refreshBossBar($faction, $data);
                }
                continue;
            }

            unset($this->boosters[$factionId]);
            $faction = Loader::getFactionManager()->getFactionById($factionId);
            $this->hideBossBar($factionId);
            if ($faction === null) {
                continue;
            }

            foreach ($faction->getOnlineMembers() as $member) {
                $member->sendMessage(C::colorize("&r&cYour faction XP booster has expired."));
            }
        }
    }

    /**
     * @param array{multiplier: float, started_at: int, expires_at: int, activated_by: string} $data
     */
    private function refreshBossBar(Faction $faction, array $data): void
    {
        $bar = $this->getBossBar($faction);
        $remaining = max(0, $data["expires_at"] - time());
        $totalDuration = max(1, $data["expires_at"] - $data["started_at"]);
        $bar->addPlayers($faction->getOnlineMembers());
        $bar->setTitle(C::colorize(
            "&r&b" . $data["activated_by"] . "'s &fFaction XP Booster &7(x"
            . rtrim(rtrim(number_format($data["multiplier"], 2), "0"), ".") . "&7)"
        ));
        $bar->setSubTitle(C::colorize(
            "&r&7Time Left: &f" . GeneralUtils::translateTime($remaining)
        ));
        $bar->setPercentage($remaining / $totalDuration);
    }

    private function getBossBar(Faction $faction): BossBar
    {
        $factionId = $faction->getFactionId();
        if (isset($this->bossBars[$factionId])) {
            return $this->bossBars[$factionId];
        }

        $bar = new BossBar();
        $bar->setColor(BarColor::PURPLE);
        $bar->setPercentage(1.0);
        return $this->bossBars[$factionId] = $bar;
    }

    private function hideBossBar(string $factionId): void
    {
        if (!isset($this->bossBars[$factionId])) {
            return;
        }

        $this->bossBars[$factionId]->hideFromAll();
        unset($this->bossBars[$factionId]);
    }
}
