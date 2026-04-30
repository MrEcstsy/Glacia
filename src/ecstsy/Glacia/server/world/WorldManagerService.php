<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\world;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\world\audit\WorldAuditLogger;
use ecstsy\Glacia\server\world\generator\EndGenerator;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use pocketmine\world\generator\Generator;
use pocketmine\world\generator\GeneratorManager;
use pocketmine\world\World;
use pocketmine\world\WorldException;
use pocketmine\world\WorldCreationOptions;
use function array_keys;
use function array_values;
use function array_unique;
use function is_dir;
use function is_string;
use function file_exists;
use function preg_match;
use function rtrim;
use function scandir;
use function sprintf;
use function strtolower;
use function trim;
use const SCANDIR_SORT_ASCENDING;

final class WorldManagerService {
    private const GAMERULE_RANDOM_TICK_SPEED = "randomtickspeed";
    private const GAMERULE_DAYLIGHT_CYCLE = "dodaylightcycle";

    private const SUPPORTED_GAMERULES = [
        self::GAMERULE_RANDOM_TICK_SPEED => "int",
        self::GAMERULE_DAYLIGHT_CYCLE => "bool",
    ];

    private static ?\ReflectionProperty $randomTickSpeedProperty = null;


    private bool $enabled = true;
    private bool $autoLoad = true;

    /** @var array<string, WorldDefinition> */
    private array $definitions = [];

    public function __construct(private Loader $loader) {
    }

    public function loadFromConfig(): void {
        $config = GeneralUtils::getConfiguration($this->loader, "config.yml");
        $section = (array) ($config?->get("worlds", []) ?? []);

        $this->enabled = (bool) ($section["enabled"] ?? true);
        $this->autoLoad = (bool) ($section["auto-load"] ?? true);

        $this->definitions = [];
        $definitions = (array) ($section["definitions"] ?? []);
        foreach ($definitions as $name => $data) {
            if (!is_string($name) || trim($name) === "" || !is_array($data)) {
                continue;
            }
            $def = WorldDefinition::fromArray($name, $data);
            $this->definitions[strtolower($def->name)] = $def;
        }

        $invalidGenerators = [];
        foreach ($this->definitions as $definition) {
            if (GeneratorManager::getInstance()->getGenerator($definition->generator) === null) {
                $invalidGenerators[] = $definition->name . "=" . $definition->generator;
            }
        }
        if ($invalidGenerators !== []) {
            $this->loader->getLogger()->warning("Unknown world generators in config.yml: " . implode(", ", array_values($invalidGenerators)));
        }
    }

    /**
     * @return array{loaded:int,generated:int,skipped:int,failed:int}
     */
    public function ensureConfiguredWorlds(): array {
        $stats = ["loaded" => 0, "generated" => 0, "skipped" => 0, "failed" => 0];
        if (!$this->enabled || !$this->autoLoad) {
            return $stats;
        }

        foreach ($this->definitions as $def) {
            if (!$def->autoLoad) {
                $stats["skipped"]++;
                continue;
            }

            $beforeGenerated = $this->loader->getServer()->getWorldManager()->isWorldGenerated($def->name);
            $ok = $this->ensureWorld($def);
            if (!$ok) {
                $stats["failed"]++;
                continue;
            }

            if (!$beforeGenerated && $this->loader->getServer()->getWorldManager()->isWorldGenerated($def->name)) {
                $stats["generated"]++;
            } else {
                $stats["loaded"]++;
            }
        }

        return $stats;
    }

    public function ensureWorld(WorldDefinition $def): bool {
        $wm = $this->loader->getServer()->getWorldManager();
        $name = $def->name;

        if ($wm->isWorldLoaded($name)) {
            return true;
        }

        if (!$wm->isWorldGenerated($name)) {
            if (!$def->autoGenerate) {
                return false;
            }
            if (!$this->generateWorld($name, $def->generator, $def->seed, $def->preset)) {
                return false;
            }
        }

        return $this->loadWorld($name);
    }

