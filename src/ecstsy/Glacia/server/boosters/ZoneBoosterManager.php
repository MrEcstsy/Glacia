<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\boosters;

use ecstsy\Glacia\server\zones\ZoneManager;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\TextFormat as C;
use xenialdan\apibossbar\BarColor;
use xenialdan\apibossbar\BossBar;

final class ZoneBoosterManager
{
    use SingletonTrait;

    public const TYPE_ZONE_XP = "zone_xp";
    public const TYPE_FISHING = "fishing";

    /**
     * @var array<string, array<string, array{multiplier: float, started_at: int, expires_at: int, activated_by: string}>>
     */
    private array $boosters = [];

    /** @var array<string, BossBar> */
    private array $bossBars = [];

    public function __construct()
    {
        self::setInstance($this);
    }

    /**
     * @return list<string>
     */
    public function getSupportedTypes(): array
    {
        return [self::TYPE_ZONE_XP, self::TYPE_FISHING];
    }

    public function normalizeType(string $rawType): ?string
    {
        $type = strtolower(trim($rawType));
        return match ($type) {
            "zone_xp", "zonexp", "zone-xp", "xp", "zxp", "grind", "grinding", "mob_xp", "mobxp" => self::TYPE_ZONE_XP,
            "fishing", "fish", "rod", "fishing_xp", "fishingxp", "fish_xp" => self::TYPE_FISHING,
            default => null,
        };
    }

    public function getTypeDisplayName(string $type): string
    {
        return match ($type) {
            self::TYPE_ZONE_XP => "Zone XP Booster",
            self::TYPE_FISHING => "Fishing Booster",
            default => "Booster",
        };
    }

    public function activate(string $zoneId, string $type, float $multiplier, int $durationSeconds, string $activatedBy): bool
    {
        $zoneId = strtolower(trim($zoneId));
        $type = $this->normalizeType($type) ?? "";
        if ($zoneId === "" || $type === "") {
            return false;
        }

        $zone = ZoneManager::getZone($zoneId);
        if ($zone === null) {
            return false;
        }

        $durationSeconds = max(1, $durationSeconds);
        $multiplier = max(1.0, round($multiplier, 2));
        $startedAt = time();
        $expiresAt = $startedAt + $durationSeconds;

        $this->boosters[$zoneId][$type] = [
            "multiplier" => $multiplier,
            "started_at" => $startedAt,
            "expires_at" => $expiresAt,
            "activated_by" => trim($activatedBy) !== "" ? trim($activatedBy) : "System",
        ];

        $durationText = GeneralUtils::translateTime($durationSeconds);
        $typeDisplay = $this->getTypeDisplayName($type);
        $zoneDisplay = C::clean((string) ($zone["display"] ?? $zoneId));
        $this->notifyZonePlayers(
            $zoneId,
            C::colorize(
                "&r&b{$typeDisplay} &r&7activated in &f{$zoneDisplay}&7: &bx" .
                rtrim(rtrim(number_format($multiplier, 2), "0"), ".") .
                " &7for &f{$durationText}&7."
            )
        );

        return true;
    }

    public function deactivate(string $zoneId, string $type, ?string $reasonMessage = null): bool
    {
        $zoneId = strtolower(trim($zoneId));
        $type = $this->normalizeType($type) ?? "";
        if ($zoneId === "" || $type === "") {
            return false;
        }

        $existing = $this->boosters[$zoneId][$type] ?? null;
        if ($existing === null) {
            return false;
        }

        unset($this->boosters[$zoneId][$type]);
        if ($this->boosters[$zoneId] === []) {
            unset($this->boosters[$zoneId]);
        }

        if ($reasonMessage !== null && $reasonMessage !== "") {
            $this->notifyZonePlayers($zoneId, $reasonMessage);
        }

        return true;
    }

    /**
     * @return array{multiplier: float, started_at: int, expires_at: int, activated_by: string}|null
     */
    public function getActiveBooster(string $zoneId, string $type): ?array
    {
        $zoneId = strtolower(trim($zoneId));
        $type = $this->normalizeType($type) ?? "";
        if ($zoneId === "" || $type === "") {
            return null;
        }

        $data = $this->boosters[$zoneId][$type] ?? null;
        if ($data === null) {
            return null;
        }

        if ($data["expires_at"] <= time()) {
            $this->expireBooster($zoneId, $type, $data);
            return null;
        }

        return $data;
    }

    public function getZoneXpMultiplier(string $zoneId): float
    {
        $data = $this->getActiveBooster($zoneId, self::TYPE_ZONE_XP);
        return max(1.0, (float) ($data["multiplier"] ?? 1.0));
    }

    public function getFishingMultiplier(string $zoneId): float
    {
        $data = $this->getActiveBooster($zoneId, self::TYPE_FISHING);
        return max(1.0, (float) ($data["multiplier"] ?? 1.0));
    }

    public function getFishingMultiplierAt(Player $player): float
    {
        $zone = ZoneManager::getZoneAt($player->getPosition());
        if ($zone === null) {
            return 1.0;
        }
        return $this->getFishingMultiplier((string) ($zone["id"] ?? ""));
    }

