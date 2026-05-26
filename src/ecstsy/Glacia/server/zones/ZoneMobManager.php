<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\zones;

use ecstsy\Glacia\entity\zones\ZoneMob;
use ecstsy\Glacia\entity\zones\ZoneMobBlaze;
use ecstsy\Glacia\entity\zones\ZoneMobCreeper;
use ecstsy\Glacia\entity\zones\ZoneMobEnderman;
use ecstsy\Glacia\entity\zones\ZoneMobHusk;
use ecstsy\Glacia\entity\zones\ZoneMobIronGolem;
use ecstsy\Glacia\entity\zones\ZoneMobMagmaCube;
use ecstsy\Glacia\entity\zones\ZoneMobPigman;
use ecstsy\Glacia\entity\zones\ZoneMobSkeleton;
use ecstsy\Glacia\entity\zones\ZoneMobSlime;
use ecstsy\Glacia\entity\zones\ZoneMobSnowGolem;
use ecstsy\Glacia\entity\zones\ZoneMobSpider;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\items\runes\RuneManager;
use ecstsy\Glacia\server\world\events\WorldEventManager;
use ecstsy\Glacia\utils\RewardResolver;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\block\Block;
use pocketmine\block\Liquid;
use pocketmine\entity\Location;
use pocketmine\item\Item;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat as C;
use pocketmine\world\Position;
use pocketmine\world\World;

final class ZoneMobManager {
    private const SUPPORTED_ELITE_TYPES = [
        "exploding",
        "imploding",
        "molten",
        "frost",
        "charged",
        "gravitic",
        "reinforced",
        "leeching",
        "corrupted",
        "splitter",
    ];

    private static int $tickIntervalSeconds = 8;
    private static int $edgePadding = 16;
    private static int $activationRange = 56;
    private static int $defaultBaseAlive = 3;
    private static int $defaultMobsPerPlayer = 2;
    private static int $defaultMaxAlive = 12;
    private static int $defaultAnchorRadius = 22;
    private static int $anchorMaxSpawnAbove = 3;
    private static int $anchorMaxSpawnBelow = 8;
    private static int $spawnPositionAttempts = 10;
    private static int $eliteMinKills = 20;
    private static int $eliteMaxKills = 40;
    private static float $eliteHealthMultiplier = 1.65;
    private static float $eliteDamageMultiplier = 1.45;
    private static float $eliteSpeedMultiplier = 1.08;
    private static float $eliteXpMultiplier = 1.75;
    private static float $eliteLootMultiplier = 1.45;
    private static string $eliteNamePrefix = "&6&lElite &r";
    /** @var list<int> */
    private static array $streakBroadcastMilestones = [10, 25, 50, 100];
    /** @var list<string> */
    private static array $eliteTypes = ["exploding", "imploding", "frost", "charged", "gravitic", "reinforced", "leeching", "corrupted", "splitter", "molten"];
    /** @var array<string, array<string, mixed>> */
    private static array $zones = [];
    /** @var array<string, array{zone: string, count: int, next_elite: int}> */
    private static array $playerStreaks = [];
    /** Server tick deadline (see shouldPurgeZoneMobsOnPersistedChunkLoad) for post-reboot chunk stripping. */
    private static int $zoneMobColdPurgeEndsAtTick = 0;

