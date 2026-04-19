<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\envoys;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\QueryStmts;
use ecstsy\Glacia\utils\RewardResolver;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Location;
use pocketmine\entity\object\FireworkRocket;
use pocketmine\item\FireworkRocketExplosion;
use pocketmine\item\FireworkRocketType;
use pocketmine\math\Vector3;
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
    }

    public function tick(): void
    {
        if (!(bool) ($this->config["enabled"] ?? true) || !$this->loaded) {
            return;
        }

        $now = time();
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
     * @return string[]
     */
    public function getTypeIds(): array
    {
        return array_keys($this->getEnabledTypes());
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
        $typeConfig = $this->getTypeConfig($crateType);
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

        $world->setBlock($position, VanillaBlocks::AIR());
        Loader::getBlockData()->remove($block);
        $this->removeActivePosition($position);

        foreach ($rewards as $reward) {
            foreach ($player->getInventory()->addItem($reward) as $leftover) {
                $world->dropItem($player->getPosition(), $leftover);
            }
        }

        PlayerUtils::playSound($player, "random.levelup");
        $world->addSound($position, new XpCollectSound());
        $this->spawnFirework($position->x + 0.5, $position->y + 1, $position->z + 0.5, $world, $crateType);
        $remaining = $this->getRemainingCrates();
        $player->sendMessage(C::colorize(
            "&r&bEnvoy &8| &r&aYou looted a " . $this->formatTypeName($crateType) . " &r&acrate&7. "
            . "&f{$remaining} &7remaining."
        ));

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
        return max(1, (int) ($this->config["crate-count"] ?? 10));
    }

    private function getSpawnAttempts(): int
    {
        return max(10, (int) ($this->config["spawn-attempts"] ?? 120));
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getEnabledTypes(): array
    {
        $enabled = [];
        foreach ((array) ($this->config["types"] ?? []) as $id => $entry) {
            if (!is_array($entry) || !(bool) ($entry["enabled"] ?? true)) {
                continue;
            }

            $enabled[(string) $id] = $entry;
        }

        return $enabled;
    }

    /**
     * @return array<string, mixed>
     */
    private function getTypeConfig(string $type): array
    {
        return (array) ($this->getEnabledTypes()[$type] ?? []);
    }

    private function rollType(): string
    {
        $types = $this->getEnabledTypes();
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

        $types = $this->getEnabledTypes();
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
        for ($attempt = 0; $attempt < $this->getSpawnAttempts(); $attempt++) {
            $x = mt_rand((int) $min->x, (int) $max->x);
            $z = mt_rand((int) $min->z, (int) $max->z);
            $y = $world->getHighestBlockAt($x, $z) + 1;
            $vec = new Vector3($x, $y, $z);

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

            $target = $world->getBlock($vec);
            $below = $world->getBlock(new Vector3($x, $y - 1, $z));
            if (!$target->isTransparent() || $below->isTransparent()) {
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
            }
        }

        if (count($remaining) !== count((array) ($this->active["positions"] ?? []))) {
            $this->active["positions"] = array_values($remaining);
            $this->saveState();
        }

        return count($remaining);
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

        $positions = array_values(array_filter(
            (array) ($this->active["positions"] ?? []),
            static fn(mixed $entry): bool => is_array($entry)
                && isset($entry["x"], $entry["y"], $entry["z"], $entry["type"])
        ));

        if ($positions === []) {
            return;
        }

        $world = Server::getInstance()->getWorldManager()->getDefaultWorld();
        if ($world === null) {
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

    private function removeActivePosition(Vector3 $position): void
    {
        if ($this->active === null) {
            return;
        }

        $this->active["positions"] = array_values(array_filter(
            (array) ($this->active["positions"] ?? []),
            static fn(array $entry): bool => !(
                (int) ($entry["x"] ?? 0) === $position->getFloorX()
                && (int) ($entry["y"] ?? 0) === $position->getFloorY()
                && (int) ($entry["z"] ?? 0) === $position->getFloorZ()
            )
        ));
    }

    private function loadState(): void
    {
        Loader::getDatabase()->executeSelect(QueryStmts::SERVER_GLACIA_SELECT, [], function(array $rows): void {
            $stateKeys = $this->getStateKeys();
            foreach ($rows as $row) {
                if (!in_array((string) ($row["key"] ?? ""), $stateKeys, true)) {
                    continue;
                }

                $decoded = json_decode((string) ($row["value"] ?? ""), true);
                if (!is_array($decoded)) {
                    break;
                }

                $this->active = is_array($decoded["active"] ?? null) ? $decoded["active"] : null;
                $this->nextStartAt = max(0, (int) ($decoded["next_start_at"] ?? 0));
                break;
            }

            if ($this->active !== null) {
                $this->clearTrackedPositions((array) ($this->active["positions"] ?? []));
                $this->active = null;
            }

            $this->loaded = true;
            if ($this->nextStartAt <= time()) {
                $this->nextStartAt = 0;
            }

            if ($this->active === null && $this->nextStartAt <= 0) {
                $this->scheduleNext();
            }
            $this->saveState();
        });
    }

    private function saveState(): void
    {
        $payload = json_encode([
            "active" => $this->active,
            "next_start_at" => $this->nextStartAt,
        ], JSON_UNESCAPED_SLASHES);

        if ($payload === false) {
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
        }
    }

    private function formatTypeName(string $type): string
    {
        $display = match (strtolower($type)) {
            "simple" => "&fSimple",
            "unique" => "&aUnique",
            "elite" => "&bElite",
            "exotic" => "&dExotic",
            "legendary" => "&6Legendary",
            "heroic" => "&3Heroic",
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
        [$mainColors, $fadeColors] = match ($type) {
            "simple" => [[DyeColor::LIGHT_GRAY(), DyeColor::WHITE()], [DyeColor::WHITE(), DyeColor::LIGHT_GRAY()]],
            "unique" => [[DyeColor::LIME(), DyeColor::GREEN()], [DyeColor::GREEN(), DyeColor::LIME()]],
            "elite" => [[DyeColor::CYAN(), DyeColor::LIGHT_BLUE()], [DyeColor::LIGHT_BLUE(), DyeColor::CYAN()]],
            "exotic" => [[DyeColor::PURPLE(), DyeColor::MAGENTA()], [DyeColor::MAGENTA(), DyeColor::PINK()]],
            "legendary" => [[DyeColor::ORANGE(), DyeColor::YELLOW()], [DyeColor::YELLOW(), DyeColor::ORANGE()]],
            "heroic" => [[DyeColor::CYAN(), DyeColor::BLUE()], [DyeColor::BLUE(), DyeColor::WHITE()]],
            default => [[DyeColor::WHITE()], [DyeColor::LIGHT_GRAY()]],
        };

        $firework = new FireworkRocket(
            new Location($x, $y, $z, $world, 0, 0),
            10,
            [new FireworkRocketExplosion(FireworkRocketType::LARGE_BALL, $mainColors, $fadeColors, true, true)]
        );
        $firework->spawnToAll();
    }
}