    /**
     * @return list<array{zone_id: string, zone_display: string, type: string, multiplier: float, started_at: int, expires_at: int, activated_by: string}>
     */
    public function getAllActiveBoosters(?string $zoneFilter = null): array
    {
        $out = [];
        $zones = $zoneFilter !== null && $zoneFilter !== ""
            ? [strtolower($zoneFilter)]
            : array_keys($this->boosters);

        foreach ($zones as $zoneId) {
            $zone = ZoneManager::getZone($zoneId);
            if ($zone === null) {
                continue;
            }

            foreach ($this->getSupportedTypes() as $type) {
                $data = $this->getActiveBooster($zoneId, $type);
                if ($data === null) {
                    continue;
                }

                $out[] = [
                    "zone_id" => $zoneId,
                    "zone_display" => C::clean((string) ($zone["display"] ?? $zoneId)),
                    "type" => $type,
                    "multiplier" => (float) $data["multiplier"],
                    "started_at" => (int) $data["started_at"],
                    "expires_at" => (int) $data["expires_at"],
                    "activated_by" => (string) $data["activated_by"],
                ];
            }
        }

        usort($out, static fn(array $a, array $b): int => $b["started_at"] <=> $a["started_at"]);
        return $out;
    }

    public function tick(): void
    {
        foreach ($this->boosters as $zoneId => $types) {
            foreach ($types as $type => $data) {
                if (($data["expires_at"] ?? 0) > time()) {
                    continue;
                }
                $this->expireBooster($zoneId, $type, $data);
            }
        }

        $onlineByUuid = [];
        foreach (Server::getInstance()->getOnlinePlayers() as $player) {
            $onlineByUuid[$player->getUniqueId()->toString()] = $player;
        }

        foreach (array_keys($this->bossBars) as $uuid) {
            if (isset($onlineByUuid[$uuid])) {
                continue;
            }
            $this->bossBars[$uuid]->hideFromAll();
            unset($this->bossBars[$uuid]);
        }

        foreach ($onlineByUuid as $player) {
            $this->refreshBossBarFor($player);
        }
    }

    /**
     * @param array{multiplier: float, started_at: int, expires_at: int, activated_by: string} $data
     */
    private function expireBooster(string $zoneId, string $type, array $data): void
    {
        unset($this->boosters[$zoneId][$type]);
        if (($this->boosters[$zoneId] ?? []) === []) {
            unset($this->boosters[$zoneId]);
        }

        $zone = ZoneManager::getZone($zoneId);
        if ($zone === null) {
            return;
        }

        $typeDisplay = $this->getTypeDisplayName($type);
        $zoneDisplay = C::clean((string) ($zone["display"] ?? $zoneId));
        $this->notifyZonePlayers($zoneId, C::colorize("&r&c{$typeDisplay} &7expired in &f{$zoneDisplay}&7."));
    }

    private function refreshBossBarFor(Player $player): void
    {
        $latest = $this->resolveLatestVisibleBooster($player);
        $uuid = $player->getUniqueId()->toString();

        if ($latest === null) {
            if (!isset($this->bossBars[$uuid])) {
                return;
            }
            $this->bossBars[$uuid]->hideFrom([$player]);
            $this->bossBars[$uuid]->removePlayer($player);
            unset($this->bossBars[$uuid]);
            return;
        }

        $bar = $this->bossBars[$uuid] ?? new BossBar();
        $bar->setColor($latest["type"] === self::TYPE_FISHING ? BarColor::GREEN : BarColor::BLUE);
        $bar->setTitle(C::colorize(
            "&r&b" . $latest["activated_by"] . "'s &f" . $this->getTypeDisplayName($latest["type"]) .
            " &7(x" . rtrim(rtrim(number_format($latest["multiplier"], 2), "0"), ".") . "&7)"
        ));

        $remaining = max(0, $latest["expires_at"] - time());
        $total = max(1, $latest["expires_at"] - $latest["started_at"]);
        $bar->setSubTitle(C::colorize("&r&7Zone: &f{$latest["zone_display"]} &8| &7Time Left: &f" . GeneralUtils::translateTime($remaining)));
        $bar->setPercentage($remaining / $total);
        $bar->addPlayer($player);

        $this->bossBars[$uuid] = $bar;
    }

    /**
     * @return array{zone_id: string, zone_display: string, type: string, multiplier: float, started_at: int, expires_at: int, activated_by: string}|null
     */
    private function resolveLatestVisibleBooster(Player $player): ?array
    {
        $zone = ZoneManager::getZoneAt($player->getPosition());
        if ($zone === null) {
            return null;
        }

        $zoneId = strtolower((string) ($zone["id"] ?? ""));
        if ($zoneId === "") {
            return null;
        }

        $candidates = [];
        foreach ($this->getSupportedTypes() as $type) {
            $data = $this->getActiveBooster($zoneId, $type);
            if ($data === null) {
                continue;
            }

            $candidates[] = [
                "zone_id" => $zoneId,
                "zone_display" => C::clean((string) ($zone["display"] ?? $zoneId)),
                "type" => $type,
                "multiplier" => (float) $data["multiplier"],
                "started_at" => (int) $data["started_at"],
                "expires_at" => (int) $data["expires_at"],
                "activated_by" => (string) $data["activated_by"],
            ];
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, static fn(array $a, array $b): int => $b["started_at"] <=> $a["started_at"]);
        return $candidates[0];
    }

    private function notifyZonePlayers(string $zoneId, string $message): void
    {
        $zone = ZoneManager::getZone($zoneId);
        if ($zone === null) {
            return;
        }

        foreach (Server::getInstance()->getOnlinePlayers() as $player) {
            if (!ZoneManager::isInsideZone($player->getPosition(), $zone)) {
                continue;
            }
            $player->sendMessage($message);
        }
    }
}