    public function generateWorld(string $name, string $generator, ?string $seed = null, string $preset = ""): bool {
        if (!$this->isSafeWorldName($name)) {
            return false;
        }
        $generator = WorldDefinition::normalizeGenerator($generator);
        $entry = GeneratorManager::getInstance()->getGenerator($generator);
        if ($entry === null) {
            return false;
        }

        $wm = $this->loader->getServer()->getWorldManager();
        if ($wm->isWorldGenerated($name)) {
            return true;
        }

        $options = WorldCreationOptions::create()
            ->setGeneratorClass($entry->getGeneratorClass())
            ->setGeneratorOptions($preset);

        if ($seed !== null && trim($seed) !== "") {
            $converted = Generator::convertSeed($seed);
            if ($converted !== null) {
                $options->setSeed($converted);
            }
        }

        $ok = $wm->generateWorld($name, $options);
        if ($ok) {
            $this->rememberWorldDefinition(new WorldDefinition($name, $generator, $seed, $preset, true, true), true);
            $this->applyGeneratedWorldDefaults($name, $generator);
        }
        WorldAuditLogger::log("Generate", [
            "actor" => "system",
            "world" => $name,
            "generator" => $generator,
            "result" => $ok ? "ok" : "failed",
        ]);
        return $ok;
    }

    public function loadWorld(string $name): bool {
        if (!$this->isSafeWorldName($name)) {
            return false;
        }
        $ok = $this->loader->getServer()->getWorldManager()->loadWorld($name, true);
        if ($ok) {
            $this->applyConfiguredGamerules($name);
        }
        WorldAuditLogger::log("Load", [
            "actor" => "system",
            "world" => $name,
            "result" => $ok ? "ok" : "failed",
        ]);
        return $ok;
    }

    public function unloadWorld(string $name, ?string $actor = null): bool {
        if (!$this->isSafeWorldName($name)) {
            return false;
        }
        $wm = $this->loader->getServer()->getWorldManager();
        $world = $wm->getWorldByName($name);
        if ($world === null) {
            return false;
        }

        if ($world === $wm->getDefaultWorld()) {
            return false;
        }

        if (count($world->getPlayers()) > 0) {
            return false;
        }

        $ok = $wm->unloadWorld($world);
        WorldAuditLogger::log("Unload", [
            "actor" => $actor ?? "system",
            "world" => $name,
            "result" => $ok ? "ok" : "failed",
        ]);
        return $ok;
    }

    public function deleteWorld(string $name, ?string $actor = null): bool {
        $name = trim($name);
        if (!$this->isSafeWorldName($name)) {
            return false;
        }

        $wm = $this->loader->getServer()->getWorldManager();
        $default = $wm->getDefaultWorld();
        if ($default !== null && strtolower($default->getFolderName()) === strtolower($name)) {
            return false;
        }

        $world = $wm->getWorldByName($name);
        if ($world !== null) {
            if (count($world->getPlayers()) > 0 || !$wm->unloadWorld($world)) {
                return false;
            }
        }

        $worldPath = rtrim($this->loader->getServer()->getDataPath(), "\\/") . DIRECTORY_SEPARATOR . "worlds" . DIRECTORY_SEPARATOR . $name;
        if (!file_exists($worldPath) || !is_dir($worldPath)) {
            return false;
        }

        $ok = $this->deleteDirectory($worldPath);
        WorldAuditLogger::log("Delete", [
            "actor" => $actor ?? "system",
            "world" => $name,
            "result" => $ok ? "ok" : "failed",
        ]);
        return $ok;
    }

    public function setWorldSpawn(string $name, Vector3 $spawn, ?string $actor = null): bool {
        $world = $this->loader->getServer()->getWorldManager()->getWorldByName($name);
        if ($world === null) {
            return false;
        }
        $world->setSpawnLocation($spawn);
        WorldAuditLogger::log("SetSpawn", [
            "actor" => $actor ?? "system",
            "world" => $name,
            "x" => $spawn->getFloorX(),
            "y" => $spawn->getFloorY(),
            "z" => $spawn->getFloorZ(),
            "result" => "ok",
        ]);
        return true;
    }

    public function teleportToWorld(Player $player, string $name, ?Player $target = null): bool {
        $wm = $this->loader->getServer()->getWorldManager();
        if (!$wm->isWorldLoaded($name) && !$this->loadWorld($name)) {
            return false;
        }

        $world = $wm->getWorldByName($name);
        if (!$world instanceof World) {
            return false;
        }

        $destination = $target ?? $player;
        $spawn = $this->getSpawnSearchOrigin($world);
        $world->requestSafeSpawn($spawn)->onCompletion(
            function(\pocketmine\world\Position $safeSpawn) use ($player, $destination, $name) : void {
                if (!$destination->isConnected()) {
                    return;
                }

                $destination->teleport($safeSpawn);
                WorldAuditLogger::log("Teleport", [
                    "actor" => $player->getName(),
                    "target" => $destination->getName(),
                    "world" => $name,
                    "result" => "ok",
                ]);
            },
            function() use ($player, $destination, $world, $name, $spawn) : void {
                if (!$destination->isConnected()) {
                    return;
                }

                $fallback = $this->resolveFallbackSpawn($world, $spawn);
                $destination->teleport($fallback);
                WorldAuditLogger::log("Teleport", [
                    "actor" => $player->getName(),
                    "target" => $destination->getName(),
                    "world" => $name,
                    "result" => "fallback",
                ]);
            }
        );
        return true;
    }

