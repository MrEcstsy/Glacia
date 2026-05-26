<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\world\events;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\entity\boss\BossEntity;
use ecstsy\Glacia\entity\boss\bosses\AstralDevourer;
use ecstsy\Glacia\entity\boss\bosses\GlacialHydra;
use ecstsy\Glacia\entity\boss\bosses\TitanKnight;
use ecstsy\Glacia\entity\zones\ZoneMob;
use ecstsy\Glacia\server\zones\ZoneManager;
use ecstsy\Glacia\server\zones\ZoneMobManager;
use ecstsy\Glacia\utils\RewardResolver;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\block\Beetroot;
use pocketmine\block\Block;
use pocketmine\block\Carrot;
use pocketmine\block\Liquid;
use pocketmine\block\NetherWartPlant;
use pocketmine\block\Potato;
use pocketmine\block\VanillaBlocks;
use pocketmine\block\Wheat;
use pocketmine\color\Color;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as C;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\entity\Location;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\world\particle\BlockBreakParticle;
use pocketmine\world\particle\DustParticle;
use pocketmine\world\particle\FlameParticle;
use pocketmine\world\particle\HappyVillagerParticle;
use pocketmine\world\particle\LavaParticle;
use pocketmine\world\particle\PortalParticle;
use pocketmine\world\Position;
use pocketmine\world\World;

final class WorldEventManager
{
    private static bool $enabled = true;
    private static int $defaultEdgePadding = 10;
    private static string $startBroadcast = "&r&l&bWorld Event &8| &r{event} &7has begun in &f{zone}&7 for &b{duration}&7.";
    private static string $endBroadcast = "&r&l&bWorld Event &8| &r{event} &7has ended in &f{zone}&7.";

    /** @var array<string, array<string, mixed>> */
    private static array $zones = [];
    /** @var array<string, int> */
    private static array $nextStartAt = [];
    /** @var array<string, array<string, mixed>> */
    private static array $activeEvents = [];
    /** @var array<string, array<string, mixed>> */
    private static array $activeNodes = [];
    /** @var array<string, array<string, mixed>> */
    private static array $globalEvents = [];
    /** @var array<string, mixed>|null */
    private static ?array $activeWarzoneBossEvent = null;
    private static int $nextWarzoneBossAt = 0;
    /** @var array<string, int> */
    private static array $ambientSoundCooldowns = [];

    public static function loadFromConfig(): void
    {
        self::resetRuntimeState(true);

        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "world_events.yml");
        $settings = (array) ($config?->get("settings", []) ?? []);

        self::$enabled = (bool) ($settings["enabled"] ?? true);
        self::$defaultEdgePadding = max(0, (int) ($settings["default-edge-padding"] ?? 10));
        self::$startBroadcast = (string) ($settings["start-broadcast"] ?? self::$startBroadcast);
        self::$endBroadcast = (string) ($settings["end-broadcast"] ?? self::$endBroadcast);
        self::$zones = [];
        self::$globalEvents = array_filter(
            (array) ($config?->get("global-events", []) ?? []),
            static fn(mixed $entry): bool => is_array($entry)
        );

        foreach ((array) ($config?->get("zones", []) ?? []) as $zoneId => $zoneConfig) {
            if (!is_string($zoneId) || !is_array($zoneConfig)) {
                continue;
            }

            $zoneId = strtolower(trim($zoneId));
            if ($zoneId === "") {
                continue;
            }

            self::$zones[$zoneId] = $zoneConfig;
            self::scheduleNextEvent($zoneId, true);
        }

