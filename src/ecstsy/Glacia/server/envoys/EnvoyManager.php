<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\envoys;

use Dom\Entity;
use ecstsy\Glacia\entity\envoys\EnvoyBabyZombie;
use ecstsy\Glacia\entity\envoys\EnvoyBlaze;
use ecstsy\Glacia\entity\envoys\EnvoyGhast;
use ecstsy\Glacia\entity\envoys\EnvoySkeleton;
use ecstsy\Glacia\entity\envoys\EnvoySlime;
use ecstsy\Glacia\entity\envoys\EnvoyZombie;
use ecstsy\Glacia\entity\envoys\EnvoyZombiePigman;
use ecstsy\Glacia\entity\SpawnerEntity;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\items\pets\PetManager;
use ecstsy\Glacia\utils\QueryStmts;
use ecstsy\Glacia\utils\RewardResolver;
use ecstsy\Glacia\utils\StorageUtils;
use ecstsy\Glacia\server\items\runes\RuneManager;
use ecstsy\MartianUtilities\entity\BaseHostileEntity;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\block\Liquid;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Location;
use pocketmine\entity\object\FireworkRocket;
use pocketmine\item\FireworkRocketExplosion;
use pocketmine\item\FireworkRocketType;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat as C;
use pocketmine\world\World;
use pocketmine\world\sound\XpCollectSound;

final class EnvoyManager
{
    /** @var array<string, mixed> */
    private array $config = [];
    /** @var array<string, mixed>|null */
    private ?array $active = null;
    private int $nextStartAt = 0;
    private bool $loaded = false;
    private int $lastIndicatorAt = 0;
    private int $nextIndicatorIndex = 0;
    /** @var array<string, mixed>|null */
    private ?array $specialEvent = null;
    private int $nextSpecialEventStartAt = 0;

    public function __construct()
    {
        $this->reload();
        $this->loadState();
    }