    /**
     * @return array<int, array<string, scalar|null>>
     */
    public function listWorldInfo(): array {
        $wm = $this->loader->getServer()->getWorldManager();
        $worldNames = [];

        foreach ($this->definitions as $def) {
            $worldNames[] = $def->name;
        }

        foreach ($wm->getWorlds() as $loadedWorld) {
            $worldNames[] = $loadedWorld->getFolderName();
        }

        $worldsDir = rtrim($this->loader->getServer()->getDataPath(), "\\/") . DIRECTORY_SEPARATOR . "worlds";
        if (is_dir($worldsDir)) {
            $entries = scandir($worldsDir, SCANDIR_SORT_ASCENDING);
            if (is_array($entries)) {
                foreach ($entries as $entry) {
                    if (!is_string($entry) || $entry === "." || $entry === "..") {
                        continue;
                    }
                    if ($wm->isWorldGenerated($entry)) {
                        $worldNames[] = $entry;
                    }
                }
            }
        }

        $worldNames = array_unique($worldNames);
        $rows = [];
        foreach ($worldNames as $name) {
            if (!is_string($name) || $name === "") {
                continue;
            }
            $def = $this->definitions[strtolower($name)] ?? null;
            $rows[] = [
                "name" => $name,
                "generator" => $def?->generator ?? "unknown",
                "loaded" => $wm->isWorldLoaded($name),
                "generated" => $wm->isWorldGenerated($name),
                "auto-load" => $def?->autoLoad,
                "auto-generate" => $def?->autoGenerate,
                "configured" => $def !== null,
            ];
        }
        return $rows;
    }

    /**
     * @return array<string, scalar|null>
     */
    public function getWorldInfo(string $name): array {
        $wm = $this->loader->getServer()->getWorldManager();
        $world = $wm->getWorldByName($name);
        $def = $this->definitions[strtolower($name)] ?? null;

        return [
            "name" => $name,
            "loaded" => $wm->isWorldLoaded($name),
            "generated" => $wm->isWorldGenerated($name),
            "players" => $world?->getPlayers() !== null ? count($world->getPlayers()) : 0,
            "generator" => $def?->generator ?? "unknown",
            "auto-load" => $def?->autoLoad,
            "auto-generate" => $def?->autoGenerate,
            "gamerules" => $this->getConfiguredGamerules($name),
        ];
    }

    public function getConfiguredWorldCount(): int
    {
        return count($this->definitions);
    }

    /**
     * @return array<string, string>
     */
    public function getSupportedGamerules(): array {
        return self::SUPPORTED_GAMERULES;
    }

    /**
     * @return array<string, bool|int|string|float>
     */
    public function getConfiguredGamerules(string $worldName): array {
        return $this->definitions[strtolower($worldName)]?->gamerules ?? [];
    }

    /**
     * @return array{rule:string,value:bool|int|string|float,loaded:bool}|null
     */
    public function getWorldGamerule(string $worldName, string $rule): ?array {
        $normalizedRule = strtolower(trim($rule));
        if (!isset(self::SUPPORTED_GAMERULES[$normalizedRule])) {
            return null;
        }

        $world = $this->loader->getServer()->getWorldManager()->getWorldByName($worldName);
        if ($world instanceof World) {
            return [
                "rule" => $normalizedRule,
                "value" => $this->readLiveGameruleValue($world, $normalizedRule),
                "loaded" => true,
            ];
        }

        $configured = $this->getConfiguredGamerules($worldName);
        if (array_key_exists($normalizedRule, $configured)) {
            return [
                "rule" => $normalizedRule,
                "value" => $configured[$normalizedRule],
                "loaded" => false,
            ];
        }

        return [
            "rule" => $normalizedRule,
            "value" => $this->getDefaultGameruleValue($normalizedRule),
            "loaded" => false,
        ];
    }

