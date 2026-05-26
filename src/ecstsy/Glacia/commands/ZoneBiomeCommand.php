<?php

declare(strict_types=1);

namespace ecstsy\Glacia\commands;

use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\zones\ZoneManager;
use pocketmine\command\CommandSender;
use pocketmine\data\bedrock\BiomeIds;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\TaskHandler;
use pocketmine\utils\TextFormat as C;
use pocketmine\world\format\Chunk;
use pocketmine\world\World;

final class ZoneBiomeCommand extends BaseCommand
{
    private const CHUNKS_PER_TICK = 6;
    private const MAX_CHUNKS_PER_JOB = 4096;

    /** @var array<string, TaskHandler> */
    private static array $jobs = [];

    protected function prepare(): void
    {
        $this->setPermission($this->getPermission());
        $this->registerArgument(0, new RawStringArgument("action", true));
        $this->registerArgument(1, new RawStringArgument("zone", true));
        $this->registerArgument(2, new RawStringArgument("biome", true));
    }

    public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
    {
        if (!$sender->hasPermission($this->getPermission())) {
            $sender->sendMessage(C::colorize("&r&cYou do not have permission to edit zone biomes."));
            return;
        }

        $action = strtolower((string) ($args["action"] ?? "help"));
        if ($action === "help" || $action === "") {
            $this->sendHelp($sender);
            return;
        }

        if ($action === "list") {
            $sender->sendMessage(C::colorize("&r&bZone Biomes &8| &7Available: &f" . implode(", ", array_keys(self::getBiomeMap()))));
            return;
        }

        if ($action === "cancel") {
            $zoneId = strtolower((string) ($args["zone"] ?? ""));
            if ($zoneId === "") {
                $sender->sendMessage(C::colorize("&r&cUsage: &f/zonebiome cancel <zone>"));
                return;
            }

            $this->cancelJob($sender, $zoneId);
            return;
        }

        if ($action === "set") {
            $zoneId = strtolower((string) ($args["zone"] ?? ""));
            $biome = strtolower((string) ($args["biome"] ?? ""));
        } else {
            $zoneId = $action;
            $biome = strtolower((string) ($args["zone"] ?? ""));
        }

        if ($zoneId === "" || $biome === "") {
            $this->sendHelp($sender);
            return;
        }

        $this->startBiomeJob($sender, $zoneId, $biome);
    }

    private function startBiomeJob(CommandSender $sender, string $zoneId, string $biomeName): void
    {
        $zone = ZoneManager::getZone($zoneId);
        if ($zone === null) {
            $sender->sendMessage(C::colorize("&r&cUnknown zone &f{$zoneId}&c. Available: &f" . implode(", ", array_keys(ZoneManager::getZones()))));
            return;
        }

        $biomeId = self::getBiomeMap()[$biomeName] ?? null;
        if ($biomeId === null) {
            $sender->sendMessage(C::colorize("&r&cUnknown biome &f{$biomeName}&c. Use &f/zonebiome list &cfor options."));
            return;
        }

        $chunks = $this->collectZoneChunks($zone);
        if ($chunks === []) {
            $sender->sendMessage(C::colorize("&r&cThat zone does not have a bounded area or radius to apply biomes to."));
            return;
        }

        if (count($chunks) > self::MAX_CHUNKS_PER_JOB) {
            $sender->sendMessage(C::colorize("&r&cThat zone covers &f" . number_format(count($chunks)) . " &cchunks. Max per job is &f" . self::MAX_CHUNKS_PER_JOB . "&c."));
            return;
        }

        $worldName = (string) ($zone["world"] ?? "");
        $worldManager = Loader::getInstance()->getServer()->getWorldManager();
        if (!$worldManager->isWorldLoaded($worldName) && !$worldManager->loadWorld($worldName)) {
            $sender->sendMessage(C::colorize("&r&cWorld &f{$worldName} &cis not loaded/generated."));
            return;
        }

        $world = $worldManager->getWorldByName($worldName);
        if (!$world instanceof World) {
            $sender->sendMessage(C::colorize("&r&cFailed to access world &f{$worldName}&c."));
            return;
        }

        if (isset(self::$jobs[$zoneId])) {
            self::$jobs[$zoneId]->cancel();
            unset(self::$jobs[$zoneId]);
        }

        $total = count($chunks);
        $processed = 0;
        $changed = 0;
        $skipped = 0;
        $handler = null;

        $sender->sendMessage(C::colorize("&r&bZone Biome &8| &7Applying &f{$biomeName} &7to &f{$total} &7chunks in &f{$zoneId}&7..."));

        $handler = Loader::getInstance()->getScheduler()->scheduleRepeatingTask(new ClosureTask(function () use (
            $sender,
            $zoneId,
            $world,
            $biomeId,
            &$chunks,
            &$processed,
            &$changed,
            &$skipped,
            &$handler
        ): void {
            for ($i = 0; $i < self::CHUNKS_PER_TICK && $chunks !== []; $i++) {
                [$chunkX, $chunkZ] = array_shift($chunks);
                $processed++;

                $chunk = $world->loadChunk($chunkX, $chunkZ);
                if (!$chunk instanceof Chunk) {
                    $skipped++;
                    continue;
                }

                $this->applyBiomeToChunk($world, $chunk, $biomeId);
                $world->setChunk($chunkX, $chunkZ, $chunk);
                $changed++;
            }

            if ($chunks !== []) {
                return;
            }

            $world->saveChunks();
            unset(self::$jobs[$zoneId]);
            $handler?->cancel();
            $sender->sendMessage(C::colorize(
                "&r&bZone Biome &8| &aDone. Changed &f" . number_format($changed) .
                " &achunks, skipped &f" . number_format($skipped) .
                "&a ungenerated chunks. Rejoin or reload chunks if the fog does not appear immediately."
            ));
        }), 1);

        self::$jobs[$zoneId] = $handler;
    }