        self::scheduleNextWarzoneBoss(true);
        self::restoreActiveNodesRuntime();
        self::restoreWarzoneBossRuntime();
    }

    public static function tick(): void
    {
        if (!self::$enabled) {
            return;
        }

        $now = time();
        foreach (array_keys(self::$activeEvents) as $zoneId) {
            $state = self::$activeEvents[$zoneId] ?? null;
            if (!is_array($state)) {
                continue;
            }

            if ($now >= (int) ($state["ends_at"] ?? 0)) {
                self::endEvent($zoneId, true);
                continue;
            }

            self::tickActiveEvent($zoneId, $state, $now);
        }

        foreach (self::$zones as $zoneId => $zoneConfig) {
            if ((bool) ($zoneConfig["enabled"] ?? true) !== true) {
                continue;
            }
            if (isset(self::$activeEvents[$zoneId])) {
                continue;
            }

            $nextAt = (int) (self::$nextStartAt[$zoneId] ?? 0);
            if ($nextAt > 0 && $now >= $nextAt) {
                self::startRandomEvent($zoneId, $zoneConfig, $now);
            }
        }

        self::tickWarzoneBossEvent($now);
    }

    public static function tickPlayer(Player $player): void
    {
        if (!self::$enabled) {
            return;
        }

        $zone = ZoneManager::getZoneAt($player->getPosition());
        if ($zone === null) {
            return;
        }

        $state = self::$activeEvents[strtolower((string) ($zone["id"] ?? ""))] ?? null;
        if (!is_array($state)) {
            return;
        }

        $event = (array) ($state["config"] ?? []);
        self::applyAmbientEffects($player, $event);
        self::playAmbientSound($player, $event, (string) ($state["event_id"] ?? ""));
        self::boostNearbyCrops($player, $event);
    }

    public static function getActiveEvent(string $zoneId): ?array
    {
        $state = self::$activeEvents[strtolower($zoneId)] ?? null;
        return is_array($state) ? $state : null;
    }

    public static function getActiveEventName(string $zoneId): string
    {
        $state = self::getActiveEvent($zoneId);
        if ($state === null) {
            return "";
        }

        return C::clean((string) (($state["config"]["display"] ?? $state["event_id"] ?? "") ?? ""));
    }

    /**
     * @return string[]
     */
    public static function getZoneIds(): array
    {
        return array_keys(self::$zones);
    }

    /**
     * @return string[]
     */
    public static function getEventIds(string $zoneId): array
    {
        $zone = self::$zones[strtolower($zoneId)] ?? null;
        if (!is_array($zone)) {
            return [];
        }

        return array_keys(array_filter((array) ($zone["events"] ?? []), static fn(mixed $event): bool => is_array($event)));
    }

    public static function getNextStartAt(string $zoneId): ?int
    {
        $zoneId = strtolower($zoneId);
        return isset(self::$nextStartAt[$zoneId]) ? (int) self::$nextStartAt[$zoneId] : null;
    }

    public static function setNextDelay(string $zoneId, int $seconds): bool
    {
        $zoneId = strtolower($zoneId);
        if (!isset(self::$zones[$zoneId]) || $seconds <= 0) {
            return false;
        }

        self::$nextStartAt[$zoneId] = time() + $seconds;
        return true;
    }

    public static function setActiveTime(string $zoneId, int $seconds): bool
    {
        $zoneId = strtolower($zoneId);
        if (!isset(self::$activeEvents[$zoneId]) || $seconds <= 0) {
            return false;
        }

        self::$activeEvents[$zoneId]["ends_at"] = time() + $seconds;
        return true;
    }

    public static function forceStart(string $zoneId, ?string $eventId = null): bool
    {
        $zoneId = strtolower($zoneId);
        $zoneConfig = self::$zones[$zoneId] ?? null;
        if (!is_array($zoneConfig) || isset(self::$activeEvents[$zoneId])) {
            return false;
        }

        if ($eventId !== null && trim($eventId) !== "") {
            return self::startEvent($zoneId, strtolower(trim($eventId)), $zoneConfig, time());
        }

        return self::startRandomEvent($zoneId, $zoneConfig, time());
    }

    public static function forceStop(string $zoneId, bool $broadcast = true): bool
    {
        $zoneId = strtolower($zoneId);
        if (!isset(self::$activeEvents[$zoneId])) {
            return false;
        }

        self::endEvent($zoneId, $broadcast);
        return true;
    }

    public static function getSpawnMultiplier(string $zoneId): float
    {
        return max(1.0, (float) self::readActiveEventValue($zoneId, "spawn-multiplier", 1.0));
    }

    public static function getKillProgressPerKill(string $zoneId): int
    {
        return max(1, (int) self::readActiveEventValue($zoneId, "kill-progress-per-kill", 1));
    }

    public static function getLootMultiplier(string $zoneId): float
    {
        return max(1.0, (float) self::readActiveEventValue($zoneId, "loot-multiplier", 1.0));
    }

    /**
     * @param array<string, mixed> $basePool
     * @return array<string, mixed>
     */
    public static function getSpawnMobPool(string $zoneId, array $basePool): array
    {
        $state = self::getActiveEvent($zoneId);
        if ($state === null) {
            return $basePool;
        }

        foreach ((array) (($state["config"]["extra-mobs"] ?? []) ?? []) as $mobId => $mobConfig) {
            if (!is_string($mobId) || !is_array($mobConfig)) {
                continue;
            }

            $mobConfig["event-extra-mob"] = true;
            $basePool[strtolower($mobId)] = $mobConfig;
        }

        return $basePool;
    }

    /**
     * @param array<string, mixed> $mobConfig
     * @return array<string, mixed>
     */
    public static function applyMobConfigModifiers(string $zoneId, array $mobConfig): array
    {
        $state = self::getActiveEvent($zoneId);
        if ($state === null) {
            return $mobConfig;
        }

        $event = (array) ($state["config"] ?? []);
        $multipliers = (array) ($event["mob-stat-multipliers"] ?? []);

        if (isset($mobConfig["health"])) {
            $mobConfig["health"] = (int) ceil((float) $mobConfig["health"] * max(0.1, (float) ($multipliers["health"] ?? 1.0)));
        }
        if (isset($mobConfig["damage"])) {
            $mobConfig["damage"] = (float) $mobConfig["damage"] * max(0.1, (float) ($multipliers["damage"] ?? 1.0));
        }
        if (isset($mobConfig["speed"])) {
            $mobConfig["speed"] = min(0.34, (float) $mobConfig["speed"] * max(0.1, (float) ($multipliers["speed"] ?? 1.0)));
        }
        if (isset($mobConfig["xp"])) {
            $mobConfig["xp"] = (int) ceil((float) $mobConfig["xp"] * max(0.1, (float) ($multipliers["xp"] ?? 1.0)));
        }

        $behavior = (array) ($event["mob-behavior"] ?? []);
        if (isset($behavior["teleport-chance"])) {
            $mobConfig["event-teleport-chance"] = max(0, min(100, (int) $behavior["teleport-chance"]));
        }

        $mobConfig["world-event-id"] = (string) ($state["event_id"] ?? "");
        $mobConfig["world-event-name"] = (string) ($event["display"] ?? ($state["event_id"] ?? ""));

        return $mobConfig;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function getExtraRewardEntries(string $zoneId): array
    {
        $state = self::getActiveEvent($zoneId);
        if ($state === null) {
            return [];
        }

        return array_values(array_filter(
            (array) (($state["config"]["extra-rewards"] ?? []) ?? []),
            static fn(mixed $entry): bool => is_array($entry)
        ));
    }

    public static function getSellMultiplier(Player $player): float
    {
        $zone = ZoneManager::getZoneAt($player->getPosition());
        if ($zone === null) {
            return 1.0;
        }

        $state = self::getActiveEvent((string) ($zone["id"] ?? ""));
        if ($state === null) {
            return 1.0;
        }

        return max(1.0, (float) (((array) (($state["config"]["economy"] ?? []) ?? []))["sell-multiplier"] ?? 1.0));
    }

    /**
     * @param array<int, array<string, mixed>> $table
     * @return array<int, array<string, mixed>>
     */
    public static function decorateFishingLootTable(Player $player, array $table): array
    {
        $zone = ZoneManager::getZoneAt($player->getPosition());
        if ($zone === null) {
            return $table;
        }

        $state = self::getActiveEvent((string) ($zone["id"] ?? ""));
        if ($state === null) {
            return $table;
        }

        $fishing = (array) (($state["config"]["fishing"] ?? []) ?? []);
        if (((bool) ($fishing["enabled"] ?? false)) !== true) {
            return $table;
        }

        $rareMultiplier = max(0.1, (float) ($fishing["rare-chance-multiplier"] ?? 1.0));
        $epicMultiplier = max(0.1, (float) ($fishing["epic-chance-multiplier"] ?? $rareMultiplier));
        $uncommonMultiplier = max(0.1, (float) ($fishing["uncommon-chance-multiplier"] ?? 1.0));

        foreach ($table as &$entry) {
            if (!is_array($entry)) {
                continue;
            }

            $chance = max(1.0, (float) ($entry["chance"] ?? 1));
            $rarity = strtolower((string) ($entry["rarity"] ?? ""));
            if ($rarity === "") {
                $rarity = match (true) {
                    $chance <= 2.0 => "epic",
                    $chance <= 8.0 => "rare",
                    $chance <= 18.0 => "uncommon",
                    default => "common",
                };
            }

            $multiplier = match ($rarity) {
                "epic" => $epicMultiplier,
                "rare" => $rareMultiplier,
                "uncommon" => $uncommonMultiplier,
                default => 1.0,
            };
            $entry["chance"] = max(1, (int) round($chance * $multiplier));
        }
        unset($entry);

        foreach ((array) ($fishing["extra-rewards"] ?? []) as $entry) {
            if (is_array($entry)) {
                $table[] = $entry;
            }
        }

        return $table;
    }

    public static function handleFishingCatchBroadcast(Player $player, array $entry, Item $item): void
    {
        $zone = ZoneManager::getZoneAt($player->getPosition());
        if ($zone === null) {
            return;
        }

        self::announceConfiguredReward(
            $player,
            $entry,
            $item,
            (string) ($zone["id"] ?? ""),
            "Fishing",
            ["event" => self::getActiveEventName((string) ($zone["id"] ?? ""))]
        );
    }

    public static function handleNodeBreak(\pocketmine\event\block\BlockBreakEvent $event): bool
    {
        $position = $event->getBlock()->getPosition();
        $key = self::nodeKey($position);
        $record = self::$activeNodes[$key] ?? null;
        if (!is_array($record)) {
            return false;
        }

        $event->cancel();
        $world = $position->getWorld();
        $original = $record["original_block"] ?? null;
        if ($original instanceof Block) {
            $world->setBlock($position, $original);
        } else {
            $world->setBlock($position, VanillaBlocks::AIR());
        }

        unset(self::$activeNodes[$key]);
        self::persistActiveNodesRuntime();

        $player = $event->getPlayer();
        $zoneId = (string) ($record["zone_id"] ?? "");
        $nodeName = (string) ($record["display"] ?? "Event Node");
        $granted = self::grantRewardEntries($player, (array) ($record["rewards"] ?? []), $zoneId, $nodeName, [
            "node" => $nodeName,
            "event" => self::getActiveEventName($zoneId),
        ]);

        $particleBlock = $record["particle_block"] ?? null;
        if ($particleBlock instanceof Block) {
            for ($i = 0; $i < 10; $i++) {
                $world->addParticle($position->add(mt_rand(-5, 5) / 10, mt_rand(0, 10) / 10, mt_rand(-5, 5) / 10), new BlockBreakParticle($particleBlock));
            }
        }

        $player->sendMessage(C::colorize("&r&bYou harvested &f{$nodeName}&b and found &f" . max(1, $granted) . " &breward" . ($granted === 1 ? "" : "s") . "&b."));
        return true;
    }

    public static function announceConfiguredReward(Player $player, array $entry, Item $item, string $zoneId, string $sourceName, array $extra = []): void
    {
        $template = trim((string) ($entry["broadcast"] ?? ""));
        if ($template === "") {
            return;
        }

        $zone = ZoneManager::getZone($zoneId);
        $replacements = array_merge([
            "{player}" => $player->getName(),
            "{zone}" => C::clean((string) (($zone["display"] ?? $zoneId) ?? $zoneId)),
            "{item}" => C::clean($item->getCustomName() !== "" ? $item->getCustomName() : $item->getName()),
            "{count}" => (string) $item->getCount(),
            "{source}" => $sourceName,
            "{event}" => (string) ($extra["event"] ?? self::getActiveEventName($zoneId)),
            "{mob}" => (string) ($extra["mob"] ?? $sourceName),
            "{node}" => (string) ($extra["node"] ?? $sourceName),
        ], []);

        $message = str_replace(array_keys($replacements), array_values($replacements), $template);
        foreach (Server::getInstance()->getOnlinePlayers() as $online) {
            $session = Loader::getPlayerManager()->getSession($online);
            if ((bool) ($session?->getSetting("loot_announcer") ?? true)) {
                $online->sendMessage(C::colorize($message));
            }
        }
    }

    private static function tickActiveEvent(string $zoneId, array $state, int $now): void
    {
        $event = (array) ($state["config"] ?? []);
        $nodes = (array) ($event["nodes"] ?? []);
        if ((bool) ($nodes["enabled"] ?? false) && $now >= (int) ($state["next_node_spawn_at"] ?? 0)) {
            self::spawnNode($zoneId, $event);
            self::$activeEvents[$zoneId]["next_node_spawn_at"] = $now + max(5, (int) ($nodes["spawn-interval-seconds"] ?? 30));
        }
    }

    private static function startRandomEvent(string $zoneId, array $zoneConfig, int $now): bool
    {
        $eventId = self::pickWeightedEventId((array) ($zoneConfig["events"] ?? []));
        if ($eventId === null) {
            self::scheduleNextEvent($zoneId, false);
            return false;
        }

        return self::startEvent($zoneId, $eventId, $zoneConfig, $now);
    }

    private static function startEvent(string $zoneId, string $eventId, array $zoneConfig, int $now): bool
    {
        $eventConfig = (array) (((array) ($zoneConfig["events"] ?? []))[$eventId] ?? []);
        if ($eventConfig === [] || (bool) ($eventConfig["enabled"] ?? true) !== true) {
            return false;
        }

        $duration = max(60, (int) ($eventConfig["duration-seconds"] ?? 900));
        self::$activeEvents[$zoneId] = [
            "zone_id" => $zoneId,
            "event_id" => $eventId,
            "config" => $eventConfig,
            "started_at" => $now,
            "ends_at" => $now + $duration,
            "next_node_spawn_at" => $now + max(5, (int) (((array) ($eventConfig["nodes"] ?? []))["spawn-interval-seconds"] ?? 30)),
            "boss_entity_id" => null,
            "boss_world" => null,
        ];

        self::broadcastEventMessage(self::$startBroadcast, $zoneId, $eventConfig, $duration);
        self::maybeSpawnBoss($zoneId, $eventConfig);
        return true;
    }

    private static function endEvent(string $zoneId, bool $broadcast): void
    {
        $state = self::$activeEvents[$zoneId] ?? null;
        if (!is_array($state)) {
            self::scheduleNextEvent($zoneId, false);
            return;
        }

        foreach (array_keys(self::$activeNodes) as $key) {
            $record = self::$activeNodes[$key] ?? null;
            if (!is_array($record) || (string) ($record["zone_id"] ?? "") !== $zoneId) {
                continue;
            }

            $world = Server::getInstance()->getWorldManager()->getWorldByName((string) ($record["world"] ?? ""));
            if ($world !== null) {
                $position = new Position((int) $record["x"], (int) $record["y"], (int) $record["z"], $world);
                $original = $record["original_block"] ?? null;
                $world->setBlock($position, $original instanceof Block ? $original : VanillaBlocks::AIR());
            }
            unset(self::$activeNodes[$key]);
        }
        self::persistActiveNodesRuntime();

        $bossWorldName = (string) ($state["boss_world"] ?? "");
        $bossEntityId = $state["boss_entity_id"] ?? null;
        if ($bossWorldName !== "" && is_int($bossEntityId)) {
            $world = Server::getInstance()->getWorldManager()->getWorldByName($bossWorldName);
            $boss = $world?->getEntity($bossEntityId);
            if ($boss instanceof ZoneMob && !$boss->isClosed()) {
                $boss->flagForDespawn();
            }
        }

        unset(self::$activeEvents[$zoneId]);

        if ($broadcast) {
            self::broadcastEventMessage(self::$endBroadcast, $zoneId, (array) ($state["config"] ?? []), 0);
        }
        self::scheduleNextEvent($zoneId, false);
    }

    private static function scheduleNextEvent(string $zoneId, bool $initialLoad): void
    {
        $zoneConfig = self::$zones[$zoneId] ?? null;
        if (!is_array($zoneConfig)) {
            return;
        }

        $interval = $zoneConfig["interval-seconds"] ?? ["min" => 10800, "max" => 14400];
        $min = 10800;
        $max = 14400;
        if (is_array($interval)) {
            $min = max(60, (int) ($interval["min"] ?? $min));
            $max = max($min, (int) ($interval["max"] ?? $max));
        } elseif (is_numeric($interval)) {
            $min = max(60, (int) $interval);
            $max = $min;
        }

        $delay = mt_rand($min, $max);
        if ($initialLoad && isset($zoneConfig["initial-delay-seconds"])) {
            $delay = max(10, (int) $zoneConfig["initial-delay-seconds"]);
        }
        self::$nextStartAt[$zoneId] = time() + $delay;
    }

    private static function maybeSpawnBoss(string $zoneId, array $eventConfig): void
    {
        $boss = (array) ($eventConfig["boss"] ?? []);
        if (((bool) ($boss["enabled"] ?? false)) !== true) {
            return;
        }

        $chance = max(0, min(100, (int) ($boss["spawn-chance"] ?? 0)));
        if ($chance <= 0 || mt_rand(1, 100) > $chance) {
            return;
        }

        $zone = ZoneManager::getZone($zoneId);
        $position = $zone !== null ? ZoneManager::getRandomSafePosition($zone, self::$defaultEdgePadding) : null;
        if (!$position instanceof Position) {
            return;
        }

        $mobId = strtolower((string) ($boss["mob-id"] ?? ($boss["id"] ?? "event_boss")));
        $mob = ZoneMobManager::spawnConfiguredMob($position, $zoneId, $mobId, self::applyMobConfigModifiers($zoneId, $boss));
        if (!$mob instanceof ZoneMob) {
            return;
        }

        self::$activeEvents[$zoneId]["boss_entity_id"] = $mob->getId();
        self::$activeEvents[$zoneId]["boss_world"] = $mob->getWorld()->getFolderName();

        $message = (string) ($boss["broadcast"] ?? "&r&6Boss Alert &8| &r{event} &7has awakened &f{boss} &7inside &f{zone} &7at &f{coords}&7.");
        $zoneName = C::clean((string) (($zone["display"] ?? $zoneId) ?? $zoneId));
        $coords = self::formatCoords($position);
        $message = str_replace(
            ["{event}", "{zone}", "{boss}", "{coords}"],
            [C::clean((string) ($eventConfig["display"] ?? "Event")), $zoneName, C::clean((string) ($boss["display"] ?? "Event Boss")), $coords],
            $message
        );
        self::broadcastToOnline(C::colorize($message), "broadcasts");
    }

    private static function spawnNode(string $zoneId, array $eventConfig): void
    {
        $nodeConfig = (array) ($eventConfig["nodes"] ?? []);
        if (((bool) ($nodeConfig["enabled"] ?? false)) !== true) {
            return;
        }

        $maxActive = max(1, (int) ($nodeConfig["max-active"] ?? 8));
        $activeCount = 0;
        foreach (self::$activeNodes as $record) {
            if ((string) ($record["zone_id"] ?? "") === $zoneId) {
                $activeCount++;
            }
        }
        if ($activeCount >= $maxActive) {
            return;
        }

        $zone = ZoneManager::getZone($zoneId);
        if ($zone === null) {
            return;
        }

        $world = ZoneManager::getWorld($zone);
        if ($world === null) {
            return;
        }

        $attempts = max(1, (int) ($nodeConfig["spawn-attempts"] ?? 6));
        for ($i = 0; $i < $attempts; $i++) {
            $candidate = ZoneManager::getRandomSafePosition($zone, self::$defaultEdgePadding);
            if (!$candidate instanceof Position) {
                continue;
            }

            $position = new Position($candidate->getFloorX(), $candidate->getFloorY(), $candidate->getFloorZ(), $world);
            $blockAt = $world->getBlock($position);
            if (!$blockAt->canBeReplaced()) {
                continue;
            }

            $block = self::resolveConfiguredBlock((string) ($nodeConfig["block"] ?? "packed_ice"));
            if (!$block instanceof Block) {
                continue;
            }

            $world->setBlock($position, $block);
            $key = self::nodeKey($position);
            self::$activeNodes[$key] = [
                "zone_id" => $zoneId,
                "event_id" => (string) ($eventConfig["display"] ?? ""),
                "world" => $world->getFolderName(),
                "x" => $position->getFloorX(),
                "y" => $position->getFloorY(),
                "z" => $position->getFloorZ(),
                "display" => (string) ($nodeConfig["display"] ?? "Event Node"),
                "original_block" => $blockAt,
                "original_state_id" => $blockAt->getStateId(),
                "node_state_id" => $block->getStateId(),
                "particle_block" => $block,
                "rewards" => (array) ($nodeConfig["rewards"] ?? []),
            ];
            self::persistActiveNodesRuntime();

            for ($burst = 0; $burst < 8; $burst++) {
                $world->addParticle($position->add(mt_rand(-5, 5) / 10, mt_rand(0, 10) / 10, mt_rand(-5, 5) / 10), new BlockBreakParticle($block));
            }
            return;
        }
    }

    private static function applyAmbientEffects(Player $player, array $eventConfig): void
    {
        $ambient = (array) ($eventConfig["ambient"] ?? []);
        $particle = strtolower((string) ($ambient["particle"] ?? ""));
        $perTick = max(0, (int) ($ambient["particles-per-tick"] ?? 0));
        $chance = max(0, min(100, (int) ($ambient["particle-chance-percent"] ?? 100)));
        $minDistance = max(1.5, (float) ($ambient["min-distance"] ?? 5.5));
        $maxDistance = max($minDistance + 0.5, (float) ($ambient["max-distance"] ?? max(8.5, $minDistance + 2.5)));
        $yMin = (float) ($ambient["min-y-offset"] ?? 0.5);
        $yMax = max($yMin, (float) ($ambient["max-y-offset"] ?? 2.5));

        for ($i = 0; $i < $perTick; $i++) {
            if ($chance < 100 && mt_rand(1, 100) > $chance) {
                continue;
            }

            $particlePos = self::randomAmbientParticlePosition($player, $minDistance, $maxDistance, $yMin, $yMax);
            match ($particle) {
                "packed_ice", "blue_ice", "ice" => $player->getWorld()->addParticle($particlePos, new DustParticle(new Color(180, 225, 255))),
                "amethyst", "amethyst_block", "astral" => $player->getWorld()->addParticle($particlePos, new DustParticle(new Color(180, 120, 255))),
                "flame" => $player->getWorld()->addParticle($particlePos, new FlameParticle()),
                "lava" => $player->getWorld()->addParticle($particlePos, new LavaParticle()),
                "moss" => $player->getWorld()->addParticle($particlePos, new DustParticle(new Color(110, 170, 95))),
                "bloom" => $player->getWorld()->addParticle($particlePos, new HappyVillagerParticle()),
                default => $player->getWorld()->addParticle($particlePos, new PortalParticle()),
            };
        }
    }

    private static function playAmbientSound(Player $player, array $eventConfig, string $eventId): void
    {
        $ambient = (array) ($eventConfig["ambient"] ?? []);
        $sound = trim((string) ($ambient["sound"] ?? ""));
        if ($sound === "") {
            return;
        }

        $zone = ZoneManager::getZoneAt($player->getPosition());
        $zoneId = strtolower((string) ($zone["id"] ?? ""));
        if ($zoneId === "") {
            return;
        }

        $interval = max(8, (int) ($ambient["sound-interval-seconds"] ?? 45));
        $chance = max(0, min(100, (int) ($ambient["sound-chance-percent"] ?? 100)));
        $volume = max(0.0, (float) ($ambient["sound-volume"] ?? 0.22));
        $pitch = max(0.05, (float) ($ambient["sound-pitch"] ?? 1.0));
        $pitchJitter = max(0.0, (float) ($ambient["sound-pitch-jitter"] ?? 0.0));
        $cooldownKey = strtolower($player->getUniqueId()->toString() . ":" . $zoneId . ":" . $eventId . ":" . $sound);
        $now = time();
        if ((self::$ambientSoundCooldowns[$cooldownKey] ?? 0) > $now) {
            return;
        }

        self::$ambientSoundCooldowns[$cooldownKey] = $now + $interval;
        if ($chance < 100 && mt_rand(1, 100) > $chance) {
            return;
        }

        $resolvedPitch = $pitch;
        if ($pitchJitter > 0.0) {
            $resolvedPitch += mt_rand((int) round(-$pitchJitter * 100), (int) round($pitchJitter * 100)) / 100;
            $resolvedPitch = max(0.05, $resolvedPitch);
        }

        $packet = new PlaySoundPacket();
        $packet->soundName = $sound;
        $packet->x = $player->getLocation()->getX();
        $packet->y = $player->getLocation()->getY();
        $packet->z = $player->getLocation()->getZ();
        $packet->volume = $volume;
        $packet->pitch = $resolvedPitch;
        $player->getNetworkSession()->sendDataPacket($packet);
    }

    private static function randomAmbientParticlePosition(Player $player, float $minDistance, float $maxDistance, float $yMin, float $yMax): Vector3
    {
        $angle = mt_rand(0, 628) / 100;
        $distance = $minDistance + (mt_rand(0, 1000) / 1000) * ($maxDistance - $minDistance);
        $yOffset = $yMin + (mt_rand(0, 1000) / 1000) * ($yMax - $yMin);
        $base = $player->getPosition();

        return $base->add(cos($angle) * $distance, $yOffset, sin($angle) * $distance);
    }

    private static function boostNearbyCrops(Player $player, array $eventConfig): void
    {
        $growth = (array) ($eventConfig["crop-growth"] ?? []);
        if (((bool) ($growth["enabled"] ?? false)) !== true) {
            return;
        }

        $center = $player->getPosition();
        $world = $center->getWorld();
        $radius = max(1, (int) ($growth["radius"] ?? 4));
        $chance = max(0, min(100, (int) ($growth["per-block-chance"] ?? 20)));
        $levels = max(1, (int) ($growth["growth-steps"] ?? 1));

        for ($x = -$radius; $x <= $radius; $x++) {
            for ($y = -1; $y <= 2; $y++) {
                for ($z = -$radius; $z <= $radius; $z++) {
                    if (mt_rand(1, 100) > $chance) {
                        continue;
                    }

                    $position = new Position($center->getFloorX() + $x, $center->getFloorY() + $y, $center->getFloorZ() + $z, $world);
                    $block = $world->getBlock($position);

                    if ($block instanceof Wheat && $block->getAge() < Wheat::MAX_AGE) {
                        $world->setBlock($position, $block->setAge(min(Wheat::MAX_AGE, $block->getAge() + $levels)));
                        continue;
                    }

                    if ($block instanceof Carrot && $block->getAge() < Carrot::MAX_AGE) {
                        $world->setBlock($position, $block->setAge(min(Carrot::MAX_AGE, $block->getAge() + $levels)));
                        continue;
                    }

                    if ($block instanceof Potato && $block->getAge() < Potato::MAX_AGE) {
                        $world->setBlock($position, $block->setAge(min(Potato::MAX_AGE, $block->getAge() + $levels)));
                        continue;
                    }

                    if ($block instanceof Beetroot && $block->getAge() < Beetroot::MAX_AGE) {
                        $world->setBlock($position, $block->setAge(min(Beetroot::MAX_AGE, $block->getAge() + $levels)));
                        continue;
                    }

                    if ($block instanceof NetherWartPlant && $block->getAge() < NetherWartPlant::MAX_AGE) {
                        $world->setBlock($position, $block->setAge(min(NetherWartPlant::MAX_AGE, $block->getAge() + $levels)));
                    }
                }
            }
        }
    }

    private static function grantRewardEntries(Player $player, array $entries, string $zoneId, string $sourceName, array $extra): int
    {
        $granted = 0;
        $session = Loader::getPlayerManager()->getSession($player);
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $item = RewardResolver::resolveItem($entry, $player);
            if ($item === null) {
                continue;
            }

            foreach ($player->getInventory()->addItem($item) as $leftover) {
                $session?->addItemToCollection($leftover);
            }

            self::announceConfiguredReward($player, $entry, $item, $zoneId, $sourceName, $extra);
            $granted++;
        }

        return $granted;
    }

    private static function broadcastEventMessage(string $template, string $zoneId, array $eventConfig, int $duration): void
    {
        $zone = ZoneManager::getZone($zoneId);
        $message = str_replace(
            ["{event}", "{zone}", "{duration}"],
            [
                (string) ($eventConfig["display"] ?? ucfirst($zoneId)),
                C::clean((string) (($zone["display"] ?? $zoneId) ?? $zoneId)),
                GeneralUtils::translateTime($duration),
            ],
            $template
        );

        self::broadcastToOnline(C::colorize($message), "broadcasts");
    }

    private static function broadcastToOnline(string $message, string $setting): void
    {
        foreach (Server::getInstance()->getOnlinePlayers() as $online) {
            $session = Loader::getPlayerManager()->getSession($online);
            if ((bool) ($session?->getSetting($setting) ?? true)) {
                $online->sendMessage($message);
            }
        }
    }

    private static function resetRuntimeState(bool $preserveWarzoneBoss = false): void
    {
        foreach (array_keys(self::$activeEvents) as $zoneId) {
            self::endEvent($zoneId, false);
        }

        foreach (self::$activeNodes as $record) {
            if (!is_array($record)) {
                continue;
            }

            $world = Server::getInstance()->getWorldManager()->getWorldByName((string) ($record["world"] ?? ""));
            if ($world === null) {
                continue;
            }

            $position = new Position((int) $record["x"], (int) $record["y"], (int) $record["z"], $world);
            $original = $record["original_block"] ?? null;
            $world->setBlock($position, $original instanceof Block ? $original : VanillaBlocks::AIR());
        }

        self::$activeEvents = [];
        self::$activeNodes = [];
        self::persistActiveNodesRuntime();
        self::$nextStartAt = [];
        if (!$preserveWarzoneBoss) {
            self::clearWarzoneBossEvent(true);
        }
        self::$globalEvents = [];
        if (!$preserveWarzoneBoss) {
            self::$nextWarzoneBossAt = 0;
        }
    }

    private static function tickWarzoneBossEvent(int $now): void
    {
        $config = self::getWarzoneBossConfig();
        if ($config === []) {
            self::clearWarzoneBossEvent(true);
            self::$nextWarzoneBossAt = 0;
            return;
        }

        if (self::$activeWarzoneBossEvent !== null) {
            $worldName = (string) (self::$activeWarzoneBossEvent["world"] ?? "");
            $entityId = (int) (self::$activeWarzoneBossEvent["entity_id"] ?? 0);
            $world = $worldName !== "" ? Server::getInstance()->getWorldManager()->getWorldByName($worldName) : null;
            $entity = $world?->getEntity($entityId);
            $expired = $now >= (int) (self::$activeWarzoneBossEvent["ends_at"] ?? 0);
            $missing = !($entity instanceof BossEntity) || $entity->isClosed() || !$entity->isAlive();

            if ($expired || $missing) {
                self::endWarzoneBossEvent(true);
            }
            return;
        }

        if (self::$nextWarzoneBossAt > 0 && $now >= self::$nextWarzoneBossAt) {
            self::startWarzoneBossEvent($now);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function getWarzoneBossConfig(): array
    {
        $config = self::$globalEvents["warzone_boss"] ?? null;
        if (!is_array($config) || (bool) ($config["enabled"] ?? false) !== true) {
            return [];
        }

        return $config;
    }

    private static function scheduleNextWarzoneBoss(bool $initial): void
    {
        $config = self::getWarzoneBossConfig();
        if ($config === []) {
            self::$nextWarzoneBossAt = 0;
            return;
        }

        $interval = $config["interval-seconds"] ?? ["min" => 14400, "max" => 21600];
        $min = 14400;
        $max = 21600;
        if (is_array($interval)) {
            $min = max(300, (int) ($interval["min"] ?? $min));
            $max = max($min, (int) ($interval["max"] ?? $max));
        } elseif (is_numeric($interval)) {
            $min = max(300, (int) $interval);
            $max = $min;
        }

        $delay = mt_rand($min, $max);
        if ($initial && isset($config["initial-delay-seconds"])) {
            $delay = max(60, (int) $config["initial-delay-seconds"]);
        }

        self::$nextWarzoneBossAt = time() + $delay;
    }

    private static function startWarzoneBossEvent(int $now): bool
    {
        $config = self::getWarzoneBossConfig();
        if ($config === []) {
            self::$nextWarzoneBossAt = 0;
            return false;
        }

        $warzone = Loader::getRegionManager()->getRegionByName("Warzone");
        $world = Server::getInstance()->getWorldManager()->getDefaultWorld();
        if ($warzone === null || !$world instanceof World) {
            self::scheduleNextWarzoneBoss(false);
            return false;
        }

        $bossId = self::pickWarzoneBossId($config);
        if ($bossId === null) {
            self::scheduleNextWarzoneBoss(false);
            return false;
        }

        $attempts = max(8, (int) ($config["spawn-attempts"] ?? 32));
        $position = self::findWarzoneSpawnPosition($world, $warzone->getMin(), $warzone->getMax(), $attempts);
        if (!$position instanceof Position) {
            self::scheduleNextWarzoneBoss(false);
            return false;
        }

        $duration = max(60, (int) ($config["duration-seconds"] ?? 1800));
        $boss = self::spawnWarzoneBossEntity($bossId, $position);
        if (!$boss instanceof BossEntity) {
            self::scheduleNextWarzoneBoss(false);
            return false;
        }
        $boss->spawnToAll();
        $display = C::clean($boss->getName());

        self::$activeWarzoneBossEvent = [
            "boss_id" => $bossId,
            "display" => $display,
            "entity_id" => $boss->getId(),
            "world" => $boss->getWorld()->getFolderName(),
            "x" => $boss->getPosition()->getFloorX(),
            "y" => $boss->getPosition()->getFloorY(),
            "z" => $boss->getPosition()->getFloorZ(),
            "ends_at" => $now + $duration,
        ];
        self::$nextWarzoneBossAt = 0;
        self::persistWarzoneBossRuntime();

        $message = (string) ($config["broadcasts"]["start"] ?? "&r&l&dWorld Event &8| &r&f{boss} &7has spawned in &cWarzone &7at &f{coords} &7for &f{time}&7.");
        $message = str_replace(
            ["{boss}", "{time}", "{coords}"],
            [$display, GeneralUtils::translateTime($duration), self::formatCoords($position)],
            $message
        );
        self::broadcastToOnline(C::colorize($message), "broadcasts");
        return true;
    }

    private static function endWarzoneBossEvent(bool $broadcast): void
    {
        $config = self::getWarzoneBossConfig();
        $display = (string) (self::$activeWarzoneBossEvent["display"] ?? "Warzone Boss");

        self::clearWarzoneBossEvent(true);
        self::scheduleNextWarzoneBoss(false);

        if ($broadcast && $config !== []) {
            $message = (string) ($config["broadcasts"]["end"] ?? "&r&l&dWorld Event &8| &r&7The &f{boss} &7warzone event has ended.");
            $message = str_replace("{boss}", $display, $message);
            self::broadcastToOnline(C::colorize($message), "broadcasts");
        }
    }

    private static function clearWarzoneBossEvent(bool $despawn): void
    {
        if (self::$activeWarzoneBossEvent !== null && $despawn) {
            $worldName = (string) (self::$activeWarzoneBossEvent["world"] ?? "");
            $entityId = (int) (self::$activeWarzoneBossEvent["entity_id"] ?? 0);
            $world = $worldName !== "" ? Server::getInstance()->getWorldManager()->getWorldByName($worldName) : null;
            $entity = $world?->getEntity($entityId);
            if ($entity instanceof BossEntity && !$entity->isClosed()) {
                $entity->flagForDespawn();
            }
        }

        self::$activeWarzoneBossEvent = null;
        self::persistWarzoneBossRuntime();
    }

    private static function pickWarzoneBossId(array $config): ?string
    {
        $pool = [];
        foreach ((array) ($config["bosses"] ?? []) as $key => $entry) {
            if (is_array($entry)) {
                $id = strtolower((string) ($entry["id"] ?? $key));
                if ($id === "") {
                    continue;
                }

                $pool[] = [
                    "id" => $id,
                    "chance" => max(1, (int) ($entry["weight"] ?? $entry["chance"] ?? 1)),
                ];
                continue;
            }

            if (!is_string($entry)) {
                continue;
            }

            $pool[] = [
                "id" => strtolower($entry),
                "chance" => 1,
            ];
        }

        if ($pool === []) {
            return null;
        }

        $selected = RewardResolver::rollEntry($pool);
        return is_array($selected) ? strtolower((string) ($selected["id"] ?? "")) : null;
    }

    private static function spawnWarzoneBossEntity(string $bossId, Position $position): ?BossEntity
    {
        $location = Location::fromObject($position, $position->getWorld());

        return match (strtolower($bossId)) {
            "astral_devourer" => new AstralDevourer($location),
            "titan_knight" => new TitanKnight($location),
            "glacial_hydra" => new GlacialHydra($location),
            default => null,
        };
    }

    private static function findWarzoneSpawnPosition(World $world, Vector3 $min, Vector3 $max, int $attempts): ?Position
    {
        $spawn = Loader::getRegionManager()->getRegionByName("Spawn");
        for ($attempt = 0; $attempt < $attempts; $attempt++) {
            $x = mt_rand((int) min($min->x, $max->x), (int) max($min->x, $max->x));
            $z = mt_rand((int) min($min->z, $max->z), (int) max($min->z, $max->z));
            $surfaceY = $world->getHighestBlockAt($x, $z);
            $solidY = null;

            for ($scanY = $surfaceY; $scanY > max(-64, $surfaceY - 18); $scanY--) {
                $candidate = $world->getBlock(new Vector3($x, $scanY, $z));
                if ($candidate->isTransparent() || $candidate instanceof Liquid) {
                    continue;
                }

                $solidY = $scanY;
                break;
            }

            if ($solidY === null) {
                continue;
            }

            $feet = new Vector3($x, $solidY + 1, $z);
            if ($spawn !== null && $spawn->contains($feet)) {
                continue;
            }

            $target = $world->getBlock($feet);
            $head = $world->getBlock($feet->add(0, 1, 0));
            $ground = $world->getBlock(new Vector3($x, $solidY, $z));
            if (!$target->isTransparent() || !$head->isTransparent() || $ground->isTransparent() || $ground instanceof Liquid) {
                continue;
            }

            return new Position($x + 0.5, $solidY + 1, $z + 0.5, $world);
        }

        return null;
    }

    private static function formatCoords(Position $position): string
    {
        return $position->getFloorX() . ", " . $position->getFloorY() . ", " . $position->getFloorZ();
    }

    private static function getRuntimeConfig(): Config
    {
        static $config = null;
        if (!$config instanceof Config) {
            $config = new Config(Loader::getInstance()->getDataFolder() . "world_events_runtime.yml", Config::YAML);
        }

        return $config;
    }

    private static function persistActiveNodesRuntime(): void
    {
        $config = self::getRuntimeConfig();
        if (self::$activeNodes === []) {
            $config->remove("active_nodes");
            $config->save();
            return;
        }

        $serialized = [];
        foreach (self::$activeNodes as $key => $record) {
            if (!is_array($record)) {
                continue;
            }

            $serialized[$key] = [
                "zone_id" => (string) ($record["zone_id"] ?? ""),
                "event_id" => (string) ($record["event_id"] ?? ""),
                "world" => (string) ($record["world"] ?? ""),
                "x" => (int) ($record["x"] ?? 0),
                "y" => (int) ($record["y"] ?? 0),
                "z" => (int) ($record["z"] ?? 0),
                "display" => (string) ($record["display"] ?? "Event Node"),
                "original_state_id" => (int) ($record["original_state_id"] ?? (($record["original_block"] ?? null) instanceof Block ? $record["original_block"]->getStateId() : VanillaBlocks::AIR()->getStateId())),
                "node_state_id" => (int) ($record["node_state_id"] ?? (($record["particle_block"] ?? null) instanceof Block ? $record["particle_block"]->getStateId() : VanillaBlocks::AIR()->getStateId())),
                "rewards" => (array) ($record["rewards"] ?? []),
            ];
        }

        $config->set("active_nodes", $serialized);
        $config->save();
    }

    private static function persistWarzoneBossRuntime(): void
    {
        $config = self::getRuntimeConfig();
        if (self::$activeWarzoneBossEvent === null) {
            $config->remove("warzone_boss");
            $config->save();
            return;
        }

        $config->set("warzone_boss", self::$activeWarzoneBossEvent);
        $config->save();
    }

    private static function restoreActiveNodesRuntime(): void
    {
        $saved = self::getRuntimeConfig()->get("active_nodes");
        if (!is_array($saved)) {
            return;
        }

        self::$activeNodes = [];
        foreach ($saved as $key => $record) {
            if (!is_array($record)) {
                continue;
            }

            $worldName = (string) ($record["world"] ?? "");
            $world = $worldName !== "" ? Server::getInstance()->getWorldManager()->getWorldByName($worldName) : null;
            if (!$world instanceof World) {
                continue;
            }

            $x = (int) ($record["x"] ?? 0);
            $y = (int) ($record["y"] ?? 0);
            $z = (int) ($record["z"] ?? 0);
            $world->loadChunk($x >> 4, $z >> 4);

            $position = new Position($x, $y, $z, $world);
            $current = $world->getBlock($position);
            $nodeStateId = (int) ($record["node_state_id"] ?? -1);
            $originalStateId = (int) ($record["original_state_id"] ?? VanillaBlocks::AIR()->getStateId());

            if ($nodeStateId >= 0 && $current->getStateId() === $nodeStateId) {
                $world->setBlock($position, RuntimeBlockStateRegistry::getInstance()->fromStateId($originalStateId));
            }

            $originalBlock = RuntimeBlockStateRegistry::getInstance()->fromStateId($originalStateId);
            $particleBlock = $nodeStateId >= 0 ? RuntimeBlockStateRegistry::getInstance()->fromStateId($nodeStateId) : VanillaBlocks::AIR();
            self::$activeNodes[(string) $key] = [
                "zone_id" => (string) ($record["zone_id"] ?? ""),
                "event_id" => (string) ($record["event_id"] ?? ""),
                "world" => $worldName,
                "x" => $x,
                "y" => $y,
                "z" => $z,
                "display" => (string) ($record["display"] ?? "Event Node"),
                "original_block" => $originalBlock,
                "original_state_id" => $originalStateId,
                "node_state_id" => $nodeStateId,
                "particle_block" => $particleBlock,
                "rewards" => (array) ($record["rewards"] ?? []),
            ];
        }

        self::$activeNodes = [];
        self::persistActiveNodesRuntime();
    }

    private static function restoreWarzoneBossRuntime(): void
    {
        if (self::$activeWarzoneBossEvent !== null) {
            return;
        }

        $saved = self::getRuntimeConfig()->get("warzone_boss");
        if (!is_array($saved)) {
            return;
        }

        $bossId = strtolower((string) ($saved["boss_id"] ?? ""));
        $worldName = (string) ($saved["world"] ?? "");
        $endsAt = (int) ($saved["ends_at"] ?? 0);
        if ($bossId === "" || $worldName === "" || $endsAt <= time()) {
            self::$activeWarzoneBossEvent = null;
            self::persistWarzoneBossRuntime();
            return;
        }

        $world = Server::getInstance()->getWorldManager()->getWorldByName($worldName);
        if (!$world instanceof World) {
            return;
        }

        $x = (int) ($saved["x"] ?? 0);
        $y = (int) ($saved["y"] ?? 0);
        $z = (int) ($saved["z"] ?? 0);
        $world->loadChunk($x >> 4, $z >> 4);

        $entity = self::findExistingWarzoneBossEntity($world, $bossId, $x, $y, $z);
        if (!$entity instanceof BossEntity) {
            $entity = self::spawnWarzoneBossEntity($bossId, new Position($x + 0.5, $y, $z + 0.5, $world));
            if ($entity instanceof BossEntity) {
                $entity->spawnToAll();
            }
        }

        if (!$entity instanceof BossEntity) {
            self::$activeWarzoneBossEvent = null;
            self::persistWarzoneBossRuntime();
            return;
        }

        $saved["entity_id"] = $entity->getId();
        $saved["display"] = C::clean((string) ($saved["display"] ?? $entity->getName()));
        $saved["world"] = $entity->getWorld()->getFolderName();
        $saved["x"] = $entity->getPosition()->getFloorX();
        $saved["y"] = $entity->getPosition()->getFloorY();
        $saved["z"] = $entity->getPosition()->getFloorZ();
        self::$activeWarzoneBossEvent = $saved;
        self::$nextWarzoneBossAt = 0;
        self::persistWarzoneBossRuntime();
    }

    private static function findExistingWarzoneBossEntity(World $world, string $bossId, int $x, int $y, int $z): ?BossEntity
    {
        $expectedClass = self::getWarzoneBossEntityClass($bossId);
        $center = new Vector3($x + 0.5, $y, $z + 0.5);

        foreach ($world->getEntities() as $entity) {
            if (!$entity instanceof BossEntity || $entity->isClosed() || !$entity->isAlive()) {
                continue;
            }

            if ($expectedClass !== null && !($entity instanceof $expectedClass)) {
                continue;
            }

            if ($entity->getPosition()->distanceSquared($center) > 64.0) {
                continue;
            }

            return $entity;
        }

        return null;
    }

    private static function getWarzoneBossEntityClass(string $bossId): ?string
    {
        return match (strtolower($bossId)) {
            "astral_devourer" => AstralDevourer::class,
            "titan_knight" => TitanKnight::class,
            "glacial_hydra" => GlacialHydra::class,
            default => null,
        };
    }

    private static function readActiveEventValue(string $zoneId, string $key, mixed $default): mixed
    {
        $state = self::getActiveEvent($zoneId);
        if ($state === null) {
            return $default;
        }

        return ((array) ($state["config"] ?? []))[$key] ?? $default;
    }

    private static function pickWeightedEventId(array $events): ?string
    {
        $total = 0;
        foreach ($events as $event) {
            if (is_array($event) && (bool) ($event["enabled"] ?? true)) {
                $total += max(1, (int) ($event["weight"] ?? 1));
            }
        }

        if ($total <= 0) {
            return null;
        }

        $roll = mt_rand(1, $total);
        foreach ($events as $eventId => $event) {
            if (!is_string($eventId) || !is_array($event) || (bool) ($event["enabled"] ?? true) !== true) {
                continue;
            }

            $roll -= max(1, (int) ($event["weight"] ?? 1));
            if ($roll <= 0) {
                return strtolower($eventId);
            }
        }

        return null;
    }

    private static function resolveConfiguredBlock(string $id): ?Block
    {
        $item = StringToItemParser::getInstance()->parse($id);
        if ($item !== null && method_exists($item, "getBlock")) {
            $block = $item->getBlock();
            if ($block instanceof Block) {
                return $block;
            }
        }

        return match (strtolower($id)) {
            "packed_ice" => VanillaBlocks::PACKED_ICE(),
            "blue_ice" => VanillaBlocks::BLUE_ICE(),
            "amethyst", "amethyst_block" => VanillaBlocks::OBSIDIAN(),
            "magma", "magma_block" => VanillaBlocks::MAGMA(),
            "moss", "moss_block" => VanillaBlocks::GRASS(),
            default => null,
        };
    }

    private static function nodeKey(Position $position): string
    {
        return strtolower($position->getWorld()->getFolderName()) . ":" . $position->getFloorX() . ":" . $position->getFloorY() . ":" . $position->getFloorZ();
    }
}