    /**
     * @return array{rule:string,value:bool|int|string|float,loaded:bool}|null
     */
    public function setWorldGamerule(string $worldName, string $rule, string $value, ?string $actor = null): ?array {
        $worldName = trim($worldName);
        $normalizedRule = strtolower(trim($rule));
        if (!$this->isSafeWorldName($worldName) || !isset(self::SUPPORTED_GAMERULES[$normalizedRule])) {
            return null;
        }

        $parsedValue = $this->parseGameruleValue($normalizedRule, $value);
        if ($parsedValue === null) {
            return null;
        }

        $key = strtolower($worldName);
        $definition = $this->definitions[$key] ?? new WorldDefinition(
            $worldName,
            "normal",
            null,
            "",
            false,
            false,
            []
        );
        $definition->gamerules[$normalizedRule] = $parsedValue;
        $this->rememberWorldDefinition($definition, true);

        $loaded = false;
        $world = $this->loader->getServer()->getWorldManager()->getWorldByName($worldName);
        if ($world instanceof World) {
            $this->applyGameruleToWorld($world, $normalizedRule, $parsedValue);
            $world->save(true);
            $loaded = true;
        }

        WorldAuditLogger::log("Gamerule", [
            "actor" => $actor ?? "system",
            "world" => $worldName,
            "rule" => $normalizedRule,
            "value" => is_bool($parsedValue) ? ($parsedValue ? "true" : "false") : (string) $parsedValue,
            "result" => "ok",
            "loaded" => $loaded ? "yes" : "no",
        ]);

        return [
            "rule" => $normalizedRule,
            "value" => $parsedValue,
            "loaded" => $loaded,
        ];
    }

    public function getDimensionId(World|string|null $world): int {
        $worldName = $world instanceof World ? $world->getFolderName() : $world;
        if ($worldName === null || trim($worldName) === "") {
            return DimensionIds::OVERWORLD;
        }

        $definition = $this->definitions[strtolower($worldName)] ?? null;
        if ($definition !== null) {
            return $this->getDimensionIdFromGenerator($definition->generator);
        }

        return match (strtolower($worldName)) {
            "nether" => DimensionIds::NETHER,
            "end", "the_end" => DimensionIds::THE_END,
            default => DimensionIds::OVERWORLD,
        };
    }

    private function getDimensionIdFromGenerator(string $generator): int {
        return match (WorldDefinition::normalizeGenerator($generator)) {
            "vanilla_nether", "nether" => DimensionIds::NETHER,
            "end", "the_end" => DimensionIds::THE_END,
            default => DimensionIds::OVERWORLD,
        };
    }

    private function rememberWorldDefinition(WorldDefinition $definition, bool $persist): void {
        $this->definitions[strtolower($definition->name)] = $definition;
        if (!$persist) {
            return;
        }

        $config = GeneralUtils::getConfiguration($this->loader, "config.yml");
        if ($config === null) {
            return;
        }

        $base = "worlds.definitions.{$definition->name}";
        $config->setNested("{$base}.generator", $definition->generator);
        $config->setNested("{$base}.seed", $definition->seed);
        $config->setNested("{$base}.preset", $definition->preset);
        $config->setNested("{$base}.auto-load", $definition->autoLoad);
        $config->setNested("{$base}.auto-generate", $definition->autoGenerate);
        $config->setNested("{$base}.gamerules", $definition->gamerules);
        $config->save();
    }

    private function applyGeneratedWorldDefaults(string $name, string $generator): void {
        $world = $this->loader->getServer()->getWorldManager()->getWorldByName($name);
        if (!$world instanceof World) {
            return;
        }

        if (WorldDefinition::normalizeGenerator($generator) === "end") {
            $world->setSpawnLocation(new Vector3(8.5, EndGenerator::SPAWN_PLATFORM_Y + 2, 8.5));
        }

        $this->applyConfiguredGamerules($name);
    }

    private function getSpawnSearchOrigin(World $world): Vector3 {
        if ($this->getDimensionId($world) === DimensionIds::THE_END) {
            return new Vector3(8.5, EndGenerator::SPAWN_PLATFORM_Y + 2, 8.5);
        }

        return $world->getSpawnLocation();
    }

    private function resolveFallbackSpawn(World $world, Vector3 $spawn): \pocketmine\world\Position {
        try {
            return $world->getSafeSpawn($spawn);
        } catch (WorldException) {
            return \pocketmine\world\Position::fromObject($spawn, $world);
        }
    }