    public function reload(): void
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "envoy.yml");
        $this->config = $config?->getAll() ?? [];

        if (!$this->loaded && $this->nextStartAt <= 0) {
            $this->nextStartAt = time() + $this->getInterval();
        }
        if (!$this->loaded && $this->nextSpecialEventStartAt <= 0) {
            $this->scheduleNextSpecialEvent(true);
        }
    }

    public function tick(): void
    {
        if (!(bool) ($this->config["enabled"] ?? true) || !$this->loaded) {
            return;
        }

        $now = time();
        $this->tickSpecialEvent($now);
        if ($this->active !== null) {
            if (($this->active["ends_at"] ?? 0) <= $now || $this->getRemainingCrates() <= 0) {
                $this->endActiveEvent();
            } elseif ($this->shouldPulseIndicators($now)) {
                $this->pulseActiveIndicator();
            }
            return;
        }

        if ($this->nextStartAt <= 0) {
            $this->scheduleNext();
            return;
        }

        if ($this->nextStartAt <= $now) {
            $this->startEvent();
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getActive(): ?array
    {
        return $this->active;
    }

    public function getTimeLeft(): int
    {
        if ($this->active !== null) {
            return max(0, (int) ($this->active["ends_at"] ?? 0) - time());
        }

        return max(0, $this->nextStartAt - time());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getNearestCrate(Vector3 $origin): ?array
    {
        if ($this->active === null) {
            return null;
        }

        $world = Server::getInstance()->getWorldManager()->getDefaultWorld();
        if ($world === null) {
            return null;
        }

        $nearest = null;
        $nearestDistance = PHP_INT_MAX;
        foreach ($this->getValidTrackedPositions($world) as $position) {
            $dx = ((int) ($position["x"] ?? 0)) + 0.5 - $origin->x;
            $dy = ((int) ($position["y"] ?? 0)) + 0.5 - $origin->y;
            $dz = ((int) ($position["z"] ?? 0)) + 0.5 - $origin->z;
            $distanceSquared = ($dx * $dx) + ($dy * $dy) + ($dz * $dz);
            if ($distanceSquared >= $nearestDistance) {
                continue;
            }

            $nearestDistance = (int) $distanceSquared;
            $nearest = $position;
        }

        return $nearest;
    }

    /**
     * @return string[]
     */
    public function getTypeIds(): array
    {
        return array_keys($this->getNormalRollTypes());
    }

    public function forceStart(): bool
    {
        if ($this->active !== null) {
            return false;
        }

        return $this->startEvent();
    }

    public function forceStop(): bool
    {
        if ($this->active === null) {
            return false;
        }

        $this->endActiveEvent();
        return true;
    }

    public function setNextDelay(int $seconds): void
    {
        $this->active = null;
        $this->nextStartAt = time() + max(1, $seconds);
        $this->saveState();
    }

    public function setActiveTime(int $seconds): bool
    {
        if ($this->active === null) {
            return false;
        }

        $this->active["ends_at"] = time() + max(1, $seconds);
        $this->saveState();
        return true;
    }

    public function claimChest(Player $player, Vector3 $position): bool
    {
        $world = $player->getWorld();
        $block = $world->getBlock($position);
        $data = Loader::getBlockData()->get($block);
        if (!is_array($data) || ($data["type"] ?? "") !== "envoy") {
            return false;
        }

        $crateType = (string) ($data["crate_type"] ?? "simple");
        $originalCrateType = $crateType;
        $crateType = $this->upgradeTypeForPlayer($player, $crateType);
        $typeConfig = $this->getTypeConfig($crateType);
        $spawnOnClaim = (bool) (($typeConfig["mob-wave"]["spawn-on-claim"] ?? false) === true);
        if ($spawnOnClaim) {
            $this->spawnGuardsFor($world, $position, $crateType);
        }

        $rewardCount = max(1, (int) ($typeConfig["reward-count"] ?? 1));
        $pool = is_array($typeConfig["rewards"] ?? null) ? $typeConfig["rewards"] : [];
        $rewards = [];

        for ($i = 0; $i < $rewardCount; $i++) {
            $entry = RewardResolver::rollEntry($pool);
            if ($entry === null) {
                continue;
            }

            $item = RewardResolver::resolveItem($entry, $player);
            if ($item !== null) {
                $rewards[] = $item;
            }
        }

        if (RuneManager::shouldDropExtraEnvoyReward($player)) {
            $entry = RewardResolver::rollEntry($pool);
            if ($entry !== null) {
                $item = RewardResolver::resolveItem($entry, $player);
                if ($item !== null) {
                    $rewards[] = $item;
                }
            }
        }

        $dropPosition = new Vector3($position->x + 0.5, $position->y + 1.0, $position->z + 0.5);
        $world->setBlock($position, VanillaBlocks::AIR());
        Loader::getBlockData()->remove($block);
        $this->removeActivePosition($position);

        $session = Loader::getPlayerManager()->getSession($player);
        $autoCollect = RuneManager::hasPassiveRune($player, "envoy_vacuum");

        foreach ($rewards as $reward) {
            if ($autoCollect && $session !== null) {
                foreach ($player->getInventory()->addItem($reward) as $leftover) {
                    $session->addItemToCollection($leftover);
                }
                continue;
            }

            $world->dropItem($dropPosition, $reward);
        }

        PlayerUtils::playSound($player, "random.levelup");
        $world->addSound($position, new XpCollectSound());
        $this->spawnFirework($position->x + 0.5, $position->y + 1, $position->z + 0.5, $world, $crateType);
        Loader::getChallengeManager()->incrementProgress($player, "envoys_broken", 1);
        Loader::getQuestBoardManager()->recordProgress($player, "envoys_broken", 1, [
            "crate_type" => strtolower($crateType),
        ]);
        $remaining = $this->getRemainingCrates();
        $message = "&r&bEnvoy &8| &r&aYou looted a " . $this->formatTypeName($crateType) . " &r&acrate&7. "
            . "&f{$remaining} &7remaining.";
        if ($autoCollect) {
            $message .= " &8(&bVacuumed&8)";
        }
        $player->sendMessage(C::colorize($message));
        if ($crateType !== $originalCrateType) {
            $player->sendMessage(C::colorize("&r&eSkycourier Drake Pet &r&7upgraded your crate from &f" . C::clean($this->formatTypeName($originalCrateType)) . " &r&7to &f" . C::clean($this->formatTypeName($crateType)) . "&7."));
        }

        if ($this->active !== null && $this->getRemainingCrates() <= 0) {
            $this->endActiveEvent();
        } else {
            $this->saveState();
        }

        return true;
    }

    private function getInterval(): int
    {
        return max(60, GeneralUtils::parseTimeString((string) ($this->config["interval"] ?? "2h")));
    }

    private function getDuration(): int
    {
        return max(60, GeneralUtils::parseTimeString((string) ($this->config["duration"] ?? "20m")));
    }

    private function getCrateCount(): int
    {
        $count = max(1, (int) ($this->config["crate-count"] ?? 10));
        if ($this->specialEvent !== null) {
            $multiplier = max(0.1, (float) ($this->getSpecialEventConfig()["crate-count-multiplier"] ?? 0.6));
            $count = max(1, (int) floor($count * $multiplier));
        }
        return $count;
    }

    private function getSpawnAttempts(): int
    {
        return max(10, (int) ($this->config["spawn-attempts"] ?? 120));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getConfiguredTypes(): array
    {
        $configured = [];
        foreach ((array) ($this->config["types"] ?? []) as $id => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $configured[$this->normalizeTypeId((string) $id)] = $entry;
        }

        return $configured;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getNormalRollTypes(): array
    {
        $enabled = [];
        foreach ($this->getConfiguredTypes() as $id => $entry) {
            if (!(bool) ($entry["enabled"] ?? true)) {
                continue;
            }

            $enabled[$id] = $entry;
        }

        return $enabled;
    }

    /**
     * @return array<string, mixed>
     */
    private function getTypeConfig(string $type): array
    {
        $id = $this->normalizeTypeId($type);
        $config = (array) ($this->getConfiguredTypes()[$id] ?? []);

        if ($id === "godly") {
            $mobWave = is_array($config["mob-wave"] ?? null) ? $config["mob-wave"] : [];
            $mobWave = array_replace([
                "enabled" => true,
                "spawn-on-claim" => true,
                "count" => 3,
                "spawn-radius" => 5,
                "name" => "&r&5&lGodly Envoy Beast",
                "mobs" => [
                    ["id" => "envoy_zombie", "chance" => 35],
                    ["id" => "envoy_skeleton", "chance" => 35],
                    ["id" => "envoy_blaze", "chance" => 30],
                ],
            ], $mobWave);
            $config["mob-wave"] = $mobWave;
        }

        return $config;
    }

    private function rollType(): string
    {
        if ($this->specialEvent !== null) {
            $pool = [];
            foreach ((array) ($this->getSpecialEventConfig()["types"] ?? []) as $id => $chance) {
                $normalizedId = $this->normalizeTypeId((string) $id);
                if (!$this->isTypeConfigured($normalizedId)) {
                    continue;
                }

                $pool[] = [
                    "chance" => max(1, (int) $chance),
                    "id" => $normalizedId,
                ];
            }

            if ($pool !== []) {
                $entry = RewardResolver::rollEntry($pool);
                return (string) ($entry["id"] ?? "exotic");
            }
        }

        $types = $this->getNormalRollTypes();
        $pool = [];
        foreach ($types as $id => $entry) {
            $pool[] = [
                "chance" => (int) ($entry["chance"] ?? 0),
                "id" => $id,
            ];
        }

        $entry = RewardResolver::rollEntry($pool);
        return (string) ($entry["id"] ?? array_key_first($types) ?? "simple");
    }

    private function startEvent(): bool
    {
        $warzone = Loader::getRegionManager()->getRegionByName("Warzone");
        $world = Server::getInstance()->getWorldManager()->getDefaultWorld();
        if ($warzone === null || $world === null) {
            $this->scheduleNext();
            return false;
        }

        $types = $this->getNormalRollTypes();
        if ($types === []) {
            $this->scheduleNext();
            return false;
        }

        $crateCount = $this->getCrateCount();
        $positions = [];
        $min = $warzone->getMin();
        $max = $warzone->getMax();

        for ($i = 0; $i < $crateCount; $i++) {
            $spawn = $this->findSpawnPosition($world, $min, $max, $positions);
            if ($spawn === null) {
                continue;
            }

            $type = $this->rollType();
            $world->setBlock($spawn, VanillaBlocks::CHEST());
            Loader::getBlockData()->set($world->getBlock($spawn), [
                "type" => "envoy",
                "crate_type" => $type,
                "spawned_at" => time(),
            ]);

            $positions[] = [
                "x" => $spawn->getFloorX(),
                "y" => $spawn->getFloorY(),
                "z" => $spawn->getFloorZ(),
                "type" => $type,
                "beasts" => ((bool) (($this->getTypeConfig($type)["mob-wave"]["spawn-on-claim"] ?? false) === true))
                    ? []
                    : $this->spawnGuardsFor($world, $spawn, $type),
            ];
        }

        if ($positions === []) {
            $this->scheduleNext();
            return false;
        }

        $this->active = [
            "ends_at" => time() + $this->getDuration(),
            "positions" => $positions,
        ];
        $this->lastIndicatorAt = 0;
        $this->nextIndicatorIndex = 0;
        $this->nextStartAt = 0;
        $this->saveState();

        $this->broadcast(str_replace(
            ["{count}", "{time}"],
            [count($positions), GeneralUtils::translateTime($this->getDuration())],
            (string) ($this->config["broadcasts"]["start"] ?? "&r&3&lEnvoy &8| &r&f{count} crates have landed across &bWarzone&f. They will remain for &b{time}&f.")
        ));

        return true;
    }

    private function endActiveEvent(): void
    {
        if ($this->active !== null) {
            $this->clearTrackedPositions((array) ($this->active["positions"] ?? []));
        }

        $this->active = null;
        $this->lastIndicatorAt = 0;
        $this->nextIndicatorIndex = 0;
        $this->scheduleNext();
        $this->saveState();
        $this->broadcast((string) ($this->config["broadcasts"]["end"] ?? "&r&l&bEnvoy &8| &r&7The envoy event has ended."));
    }

    /**
     * @param array<int, array<string, mixed>> $used
     */
    private function findSpawnPosition(World $world, Vector3 $min, Vector3 $max, array $used): ?Vector3
    {
        $warzone = Loader::getRegionManager()->getRegionByName("Warzone");
        $spawn = Loader::getRegionManager()->getRegionByName("Spawn");
        for ($attempt = 0; $attempt < $this->getSpawnAttempts(); $attempt++) {
            $x = mt_rand((int) $min->x, (int) $max->x);
            $z = mt_rand((int) $min->z, (int) $max->z);

            $occupied = false;
            foreach ($used as $existing) {
                if ((int) ($existing["x"] ?? 0) === $x && (int) ($existing["z"] ?? 0) === $z) {
                    $occupied = true;
                    break;
                }
            }

            if ($occupied) {
                continue;
            }

            $surfaceY = $world->getHighestBlockAt($x, $z);
            $solidY = null;
            for ($scanY = $surfaceY; $scanY > max(-64, $surfaceY - 16); $scanY--) {
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

            $y = $solidY + 1;
            $vec = new Vector3($x, $y, $z);
            if ($warzone !== null && !$warzone->contains($vec)) {
                continue;
            }

            if ($spawn !== null && $spawn->contains($vec)) {
                continue;
            }

            $target = $world->getBlock($vec);
            $below = $world->getBlock(new Vector3($x, $solidY, $z));
            if (!$target->isTransparent() || $below->isTransparent() || $below instanceof Liquid) {
                continue;
            }

            return $vec;
        }

        return null;
    }

    private function getRemainingCrates(): int
    {
        if ($this->active === null) {
            return 0;
        }

        $world = Server::getInstance()->getWorldManager()->getDefaultWorld();
        if ($world === null) {
            return count((array) ($this->active["positions"] ?? []));
        }

        return count($this->getValidTrackedPositions($world));
    }

    private function getIndicatorInterval(): int
    {
        return max(3, GeneralUtils::parseTimeString((string) ($this->config["indicator-interval"] ?? "12s")));
    }

    private function shouldPulseIndicators(int $now): bool
    {
        return $this->lastIndicatorAt <= 0 || ($now - $this->lastIndicatorAt) >= $this->getIndicatorInterval();
    }

    private function pulseActiveIndicator(): void
    {
        if ($this->active === null) {
            return;
        }

        $world = Server::getInstance()->getWorldManager()->getDefaultWorld();
        if ($world === null) {
            return;
        }

        $positions = $this->getValidTrackedPositions($world);
        if ($positions === []) {
            return;
        }

        $selection = $positions[$this->nextIndicatorIndex % count($positions)];
        $this->nextIndicatorIndex = ($this->nextIndicatorIndex + 1) % count($positions);
        $this->lastIndicatorAt = time();
        $this->spawnFirework(
            ((int) $selection["x"]) + 0.5,
            ((int) $selection["y"]) + 1,
            ((int) $selection["z"]) + 0.5,
            $world,
            (string) $selection["type"]
        );
    }

    private function scheduleNext(): void
    {
        $this->active = null;
        $this->lastIndicatorAt = 0;
        $this->nextIndicatorIndex = 0;
        $this->nextStartAt = time() + $this->getInterval();
    }

    private function tickSpecialEvent(int $now): void
    {
        if ($this->specialEvent !== null && ($this->specialEvent["ends_at"] ?? 0) <= $now) {
            $this->endSpecialEvent();
        }

        if ($this->specialEvent === null && $this->nextSpecialEventStartAt > 0 && $now >= $this->nextSpecialEventStartAt) {
            $this->startSpecialEvent();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function getSpecialEventConfig(): array
    {
        $config = (array) ($this->config["special-event"] ?? []);
        return (bool) ($config["enabled"] ?? false) ? $config : [];
    }

    private function getSpecialEventInterval(): int
    {
        $config = $this->getSpecialEventConfig();
        return max(300, GeneralUtils::parseTimeString((string) ($config["interval"] ?? "8h")));
    }

    private function getSpecialEventInitialDelay(): int
    {
        $config = $this->getSpecialEventConfig();
        return max(300, GeneralUtils::parseTimeString((string) ($config["initial-delay"] ?? ($config["interval"] ?? "8h"))));
    }

    private function getSpecialEventDuration(): int
    {
        $config = $this->getSpecialEventConfig();
        return max(60, GeneralUtils::parseTimeString((string) ($config["duration"] ?? "1h")));
    }

    private function scheduleNextSpecialEvent(bool $initial = false): void
    {
        $config = $this->getSpecialEventConfig();
        if ($config === []) {
            $this->nextSpecialEventStartAt = 0;
            return;
        }

        $delay = $initial ? $this->getSpecialEventInitialDelay() : $this->getSpecialEventInterval();
        $this->nextSpecialEventStartAt = time() + $delay;
    }

    private function startSpecialEvent(): void
    {
        $config = $this->getSpecialEventConfig();
        if ($config === []) {
            $this->nextSpecialEventStartAt = 0;
            return;
        }

        $duration = $this->getSpecialEventDuration();
        $this->specialEvent = [
            "id" => "exotic_legendary_godly_rush",
            "ends_at" => time() + $duration,
        ];
        $this->nextSpecialEventStartAt = 0;
        $this->saveState();

        $message = (string) ($config["broadcasts"]["start"] ?? "&r&l&dWorld Event &8| &r&fFor the next &d{time}&f, envoys will only spawn as &dExotic&f, &6Legendary&f, or &5Godly&f crates, with fewer crates than usual.");
        $this->broadcast(str_replace("{time}", GeneralUtils::translateTime($duration), $message));
    }

    private function endSpecialEvent(): void
    {
        if ($this->specialEvent === null) {
            return;
        }

        $config = $this->getSpecialEventConfig();
        $message = (string) ($config["broadcasts"]["end"] ?? "&r&l&dWorld Event &8| &r&7The exotic envoy surge has ended.");
        $this->specialEvent = null;
        $this->scheduleNextSpecialEvent();
        $this->saveState();
        $this->broadcast($message);
    }

    private function isTypeEnabled(string $type): bool
    {
        return isset($this->getNormalRollTypes()[$this->normalizeTypeId($type)]);
    }

    private function isTypeConfigured(string $type): bool
    {
        return isset($this->getConfiguredTypes()[$this->normalizeTypeId($type)]);
    }

    private function upgradeTypeForPlayer(Player $player, string $crateType): string
    {
        if (!PetManager::isEnvoyUpgradeActive($player)) {
            return $crateType;
        }

        if (mt_rand(1, 100) > PetManager::getEnvoyUpgradeChance()) {
            return $crateType;
        }

        return $this->getNextType($crateType);
    }

    private function getNextType(string $type): string
    {
        $type = $this->normalizeTypeId($type);
        $next = match ($type) {
            "simple" => "unique",
            "unique" => "elite",
            "elite" => "exotic",
            "exotic" => "legendary",
            "legendary" => "godly",
            default => $type,
        };

        if ($this->specialEvent !== null) {
            return $this->isTypeConfigured($next) ? $next : $type;
        }

        return $this->isTypeEnabled($next) ? $next : $type;
    }

    private function removeActivePosition(Vector3 $position): void
    {
        if ($this->active === null) {
            return;
        }

        $world = Server::getInstance()->getWorldManager()->getDefaultWorld();
        $updated = [];
        foreach ((array) ($this->active["positions"] ?? []) as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $matches = (int) ($entry["x"] ?? 0) === $position->getFloorX()
                && (int) ($entry["y"] ?? 0) === $position->getFloorY()
                && (int) ($entry["z"] ?? 0) === $position->getFloorZ();

            if ($matches) {
                if ($world !== null) {
                    $this->despawnTrackedBeasts($world, (array) ($entry["beasts"] ?? []));
                }
                continue;
            }

            $updated[] = $entry;
        }

        $this->active["positions"] = array_values($updated);
    }

    private function loadState(): void
    {
        Loader::getDatabase()->executeSelect(QueryStmts::SERVER_GLACIA_SELECT, [], function(array $rows): void {
            $stateKeys = $this->getStateKeys();
            foreach ($rows as $row) {
                if (!in_array((string) ($row["key"] ?? ""), $stateKeys, true)) {
                    continue;
                }

                $decoded = StorageUtils::decodeJsonString((string) ($row["value"] ?? ""), "envoy state");
                if (!is_array($decoded)) {
                    break;
                }

                $this->active = is_array($decoded["active"] ?? null) ? $decoded["active"] : null;
                $this->nextStartAt = max(0, (int) ($decoded["next_start_at"] ?? 0));
                $this->specialEvent = is_array($decoded["special_event"] ?? null) ? $decoded["special_event"] : null;
                $this->nextSpecialEventStartAt = max(0, (int) ($decoded["next_special_event_start_at"] ?? 0));
                break;
            }

            $this->loaded = true;
            if ($this->nextStartAt <= time()) {
                $this->nextStartAt = 0;
            }

            if ($this->active === null && $this->nextStartAt <= 0) {
                $this->scheduleNext();
            }
            if ($this->specialEvent === null && $this->nextSpecialEventStartAt <= 0) {
                $this->scheduleNextSpecialEvent(true);
            }
            $this->saveState();
        });
    }

    private function saveState(): void
    {
        $payload = StorageUtils::encodeJsonString([
            "active" => $this->active,
            "next_start_at" => $this->nextStartAt,
            "special_event" => $this->specialEvent,
            "next_special_event_start_at" => $this->nextSpecialEventStartAt,
        ], "envoy state");
        if ($payload === null) {
            return;
        }

        Loader::getDatabase()->executeInsert(QueryStmts::SERVER_GLACIA_CREATE, [
            "key" => $this->getPrimaryStateKey(),
            "value" => $payload,
        ]);
    }

    /**
     * @return string[]
     */
    private function getStateKeys(): array
    {
        return ["envoy_state", "envoystate"];
    }

    private function getPrimaryStateKey(): string
    {
        return "envoy_state";
    }

    /**
     * @param array<int, mixed> $positions
     */
    private function clearTrackedPositions(array $positions): void
    {
        $world = Server::getInstance()->getWorldManager()->getDefaultWorld();
        if ($world === null) {
            return;
        }

        foreach ($positions as $position) {
            if (!is_array($position)) {
                continue;
            }

            $vec = new Vector3((int) ($position["x"] ?? 0), (int) ($position["y"] ?? 0), (int) ($position["z"] ?? 0));
            $block = $world->getBlock($vec);
            $data = Loader::getBlockData()->get($block);
            if (is_array($data) && ($data["type"] ?? "") === "envoy") {
                $world->setBlock($vec, VanillaBlocks::AIR());
                Loader::getBlockData()->remove($block);
            }

            $this->despawnTrackedBeasts($world, (array) ($position["beasts"] ?? []));
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getValidTrackedPositions(World $world): array
    {
        if ($this->active === null) {
            return [];
        }

        $remaining = [];
        foreach ((array) ($this->active["positions"] ?? []) as $position) {
            if (!is_array($position)) {
                continue;
            }

            $x = (int) ($position["x"] ?? 0);
            $y = (int) ($position["y"] ?? 0);
            $z = (int) ($position["z"] ?? 0);
            $block = $world->getBlock(new Vector3($x, $y, $z));
            $data = Loader::getBlockData()->get($block);
            if (is_array($data) && ($data["type"] ?? "") === "envoy") {
                $remaining[] = $position;
                continue;
            }

            $this->despawnTrackedBeasts($world, (array) ($position["beasts"] ?? []));
        }

        if (count($remaining) !== count((array) ($this->active["positions"] ?? []))) {
            $this->active["positions"] = array_values($remaining);
            $this->saveState();
        }

        return array_values($remaining);
    }

    /**
     * @return int[]
     */
    private function spawnGuardsFor(World $world, Vector3 $spawn, string $type): array
    {
        $typeConfig = $this->getTypeConfig($type);
        $mobWave = is_array($typeConfig["mob-wave"] ?? null) ? $typeConfig["mob-wave"] : [];
        if (!(bool) ($mobWave["enabled"] ?? false)) {
            return [];
        }

        $pool = is_array($mobWave["mobs"] ?? null) ? $mobWave["mobs"] : [];
        if ($pool === []) {
            return [];
        }

        $count = max(1, (int) ($mobWave["count"] ?? 2));
        $radius = max(1, (int) ($mobWave["spawn-radius"] ?? 4));
        $name = (string) ($mobWave["name"] ?? "&r&3&lEnvoy Beast");

        $spawned = [];
        for ($i = 0; $i < $count; $i++) {
            $entry = RewardResolver::rollEntry($pool);
            if ($entry === null) {
                continue;
            }

            $mobType = strtolower((string) ($entry["id"] ?? $entry["mob"] ?? ""));
            $entity = $this->createGuardEntity($world, $spawn, $mobType, $radius);
            if ($entity === null) {
                continue;
            }

            $entity->setNameTag(C::colorize($name));
            $entity->setNameTagAlwaysVisible();
            $entity->spawnToAll();
            $spawned[] = $entity->getId();
        }

        return $spawned;
    }

    private function createGuardEntity(World $world, Vector3 $origin, string $mobType, int $radius): ?BaseHostileEntity
    {
        $entityClass = match ($mobType) {
            "blaze", "envoy_blaze" => EnvoyBlaze::class,
            "ghast", "envoy_ghast" => EnvoyGhast::class,
            "skeleton", "envoy_skeleton" => EnvoySkeleton::class,
            "slime", "envoy_slime" => EnvoySlime::class,
            "baby_zombie", "envoy_baby_zombie" => EnvoyBabyZombie::class,
            "zombie", "envoy_zombie" => EnvoyZombie::class,
            "pigman", "zombie_pigman", "pig_zombie", "envoy_zombie_pigman" => EnvoyZombiePigman::class,
            default => null,
        };

        if ($entityClass === null) {
            return null;
        }

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $x = $origin->getFloorX() + mt_rand(-$radius, $radius);
            $z = $origin->getFloorZ() + mt_rand(-$radius, $radius);
            $y = $world->getHighestBlockAt($x, $z) + 1;
            $spawn = new Vector3($x + 0.5, $y, $z + 0.5);
            $block = $world->getBlock(new Vector3($x, $y, $z));
            if (!$block->isTransparent()) {
                continue;
            }

            return new $entityClass(
                new Location($spawn->x, $spawn->y, $spawn->z, $world, 0, 0),
                (new CompoundTag())->setInt("stack", 1)
            );
        }

        return null;
    }

    /**
     * @param array<int, mixed> $beastIds
     */
    private function despawnTrackedBeasts(World $world, array $beastIds): void
    {
        foreach ($beastIds as $entityId) {
            $entity = $world->getEntity((int) $entityId);
            if ($entity instanceof SpawnerEntity && !$entity->isClosed()) {
                $entity->flagForDespawn();
            }
        }
    }

    private function formatTypeName(string $type): string
    {
        $display = match ($this->normalizeTypeId($type)) {
            "simple" => "&fSimple",
            "unique" => "&aUnique",
            "elite" => "&bElite",
            "exotic" => "&dExotic",
            "legendary" => "&6Legendary",
            "godly" => "&5Godly",
            default => "&f" . ucfirst($type),
        };

        return C::colorize($display);
    }

    private function broadcast(string $message): void
    {
        foreach (Server::getInstance()->getOnlinePlayers() as $player) {
            $player->sendMessage(C::colorize($message));
        }
    }

    private function spawnFirework(float $x, float $y, float $z, World $world, string $type): void
    {
        [$mainColors, $fadeColors] = match ($this->normalizeTypeId($type)) {
            "simple" => [[DyeColor::LIGHT_GRAY(), DyeColor::WHITE()], [DyeColor::WHITE(), DyeColor::LIGHT_GRAY()]],
            "unique" => [[DyeColor::LIME(), DyeColor::GREEN()], [DyeColor::GREEN(), DyeColor::LIME()]],
            "elite" => [[DyeColor::CYAN(), DyeColor::LIGHT_BLUE()], [DyeColor::LIGHT_BLUE(), DyeColor::CYAN()]],
            "exotic" => [[DyeColor::PURPLE(), DyeColor::MAGENTA()], [DyeColor::MAGENTA(), DyeColor::PINK()]],
            "legendary" => [[DyeColor::ORANGE(), DyeColor::YELLOW()], [DyeColor::YELLOW(), DyeColor::ORANGE()]],
            "godly" => [[DyeColor::PURPLE(), DyeColor::BLUE()], [DyeColor::PINK(), DyeColor::WHITE()]],
            default => [[DyeColor::WHITE()], [DyeColor::LIGHT_GRAY()]],
        };

        $firework = new FireworkRocket(
            new Location($x, $y, $z, $world, 0, 0),
            10,
            [new FireworkRocketExplosion(FireworkRocketType::LARGE_BALL, $mainColors, $fadeColors, true, true)]
        );
        $firework->spawnToAll();
    }

    private function normalizeTypeId(string $type): string
    {
        $normalized = strtolower(trim($type));
        return $normalized === "heroic" ? "godly" : $normalized;
    }
}