    public static function loadFromConfig(): void
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "zone_mobs.yml");
        $settings = (array) ($config?->get("settings", []) ?? []);

        self::$tickIntervalSeconds = max(2, (int) ($settings["tick-interval-seconds"] ?? 8));
        self::$edgePadding = max(0, (int) ($settings["edge-padding"] ?? 16));
        self::$activationRange = max(16, (int) ($settings["player-activation-range"] ?? 56));
        self::$defaultBaseAlive = max(0, (int) ($settings["default-base-alive"] ?? 3));
        self::$defaultMobsPerPlayer = max(0, (int) ($settings["default-mobs-per-player"] ?? 2));
        self::$defaultMaxAlive = max(1, (int) ($settings["default-max-alive"] ?? 12));
        self::$defaultAnchorRadius = max(6, (int) ($settings["default-anchor-radius"] ?? 22));
        self::$anchorMaxSpawnAbove = max(0, (int) ($settings["anchor-max-spawn-above"] ?? 3));
        self::$anchorMaxSpawnBelow = max(0, (int) ($settings["anchor-max-spawn-below"] ?? 8));
        self::$spawnPositionAttempts = max(1, min(30, (int) ($settings["spawn-position-attempts"] ?? 10)));
        self::$eliteMinKills = max(1, (int) ($settings["elite-min-kills"] ?? 20));
        self::$eliteMaxKills = max(self::$eliteMinKills, (int) ($settings["elite-max-kills"] ?? 40));
        self::$eliteHealthMultiplier = max(1.0, (float) ($settings["elite-health-multiplier"] ?? 1.65));
        self::$eliteDamageMultiplier = max(1.0, (float) ($settings["elite-damage-multiplier"] ?? 1.45));
        self::$eliteSpeedMultiplier = max(1.0, (float) ($settings["elite-speed-multiplier"] ?? 1.08));
        self::$eliteXpMultiplier = max(1.0, (float) ($settings["elite-xp-multiplier"] ?? 1.75));
        self::$eliteLootMultiplier = max(1.0, (float) ($settings["elite-loot-multiplier"] ?? 1.45));
        self::$eliteNamePrefix = (string) ($settings["elite-name-prefix"] ?? "&6&lElite &r");
        self::$streakBroadcastMilestones = array_values(array_unique(array_map(
            static fn(mixed $value): int => max(1, (int) $value),
            (array) ($settings["streak-broadcast-milestones"] ?? self::$streakBroadcastMilestones)
        )));
        sort(self::$streakBroadcastMilestones);
        self::$eliteTypes = self::sanitizeEliteTypes((array) ($settings["elite-types"] ?? self::$eliteTypes));
        self::$zones = (array) ($config?->get("zones", []) ?? []);
    }

    /**
     * Marks a short uptime window right after reboot: persisted chunks will strip ZoneMob entities
     * loaded from disk (they often deserialize poorly while spawners will replace them cleanly).
     */
    public static function beginColdBootZoneMobPurging(int $durationTicksFromNow): void
    {
        self::$zoneMobColdPurgeEndsAtTick = Server::getInstance()->getTick() + max(20, $durationTicksFromNow);
    }

    public static function shouldPurgeZoneMobsOnPersistedChunkLoad(Server $server): bool
    {
        return self::$zoneMobColdPurgeEndsAtTick !== 0 && $server->getTick() < self::$zoneMobColdPurgeEndsAtTick;
    }

    /**
     * Flags every spawned ZoneMob in already-loaded worlds; run once after reboot so spawner logic repopulates.
     */
    public static function flagAllSpawnedZoneMobsForDespawn(): void
    {
        foreach (Server::getInstance()->getWorldManager()->getWorlds() as $world) {
            foreach ($world->getEntities() as $entity) {
                if (!$entity instanceof ZoneMob || $entity->isClosed() || $entity->isFlaggedForDespawn()) {
                    continue;
                }
                $entity->flagForDespawn();
            }
        }
    }

    /** Clears persisted ZoneMob copies inside one chunk during cold-boot purging only. */
    public static function flagSpawnedZoneMobsInChunkForDespawn(World $world, int $chunkX, int $chunkZ): void
    {
        foreach ($world->getChunkEntities($chunkX, $chunkZ) as $entity) {
            if (!$entity instanceof ZoneMob || $entity->isClosed() || $entity->isFlaggedForDespawn()) {
                continue;
            }
            $entity->flagForDespawn();
        }
    }

    public static function getSpawnIntervalSeconds(): int
    {
        return self::$tickIntervalSeconds;
    }

    public static function getSpawnerStatus(string $zoneId): ?array
    {
        $zoneId = strtolower($zoneId);
        $zone = ZoneManager::getZone($zoneId);
        $config = self::getZoneConfig($zoneId);
        if ($zone === null || $config === null) {
            return null;
        }

        $playersInZone = self::getPlayersInZone($zone);
        return [
            "alive" => self::countAlive($zoneId),
            "desired" => self::getDesiredAliveCount($zoneId, $config, $playersInZone),
            "players" => $playersInZone,
            "interval" => self::$tickIntervalSeconds
        ];
    }

    public static function handleZoneState(Player $player, ?string $zoneId): void
    {
        $key = $player->getUniqueId()->toString();
        $zoneId = $zoneId !== null ? strtolower($zoneId) : null;
        $current = self::$playerStreaks[$key]["zone"] ?? null;
        if ($current === $zoneId) {
            return;
        }

        if ($zoneId === null) {
            unset(self::$playerStreaks[$key]);
            return;
        }

        self::$playerStreaks[$key] = [
            "zone" => $zoneId,
            "count" => 0,
            "next_elite" => self::rollNextEliteThreshold()
        ];
    }

    public static function clearPlayerState(Player $player): void
    {
        unset(self::$playerStreaks[$player->getUniqueId()->toString()]);
    }

    public static function getPlayerZoneStreak(Player $player): int
    {
        $state = self::$playerStreaks[$player->getUniqueId()->toString()] ?? null;
        if (!is_array($state)) {
            return 0;
        }

        return max(0, (int) ($state["count"] ?? 0));
    }

    public static function handleMobKill(Player $player, ZoneMob $mob): void
    {
        $zoneId = strtolower($mob->getZoneId());
        $key = $player->getUniqueId()->toString();
        $state = self::$playerStreaks[$key] ?? ["zone" => $zoneId, "count" => 0, "next_elite" => self::rollNextEliteThreshold()];
        if (($state["zone"] ?? null) !== $zoneId) {
            $state = ["zone" => $zoneId, "count" => 0, "next_elite" => self::rollNextEliteThreshold()];
        }

        $config = self::getZoneConfig($zoneId);
        if ($config === null) {
            return;
        }

        $step = WorldEventManager::getKillProgressPerKill($zoneId);
        $previousCount = max(0, (int) ($state["count"] ?? 0));
        $state["count"] = $previousCount + $step;
        self::$playerStreaks[$key] = $state;
        for ($count = $previousCount + 1; $count <= (int) $state["count"]; $count++) {
            foreach ((array) ($config["streak-rewards"] ?? []) as $threshold => $rewardConfig) {
                if ((int) $threshold !== $count || !is_array($rewardConfig)) {
                    continue;
                }

                self::grantStreakReward($player, $zoneId, $count, $rewardConfig);
            }

            if (in_array($count, self::resolveStreakBroadcastMilestones($config), true)) {
                self::broadcastStreakMilestone($player, $zoneId, $count, (array) ($config["streak-broadcast"] ?? []));
            }
        }

        $nextElite = max(1, (int) ($state["next_elite"] ?? self::rollNextEliteThreshold()));
        if ($state["count"] >= $nextElite) {
            self::spawnEliteFromKill($player, $mob, $config);
            $state["count"] = 0;
            $state["next_elite"] = self::rollNextEliteThreshold();
            self::$playerStreaks[$key] = $state;
        }
    }

    public static function tickSpawner(Position $anchor, string $zoneId): void
    {
        $zoneId = strtolower($zoneId);
        $zone = ZoneManager::getZone($zoneId);
        $config = self::getZoneConfig($zoneId);
        if ($zone === null || $config === null) {
            return;
        }

        $world = ZoneManager::getWorld($zone);
        if ($world === null || strtolower($anchor->getWorld()->getFolderName()) !== strtolower($world->getFolderName())) {
            return;
        }

        $playersInZone = self::getPlayersInZone($zone);
        $alive = self::countAlive($zoneId);
        $desiredAlive = self::getDesiredAliveCount($zoneId, $config, $playersInZone);

        if ($alive > $desiredAlive) {
            self::trimExcess($zoneId, $zone, $alive - $desiredAlive);
            $alive = self::countAlive($zoneId);
        }

        if ($playersInZone <= 0 || !self::hasPlayerNearZone($zone) || $alive >= $desiredAlive) {
            return;
        }

        $batch = max(1, min(4, (int) ($config["spawn-batch"] ?? 1)));
        $batch = (int) ceil($batch * self::getAnchorSpawnMultiplier($anchor) * WorldEventManager::getSpawnMultiplier($zoneId));
        for ($i = 0; $i < min($batch, $desiredAlive - $alive); $i++) {
            if (!self::spawnOneNearAnchor($zoneId, $zone, $config, $anchor)) {
                break;
            }
            $alive++;
        }
    }

    private static function getAnchorSpawnMultiplier(Position $anchor): float
    {
        $chunkX = $anchor->getFloorX() >> 4;
        $chunkZ = $anchor->getFloorZ() >> 4;
        $playersInChunk = [];
        foreach ($anchor->getWorld()->getPlayers() as $player) {
            $pos = $player->getPosition();
            if (($pos->getFloorX() >> 4) === $chunkX && ($pos->getFloorZ() >> 4) === $chunkZ) {
                $playersInChunk[] = $player;
            }
        }

        if (count($playersInChunk) !== 1) {
            return 1.0;
        }

        return RuneManager::getZoneSpawnSpeedMultiplier($playersInChunk[0]);
    }

    /**
     * @return Item[]
     */
    public static function rollDrops(string $zoneId, string $mobId, bool $elite = false, float $lootMultiplier = 1.0): array
    {
        return array_map(
            static fn(array $result): Item => $result["item"],
            self::rollDropResults($zoneId, $mobId, $elite, $lootMultiplier)
        );
    }

    /**
     * @return array<int, array{item: Item, entry: array<string, mixed>}>
     */
    public static function rollDropResults(string $zoneId, string $mobId, bool $elite = false, float $lootMultiplier = 1.0): array
    {
        $mob = self::getMobConfig($zoneId, $mobId);
        if ($mob === null) {
            return [];
        }

        $results = [];
        $entries = array_merge(
            array_values(array_filter((array) ($mob["rewards"] ?? []), static fn(mixed $entry): bool => is_array($entry))),
            WorldEventManager::getExtraRewardEntries($zoneId)
        );
        $effectiveMultiplier = max(1.0, $lootMultiplier * WorldEventManager::getLootMultiplier($zoneId));

        foreach ($entries as $entry) {
            $chance = max(0, min(100, (int) ($entry["chance"] ?? 100)));
            if ($elite) {
                $chance = min(100, (int) floor($chance * $effectiveMultiplier));
            }
            if ($chance < 100 && mt_rand(1, 100) > $chance) {
                continue;
            }

            $item = RewardResolver::resolveItem($entry);
            if ($item === null) {
                continue;
            }

            if ($elite || $effectiveMultiplier > 1.0) {
                $item->setCount(max(1, (int) ceil($item->getCount() * $effectiveMultiplier)));
            }

            $results[] = ["item" => $item, "entry" => $entry];
        }

        return $results;
    }

    public static function isInsideSpawnArea(ZoneMob $mob): bool
    {
        $zone = ZoneManager::getZone($mob->getZoneId());
        if ($zone === null || !ZoneManager::isInsideZone($mob->getPosition(), $zone)) {
            return false;
        }

        return ZoneManager::isInsideSpawnInset($mob->getPosition(), $zone, self::$edgePadding);
    }

    public static function getZoneConfig(string $zoneId): ?array
    {
        $zoneId = strtolower($zoneId);
        $zone = ZoneManager::getZone($zoneId);
        $config = self::$zones[$zoneId] ?? null;
        if ($zone === null || !is_array($config) || !((bool) ($config["enabled"] ?? true)) || !ZoneManager::hasRectangularBoundary($zone)) {
            return null;
        }

        return $config;
    }

    private static function spawnOneNearAnchor(string $zoneId, array $zone, array $config, Position $anchor): bool
    {
        $mobPool = WorldEventManager::getSpawnMobPool($zoneId, (array) ($config["mobs"] ?? []));
        $mobId = self::pickMobId($mobPool);
        if ($mobId === null) {
            return false;
        }

        $mob = $mobPool[$mobId] ?? self::getMobConfig($zoneId, $mobId);
        if ($mob === null) {
            return false;
        }
        $mob = WorldEventManager::applyMobConfigModifiers($zoneId, $mob);

        $anchorRadius = max(6, (int) ($config["anchor-radius"] ?? self::$defaultAnchorRadius));
        $position = null;
        for ($attempt = 0; $attempt < self::$spawnPositionAttempts; $attempt++) {
            $candidate = ZoneManager::getRandomSafePositionNear($anchor, $zone, $anchorRadius, self::$edgePadding)
                ?? ZoneManager::getRandomSafePosition($zone, self::$edgePadding);
            if (!$candidate instanceof Position) {
                continue;
            }

            if (!self::isAllowedSpawnCandidate($candidate, $anchor, $zone)) {
                continue;
            }

            $position = $candidate;
            break;
        }

        if (!$position instanceof Position) {
            return false;
        }

        $displayName = (string) ($mob["name"] ?? $mob["display"] ?? ucfirst($mobId));
        $nbt = self::buildMobNbt($zoneId, $mobId, $mob, $displayName);
        $entity = self::createZoneMob((string) ($mob["entity"] ?? "zombie"), Location::fromObject($position, $position->getWorld()), $nbt);
        $entity->spawnToAll();
        return true;
    }

    private static function isAllowedSpawnCandidate(Position $candidate, Position $anchor, array $zone): bool
    {
        $dy = $candidate->getY() - $anchor->getY();
        if ($dy > self::$anchorMaxSpawnAbove || $dy < -self::$anchorMaxSpawnBelow) {
            return false;
        }

        if (ZoneManager::isInsideSafeArea($candidate, $zone)) {
            return false;
        }

        if (!ZoneManager::isInsideZone($candidate, $zone) || !ZoneManager::isInsideSpawnInset($candidate, $zone, self::$edgePadding)) {
            return false;
        }

        $world = $candidate->getWorld();
        $x = $candidate->getFloorX();
        $y = $candidate->getFloorY();
        $z = $candidate->getFloorZ();

        $ground = $world->getBlockAt($x, $y - 1, $z);
        $feet = $world->getBlockAt($x, $y, $z);
        $head = $world->getBlockAt($x, $y + 1, $z);

        if ($ground instanceof Liquid || $feet instanceof Liquid || $head instanceof Liquid) {
            return false;
        }

        if (self::isTreeLikeBlock($ground) || self::isTreeLikeBlock($feet) || self::isTreeLikeBlock($head)) {
            return false;
        }

        return true;
    }

    private static function isTreeLikeBlock(Block $block): bool
    {
        $name = strtolower($block->getName());
        return str_contains($name, "leaves")
            || str_contains($name, "log")
            || str_contains($name, " wood")
            || str_contains($name, "wood ")
            || str_contains($name, "mangrove roots")
            || str_contains($name, "bamboo");
    }

    private static function buildMobNbt(string $zoneId, string $mobId, array $mob, string $displayName): CompoundTag
    {
        $tier = self::resolveMobTier($mob, $displayName);
        $configuredAimQuality = array_key_exists("aim-quality", $mob)
            ? max(0.0, min(1.0, (float) $mob["aim-quality"]))
            : -1.0;

        return CompoundTag::create()
            ->setString("zone_id", $zoneId)
            ->setString("zone_mob_id", $mobId)
            ->setString("zone_mob_name", $displayName)
            ->setInt("zone_mob_tier", $tier)
            ->setInt("zone_mob_health", max(10, (int) ($mob["health"] ?? 30)))
            ->setFloat("zone_mob_damage", max(1.0, (float) ($mob["damage"] ?? 4.0)))
            ->setFloat("zone_mob_speed", max(0.08, min(0.34, (float) ($mob["speed"] ?? 0.19))))
            ->setFloat("zone_mob_target_range", max(8.0, min(28.0, (float) ($mob["target-range"] ?? 15.0))))
            ->setInt("zone_mob_xp", max(1, (int) ($mob["xp"] ?? 20)))
            ->setInt("zone_mob_attack_windup", max(0, min(10, (int) ($mob["attack-windup-ticks"] ?? 6))))
            ->setString("zone_mob_combat_style", strtolower((string) ($mob["combat-style"] ?? "melee")))
            ->setString("zone_mob_weapon", strtolower((string) ($mob["weapon"] ?? "none")))
            ->setInt("zone_mob_melee_cooldown", max(6, (int) ($mob["melee-cooldown"] ?? 13)))
            ->setInt("zone_mob_ranged_cooldown", max(8, (int) ($mob["ranged-cooldown"] ?? 26)))
            ->setFloat("zone_mob_attack_reach", max(1.4, min(3.6, (float) ($mob["attack-reach"] ?? 2.0))))
            ->setFloat("zone_mob_ideal_range_min", max(4.0, min(18.0, (float) ($mob["ideal-range-min"] ?? 6.0))))
            ->setFloat("zone_mob_ideal_range_max", max(5.0, min(28.0, (float) ($mob["ideal-range-max"] ?? 13.0))))
            ->setString("zone_mob_projectile_type", strtolower((string) ($mob["projectile-type"] ?? $mob["weapon"] ?? "arrow")))
            ->setFloat("zone_mob_projectile_speed", max(0.2, min(3.0, (float) ($mob["projectile-speed"] ?? 0.0))))
            ->setFloat("zone_mob_aim_quality", $configuredAimQuality)
            ->setInt("zone_mob_fire_seconds", max(0, min(20, (int) ($mob["fire-seconds"] ?? 4))))
            ->setInt("zone_mob_strafe_chance", max(0, min(100, (int) ($mob["strafe-chance"] ?? 35))))
            ->setByte("zone_mob_retreat_when_too_close", ((bool) ($mob["retreat-when-too-close"] ?? true)) ? 1 : 0)
            ->setByte("zone_mob_allow_melee_when_ranged", ((bool) ($mob["allow-melee-when-ranged"] ?? false)) ? 1 : 0)
            ->setFloat("zone_mob_horde_separation", max(0.05, min(0.75, (float) ($mob["horde-separation"] ?? 0.26))))
            ->setByte("zone_mob_elite", ((bool) ($mob["elite"] ?? false)) ? 1 : 0)
            ->setString("zone_mob_elite_type", strtolower((string) ($mob["elite-type"] ?? "")))
            ->setFloat("zone_mob_loot_multiplier", max(1.0, (float) ($mob["loot-multiplier"] ?? 1.0)))
            ->setInt("zone_mob_event_teleport_chance", max(0, min(100, (int) ($mob["event-teleport-chance"] ?? 0))))
            ->setString("zone_mob_world_event_id", (string) ($mob["world-event-id"] ?? ""))
            ->setString("zone_mob_world_event_name", (string) ($mob["world-event-name"] ?? ""));
    }

    private static function resolveMobTier(array $mob, string $displayName): int
    {
        $explicit = (int) ($mob["tier"] ?? 0);
        if ($explicit > 0) {
            return $explicit;
        }

        $clean = C::clean($displayName);
        if (preg_match('/tier\s+([ivxlcdm]+|\d+)/i', $clean, $matches) !== 1) {
            return 1;
        }

        $token = strtoupper((string) $matches[1]);
        if (is_numeric($token)) {
            return max(1, (int) $token);
        }

        return max(1, self::romanToInt($token));
    }

    private static function romanToInt(string $roman): int
    {
        $map = [
            'I' => 1,
            'V' => 5,
            'X' => 10,
            'L' => 50,
            'C' => 100,
            'D' => 500,
            'M' => 1000,
        ];

        $total = 0;
        $previous = 0;
        for ($i = strlen($roman) - 1; $i >= 0; $i--) {
            $value = $map[$roman[$i]] ?? 0;
            if ($value < $previous) {
                $total -= $value;
            } else {
                $total += $value;
                $previous = $value;
            }
        }

        return $total;
    }

    private static function countAlive(string $zoneId): int
    {
        $count = 0;
        foreach (Server::getInstance()->getWorldManager()->getWorlds() as $world) {
            foreach ($world->getEntities() as $entity) {
                if ($entity instanceof ZoneMob && !$entity->isClosed() && !$entity->isFlaggedForDespawn() && $entity->getZoneId() === $zoneId) {
                    $count++;
                }
            }
        }

        return $count;
    }

    private static function getPlayersInZone(array $zone): int
    {
        $world = ZoneManager::getWorld($zone);
        if ($world === null) {
            return 0;
        }

        $count = 0;
        foreach ($world->getPlayers() as $player) {
            if (ZoneManager::isInsideZone($player->getPosition(), $zone)) {
                $count++;
            }
        }

        return $count;
    }

    private static function getDesiredAliveCount(string $zoneId, array $config, int $playersInZone): int
    {
        if ($playersInZone <= 0) {
            return 0;
        }

        $baseAlive = max(0, (int) ($config["base-alive"] ?? self::$defaultBaseAlive));
        $mobsPerPlayer = max(0, (int) ($config["mobs-per-player"] ?? self::$defaultMobsPerPlayer));
        $maxAlive = max(1, (int) ($config["max-alive"] ?? self::$defaultMaxAlive));

        $desired = min($maxAlive, $baseAlive + ($playersInZone * $mobsPerPlayer));
        return max(0, (int) ceil($desired * WorldEventManager::getSpawnMultiplier($zoneId)));
    }

    private static function hasPlayerNearZone(array $zone): bool
    {
        $center = ZoneManager::getPosition($zone, true);
        if ($center === null) {
            return false;
        }

        $rangeSq = self::$activationRange * self::$activationRange;
        foreach ($center->getWorld()->getPlayers() as $player) {
            if (ZoneManager::isInsideZone($player->getPosition(), $zone) || $player->getPosition()->distanceSquared($center) <= $rangeSq) {
                return true;
            }
        }

        return false;
    }

    private static function trimExcess(string $zoneId, array $zone, int $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        $world = ZoneManager::getWorld($zone);
        if ($world === null) {
            return;
        }

        $mobs = [];
        foreach ($world->getEntities() as $entity) {
            if ($entity instanceof ZoneMob && !$entity->isClosed() && !$entity->isFlaggedForDespawn() && $entity->getZoneId() === $zoneId) {
                $mobs[] = $entity;
            }
        }

        if ($mobs === []) {
            return;
        }

        usort($mobs, static function(ZoneMob $left, ZoneMob $right): int {
            return $right->getTicksLived() <=> $left->getTicksLived();
        });

        foreach (array_slice($mobs, 0, $amount) as $mob) {
            $mob->flagForDespawn();
        }
    }

    private static function pickMobId(array $mobs): ?string
    {
        $total = 0;
        foreach ($mobs as $mob) {
            if (is_array($mob)) {
                $total += max(1, (int) ($mob["weight"] ?? 1));
            }
        }

        if ($total <= 0) {
            return null;
        }

        $roll = mt_rand(1, $total);
        foreach ($mobs as $id => $mob) {
            if (!is_array($mob)) {
                continue;
            }

            $roll -= max(1, (int) ($mob["weight"] ?? 1));
            if ($roll <= 0) {
                return strtolower((string) $id);
            }
        }

        return null;
    }

    private static function getMobConfig(string $zoneId, string $mobId): ?array
    {
        $zone = self::$zones[strtolower($zoneId)] ?? null;
        if (!is_array($zone)) {
            return null;
        }

        $pool = WorldEventManager::getSpawnMobPool(strtolower($zoneId), (array) ($zone["mobs"] ?? []));
        $mob = $pool[strtolower($mobId)] ?? null;
        return is_array($mob) ? $mob : null;
    }

    private static function createZoneMob(string $entityId, Location $location, CompoundTag $nbt): ZoneMob
    {
        return match (strtolower(trim($entityId))) {
            "blaze" => new ZoneMobBlaze($location, $nbt),
            "creeper" => new ZoneMobCreeper($location, $nbt),
            "enderman" => new ZoneMobEnderman($location, $nbt),
            "husk" => new ZoneMobHusk($location, $nbt),
            "iron_golem", "irongolem" => new ZoneMobIronGolem($location, $nbt),
            "magma_cube", "magmacube" => new ZoneMobMagmaCube($location, $nbt),
            "zombie_pigman", "pigman", "zombiepigman" => new ZoneMobPigman($location, $nbt),
            "skeleton" => new ZoneMobSkeleton($location, $nbt),
            "slime" => new ZoneMobSlime($location, $nbt),
            "snow_golem", "snowman", "snowgolem" => new ZoneMobSnowGolem($location, $nbt),
            "spider" => new ZoneMobSpider($location, $nbt),
            default => new ZoneMob($location, $nbt),
        };
    }

    /**
     * @param array<string, mixed> $mobConfig
     */
    public static function spawnConfiguredMob(Position $position, string $zoneId, string $mobId, array $mobConfig): ?ZoneMob
    {
        $displayName = (string) ($mobConfig["name"] ?? $mobConfig["display"] ?? ucfirst($mobId));
        $nbt = self::buildMobNbt($zoneId, strtolower($mobId), $mobConfig, $displayName);
        $entity = self::createZoneMob((string) ($mobConfig["entity"] ?? "zombie"), Location::fromObject($position, $position->getWorld()), $nbt);
        $entity->spawnToAll();
        return $entity;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function getMobProfile(string $zoneId, string $mobId): ?array
    {
        return self::getMobConfig($zoneId, $mobId);
    }

    private static function spawnEliteFromKill(Player $player, ZoneMob $mob, array $zoneConfig): void
    {
        $zoneId = strtolower($mob->getZoneId());
        $mobId = strtolower($mob->getMobId());
        $baseMob = self::getMobConfig($zoneId, $mobId);
        if ($baseMob === null) {
            return;
        }

        $zone = ZoneManager::getZone($zoneId);
        if ($zone === null) {
            return;
        }

        $origin = $mob->getPosition();
        $position = ZoneManager::getRandomSafePositionNear($origin, $zone, 12, self::$edgePadding)
            ?? ZoneManager::getRandomSafePosition($zone, self::$edgePadding);
        if (!$position instanceof Position) {
            return;
        }

        $eliteType = self::rollEliteType();
        $eliteDisplay = self::getEliteTypePrefix($eliteType) . (string) ($baseMob["name"] ?? $baseMob["display"] ?? ucfirst($mobId));
        $eliteConfig = $baseMob;
        $eliteConfig["health"] = (int) ceil(max(10, (int) ($baseMob["health"] ?? 30)) * self::$eliteHealthMultiplier);
        $eliteConfig["damage"] = (float) ((float) ($baseMob["damage"] ?? 4.0) * self::$eliteDamageMultiplier);
        $eliteConfig["speed"] = min(0.24, (float) ((float) ($baseMob["speed"] ?? 0.19) * self::$eliteSpeedMultiplier));
        $eliteConfig["xp"] = (int) ceil(max(1, (int) ($baseMob["xp"] ?? 20)) * self::$eliteXpMultiplier);
        $eliteConfig["name"] = $eliteDisplay;
        $eliteConfig["elite"] = true;
        $eliteConfig["elite-type"] = $eliteType;
        $eliteConfig["loot-multiplier"] = max(1.0, self::$eliteLootMultiplier);
        $eliteConfig = self::applyEliteTypeAdjustments($eliteConfig, $eliteType);
        $eliteConfig = WorldEventManager::applyMobConfigModifiers($zoneId, $eliteConfig);

        $nbt = self::buildMobNbt($zoneId, $mobId, $eliteConfig, $eliteDisplay);
        $entity = self::createZoneMob((string) ($eliteConfig["entity"] ?? "zombie"), Location::fromObject($position, $position->getWorld()), $nbt);
        $entity->spawnToAll();

        $message = (string) ($zoneConfig["elite-broadcast"] ?? "&r&6⚠ &fAn elite mob has spawned in &b{zone}&f! ({player})");
        $zoneName = C::clean((string) ($zone["display"] ?? $zoneId));
        $message = str_replace(["{zone}", "{player}"], [$zoneName, $player->getName()], $message);
        foreach (Server::getInstance()->getOnlinePlayers() as $online) {
            $online->sendMessage(C::colorize($message));
        }
    }

    private static function rollNextEliteThreshold(): int
    {
        return mt_rand(self::$eliteMinKills, self::$eliteMaxKills);
    }

    public static function spawnSplitterOffspring(ZoneMob $mob, int $count = 2): void
    {
        $zoneId = strtolower($mob->getZoneId());
        $mobId = strtolower($mob->getMobId());
        $baseMob = self::getMobConfig($zoneId, $mobId);
        $zone = ZoneManager::getZone($zoneId);
        if ($baseMob === null || $zone === null) {
            return;
        }

        $origin = $mob->getPosition();
        for ($i = 0; $i < max(1, $count); $i++) {
            $offsetX = $i === 0 ? 0.0 : (mt_rand(-6, 6) / 10);
            $offsetZ = $i === 0 ? 0.0 : (mt_rand(-6, 6) / 10);
            $position = Position::fromObject($origin->add($offsetX, 0, $offsetZ), $origin->getWorld());
            if (!$position instanceof Position) {
                continue;
            }

            $splitConfig = $baseMob;
            $splitConfig["health"] = max(8, (int) ceil(max(10, (int) ($baseMob["health"] ?? 30)) * 0.55));
            $splitConfig["damage"] = max(1.0, (float) ((float) ($baseMob["damage"] ?? 4.0) * 0.6));
            $splitConfig["speed"] = min(0.30, max(0.10, (float) ((float) ($baseMob["speed"] ?? 0.19) * 1.08)));
            $splitConfig["xp"] = max(1, (int) ceil(max(1, (int) ($baseMob["xp"] ?? 20)) * 0.35));
            $splitConfig["elite"] = false;
            $splitConfig["elite-type"] = "";
            $splitConfig["loot-multiplier"] = 1.0;
            $splitConfig["name"] = "&7Split " . (string) ($baseMob["name"] ?? $baseMob["display"] ?? ucfirst($mobId));

            $nbt = self::buildMobNbt($zoneId, $mobId, $splitConfig, (string) $splitConfig["name"]);
            $entity = self::createZoneMob((string) ($splitConfig["entity"] ?? "zombie"), Location::fromObject($position, $position->getWorld()), $nbt);
            $entity->spawnToAll();
        }
    }

    /**
     * @param list<string> $types
     * @return list<string>
     */
    private static function sanitizeEliteTypes(array $types): array
    {
        $sanitized = [];
        foreach ($types as $type) {
            $type = strtolower(trim((string) $type));
            if ($type !== "" && in_array($type, self::SUPPORTED_ELITE_TYPES, true) && !in_array($type, $sanitized, true)) {
                $sanitized[] = $type;
            }
        }

        return $sanitized !== [] ? $sanitized : ["exploding"];
    }

    private static function rollEliteType(): string
    {
        $types = self::$eliteTypes !== [] ? self::$eliteTypes : ["exploding"];
        return $types[array_rand($types)];
    }

    private static function getEliteTypePrefix(string $type): string
    {
        return match ($type) {
            "exploding" => "&r&l&cEXPLODING &r",
            "imploding" => "&r&l&5IMPLODING &r",
            "molten" => "&r&l&6MOLTEN &r",
            "frost" => "&r&l&bFROST &r",
            "charged" => "&r&l&eCHARGED &r",
            "gravitic" => "&r&l&9GRAVITIC &r",
            "reinforced" => "&r&l&8REINFORCED &r",
            "leeching" => "&r&l&4LEECHING &r",
            "corrupted" => "&r&l&dCORRUPTED &r",
            "splitter" => "&r&l&7SPLITTER &r",
            default => self::$eliteNamePrefix,
        };
    }

    private static function applyEliteTypeAdjustments(array $config, string $type): array
    {
        switch ($type) {
            case "charged":
                $config["speed"] = min(0.31, (float) ($config["speed"] ?? 0.19) * 1.16);
                break;
            case "reinforced":
                $config["health"] = (int) ceil((int) ($config["health"] ?? 30) * 1.18);
                break;
            case "leeching":
                $config["health"] = (int) ceil((int) ($config["health"] ?? 30) * 1.08);
                break;
            case "gravitic":
                $config["target-range"] = min(28.0, (float) ($config["target-range"] ?? 15.0) + 2.0);
                break;
            case "splitter":
                $config["speed"] = min(0.28, (float) ($config["speed"] ?? 0.19) * 1.06);
                break;
        }

        return $config;
    }

    /**
     * @param array<string, mixed> $zoneConfig
     * @return list<int>
     */
    private static function resolveStreakBroadcastMilestones(array $zoneConfig): array
    {
        $raw = (array) ($zoneConfig["streak-broadcast-milestones"] ?? self::$streakBroadcastMilestones);
        $milestones = array_values(array_unique(array_map(static fn(mixed $value): int => max(1, (int) $value), $raw)));
        sort($milestones);
        return $milestones;
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function broadcastStreakMilestone(Player $player, string $zoneId, int $count, array $config): void
    {
        $zone = ZoneManager::getZone($zoneId);
        $message = (string) ($config["message"] ?? "&r&b{player} &7has reached a &f{count}&7 kill streak in &f{zone}&7.");
        $message = str_replace(
            ["{player}", "{count}", "{zone}"],
            [$player->getName(), (string) $count, C::clean((string) (($zone["display"] ?? $zoneId) ?? $zoneId))],
            $message
        );

        foreach (Server::getInstance()->getOnlinePlayers() as $online) {
            $online->sendMessage(C::colorize($message));
        }
    }

    public static function announceDropRolls(Player $player, ZoneMob $mob): void
    {
        $zoneId = strtolower($mob->getZoneId());
        foreach ($mob->getLastDropResults() as $result) {
            $item = $result["item"] ?? null;
            $entry = $result["entry"] ?? null;
            if (!$item instanceof Item || !is_array($entry)) {
                continue;
            }

            WorldEventManager::announceConfiguredReward(
                $player,
                $entry,
                $item,
                $zoneId,
                C::clean($mob->getName()),
                [
                    "mob" => C::clean($mob->getName()),
                    "event" => WorldEventManager::getActiveEventName($zoneId),
                ]
            );
        }
    }

    private static function grantStreakReward(Player $player, string $zoneId, int $count, array $rewardConfig): void
    {
        $rewards = [];
        foreach ((array) ($rewardConfig["rewards"] ?? []) as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $item = RewardResolver::resolveItem($entry, $player);
            if ($item !== null) {
                $rewards[] = $item;
            }
        }

        $session = Loader::getPlayerManager()->getSession($player);
        foreach ($player->getInventory()->addItem(...$rewards) as $leftover) {
            $session?->addItemToCollection($leftover);
        }

        $zone = ZoneManager::getZone($zoneId);
        $message = (string) ($rewardConfig["broadcast"] ?? "&r&b{player} &7has reached a &f{count}&7 kill streak in &f{zone}&7.");
        $message = str_replace(
            ["{player}", "{count}", "{zone}"],
            [$player->getName(), (string) $count, C::clean((string) ($zone["display"] ?? $zoneId))],
            $message
        );

        foreach (Server::getInstance()->getOnlinePlayers() as $online) {
            $online->sendMessage(C::colorize($message));
        }
    }
}