    public function sendStartupSummary(array $stats): void {
        $this->loader->getLogger()->info(C::colorize(
            "&7[WorldMgr] &floaded:&a {$stats["loaded"]} &fgenerated:&b {$stats["generated"]} &fskipped:&e {$stats["skipped"]} &ffailed:&c {$stats["failed"]}"
        ));
    }

    private function deleteDirectory(string $path): bool {
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($iterator as $item) {
                if ($item->isDir()) {
                    if (!@rmdir($item->getPathname())) {
                        return false;
                    }
                } else {
                    if (!@unlink($item->getPathname())) {
                        return false;
                    }
                }
            }
            return @rmdir($path);
        } catch (\Throwable) {
            return false;
        }
    }

    private function isSafeWorldName(string $name): bool
    {
        $name = trim($name);
        if ($name === "" || strlen($name) > 64) {
            return false;
        }
        if (str_contains($name, "/") || str_contains($name, "\\") || str_contains($name, "..") || str_contains($name, ":")) {
            return false;
        }
        return preg_match('/^[A-Za-z0-9._-]+$/', $name) === 1;
    }

    private function applyConfiguredGamerules(string $worldName): void {
        $world = $this->loader->getServer()->getWorldManager()->getWorldByName($worldName);
        if (!$world instanceof World) {
            return;
        }

        foreach ($this->getConfiguredGamerules($worldName) as $rule => $value) {
            $this->applyGameruleToWorld($world, $rule, $value);
        }
    }

    /**
     * @param bool|int|string|float $value
     */
    private function applyGameruleToWorld(World $world, string $rule, bool|int|string|float $value): void {
        switch ($rule) {
            case self::GAMERULE_RANDOM_TICK_SPEED:
                $this->setRandomTickSpeed($world, (int) $value);
                return;

            case self::GAMERULE_DAYLIGHT_CYCLE:
                if ((bool) $value) {
                    $world->startTime();
                } else {
                    $world->stopTime();
                }
                return;
        }
    }

    /**
     * @return bool|int|string|float
     */
    private function readLiveGameruleValue(World $world, string $rule): bool|int|string|float {
        return match ($rule) {
            self::GAMERULE_RANDOM_TICK_SPEED => $this->getRandomTickSpeed($world),
            self::GAMERULE_DAYLIGHT_CYCLE => !$world->stopTime,
            default => $this->getDefaultGameruleValue($rule),
        };
    }

    /**
     * @return bool|int
     */
    private function getDefaultGameruleValue(string $rule): bool|int {
        return match ($rule) {
            self::GAMERULE_RANDOM_TICK_SPEED => World::DEFAULT_TICKED_BLOCKS_PER_SUBCHUNK_PER_TICK,
            self::GAMERULE_DAYLIGHT_CYCLE => true,
            default => 0,
        };
    }

    private function parseGameruleValue(string $rule, string $value): bool|int|null {
        return match ($rule) {
            self::GAMERULE_RANDOM_TICK_SPEED => $this->parseRandomTickSpeed($value),
            self::GAMERULE_DAYLIGHT_CYCLE => $this->parseBooleanValue($value),
            default => null,
        };
    }

    private function parseRandomTickSpeed(string $value): ?int {
        $trimmed = trim($value);
        if ($trimmed === "" || !is_numeric($trimmed)) {
            return null;
        }
        $parsed = (int) $trimmed;
        if ($parsed < 0 || $parsed > 4096) {
            return null;
        }
        return $parsed;
    }

    private function parseBooleanValue(string $value): ?bool {
        return match (strtolower(trim($value))) {
            "true", "on", "yes", "1" => true,
            "false", "off", "no", "0" => false,
            default => null,
        };
    }

    private function getRandomTickSpeed(World $world): int {
        $property = $this->getRandomTickSpeedProperty();
        return (int) $property->getValue($world);
    }

    private function setRandomTickSpeed(World $world, int $speed): void {
        $property = $this->getRandomTickSpeedProperty();
        $property->setValue($world, $speed);
    }

    private function getRandomTickSpeedProperty(): \ReflectionProperty {
        if (self::$randomTickSpeedProperty === null) {
            self::$randomTickSpeedProperty = new \ReflectionProperty(World::class, "tickedBlocksPerSubchunkPerTick");
        }
        return self::$randomTickSpeedProperty;
    }
}
