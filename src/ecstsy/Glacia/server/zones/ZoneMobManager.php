<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\zones;

use ecstsy\Glacia\entity\zones\ZoneMob;
use ecstsy\Glacia\entity\zones\ZoneMobBlaze;
use ecstsy\Glacia\entity\zones\ZoneMobCreeper;
use ecstsy\Glacia\entity\zones\ZoneMobEnderman;
use ecstsy\Glacia\entity\zones\ZoneMobIronGolem;
use ecstsy\Glacia\entity\zones\ZoneMobMagmaCube;
use ecstsy\Glacia\entity\zones\ZoneMobPigman;
use ecstsy\Glacia\entity\zones\ZoneMobSkeleton;
use ecstsy\Glacia\entity\zones\ZoneMobSlime;
use ecstsy\Glacia\entity\zones\ZoneMobSnowGolem;
use ecstsy\Glacia\entity\zones\ZoneMobSpider;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\RewardResolver;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\entity\Location;
use pocketmine\item\Item;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat as C;
use pocketmine\world\Position;

final class ZoneMobManager {

    private static int $tickIntervalSeconds = 8;
    private static int $edgePadding = 16;
    private static int $activationRange = 56;
    private static int $defaultBaseAlive = 3;
    private static int $defaultMobsPerPlayer = 2;
    private static int $defaultMaxAlive = 12;
    private static int $defaultAnchorRadius = 22;
    /** @var array<string, array<string, mixed>> */
    private static array $zones = [];
    /** @var array<string, array{zone: string, count: int}> */
    private static array $playerStreaks = [];

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
        self::$zones = (array) ($config?->get("zones", []) ?? []);
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
            "desired" => self::getDesiredAliveCount($config, $playersInZone),
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
            "count" => 0
        ];
    }

    public static function clearPlayerState(Player $player): void
    {
        unset(self::$playerStreaks[$player->getUniqueId()->toString()]);
    }

    public static function handleMobKill(Player $player, ZoneMob $mob): void
    {
        $zoneId = strtolower($mob->getZoneId());
        $key = $player->getUniqueId()->toString();
        $state = self::$playerStreaks[$key] ?? ["zone" => $zoneId, "count" => 0];
        if (($state["zone"] ?? null) !== $zoneId) {
            $state = ["zone" => $zoneId, "count" => 0];
        }

        $state["count"] = (int) ($state["count"] ?? 0) + 1;
        self::$playerStreaks[$key] = $state;

        $config = self::getZoneConfig($zoneId);
        if ($config === null) {
            return;
        }

        foreach ((array) ($config["streak-rewards"] ?? []) as $threshold => $rewardConfig) {
            if ((int) $threshold !== $state["count"] || !is_array($rewardConfig)) {
                continue;
            }

            self::grantStreakReward($player, $zoneId, $state["count"], $rewardConfig);
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
        $desiredAlive = self::getDesiredAliveCount($config, $playersInZone);

        if ($alive > $desiredAlive) {
            self::trimExcess($zoneId, $zone, $alive - $desiredAlive);
            $alive = self::countAlive($zoneId);
        }

        if ($playersInZone <= 0 || !self::hasPlayerNearZone($zone) || $alive >= $desiredAlive) {
            return;
        }

        $batch = max(1, min(4, (int) ($config["spawn-batch"] ?? 1)));
        for ($i = 0; $i < min($batch, $desiredAlive - $alive); $i++) {
            if (!self::spawnOneNearAnchor($zoneId, $zone, $config, $anchor)) {
                break;
            }
            $alive++;
        }
    }

    /**
     * @return Item[]
     */
    public static function rollDrops(string $zoneId, string $mobId): array
    {
        $mob = self::getMobConfig($zoneId, $mobId);
        if ($mob === null) {
            return [];
        }

        $drops = [];
        foreach ((array) ($mob["rewards"] ?? []) as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $chance = max(0, min(100, (int) ($entry["chance"] ?? 100)));
            if ($chance < 100 && mt_rand(1, 100) > $chance) {
                continue;
            }

            $item = RewardResolver::resolveItem($entry);
            if ($item !== null) {
                $drops[] = $item;
            }
        }

        return $drops;
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
        $mobId = self::pickMobId((array) ($config["mobs"] ?? []));
        if ($mobId === null) {
            return false;
        }

        $mob = self::getMobConfig($zoneId, $mobId);
        if ($mob === null) {
            return false;
        }

        $anchorRadius = max(6, (int) ($config["anchor-radius"] ?? self::$defaultAnchorRadius));
        $position = ZoneManager::getRandomSafePositionNear($anchor, $zone, $anchorRadius, self::$edgePadding)
            ?? ZoneManager::getRandomSafePosition($zone, self::$edgePadding);
        if (!$position instanceof Position) {
            return false;
        }

        $displayName = (string) ($mob["name"] ?? $mob["display"] ?? ucfirst($mobId));
        $nbt = CompoundTag::create()
            ->setString("zone_id", $zoneId)
            ->setString("zone_mob_id", $mobId)
            ->setString("zone_mob_name", $displayName)
            ->setInt("zone_mob_health", max(10, (int) ($mob["health"] ?? 30)))
            ->setFloat("zone_mob_damage", max(1.0, (float) ($mob["damage"] ?? 4.0)))
            ->setFloat("zone_mob_speed", max(0.08, min(0.24, (float) ($mob["speed"] ?? 0.19))))
            ->setFloat("zone_mob_target_range", max(8.0, min(22.0, (float) ($mob["target-range"] ?? 15.0))))
            ->setInt("zone_mob_xp", max(1, (int) ($mob["xp"] ?? 20)));

        $entity = self::createZoneMob((string) ($mob["entity"] ?? "zombie"), Location::fromObject($position, $position->getWorld()), $nbt);
        $entity->spawnToAll();
        return true;
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

    private static function getDesiredAliveCount(array $config, int $playersInZone): int
    {
        if ($playersInZone <= 0) {
            return 0;
        }

        $baseAlive = max(0, (int) ($config["base-alive"] ?? self::$defaultBaseAlive));
        $mobsPerPlayer = max(0, (int) ($config["mobs-per-player"] ?? self::$defaultMobsPerPlayer));
        $maxAlive = max(1, (int) ($config["max-alive"] ?? self::$defaultMaxAlive));

        return min($maxAlive, $baseAlive + ($playersInZone * $mobsPerPlayer));
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

        $mob = ((array) ($zone["mobs"] ?? []))[strtolower($mobId)] ?? null;
        return is_array($mob) ? $mob : null;
    }

    private static function createZoneMob(string $entityId, Location $location, CompoundTag $nbt): ZoneMob
    {
        return match (strtolower(trim($entityId))) {
            "skeleton", "stray" => new ZoneMobSkeleton($location, $nbt),
            "spider" => new ZoneMobSpider($location, $nbt),
            "creeper" => new ZoneMobCreeper($location, $nbt),
            "blaze" => new ZoneMobBlaze($location, $nbt),
            "enderman" => new ZoneMobEnderman($location, $nbt),
            "zombie_pigman", "pigman", "zombified_piglin" => new ZoneMobPigman($location, $nbt),
            "slime" => new ZoneMobSlime($location, $nbt),
            "magma_cube", "magma" => new ZoneMobMagmaCube($location, $nbt),
            "iron_golem" => new ZoneMobIronGolem($location, $nbt),
            "snow_golem", "snowman" => new ZoneMobSnowGolem($location, $nbt),
            default => new ZoneMob($location, $nbt),
        };
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
