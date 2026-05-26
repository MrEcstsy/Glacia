<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\floatingtext;

use ecstsy\Glacia\entity\FloatingTextEntity;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\player\GlaciaPlayer;
use ecstsy\Glacia\server\envoys\EnvoyManager;
use ecstsy\Glacia\server\factions\Faction;
use ecstsy\Glacia\server\factions\contribution\FactionContributionManager;
use ecstsy\Glacia\server\factions\guardian\FactionGuardianManager;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\entity\Location;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\TaskHandler;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\world\World;
use function abs;
use function array_filter;
use function array_map;
use function array_slice;
use function array_values;
use function count;
use function implode;
use function is_array;
use function is_numeric;
use function intdiv;
use function max;
use function min;
use function number_format;
use function round;
use function str_replace;
use function strtolower;
use function strtr;
use function trim;
use function uasort;

final class FloatingTextManager
{
    private const FILE = "floating_texts.yml";

    /** @var array<string, array<string, mixed>> */
    private static array $templates = [];
    /** @var array<string, array<string, mixed>> */
    private static array $instances = [];
    /** @var array<string, FloatingTextEntity> */
    private static array $entities = [];

    private static ?TaskHandler $refreshTask = null;
    private static int $refreshIntervalTicks = 20 * 15;
    private static int $refreshIntervalSeconds = 15;
    private static int $nextGeneralRefreshAt = 0;
    /** @var array<string, int> */
    private static array $instanceRefreshAt = [];

