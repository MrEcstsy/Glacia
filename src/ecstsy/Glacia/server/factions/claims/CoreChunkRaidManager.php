<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\factions\claims;

use ecstsy\Glacia\entity\GlaciaPrimedTNT;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\factions\contribution\FactionContributionManager;
use ecstsy\Glacia\server\factions\guardian\FactionGuardianManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\Glacia\utils\StorageUtils;
use pocketmine\utils\TextFormat as C;
use pocketmine\world\Position;
use function floor;
use function time;

final class CoreChunkRaidManager
{
    /** @var array<string, int> */
    private static array $cooldowns = [];
    private static string $dataPath = "";

    public static function init(Loader $plugin): void
    {
        self::$dataPath = $plugin->getDataFolder() . "faction_core_raid_cooldowns.json";
        self::load();
    }

    public static function save(): void
    {
        if (self::$dataPath === "") {
            return;
        }

        StorageUtils::writeJsonFile(self::$dataPath, self::$cooldowns, "faction core raid cooldowns");
    }

    public static function handleTntExplosion(GlaciaPrimedTNT $tnt, Position $center): void
    {
        $attackerFactionId = $tnt->getRaidSourceFactionId();
        if ($attackerFactionId === null) {
            return;
        }

        self::handleFactionExplosionAt($attackerFactionId, $center);
    }

    public static function handleFactionExplosionAt(string $attackerFactionId, Position $center): void
    {
        $attackerFactionId = trim($attackerFactionId);
        if ($attackerFactionId === "") {
            return;
        }

        $claimManager = ClaimManager::getInstance();
        if ($center->y <= $claimManager->getBreachMinimumY()) {
            return;
        }

        $guardianBreach = false;
        $victimFactionId = FactionGuardianManager::getBreachableFactionAt($center);
        if ($victimFactionId !== null) {
            $guardianBreach = true;
        } else {
            $claim = $claimManager->getClaimAtPosition($center);
            if ($claim === null || !$claim->isCore()) {
                return;
            }
            $victimFactionId = $claim->getFactionId();
        }

        if ($victimFactionId === $attackerFactionId) {
            return;
        }

        if (self::isOnCooldown($victimFactionId)) {
            return;
        }

        $percent = $claimManager->getBreachPercentForY($center->y);
        if ($percent <= 0.0) {
            return;
        }

        $stolen = FactionContributionManager::transferFactionPoints(
            $victimFactionId,
            $attackerFactionId,
            $percent,
            "Core Chunk Breach"
        );

        self::$cooldowns[$victimFactionId] = time() + $claimManager->getBreachCooldownSeconds();
        self::save();

        self::announceBreach($attackerFactionId, $victimFactionId, $stolen, $percent, (int) floor($center->y), $guardianBreach);
    }

    public static function isOnCooldown(string $factionId): bool
    {
        $until = (int) (self::$cooldowns[$factionId] ?? 0);
        if ($until <= 0) {
            return false;
        }
        if ($until <= time()) {
            unset(self::$cooldowns[$factionId]);
            self::save();
            return false;
        }

        return true;
    }

    public static function getCooldownRemaining(string $factionId): int
    {
        return max(0, (int) (self::$cooldowns[$factionId] ?? 0) - time());
    }

    private static function announceBreach(string $attackerFactionId, string $victimFactionId, int $stolen, float $percent, int $y, bool $guardianBreach): void
    {
        $attacker = Loader::getFactionManager()->getFactionById($attackerFactionId);
        $victim = Loader::getFactionManager()->getFactionById($victimFactionId);
        $attackerName = $attacker?->getName() ?? "Unknown";
        $victimName = $victim?->getName() ?? "Unknown";
        $percentText = (string) (int) round($percent * 100.0);

        $label = $guardianBreach ? "Guardian Breach" : "Core Breach";
        Loader::getInstance()->getServer()->broadcastMessage(C::colorize(
            "&r&4&l{$label} &8| &r&c{$attackerName} &7breached &c{$victimName}&7's core at Y{$y}, stealing &f" .
            number_format($stolen) . " &7points (&f{$percentText}%&7)."
        ));

        GlaciaUtils::logFactionAudit($label, [
            "attacker_faction_id" => $attackerFactionId,
            "attacker" => $attackerName,
            "victim_faction_id" => $victimFactionId,
            "victim" => $victimName,
            "points" => $stolen,
            "percent" => $percentText,
            "y" => $y,
        ]);
    }

    private static function load(): void
    {
        if (self::$dataPath === "") {
            return;
        }

        $decoded = StorageUtils::readJsonFile(self::$dataPath, "faction core raid cooldowns");
        if (!is_array($decoded)) {
            return;
        }

        foreach ($decoded as $factionId => $until) {
            if (!is_string($factionId)) {
                continue;
            }
            $until = (int) $until;
            if ($until > time()) {
                self::$cooldowns[$factionId] = $until;
            }
        }
    }
}