    /**
     * @param array<string, mixed> $zone
     * @return array<int, array{0:int, 1:int}>
     */
    private function collectZoneChunks(array $zone): array
    {
        if ((bool) ($zone["full_world"] ?? false)) {
            return [];
        }

        $chunks = [];
        $minX = $zone["min_x"] ?? null;
        $maxX = $zone["max_x"] ?? null;
        $minZ = $zone["min_z"] ?? null;
        $maxZ = $zone["max_z"] ?? null;

        if ($minX !== null && $maxX !== null && $minZ !== null && $maxZ !== null) {
            for ($chunkX = ((int) floor((float) $minX)) >> Chunk::COORD_BIT_SIZE; $chunkX <= ((int) floor((float) $maxX)) >> Chunk::COORD_BIT_SIZE; $chunkX++) {
                for ($chunkZ = ((int) floor((float) $minZ)) >> Chunk::COORD_BIT_SIZE; $chunkZ <= ((int) floor((float) $maxZ)) >> Chunk::COORD_BIT_SIZE; $chunkZ++) {
                    $chunks[] = [$chunkX, $chunkZ];
                }
            }
            return $chunks;
        }

        $radius = (int) ($zone["radius"] ?? 0);
        if ($radius <= 0) {
            return [];
        }

        $centerX = (float) ($zone["x"] ?? 0.0);
        $centerZ = (float) ($zone["z"] ?? 0.0);
        $minChunkX = ((int) floor($centerX - $radius)) >> Chunk::COORD_BIT_SIZE;
        $maxChunkX = ((int) floor($centerX + $radius)) >> Chunk::COORD_BIT_SIZE;
        $minChunkZ = ((int) floor($centerZ - $radius)) >> Chunk::COORD_BIT_SIZE;
        $maxChunkZ = ((int) floor($centerZ + $radius)) >> Chunk::COORD_BIT_SIZE;

        for ($chunkX = $minChunkX; $chunkX <= $maxChunkX; $chunkX++) {
            for ($chunkZ = $minChunkZ; $chunkZ <= $maxChunkZ; $chunkZ++) {
                if (!$this->chunkIntersectsCircle($chunkX, $chunkZ, $centerX, $centerZ, $radius)) {
                    continue;
                }
                $chunks[] = [$chunkX, $chunkZ];
            }
        }

        return $chunks;
    }

    private function chunkIntersectsCircle(int $chunkX, int $chunkZ, float $centerX, float $centerZ, int $radius): bool
    {
        $minX = $chunkX << Chunk::COORD_BIT_SIZE;
        $minZ = $chunkZ << Chunk::COORD_BIT_SIZE;
        $maxX = $minX + Chunk::COORD_MASK;
        $maxZ = $minZ + Chunk::COORD_MASK;

        $closestX = max($minX, min($centerX, $maxX));
        $closestZ = max($minZ, min($centerZ, $maxZ));
        $dx = $centerX - $closestX;
        $dz = $centerZ - $closestZ;

        return ($dx * $dx) + ($dz * $dz) <= ($radius * $radius);
    }

    private function applyBiomeToChunk(World $world, Chunk $chunk, int $biomeId): void
    {
        for ($x = 0; $x <= Chunk::COORD_MASK; $x++) {
            for ($z = 0; $z <= Chunk::COORD_MASK; $z++) {
                for ($y = $world->getMinY(); $y < $world->getMaxY(); $y++) {
                    $chunk->setBiomeId($x, $y, $z, $biomeId);
                }
            }
        }
    }

    private function cancelJob(CommandSender $sender, string $zoneId): void
    {
        if (!isset(self::$jobs[$zoneId])) {
            $sender->sendMessage(C::colorize("&r&eNo active biome job for &f{$zoneId}&e."));
            return;
        }

        self::$jobs[$zoneId]->cancel();
        unset(self::$jobs[$zoneId]);
        $sender->sendMessage(C::colorize("&r&aCancelled biome job for &f{$zoneId}&a."));
    }

    private function sendHelp(CommandSender $sender): void
    {
        $sender->sendMessage(C::colorize("&r&bZone Biome &8| &7Usage:"));
        $sender->sendMessage(C::colorize("&r&7- &f/zonebiome set <zone> <biome>"));
        $sender->sendMessage(C::colorize("&r&7- &f/zonebiome <zone> <biome>"));
        $sender->sendMessage(C::colorize("&r&7- &f/zonebiome cancel <zone>"));
        $sender->sendMessage(C::colorize("&r&7- &f/zonebiome list"));
    }

    /**
     * @return array<string, int>
     */
    private static function getBiomeMap(): array
    {
        return [
            "plains" => BiomeIds::PLAINS,
            "ice_plains" => BiomeIds::ICE_PLAINS,
            "icy_plains" => BiomeIds::ICE_PLAINS,
            "snowy_plains" => BiomeIds::ICE_PLAINS,
            "snow" => BiomeIds::ICE_PLAINS,
            "frost" => BiomeIds::ICE_PLAINS,
            "frostbite" => BiomeIds::ICE_PLAINS,
            "frostbite_valley" => BiomeIds::ICE_PLAINS,
            "frozen_river" => BiomeIds::FROZEN_RIVER,
            "cold_taiga" => BiomeIds::COLD_TAIGA,
            "taiga" => BiomeIds::TAIGA,
            "desert" => BiomeIds::DESERT,
            "nether" => BiomeIds::HELL,
            "hell" => BiomeIds::HELL,
            "end" => BiomeIds::THE_END,
            "the_end" => BiomeIds::THE_END,
        ];
    }

    public function getPermission(): string
    {
        return "glacia.admin";
    }
}