    public static function loadFromConfig() : void
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), self::FILE);
        self::$templates = [];
        self::$instances = [];

        if ($config instanceof Config) {
            foreach ((array) $config->get("templates", []) as $id => $template) {
                if (!is_string($id) || !is_array($template)) {
                    continue;
                }
                $normalized = self::normalizeId($id);
                if ($normalized === "") {
                    continue;
                }
                self::$templates[$normalized] = $template;
            }

            foreach ((array) $config->get("instances", []) as $id => $instance) {
                if (!is_string($id) || !is_array($instance)) {
                    continue;
                }
                $normalized = self::normalizeId($id);
                if ($normalized === "") {
                    continue;
                }
                self::$instances[$normalized] = self::sanitizeInstance($instance);
            }

            self::$refreshIntervalSeconds = max(1, (int) $config->getNested("settings.refresh-interval-seconds", 15));
            self::$refreshIntervalTicks = self::$refreshIntervalSeconds * 20;
        }

        self::$nextGeneralRefreshAt = time() + self::$refreshIntervalSeconds;
        self::rebuildInstanceRefreshSchedule();
        self::respawnAll();
        self::scheduleRefreshTask();
    }

    public static function respawnAll() : void
    {
        self::removeAll();

        foreach (self::$instances as $id => $_instance) {
            self::spawnOrUpdateInstance($id);
        }

        self::refresh();
    }

    public static function refresh() : void
    {
        foreach (self::$instances as $id => $_instance) {
            self::spawnOrUpdateInstance($id);
        }
    }

    public static function removeAll() : void
    {
        self::$refreshTask?->cancel();
        self::$refreshTask = null;
        self::$instanceRefreshAt = [];

        foreach (Server::getInstance()->getWorldManager()->getWorlds() as $world) {
            foreach ($world->getEntities() as $entity) {
                if ($entity instanceof FloatingTextEntity) {
                    $entity->flagForDespawn();
                }
            }
        }

        self::$entities = [];
    }

    public static function hasTemplate(string $templateId) : bool
    {
        return isset(self::$templates[self::normalizeId($templateId)]);
    }

    /**
     * Builds a multi-line hologram string from a static floating_texts.yml template.
     */
    public static function formatStaticTemplate(string $templateId, ?string $replaceLastLine = null) : string
    {
        $templateId = self::normalizeId($templateId);
        $template = self::$templates[$templateId] ?? null;
        if ($template === null) {
            return "";
        }

        if (strtolower((string) ($template["type"] ?? "static")) !== "static") {
            return "";
        }

        $lines = array_values(array_map("strval", (array) ($template["lines"] ?? [])));
        if ($lines === []) {
            return "";
        }

        if ($replaceLastLine !== null) {
            $lines[array_key_last($lines)] = $replaceLastLine;
        }

        return implode("\n", $lines);
    }

    /**
     * @return string[]
     */
    public static function getStaticTemplateLines(string $templateId) : array
    {
        $templateId = self::normalizeId($templateId);
        $template = self::$templates[$templateId] ?? null;
        if ($template === null) {
            return [];
        }

        return array_values(array_map("strval", (array) ($template["lines"] ?? [])));
    }

    /**
     * @return string[]
     */
    public static function getTemplateIds() : array
    {
        return array_keys(self::$templates);
    }

    /**
     * @return string[]
     */
    public static function getInstanceIds() : array
    {
        return array_keys(self::$instances);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function getInstance(string $id) : ?array
    {
        return self::$instances[self::normalizeId($id)] ?? null;
    }

    public static function getInstanceIdByEntity(FloatingTextEntity $needle) : ?string
    {
        foreach (self::$entities as $id => $entity) {
            if ($entity === $needle) {
                return $id;
            }
        }

        return null;
    }

    public static function upsertInstance(string $id, string $templateId, string $world, float $x, float $y, float $z, bool $enabled = true) : bool
    {
        $id = self::normalizeId($id);
        $templateId = self::normalizeId($templateId);
        if ($id === "" || !isset(self::$templates[$templateId])) {
            return false;
        }

        self::$instances[$id] = [
            "template" => $templateId,
            "enabled" => $enabled,
            "world" => $world,
            "x" => round($x, 2),
            "y" => round($y, 2),
            "z" => round($z, 2),
        ];

        self::persistInstances();
        self::scheduleInstanceRefresh($id);
        self::spawnOrUpdateInstance($id);
        return true;
    }

    public static function moveInstance(string $id, string $world, float $x, float $y, float $z) : bool
    {
        $id = self::normalizeId($id);
        if (!isset(self::$instances[$id])) {
            return false;
        }

        self::$instances[$id]["world"] = $world;
        self::$instances[$id]["x"] = round($x, 2);
        self::$instances[$id]["y"] = round($y, 2);
        self::$instances[$id]["z"] = round($z, 2);

        self::persistInstances();
        self::scheduleInstanceRefresh($id);
        self::spawnOrUpdateInstance($id);
        return true;
    }

    public static function removeInstance(string $id) : bool
    {
        $id = self::normalizeId($id);
        if (!isset(self::$instances[$id])) {
            return false;
        }

        unset(self::$instances[$id]);
        if (isset(self::$entities[$id])) {
            self::$entities[$id]->flagForDespawn();
            unset(self::$entities[$id]);
        }
        unset(self::$instanceRefreshAt[$id]);

        self::persistInstances();
        return true;
    }

    private static function scheduleRefreshTask() : void
    {
        self::$refreshTask?->cancel();
        self::$refreshTask = Loader::getInstance()->getScheduler()->scheduleRepeatingTask(
            new ClosureTask(static function() : void {
                $now = time();
                if ($now >= self::$nextGeneralRefreshAt) {
                    self::refresh();
                    self::$nextGeneralRefreshAt = $now + self::$refreshIntervalSeconds;
                    return;
                }

                self::refreshFastInstances($now);
            }),
            20
        );
    }

    private static function rebuildInstanceRefreshSchedule() : void
    {
        self::$instanceRefreshAt = [];
        foreach (array_keys(self::$instances) as $id) {
            self::scheduleInstanceRefresh($id);
        }
    }

    private static function scheduleInstanceRefresh(string $id) : void
    {
        $instance = self::$instances[$id] ?? null;
        if ($instance === null) {
            unset(self::$instanceRefreshAt[$id]);
            return;
        }

        $interval = self::getInstanceRefreshIntervalSeconds($instance);
        if ($interval >= self::$refreshIntervalSeconds) {
            unset(self::$instanceRefreshAt[$id]);
            return;
        }

        self::$instanceRefreshAt[$id] = time() + $interval;
    }

    private static function refreshFastInstances(int $now) : void
    {
        foreach (self::$instanceRefreshAt as $id => $dueAt) {
            if ($now < $dueAt) {
                continue;
            }

            self::spawnOrUpdateInstance($id);
            $instance = self::$instances[$id] ?? null;
            if ($instance === null) {
                unset(self::$instanceRefreshAt[$id]);
                continue;
            }

            $interval = self::getInstanceRefreshIntervalSeconds($instance);
            self::$instanceRefreshAt[$id] = $now + $interval;
        }
    }

    private static function spawnOrUpdateInstance(string $id) : void
    {
        $instance = self::$instances[$id] ?? null;
        if ($instance === null || !((bool) ($instance["enabled"] ?? true))) {
            if (isset(self::$entities[$id])) {
                self::$entities[$id]->flagForDespawn();
                unset(self::$entities[$id]);
            }
            return;
        }

        $world = Server::getInstance()->getWorldManager()->getWorldByName((string) ($instance["world"] ?? ""));
        if (!$world instanceof World) {
            if (isset(self::$entities[$id])) {
                self::$entities[$id]->flagForDespawn();
                unset(self::$entities[$id]);
            }
            return;
        }

        $text = implode("\n", self::renderLines($id, $instance));
        $entity = self::$entities[$id] ?? null;
        $entityWorld = $entity?->getWorld();
        $textChanged = $entity instanceof FloatingTextEntity && $entity->getText() !== $text;

        if (
            !$entity instanceof FloatingTextEntity ||
            $entity->isClosed() ||
            $textChanged ||
            $entityWorld !== $world ||
            abs($entity->getLocation()->x - (float) $instance["x"]) > 0.01 ||
            abs($entity->getLocation()->y - (float) $instance["y"]) > 0.01 ||
            abs($entity->getLocation()->z - (float) $instance["z"]) > 0.01
        ) {
            if ($entity instanceof FloatingTextEntity && !$entity->isClosed()) {
                $entity->flagForDespawn();
            }

            $entity = new FloatingTextEntity(new Location(
                (float) $instance["x"],
                (float) $instance["y"],
                (float) $instance["z"],
                $world,
                0.0,
                0.0
            ));
            $entity->setText($text);
            $entity->spawnToAll();
            self::$entities[$id] = $entity;
            return;
        }

        $entity->setText($text);
    }

    /**
     * @param array<string, mixed> $instance
     * @return string[]
     */
    private static function renderLines(string $id, array $instance) : array
    {
        $templateId = self::normalizeId((string) ($instance["template"] ?? ""));
        $template = self::$templates[$templateId] ?? null;
        if ($template === null) {
            return ["&r&cMissing template: &f" . $templateId];
        }

        $type = strtolower((string) ($template["type"] ?? "static"));
        $lines = match ($type) {
            "leaderboard" => self::renderLeaderboard($template),
            default => array_values(array_map("strval", (array) ($template["lines"] ?? []))),
        };

        return array_map(
            static fn(string $line) : string => TextFormat::colorize(self::replaceTokens($line, $id, $instance, $templateId)),
            $lines
        );
    }

    /**
     * @param array<string, mixed> $template
     * @return string[]
     */
    private static function renderLeaderboard(array $template) : array
    {
        $state = self::resolveLeaderboardState($template);
        $provider = strtolower((string) ($state["provider"] ?? "players"));
        $metric = strtolower((string) ($state["metric"] ?? $state["category"] ?? ""));
        $limit = max(1, min(15, (int) ($template["limit"] ?? 10)));
        $header = array_values(array_map("strval", (array) ($state["title"] ?? [])));
        $footer = array_values(array_map("strval", (array) ($state["footer"] ?? [])));
        $entryFormat = (string) ($state["entry-format"] ?? "&r&e#{rank} &f{name} &7- &b{value}");
        $emptyLine = (string) ($state["empty-line"] ?? "&r&7No data yet.");
        $stateTokens = self::getLeaderboardStateTokens($state, $metric);

        $entries = match ($provider) {
            "factions", "faction" => self::getFactionEntries($metric, $limit),
            default => self::getPlayerEntries($metric, $limit, $template),
        };

        $lines = array_map(static fn(string $line) : string => strtr($line, $stateTokens), $header);
        if ($entries === []) {
            $lines[] = strtr($emptyLine, $stateTokens);
        } else {
            foreach ($entries as $entry) {
                $lines[] = strtr($entryFormat, $stateTokens + [
                    "{rank}" => (string) ($entry["rank"] ?? 0),
                    "{name}" => (string) ($entry["name"] ?? "Unknown"),
                    "{value}" => (string) ($entry["value"] ?? "0"),
                    "{kills}" => (string) ($entry["kills"] ?? 0),
                    "{deaths}" => (string) ($entry["deaths"] ?? 0),
                    "{ratio}" => (string) ($entry["ratio"] ?? "0.00"),
                    "{members}" => (string) ($entry["members"] ?? 0),
                    "{claims}" => (string) ($entry["claims"] ?? 0),
                    "{power}" => (string) ($entry["power"] ?? 0),
                    "{contribution}" => (string) ($entry["contribution"] ?? 0),
                ]);
            }
        }

        foreach ($footer as $line) {
            $lines[] = strtr($line, $stateTokens);
        }

        return $lines;
    }

    /**
     * @param array<string, mixed> $template
     * @return array<string, mixed>
     */
    private static function resolveLeaderboardState(array $template) : array
    {
        $cycle = $template["cycle"] ?? null;
        if (!is_array($cycle)) {
            return $template;
        }

        $rawStates = $cycle["states"] ?? $cycle["metrics"] ?? null;
        if (!is_array($rawStates) || $rawStates === []) {
            return $template;
        }

        $states = array_values(array_filter($rawStates, static fn(mixed $state) : bool => is_array($state)));
        if ($states === []) {
            return $template;
        }

        $interval = max(5, (int) ($cycle["interval-seconds"] ?? 30));
        $index = intdiv(time(), $interval) % count($states);
        $state = $states[$index];

        return $state + [
            "cycle_label" => (string) ($state["label"] ?? self::humanizeMetric((string) ($state["metric"] ?? $template["metric"] ?? ""))),
            "cycle_metric" => (string) ($state["metric"] ?? $template["metric"] ?? ""),
            "cycle_index" => $index,
        ] + $template;
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, string>
     */
    private static function getLeaderboardStateTokens(array $state, string $fallbackMetric) : array
    {
        $metric = (string) ($state["cycle_metric"] ?? $state["metric"] ?? $fallbackMetric);
        $label = (string) ($state["cycle_label"] ?? self::humanizeMetric($metric));
        return [
            "{cycle_label}" => $label,
            "{cycle_metric}" => $metric,
        ];
    }

    /**
     * @param array<string, mixed> $template
     * @return list<array<string, int|string|float>>
     */
    private static function getPlayerEntries(string $metric, int $limit, array $template) : array
    {
        $sessions = array_values(Loader::getPlayerManager()->getSessions());
        $minKills = max(0, (int) ($template["min-kills"] ?? 0));

        $sessions = array_values(array_filter($sessions, static function(GlaciaPlayer $session) use ($metric, $minKills) : bool {
            return match ($metric) {
                "playtime", "time" => !$session->isHiddenFromPlaytimeTop(),
                "kdr", "kd", "ratio" => $session->getKills() >= $minKills,
                default => true,
            };
        }));

        uasort($sessions, static function(GlaciaPlayer $a, GlaciaPlayer $b) use ($metric) : int {
            $aValue = self::getPlayerMetricValue($a, $metric);
            $bValue = self::getPlayerMetricValue($b, $metric);
            $cmp = $bValue <=> $aValue;
            return $cmp !== 0 ? $cmp : strcasecmp($a->getUsername(), $b->getUsername());
        });

        $entries = [];
        foreach (array_slice($sessions, 0, $limit) as $index => $session) {
            $entries[] = [
                "rank" => $index + 1,
                "name" => $session->getUsername(),
                "value" => self::formatPlayerMetric($session, $metric),
                "kills" => $session->getKills(),
                "deaths" => $session->getDeaths(),
                "ratio" => number_format($session->getKDRRatio(), 2),
            ];
        }

        return $entries;
    }

    /**
     * @return list<array<string, int|string|float>>
     */
    private static function getFactionEntries(string $metric, int $limit) : array
    {
        $factions = Loader::getFactionManager()->getAllFactions();
        if (FactionGuardianManager::shouldRequireForFtop()) {
            $factions = array_values(array_filter(
                $factions,
                static fn(Faction $faction) : bool => FactionGuardianManager::hasPlacedGuardian($faction->getFactionId())
            ));
        }

        uasort($factions, static function(Faction $a, Faction $b) use ($metric) : int {
            $aValue = self::getFactionMetricValue($a, $metric);
            $bValue = self::getFactionMetricValue($b, $metric);
            $cmp = $bValue <=> $aValue;
            return $cmp !== 0 ? $cmp : strcasecmp($a->getName(), $b->getName());
        });

        $entries = [];
        foreach (array_slice($factions, 0, $limit) as $index => $faction) {
            $entries[] = [
                "rank" => $index + 1,
                "name" => $faction->getName(),
                "value" => self::formatFactionMetric($faction, $metric),
                "members" => count($faction->getMembers()),
                "claims" => $faction->getClaimCount(),
                "power" => $faction->getFactionPower(),
                "contribution" => FactionContributionManager::getFactionPoints($faction->getFactionId()),
            ];
        }

        return $entries;
    }

    private static function getPlayerMetricValue(GlaciaPlayer $session, string $metric) : float|int
    {
        return match ($metric) {
            "balance", "money" => $session->getBalance(),
            "kills" => $session->getKills(),
            "deaths" => $session->getDeaths(),
            "kdr", "kd", "ratio" => $session->getKDRRatio(),
            "playtime", "time" => $session->getDisplayPlaytimeSeconds(),
            "level", "levels" => $session->getLevel(),
            default => 0,
        };
    }

    private static function formatPlayerMetric(GlaciaPlayer $session, string $metric) : string
    {
        return match ($metric) {
            "balance", "money" => "$" . self::formatCompactMoney($session->getBalance()),
            "kills" => number_format($session->getKills()),
            "deaths" => number_format($session->getDeaths()),
            "kdr", "kd", "ratio" => $session->getKills() . "/" . $session->getDeaths() . " (" . number_format($session->getKDRRatio(), 2) . ")",
            "playtime", "time" => GlaciaPlayer::formatPlaytime($session->getDisplayPlaytimeSeconds()),
            "level", "levels" => number_format($session->getLevel()),
            default => "0",
        };
    }

    private static function getFactionMetricValue(Faction $faction, string $metric) : float|int
    {
        return match ($metric) {
            "value", "worth", "spawners", "spawnervalue" => $faction->getFactionValue(),
            "power" => $faction->getFactionPower(),
            "claims", "claimscount" => $faction->getClaimCount(),
            "members", "membercount" => count($faction->getMembers()),
            "contribution", "points", "contrib" => FactionContributionManager::getFactionPoints($faction->getFactionId()),
            default => 0,
        };
    }

    private static function formatFactionMetric(Faction $faction, string $metric) : string
    {
        return match ($metric) {
            "value", "worth", "spawners", "spawnervalue" => "$" . self::formatCompactMoney($faction->getFactionValue()),
            "power" => number_format($faction->getFactionPower()),
            "claims", "claimscount" => (string) $faction->getClaimCount(),
            "members", "membercount" => (string) count($faction->getMembers()),
            "contribution", "points", "contrib" => number_format(FactionContributionManager::getFactionPoints($faction->getFactionId())),
            default => "0",
        };
    }

    /**
     * @param array<string, mixed> $instance
     */
    private static function replaceTokens(string $line, string $id, array $instance, string $templateId) : string
    {
        $envoyManager = Loader::getEnvoyManager();
        $envoyActive = $envoyManager->getActive() !== null;

        return strtr($line, [
            "{id}" => $id,
            "{template}" => $templateId,
            "{online}" => (string) count(Server::getInstance()->getOnlinePlayers()),
            "{max_online}" => (string) Server::getInstance()->getMaxPlayers(),
            "{date}" => date("M d, Y"),
            "{time}" => date("g:i A"),
            "{world}" => (string) ($instance["world"] ?? "world"),
            "{x}" => number_format((float) ($instance["x"] ?? 0.0), 2, ".", ""),
            "{y}" => number_format((float) ($instance["y"] ?? 0.0), 2, ".", ""),
            "{z}" => number_format((float) ($instance["z"] ?? 0.0), 2, ".", ""),
            "{envoy_status}" => $envoyActive ? "Active" : "Next Spawn",
            "{envoy_timer}" => GeneralUtils::translateTime($envoyManager->getTimeLeft()),
        ]);
    }

    /**
     * @param array<string, mixed> $instance
     * @return array<string, mixed>
     */
    private static function sanitizeInstance(array $instance) : array
    {
        return [
            "template" => self::normalizeId((string) ($instance["template"] ?? "")),
            "enabled" => (bool) ($instance["enabled"] ?? true),
            "world" => (string) ($instance["world"] ?? "world"),
            "x" => (float) ($instance["x"] ?? 0.0),
            "y" => (float) ($instance["y"] ?? 0.0),
            "z" => (float) ($instance["z"] ?? 0.0),
            "refresh-interval-seconds" => max(1, (int) ($instance["refresh-interval-seconds"] ?? self::$refreshIntervalSeconds)),
        ];
    }

    /**
     * @param array<string, mixed> $instance
     */
    private static function getInstanceRefreshIntervalSeconds(array $instance) : int
    {
        return max(1, (int) ($instance["refresh-interval-seconds"] ?? self::$refreshIntervalSeconds));
    }

    private static function persistInstances() : void
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), self::FILE);
        if (!$config instanceof Config) {
            return;
        }

        $config->set("instances", self::$instances);
        $config->save();
        GeneralUtils::forgetConfiguration(Loader::getInstance(), self::FILE);
    }

    private static function normalizeId(string $value) : string
    {
        return strtolower(trim($value));
    }

    private static function humanizeMetric(string $metric) : string
    {
        $metric = trim(strtolower($metric));
        return match ($metric) {
            "kdr", "kd", "ratio" => "KDR",
            "balance", "money" => "Balance",
            "value", "worth", "spawnervalue", "spawners" => "Value",
            "contribution", "points", "contrib" => "Points",
            "claims", "claimscount" => "Claims",
            "members", "membercount" => "Members",
            "playtime", "time" => "Playtime",
            default => ucwords(str_replace("_", " ", $metric)),
        };
    }

    private static function formatCompactMoney(float $amount) : string
    {
        $abs = abs($amount);

        return match (true) {
            $abs >= 1_000_000_000_000 => number_format($amount / 1_000_000_000_000, 1) . "T",
            $abs >= 1_000_000_000 => number_format($amount / 1_000_000_000, 1) . "B",
            $abs >= 1_000_000 => number_format($amount / 1_000_000, 1) . "M",
            $abs >= 1_000 => number_format($amount / 1_000, 1) . "K",
            default => is_numeric((string) $amount) && round($amount) === $amount ? number_format($amount, 0) : number_format($amount, 2),
        };
    }
}
