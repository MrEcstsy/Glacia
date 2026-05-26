<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\duels;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\player\GlaciaPlayer;
use ecstsy\Glacia\server\economy\EconomyManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantEffectManager;
use ecstsy\Glacia\server\gkits\GKitRepository;
use ecstsy\Glacia\server\items\GlaciaItemFactory;
use ecstsy\Glacia\server\zones\ZoneManager;
use ecstsy\Glacia\utils\RewardResolver;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\StringToEffectParser;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\object\ItemEntity;
use pocketmine\inventory\ArmorInventory;
use pocketmine\item\Armor;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as C;
use pocketmine\world\sound\XpCollectSound;
use pocketmine\world\Position;
use Ramsey\Uuid\Uuid;

final class DuelManager
{
    private static ?self $instance = null;

    private Loader $plugin;
    private Config $config;
    private string $statsFile;
    /** @var array<string, mixed> */
    private array $settings = [];
    /** @var array<string, array<string, mixed>> */
    private array $pendingRequests = [];
    /** @var array<string, array<string, mixed>> */
    private array $pendingRespawnRestores = [];
    /** @var array<string, array<string, int|float|string>> */
    private array $stats = [];
    /** @var array<string, mixed>|null */
    private ?array $activeDuel = null;

    private function __construct(Loader $plugin)
    {
        $this->plugin = $plugin;
        $this->config = new Config($plugin->getDataFolder() . "duels.yml", Config::YAML, $this->getDefaultConfig());
        $this->statsFile = $plugin->getDataFolder() . "duel_stats.json";
        $this->load();
    }

    public static function init(Loader $plugin): void
    {
        self::$instance = new self($plugin);
        $plugin->getScheduler()->scheduleRepeatingTask(new ClosureTask(static function(): void {
            self::$instance?->tick();
        }), 20);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            throw new \LogicException("DuelManager has not been initialized.");
        }

        return self::$instance;
    }

    public static function isInitialized(): bool
    {
        return self::$instance !== null;
    }

    public function save(): void
    {
        if ($this->activeDuel !== null) {
            $this->endActiveDuel(null, "server_shutdown", false, true);
        }

        file_put_contents($this->statsFile, json_encode($this->stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function reload(): void
    {
        $this->config->reload();
        $this->load();
    }

    public function isArenaConfigured(): bool
    {
        return $this->getArenaWorldName() !== ""
            && $this->getArenaCenter() !== null
            && $this->getArenaRadius() > 0
            && $this->getArenaSpawn(1) !== null
            && $this->getArenaSpawn(2) !== null;
    }

    public function getArenaRadius(): float
    {
        return max(1.0, (float) ($this->settings["arena"]["radius"] ?? 0.0));
    }

    public function getArenaMinY(): int
    {
        if (isset($this->settings["arena"]["min-y"])) {
            return (int) floor((float) $this->settings["arena"]["min-y"]);
        }

        $center = $this->getArenaCenter();
        return $center !== null ? (int) floor($center->y) : 0;
    }

    public function getArenaMaxY(): int
    {
        if (isset($this->settings["arena"]["max-y"])) {
            return (int) ceil((float) $this->settings["arena"]["max-y"]);
        }

        $center = $this->getArenaCenter();
        return $center !== null ? ((int) floor($center->y) + 19) : 255;
    }

    public function getArenaWorldName(): string
    {
        return trim((string) ($this->settings["arena"]["world"] ?? ""));
    }

    public function getArenaCenter(): ?Position
    {
        $worldName = $this->getArenaWorldName();
        $world = $worldName !== "" ? Server::getInstance()->getWorldManager()->getWorldByName($worldName) : null;
        if ($world === null) {
            return null;
        }

        $center = (array) ($this->settings["arena"]["center"] ?? []);
        if (!isset($center["x"], $center["y"], $center["z"])) {
            return null;
        }

        return new Position((float) $center["x"], (float) $center["y"], (float) $center["z"], $world);
    }

    public function getArenaSpawn(int $index): ?Location
    {
        $worldName = $this->getArenaWorldName();
        $world = $worldName !== "" ? Server::getInstance()->getWorldManager()->getWorldByName($worldName) : null;
        if ($world === null) {
            return null;
        }

        $key = $index === 2 ? "spawn2" : "spawn1";
        $spawn = (array) ($this->settings["arena"][$key] ?? []);
        if (!isset($spawn["x"], $spawn["y"], $spawn["z"])) {
            return null;
        }

        return new Location(
            (float) $spawn["x"],
            (float) $spawn["y"],
            (float) $spawn["z"],
            $world,
            (float) ($spawn["yaw"] ?? 0.0),
            (float) ($spawn["pitch"] ?? 0.0)
        );
    }

    public function hasActiveDuel(): bool
    {
        return $this->activeDuel !== null;
    }

    public function hasPendingRequests(): bool
    {
        return $this->pendingRequests !== [];
    }

    public function isArenaOccupied(): bool
    {
        return $this->hasActiveDuel() || $this->hasPendingRequests();
    }

    public function getActiveDuelSummary(): ?array
    {
        if ($this->activeDuel === null) {
            return null;
        }

        return [
            "challenger" => (string) $this->activeDuel["challenger_name"],
            "target" => (string) $this->activeDuel["target_name"],
            "wager" => $this->formatWagerSummary((array) ($this->activeDuel["wager"] ?? [])),
            "loadout" => (string) (($this->activeDuel["loadout"]["label"] ?? "Unknown")),
            "remaining" => max(0, ((int) $this->activeDuel["ends_at"]) - time()),
            "countdown" => max(0, ((int) ($this->activeDuel["combat_starts_at"] ?? time())) - time()),
        ];
    }

    public function isInDuel(Player $player): bool
    {
        if ($this->activeDuel === null) {
            return false;
        }

        $uuid = $player->getUniqueId()->toString();
        return $uuid === $this->activeDuel["challenger_uuid"] || $uuid === $this->activeDuel["target_uuid"];
    }

    public function isPlayerBusy(Player $player): bool
    {
        $uuid = $player->getUniqueId()->toString();
        if ($this->isInDuel($player)) {
            return true;
        }

        foreach ($this->pendingRequests as $request) {
            if ($request["challenger_uuid"] === $uuid || $request["target_uuid"] === $uuid) {
                return true;
            }
        }

        return false;
    }

    public function getOpponent(Player $player): ?Player
    {
        if (!$this->isInDuel($player)) {
            return null;
        }

        $uuid = $player->getUniqueId()->toString();
        $opponentUuid = $uuid === $this->activeDuel["challenger_uuid"]
            ? $this->activeDuel["target_uuid"]
            : $this->activeDuel["challenger_uuid"];

        return $this->getOnlinePlayerByUuidString((string) $opponentUuid);
    }

    public function isCombatLive(): bool
    {
        return $this->activeDuel !== null && time() >= (int) ($this->activeDuel["combat_starts_at"] ?? 0);
    }

    /**
     * @return array<int, int>
     */
    public function getDurationOptions(): array
    {
        $raw = (array) ($this->settings["duration-options-seconds"] ?? []);
        $options = [];
        foreach ($raw as $value) {
            $seconds = max(30, (int) $value);
            $options[$seconds] = $seconds;
        }

        if ($options === []) {
            $options = [60 => 60, 120 => 120, 180 => 180, 240 => 240, 300 => 300];
        }

        ksort($options);
        return array_values($options);
    }

    public function getAllowedCommands(): array
    {
        $allowed = [];
        foreach ((array) ($this->settings["allowed-commands"] ?? []) as $command) {
            $root = strtolower(trim((string) $command));
            if ($root !== "") {
                $allowed[$root] = true;
            }
        }

        return array_keys($allowed);
    }

    public function getRequestProximityRadius(): float
    {
        return max(0.0, (float) ($this->settings["request-proximity-radius"] ?? 10.0));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getSelectableGKits(): array
    {
        $options = [];
        foreach ($this->getConfiguredAllowedGkits() as $entry) {
            if (is_string($entry)) {
                $kit = $this->resolveConfiguredGkit($entry);
                if ($kit === null) {
                    continue;
                }

                $options[] = [
                "id" => (string) $kit["id"],
                "display" => (string) ($kit["display"] ?? $kit["id"]),
                "level" => 1,
            ];
                continue;
            }

            if (!is_array($entry)) {
                continue;
            }

            $id = strtolower(trim((string) ($entry["id"] ?? "")));
            $kit = $id !== "" ? $this->resolveConfiguredGkit($id) : null;
            if ($kit === null) {
                continue;
            }

            $options[] = [
                "id" => (string) $kit["id"],
                "display" => (string) ($entry["display"] ?? (string) ($kit["display"] ?? $kit["id"])),
                "level" => max(1, (int) ($entry["level"] ?? 1)),
            ];
        }

        return $options;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPresetSets(): array
    {
        $presets = [];
        foreach ($this->getConfiguredPresetEntries() as $id => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $key = strtolower(trim((string) $id));
            if ($key === "") {
                continue;
            }

            $presets[] = [
                "id" => $key,
                "display" => (string) ($entry["display"] ?? ucfirst(str_replace("_", " ", $key))),
                "items" => array_values(array_filter((array) ($entry["items"] ?? []), static fn(mixed $v): bool => is_array($v))),
                "effects" => array_values(array_filter((array) ($entry["effects"] ?? []), static fn(mixed $v): bool => is_array($v))),
                "fill-empty" => is_array($entry["fill-empty"] ?? null) ? $entry["fill-empty"] : null,
                "custom-set" => isset($entry["custom-set"]) ? (string) $entry["custom-set"] : "",
                "enchanted" => (bool) ($entry["enchanted"] ?? true),
            ];
        }

        return $presets;
    }

    public function canUseCommand(Player $player, string $root): bool
    {
        if (!$this->isInDuel($player)) {
            return true;
        }

        return in_array(strtolower($root), $this->getAllowedCommands(), true);
    }

    public function isInsideArena(Position $position): bool
    {
        $center = $this->getArenaCenter();
        if ($center === null || $position->getWorld()->getFolderName() !== $center->getWorld()->getFolderName()) {
            return false;
        }

        $y = (int) floor($position->y);
        if ($y < $this->getArenaMinY() || $y > $this->getArenaMaxY()) {
            return false;
        }

        $dx = $position->x - $center->x;
        $dz = $position->z - $center->z;
        return ($dx * $dx) + ($dz * $dz) <= ($this->getArenaRadius() * $this->getArenaRadius());
    }

    public function shouldBlockArenaEntry(Player $player, Position $to): bool
    {
        if (!$this->isArenaConfigured()) {
            return false;
        }

        if (!$this->isInsideArena($to)) {
            return false;
        }

        if ($this->isInDuel($player)) {
            return false;
        }

        return !$player->hasPermission("glacia.admin");
    }

    public function shouldKeepDuelerInside(Player $player, Position $to): bool
    {
        return $this->isInDuel($player) && !$this->isInsideArena($to);
    }

    public function sendRequest(Player $challenger, Player $target, int $durationSeconds, array $wager, array $loadout): bool
    {
        if (!$this->isArenaConfigured()) {
            $challenger->sendMessage(C::colorize("&r&cThe duel arena is not configured yet."));
            return false;
        }

        if ($challenger->getUniqueId()->equals($target->getUniqueId())) {
            $challenger->sendMessage(C::colorize("&r&cYou cannot duel yourself."));
            return false;
        }

        if ($this->isArenaOccupied()) {
            $challenger->sendMessage(C::colorize("&r&cThe duel arena is already reserved for another duel right now."));
            return false;
        }

        if ($this->isPlayerBusy($challenger) || $this->isPlayerBusy($target)) {
            $challenger->sendMessage(C::colorize("&r&cOne of you is already tied up with another duel request or match."));
            return false;
        }

        if (!$this->arePlayersNearEachOther($challenger, $target)) {
            $challenger->sendMessage(C::colorize("&r&cYou both need to be within &f" . number_format($this->getRequestProximityRadius(), 1) . " blocks &cof each other to start a duel."));
            return false;
        }

        $durationOptions = $this->getDurationOptions();
        $durationSeconds = in_array($durationSeconds, $durationOptions, true)
            ? $durationSeconds
            : (int) ($this->settings["default-duration-seconds"] ?? 180);

        $challengerSession = Loader::getPlayerManager()->getSession($challenger);
        $targetSession = Loader::getPlayerManager()->getSession($target);
        if ($challengerSession === null || $targetSession === null) {
            $challenger->sendMessage(C::colorize("&r&cCould not load one of the duel profiles."));
            return false;
        }

        $normalizedWager = $this->normalizeWagerSelection($challenger, $target, $wager);
        if ($normalizedWager === null) {
            return false;
        }

        $normalizedLoadout = $this->normalizeLoadoutSelection($loadout);
        if ($normalizedLoadout === null) {
            $challenger->sendMessage(C::colorize("&r&cThat duel loadout selection is invalid."));
            return false;
        }

        $requestId = uniqid("duel_", true);
        $request = [
            "id" => $requestId,
            "challenger_uuid" => $challenger->getUniqueId()->toString(),
            "challenger_name" => $challenger->getName(),
            "target_uuid" => $target->getUniqueId()->toString(),
            "target_name" => $target->getName(),
            "created_at" => time(),
            "expires_at" => time() + max(10, (int) ($this->settings["request-expire-seconds"] ?? 30)),
            "duration" => $durationSeconds,
            "wager" => $normalizedWager,
            "loadout" => $normalizedLoadout,
        ];

        $this->pendingRequests[$requestId] = $request;

        $challenger->sendMessage(C::colorize(
            "&r&aDuel request sent to &f" . $target->getName() . "&a. " .
            "&7Mode: &f" . $normalizedLoadout["label"] . "&7, Duration: &f" . GeneralUtils::translateTime($durationSeconds) .
            ($normalizedWager["type"] !== "none" ? "&7, Wager: " . $this->formatWagerSummary($normalizedWager) : "")
        ));
        $target->sendMessage(C::colorize("&r&e" . $challenger->getName() . " &7has challenged you to a duel."));
        $target->sendMessage(C::colorize(
            "&r&7Mode: &f" . $normalizedLoadout["label"] .
            "&7 | Duration: &f" . GeneralUtils::translateTime($durationSeconds) .
            ($normalizedWager["type"] !== "none" ? "&7 | Wager: " . $this->formatWagerSummary($normalizedWager) : "")
        ));
        $target->sendMessage(C::colorize("&r&aType &f/duel accept " . $challenger->getName() . " &ato accept, or &f/duel deny " . $challenger->getName() . " &ato deny."));

        return true;
    }

    public function findIncomingRequest(Player $target, ?string $challengerPrefix = null): ?array
    {
        $targetUuid = $target->getUniqueId()->toString();
        foreach ($this->pendingRequests as $request) {
            if ($request["target_uuid"] !== $targetUuid) {
                continue;
            }

            if ($challengerPrefix !== null && !str_starts_with(strtolower($request["challenger_name"]), strtolower($challengerPrefix))) {
                continue;
            }

            return $request;
        }

        return null;
    }

    public function acceptRequest(Player $target, ?string $challengerPrefix = null): bool
    {
        $request = $this->findIncomingRequest($target, $challengerPrefix);
        if ($request === null) {
            $target->sendMessage(C::colorize("&r&cNo matching duel request was found."));
            return false;
        }

        if ($this->hasActiveDuel()) {
            $target->sendMessage(C::colorize("&r&cAnother duel already started in the arena."));
            return false;
        }

        unset($this->pendingRequests[$request["id"]]);
        $challenger = $this->getOnlinePlayerByUuidString((string) $request["challenger_uuid"]);
        if (!$challenger instanceof Player || !$challenger->isOnline()) {
            $target->sendMessage(C::colorize("&r&cThat player is no longer online."));
            return false;
        }

        if (!$this->arePlayersNearEachOther($challenger, $target)) {
            $target->sendMessage(C::colorize("&r&cYou both need to still be within &f" . number_format($this->getRequestProximityRadius(), 1) . " blocks &cof each other to begin the duel."));
            $challenger->sendMessage(C::colorize("&r&cThe duel was not started because you were no longer close enough together."));
            return false;
        }

        $target->sendMessage(C::colorize("&r&aAccepted duel request. Preparing arena..."));
        $challenger->sendMessage(C::colorize("&r&a" . $target->getName() . " accepted your duel request. Preparing arena..."));
        $this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($request): void {
            $this->startDuel($request);
        }), 1);
        return true;
    }

    public function denyRequest(Player $target, ?string $challengerPrefix = null): bool
    {
        $request = $this->findIncomingRequest($target, $challengerPrefix);
        if ($request === null) {
            $target->sendMessage(C::colorize("&r&cNo matching duel request was found."));
            return false;
        }

        unset($this->pendingRequests[$request["id"]]);
        $challenger = $this->getOnlinePlayerByUuidString((string) $request["challenger_uuid"]);
        if ($challenger instanceof Player) {
            $challenger->sendMessage(C::colorize("&r&c" . $target->getName() . " denied your duel request."));
        }

        $target->sendMessage(C::colorize("&r&eYou denied the duel request from &f" . $request["challenger_name"] . "&e."));
        return true;
    }

    public function requestMutualEnd(Player $player): bool
    {
        if (!$this->isInDuel($player)) {
            $player->sendMessage(C::colorize("&r&cYou are not in an active duel."));
            return false;
        }

        $uuid = $player->getUniqueId()->toString();
        $votes = (array) ($this->activeDuel["end_votes"] ?? []);
        $votes[$uuid] = true;
        $this->activeDuel["end_votes"] = $votes;

        $opponent = $this->getOpponent($player);
        if ($opponent !== null) {
            $opponent->sendMessage(C::colorize("&r&e" . $player->getName() . " wants to end the duel. Type &f/duel end &eto agree."));
        }

        $player->sendMessage(C::colorize("&r&aYour duel end request has been recorded."));

        if (count($votes) >= 2) {
            $this->endActiveDuel(null, "mutual_end", false, false);
        }

        return true;
    }

    public function forceEndByPlayerName(string $name): bool
    {
        if ($this->activeDuel === null) {
            return false;
        }

        $needle = strtolower(trim($name));
        $challenger = strtolower((string) $this->activeDuel["challenger_name"]);
        $target = strtolower((string) $this->activeDuel["target_name"]);
        if (!str_starts_with($challenger, $needle) && !str_starts_with($target, $needle)) {
            return false;
        }

        $this->endActiveDuel(null, "forced_end", false, false);
        return true;
    }

    public function handlePlayerDeath(Player $player): void
    {
        if (!$this->isInDuel($player)) {
            return;
        }

        $opponent = $this->getOpponent($player);
        $winnerUuid = $opponent?->getUniqueId()->toString();
        $this->endActiveDuel($winnerUuid, "death", false, false);
    }

    public function handlePlayerQuit(Player $player): void
    {
        $uuid = $player->getUniqueId()->toString();

        foreach ($this->pendingRequests as $id => $request) {
            if ($request["challenger_uuid"] === $uuid || $request["target_uuid"] === $uuid) {
                unset($this->pendingRequests[$id]);
            }
        }

        if (!$this->isInDuel($player)) {
            return;
        }

        $snapshot = $this->activeDuel["snapshots"][$uuid] ?? null;
        if (is_array($snapshot)) {
            $this->restoreSnapshot($player, $snapshot);
        }

        $opponent = $this->getOpponent($player);
        $winnerUuid = $opponent?->getUniqueId()->toString();
        $this->endActiveDuel($winnerUuid, "disconnect", true, false);
    }

    public function handleRespawn(Player $player): void
    {
        $uuid = $player->getUniqueId()->toString();
        $pending = $this->pendingRespawnRestores[$uuid] ?? null;
        if ($pending === null) {
            return;
        }

        unset($this->pendingRespawnRestores[$uuid]);
        $snapshot = $pending["snapshot"];
        $restoreItems = (bool) ($pending["restore_items"] ?? true);
        $restoreXp = (bool) ($pending["restore_xp"] ?? true);
        $returnToSpawn = (bool) ($pending["return_to_spawn"] ?? false);
        $this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($player, $snapshot, $restoreItems, $restoreXp, $returnToSpawn): void {
            if (!$player->isOnline()) {
                return;
            }

            $this->restoreSnapshot($player, $snapshot, $restoreItems, $restoreXp, $returnToSpawn);
        }), 1);
    }

    public function getStatsLine(string $playerName): ?string
    {
        $entry = $this->findStatsEntry($playerName);
        if ($entry === null) {
            return null;
        }

        return C::colorize(
            "&r&bDuel Stats &7for &f" . $entry["name"] .
            "&7: &a" . $entry["wins"] . "W &7/ &c" . $entry["losses"] . "L &7/ &e" . $entry["kills"] . " Kills &7/ &6" . $entry["deaths"] . " Deaths"
        );
    }

    public function getStatsFor(Player $player): array
    {
        return $this->stats[$player->getUniqueId()->toString()] ?? $this->defaultStats($player->getName());
    }

    public function setArenaCenter(Location $location): void
    {
        $this->settings["arena"]["world"] = $location->getWorld()->getFolderName();
        $this->settings["arena"]["center"] = $this->encodeLocation($location);
        $this->flushSettings();
    }

    public function setArenaRadius(float $radius): void
    {
        $this->settings["arena"]["radius"] = max(1.0, round($radius, 2));
        $this->flushSettings();
    }

    public function setArenaHeightBand(int $minY, int $maxY): void
    {
        $this->settings["arena"]["min-y"] = min($minY, $maxY);
        $this->settings["arena"]["max-y"] = max($minY, $maxY);
        $this->flushSettings();
    }

    public function setArenaSpawn(int $index, Location $location): void
    {
        $this->settings["arena"]["world"] = $location->getWorld()->getFolderName();
        $this->settings["arena"][$index === 2 ? "spawn2" : "spawn1"] = $this->encodeLocation($location);
        $this->flushSettings();
    }

    public function formatArenaSummary(): string
    {
        $center = $this->getArenaCenter();
        if ($center === null) {
            return C::colorize("&r&cDuel arena is not configured.");
        }

        return C::colorize(
            "&r&bArena &7World: &f" . $center->getWorld()->getFolderName() .
            "&7, Center: &f" . number_format($center->x, 1) . ", " . number_format($center->y, 1) . ", " . number_format($center->z, 1) .
            "&7, Radius: &f" . number_format($this->getArenaRadius(), 1) .
            "&7, Y: &f" . $this->getArenaMinY() . "-" . $this->getArenaMaxY()
        );
    }

    private function load(): void
    {
        $this->settings = $this->config->getAll();
        if (is_file($this->statsFile)) {
            $decoded = json_decode((string) file_get_contents($this->statsFile), true);
            $this->stats = is_array($decoded) ? $decoded : [];
        } else {
            $this->stats = [];
        }
    }

    private function tick(): void
    {
        $now = time();

        foreach ($this->pendingRequests as $id => $request) {
            if (($request["expires_at"] ?? 0) > $now) {
                continue;
            }

            unset($this->pendingRequests[$id]);
            $challenger = $this->getOnlinePlayerByUuidString((string) $request["challenger_uuid"]);
            $target = $this->getOnlinePlayerByUuidString((string) $request["target_uuid"]);
            if ($challenger instanceof Player) {
                $challenger->sendMessage(C::colorize("&r&cYour duel request to &f" . $request["target_name"] . "&c expired."));
            }
            if ($target instanceof Player) {
                $target->sendMessage(C::colorize("&r&eThe duel request from &f" . $request["challenger_name"] . "&e expired."));
            }
        }

        if ($this->activeDuel !== null && (int) $this->activeDuel["ends_at"] <= $now) {
            $this->endActiveDuel(null, "timeout", false, false);
        }
    }

    private function startDuel(array $request): bool
    {
        if ($this->activeDuel !== null) {
            return false;
        }

        $challenger = $this->getOnlinePlayerByUuidString((string) $request["challenger_uuid"]);
        $target = $this->getOnlinePlayerByUuidString((string) $request["target_uuid"]);
        if (!$challenger instanceof Player || !$target instanceof Player || !$challenger->isOnline() || !$target->isOnline()) {
            if ($challenger instanceof Player) {
                $challenger->sendMessage(C::colorize("&r&cThe duel could not start because one player went offline."));
            }
            if ($target instanceof Player) {
                $target->sendMessage(C::colorize("&r&cThe duel could not start because one player went offline."));
            }
            return false;
        }

        if (!$this->isArenaConfigured()) {
            $challenger->sendMessage(C::colorize("&r&cThe duel arena is no longer configured."));
            $target->sendMessage(C::colorize("&r&cThe duel arena is no longer configured."));
            return false;
        }

        if (!$this->arePlayersNearEachOther($challenger, $target)) {
            $challenger->sendMessage(C::colorize("&r&cThe duel was cancelled because you were no longer close enough together."));
            $target->sendMessage(C::colorize("&r&cThe duel was cancelled because you were no longer close enough together."));
            return false;
        }

        $challengerSession = Loader::getPlayerManager()->getSession($challenger);
        $targetSession = Loader::getPlayerManager()->getSession($target);
        if ($challengerSession === null || $targetSession === null) {
            $challenger->sendMessage(C::colorize("&r&cCould not load the duel profiles."));
            $target->sendMessage(C::colorize("&r&cCould not load the duel profiles."));
            return false;
        }

        $challengerSnapshot = $this->captureSnapshot($challenger);
        $targetSnapshot = $this->captureSnapshot($target);

        $wager = (array) ($request["wager"] ?? ["type" => "none", "amount" => 0]);
        $wagerType = (string) ($wager["type"] ?? "none");
        $wagerAmount = (int) ($wager["amount"] ?? 0);
        $challengerCharged = false;
        $targetCharged = false;
        if ($wagerType === "money" && $wagerAmount > 0) {
            $challengerCharged = EconomyManager::take($challenger, (float) $wagerAmount);
            $targetCharged = $challengerCharged && EconomyManager::take($target, (float) $wagerAmount);
            if (!$challengerCharged || !$targetCharged) {
                if ($challengerCharged) {
                    $challengerSession->addBalance((float) $wagerAmount);
                }
                if ($targetCharged) {
                    $targetSession->addBalance((float) $wagerAmount);
                }
                $challenger->sendMessage(C::colorize("&r&cThe duel wager could not be locked in."));
                $target->sendMessage(C::colorize("&r&cThe duel wager could not be locked in."));
                return false;
            }
        }

        $loadout = (array) $request["loadout"];
        if (!$this->applyLoadout($challenger, $loadout) || !$this->applyLoadout($target, $loadout)) {
            $this->restoreSnapshot($challenger, $challengerSnapshot);
            $this->restoreSnapshot($target, $targetSnapshot);
            if ($wagerType === "money" && $wagerAmount > 0) {
                if ($challengerCharged) {
                    $challengerSession->addBalance((float) $wagerAmount);
                }
                if ($targetCharged) {
                    $targetSession->addBalance((float) $wagerAmount);
                }
            }
            $challenger->sendMessage(C::colorize("&r&cThe duel loadout could not be prepared."));
            $target->sendMessage(C::colorize("&r&cThe duel loadout could not be prepared."));
            return false;
        }

        $this->removeArenaDrops();
        $this->ejectOutsiders($challenger, $target);

        $spawn1 = $this->getArenaSpawn(1);
        $spawn2 = $this->getArenaSpawn(2);
        if ($spawn1 === null || $spawn2 === null) {
            $this->restoreSnapshot($challenger, $challengerSnapshot);
            $this->restoreSnapshot($target, $targetSnapshot);
            if ($wagerType === "money" && $wagerAmount > 0) {
                if ($challengerCharged) {
                    $challengerSession->addBalance((float) $wagerAmount);
                }
                if ($targetCharged) {
                    $targetSession->addBalance((float) $wagerAmount);
                }
            }
            $challenger->sendMessage(C::colorize("&r&cThe duel spawns are not configured."));
            $target->sendMessage(C::colorize("&r&cThe duel spawns are not configured."));
            return false;
        }

        $this->activeDuel = [
            "id" => (string) $request["id"],
            "challenger_uuid" => $challenger->getUniqueId()->toString(),
            "challenger_name" => $challenger->getName(),
            "target_uuid" => $target->getUniqueId()->toString(),
            "target_name" => $target->getName(),
            "started_at" => time(),
            "combat_starts_at" => time() + 3,
            "ends_at" => time() + max(30, (int) ($request["duration"] ?? 180)),
            "wager" => $wager,
            "loadout" => $loadout,
            "snapshots" => [
                $challenger->getUniqueId()->toString() => $challengerSnapshot,
                $target->getUniqueId()->toString() => $targetSnapshot,
            ],
            "end_votes" => [],
        ];

        ZoneManager::allowNextBoundaryCrossing($challenger, 5);
        ZoneManager::allowNextBoundaryCrossing($target, 5);
        $challenger->teleport($spawn1);
        $target->teleport($spawn2);
        $challenger->setAllowFlight(false);
        $challenger->setFlying(false);
        $challenger->resetFallDistance();
        $target->setAllowFlight(false);
        $target->setFlying(false);
        $target->resetFallDistance();

        $durationText = GeneralUtils::translateTime((int) $request["duration"]);
        $modeText = (string) ($loadout["label"] ?? "Unknown");
        $wagerText = $wagerType !== "none" ? " &8| &7Wager: " . $this->formatWagerSummary($wager) : "";
        $challenger->sendMessage(C::colorize("&r&aDuel accepted. &7Mode: &f{$modeText}&7, Duration: &f{$durationText}{$wagerText}"));
        $target->sendMessage(C::colorize("&r&aDuel accepted. &7Mode: &f{$modeText}&7, Duration: &f{$durationText}{$wagerText}"));
        $this->beginCountdown($challenger, $target);

        return true;
    }

    private function endActiveDuel(?string $winnerUuid, string $reason, bool $forfeit, bool $silent): void
    {
        if ($this->activeDuel === null) {
            return;
        }

        $session = $this->activeDuel;
        $this->activeDuel = null;

        $challengerUuid = $session["challenger_uuid"];
        $targetUuid = $session["target_uuid"];
        $challenger = $this->getOnlinePlayerByUuidString((string) $challengerUuid);
        $target = $this->getOnlinePlayerByUuidString((string) $targetUuid);
        $wager = (array) ($session["wager"] ?? ["type" => "none", "amount" => 0]);
        $wagerType = (string) ($wager["type"] ?? "none");
        $wagerAmount = (int) ($wager["amount"] ?? 0);

        $loserUuid = null;
        if ($winnerUuid !== null) {
            $loserUuid = $winnerUuid === $challengerUuid ? $targetUuid : $challengerUuid;
        }

        if ($winnerUuid !== null) {
            $winnerName = $winnerUuid === $challengerUuid ? $session["challenger_name"] : $session["target_name"];
            $loserName = $winnerUuid === $challengerUuid ? $session["target_name"] : $session["challenger_name"];
            $this->recordStats($winnerUuid, $winnerName, true, true, false, false);
            $this->recordStats($loserUuid, $loserName, false, false, true, $forfeit);
        } else {
            $this->recordStats($challengerUuid, $session["challenger_name"], false, false, false, false, true);
            $this->recordStats($targetUuid, $session["target_name"], false, false, false, false, true);
        }

        foreach ([$challengerUuid => $challenger, $targetUuid => $target] as $uuid => $player) {
            $snapshot = $session["snapshots"][$uuid] ?? null;
            if (!is_array($snapshot) || !$player instanceof Player) {
                continue;
            }

            $restoreItems = !($winnerUuid !== null && $wagerType === "inventory" && $uuid === $loserUuid);
            $restoreXp = !($winnerUuid !== null && $wagerType === "xp" && $uuid === $loserUuid);

            if (!$player->isAlive()) {
                $this->pendingRespawnRestores[$uuid] = [
                    "snapshot" => $snapshot,
                    "restore_items" => $restoreItems,
                    "restore_xp" => $restoreXp,
                    "return_to_spawn" => true,
                ];
                continue;
            }

            $this->restoreSnapshot($player, $snapshot, $restoreItems, $restoreXp, true);
        }

        $this->settleWager($session, $winnerUuid, $loserUuid, $wager);

        $this->removeArenaDrops();

        if ($silent) {
            return;
        }

        if ($winnerUuid !== null) {
            $winnerName = $winnerUuid === $challengerUuid ? $session["challenger_name"] : $session["target_name"];
            $loserName = $winnerUuid === $challengerUuid ? $session["target_name"] : $session["challenger_name"];
            $message = match ($reason) {
                "death" => "&r&a{$winnerName} &7won the duel against &f{$loserName}&7.",
                "disconnect" => "&r&a{$winnerName} &7won because &f{$loserName} &7left the duel.",
                default => "&r&a{$winnerName} &7won the duel against &f{$loserName}&7.",
            };
            foreach ([$challenger, $target] as $player) {
                if ($player instanceof Player) {
                    $player->sendMessage(C::colorize($message));
                }
            }
            return;
        }

        $message = match ($reason) {
            "timeout" => "&r&eThe duel ended in a draw because the timer expired.",
            "mutual_end" => "&r&eThe duel ended early by mutual agreement.",
            "forced_end" => "&r&eThe duel was force-ended by staff.",
            default => "&r&eThe duel ended without a winner.",
        };
        foreach ([$challenger, $target] as $player) {
            if ($player instanceof Player) {
                $player->sendMessage(C::colorize($message));
            }
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeLoadoutSelection(array $loadout): ?array
    {
        $mode = strtolower(trim((string) ($loadout["mode"] ?? "current")));
        return match ($mode) {
            "current" => [
                "mode" => "current",
                "label" => "Current Inventory",
            ],
            "gkit" => $this->normalizeGkitLoadout($loadout),
            "preset" => $this->normalizePresetLoadout($loadout),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeGkitLoadout(array $loadout): ?array
    {
        $selectedId = strtolower(trim((string) ($loadout["gkit"] ?? "")));
        foreach ($this->getSelectableGKits() as $option) {
            if ($option["id"] !== $selectedId) {
                continue;
            }

            return [
                "mode" => "gkit",
                "label" => "GKit: " . $option["display"],
                "gkit" => $option["id"],
                "gkit_level" => (int) $option["level"],
            ];
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizePresetLoadout(array $loadout): ?array
    {
        $selectedId = strtolower(trim((string) ($loadout["preset"] ?? "")));
        foreach ($this->getPresetSets() as $preset) {
            if ($preset["id"] !== $selectedId) {
                continue;
            }

            return [
                "mode" => "preset",
                "label" => "Preset: " . $preset["display"],
                "preset" => $preset["id"],
            ];
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function captureSnapshot(Player $player): array
    {
        $effects = [];
        foreach ($player->getEffects()->all() as $effect) {
            $effects[] = clone $effect;
        }

        return [
            "location" => $this->encodeLocation($player->getLocation()),
            "inventory" => $player->getInventory()->getContents(),
            "armor" => $player->getArmorInventory()->getContents(),
            "offhand" => clone $player->getOffHandInventory()->getItem(0),
            "cursor" => clone $player->getCursorInventory()->getItem(0),
            "held_slot" => $player->getInventory()->getHeldItemIndex(),
            "health" => $player->getHealth(),
            "food" => $player->getHungerManager()->getFood(),
            "saturation" => $player->getHungerManager()->getSaturation(),
            "xp_total" => $player->getXpManager()->getCurrentTotalXp(),
            "effects" => $effects,
            "allow_flight" => $player->getAllowFlight(),
            "flying" => $player->isFlying(),
        ];
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    private function restoreSnapshot(Player $player, array $snapshot, bool $restoreItems = true, bool $restoreXp = true, bool $returnToSpawn = false): void
    {
        if ($restoreItems) {
            $player->getInventory()->clearAll();
            $player->getArmorInventory()->clearAll();
            $player->getOffHandInventory()->clearAll();
            $player->getCursorInventory()->clearAll();
        }
        $player->getEffects()->clear();

        if ($restoreItems) {
            $player->getInventory()->setContents((array) ($snapshot["inventory"] ?? []));
            $player->getArmorInventory()->setContents((array) ($snapshot["armor"] ?? []));

            $offhand = $snapshot["offhand"] ?? null;
            if ($offhand instanceof Item) {
                $player->getOffHandInventory()->setItem(0, clone $offhand);
            }

            $cursor = $snapshot["cursor"] ?? null;
            if ($cursor instanceof Item) {
                $player->getCursorInventory()->setItem(0, clone $cursor);
            }
        }

        $player->getInventory()->setHeldItemIndex(max(0, min(8, (int) ($snapshot["held_slot"] ?? 0))));
        $player->setHealth(min($player->getMaxHealth(), max(1.0, (float) ($snapshot["health"] ?? $player->getMaxHealth()))));
        $player->getHungerManager()->setFood(max(0, min(20, (int) ($snapshot["food"] ?? 20))));
        $player->getHungerManager()->setSaturation(max(0.0, min(20.0, (float) ($snapshot["saturation"] ?? 20.0))));
        if ($restoreXp) {
            $player->getXpManager()->setCurrentTotalXp(max(0, (int) ($snapshot["xp_total"] ?? 0)));
        }
        $player->setAllowFlight((bool) ($snapshot["allow_flight"] ?? false));
        $player->setFlying((bool) ($snapshot["flying"] ?? false));

        foreach ((array) ($snapshot["effects"] ?? []) as $effect) {
            if ($effect instanceof EffectInstance) {
                $player->getEffects()->add(clone $effect);
            }
        }

        if ($returnToSpawn) {
            $spawnWorld = Server::getInstance()->getWorldManager()->getDefaultWorld();
            if ($spawnWorld !== null) {
                $player->teleport($spawnWorld->getSpawnLocation());
            }
        } else {
            $locationData = (array) ($snapshot["location"] ?? []);
            $location = $this->decodeLocation($locationData);
            if ($location !== null) {
                $player->teleport($location);
            }
        }

        EnchantEffectManager::refreshArmorEffects($player);
    }

    private function beginCountdown(Player $challenger, Player $target): void
    {
        $players = [$challenger, $target];
        foreach ($players as $player) {
            if ($player->isOnline()) {
                $player->sendTitle(C::colorize("&r&e3"), C::colorize("&r&7Duel starting..."), 0, 20, 0);
            }
        }

        foreach ([2 => "&e2", 1 => "&e1"] as $delay => $title) {
            $this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($players, $title): void {
                foreach ($players as $player) {
                    if ($player->isOnline() && $this->isInDuel($player)) {
                        $player->sendTitle(C::colorize("&r{$title}"), C::colorize("&r&7Get ready..."), 0, 20, 0);
                    }
                }
            }), 20 * $delay);
        }

        $this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($players): void {
            foreach ($players as $player) {
                if ($player->isOnline() && $this->isInDuel($player)) {
                    $player->sendTitle(C::colorize("&r&aFight!"), C::colorize("&r&7Good luck."), 0, 30, 10);
                    $player->sendMessage(C::colorize("&r&aThe duel has started."));
                }
            }
        }), 60);
    }

    private function arePlayersNearEachOther(Player $first, Player $second): bool
    {
        if ($first->getWorld()->getFolderName() !== $second->getWorld()->getFolderName()) {
            return false;
        }

        $radius = $this->getRequestProximityRadius();
        if ($radius <= 0) {
            return true;
        }

        return $first->getPosition()->distanceSquared($second->getPosition()) <= ($radius * $radius);
    }

    /**
     * @param array<string, mixed> $loadout
     */
    private function applyLoadout(Player $player, array $loadout): bool
    {
        $player->getEffects()->clear();
        $player->setHealth($player->getMaxHealth());
        $player->getHungerManager()->setFood(20);
        $player->getHungerManager()->setSaturation(20.0);

        $mode = (string) ($loadout["mode"] ?? "current");
        if ($mode === "current") {
            return true;
        }

        $items = [];
        if ($mode === "gkit") {
            $kit = GKitRepository::getGKitById((string) ($loadout["gkit"] ?? ""));
            if ($kit === null) {
                return false;
            }

            $items = GKitRepository::resolveItemsForLevel($kit, max(1, (int) ($loadout["gkit_level"] ?? 1)));
        } elseif ($mode === "preset") {
            $presetId = (string) ($loadout["preset"] ?? "");
            foreach ($this->getPresetSets() as $preset) {
                if ($preset["id"] !== $presetId) {
                    continue;
                }

                $items = $this->buildPresetItems($preset, $player);
                break;
            }
        }

        if ($items === []) {
            return false;
        }

        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        $player->getOffHandInventory()->clearAll();
        $player->getCursorInventory()->clearAll();
        $this->equipItems($player, $items);
        if ($mode === "preset") {
            foreach ($this->getPresetSets() as $preset) {
                if ($preset["id"] !== (string) ($loadout["preset"] ?? "")) {
                    continue;
                }

                $this->applyPresetFillEmpty($player, $preset);
                $this->applyPresetEffects($player, $preset);
                break;
            }
        }
        EnchantEffectManager::refreshArmorEffects($player);
        return true;
    }

    /**
     * @param array<string, mixed> $wager
     * @return array<string, mixed>|null
     */
    private function normalizeWagerSelection(Player $challenger, Player $target, array $wager): ?array
    {
        $type = strtolower(trim((string) ($wager["type"] ?? "none")));
        if ($type === "" || $type === "none") {
            return ["type" => "none", "amount" => 0];
        }

        if ($type === "inventory") {
            if ($this->countSnapshotItems($this->captureSnapshot($challenger)) <= 0 || $this->countSnapshotItems($this->captureSnapshot($target)) <= 0) {
                $challenger->sendMessage(C::colorize("&r&cBoth players need a real inventory to wager inventories."));
                return null;
            }

            return ["type" => "inventory", "amount" => 1];
        }

        $amount = max(0, (int) ($wager["amount"] ?? 0));
        if ($amount <= 0) {
            $challenger->sendMessage(C::colorize("&r&cThat wager amount is invalid."));
            return null;
        }

        if ($type === "money") {
            $maxWager = max(0, (int) ($this->settings["max-wager-money"] ?? $this->settings["max-wager"] ?? 0));
            if ($maxWager > 0) {
                $amount = min($amount, $maxWager);
            }

            if (!EconomyManager::has($challenger, (float) $amount) || !EconomyManager::has($target, (float) $amount)) {
                $challenger->sendMessage(C::colorize("&r&cBoth players need enough money to cover the wager."));
                return null;
            }

            return ["type" => "money", "amount" => $amount];
        }

        if ($type === "xp") {
            $maxWager = max(0, (int) ($this->settings["max-wager-xp"] ?? 0));
            if ($maxWager > 0) {
                $amount = min($amount, $maxWager);
            }

            if ($challenger->getXpManager()->getCurrentTotalXp() < $amount || $target->getXpManager()->getCurrentTotalXp() < $amount) {
                $challenger->sendMessage(C::colorize("&r&cBoth players need enough XP to cover the wager."));
                return null;
            }

            return ["type" => "xp", "amount" => $amount];
        }

        $challenger->sendMessage(C::colorize("&r&cThat wager type is invalid."));
        return null;
    }

    /**
     * @param array<string, mixed> $wager
     */
    public function formatWagerSummary(array $wager): string
    {
        $type = strtolower(trim((string) ($wager["type"] ?? "none")));
        $amount = max(0, (int) ($wager["amount"] ?? 0));

        return match ($type) {
            "money" => "&a$" . number_format($amount),
            "xp" => "&d" . number_format($amount) . " XP",
            "inventory" => "&6Full Inventory",
            default => "&7None",
        };
    }

    /**
     * @param array<string, mixed> $session
     * @param array<string, mixed> $wager
     */
    private function settleWager(array $session, ?string $winnerUuid, ?string $loserUuid, array $wager): void
    {
        $type = strtolower(trim((string) ($wager["type"] ?? "none")));
        $amount = max(0, (int) ($wager["amount"] ?? 0));
        if ($type === "none") {
            return;
        }

        $challenger = $this->getOnlinePlayerByUuidString((string) $session["challenger_uuid"]);
        $target = $this->getOnlinePlayerByUuidString((string) $session["target_uuid"]);

        if ($type === "money") {
            if ($winnerUuid !== null) {
                $winner = $this->getOnlinePlayerByUuidString($winnerUuid);
                $winnerSession = $winner instanceof Player ? Loader::getPlayerManager()->getSession($winner) : null;
                if ($winnerSession !== null) {
                    $winnerSession->addBalance((float) ($amount * 2));
                }
            } else {
                foreach ([$challenger, $target] as $player) {
                    if (!$player instanceof Player) {
                        continue;
                    }
                    $playerSession = Loader::getPlayerManager()->getSession($player);
                    if ($playerSession !== null) {
                        $playerSession->addBalance((float) $amount);
                    }
                }
            }
            return;
        }

        if ($winnerUuid === null || $loserUuid === null) {
            return;
        }

        if ($type === "xp") {
            $winner = $this->getOnlinePlayerByUuidString($winnerUuid);
            if ($winner instanceof Player && $winner->isOnline()) {
                $winner->getXpManager()->addXp($amount);
                $winner->getWorld()->addSound($winner->getPosition(), new XpCollectSound());
            }
            return;
        }

        if ($type === "inventory") {
            $winner = $this->getOnlinePlayerByUuidString($winnerUuid);
            if (!$winner instanceof Player || !$winner->isOnline()) {
                return;
            }

            $loserSnapshot = $session["snapshots"][$loserUuid] ?? null;
            if (!is_array($loserSnapshot)) {
                return;
            }

            $this->giveSnapshotItemsToWinner($winner, $loserSnapshot);
        }
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    private function countSnapshotItems(array $snapshot): int
    {
        return count((array) ($snapshot["inventory"] ?? []))
            + count((array) ($snapshot["armor"] ?? []))
            + (($snapshot["offhand"] ?? null) instanceof Item && !($snapshot["offhand"])->isNull() ? 1 : 0);
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    private function giveSnapshotItemsToWinner(Player $winner, array $snapshot): void
    {
        foreach ($this->extractSnapshotItems($snapshot) as $item) {
            $leftovers = $winner->getInventory()->addItem(clone $item);
            foreach ($leftovers as $leftover) {
                $winner->getWorld()->dropItem($winner->getPosition(), $leftover);
            }
        }
    }

    /**
     * @param array<string, mixed> $snapshot
     * @return list<Item>
     */
    private function extractSnapshotItems(array $snapshot): array
    {
        $items = [];
        foreach ((array) ($snapshot["inventory"] ?? []) as $item) {
            if ($item instanceof Item && !$item->isNull()) {
                $items[] = clone $item;
            }
        }
        foreach ((array) ($snapshot["armor"] ?? []) as $item) {
            if ($item instanceof Item && !$item->isNull()) {
                $items[] = clone $item;
            }
        }
        $offhand = $snapshot["offhand"] ?? null;
        if ($offhand instanceof Item && !$offhand->isNull()) {
            $items[] = clone $offhand;
        }

        return $items;
    }

    /**
     * @param list<Item> $items
     */
    private function equipItems(Player $player, array $items): void
    {
        foreach ($items as $item) {
            if ($item instanceof Armor) {
                $slot = $item->getArmorSlot();
                $armor = $player->getArmorInventory();
                if ($slot === ArmorInventory::SLOT_HEAD && $armor->getHelmet()->isNull()) {
                    $armor->setHelmet(clone $item);
                    continue;
                }
                if ($slot === ArmorInventory::SLOT_CHEST && $armor->getChestplate()->isNull()) {
                    $armor->setChestplate(clone $item);
                    continue;
                }
                if ($slot === ArmorInventory::SLOT_LEGS && $armor->getLeggings()->isNull()) {
                    $armor->setLeggings(clone $item);
                    continue;
                }
                if ($slot === ArmorInventory::SLOT_FEET && $armor->getBoots()->isNull()) {
                    $armor->setBoots(clone $item);
                    continue;
                }
            }

            $player->getInventory()->addItem(clone $item);
        }
    }

    private function removeArenaDrops(): void
    {
        $center = $this->getArenaCenter();
        if ($center === null) {
            return;
        }

        $radius = $this->getArenaRadius() + max(1.0, (float) ($this->settings["arena"]["cleanup-radius-padding"] ?? 4.0));
        $radiusSquared = $radius * $radius;
        foreach ($center->getWorld()->getEntities() as $entity) {
            if (!$entity instanceof ItemEntity) {
                continue;
            }

            $dx = $entity->getLocation()->x - $center->x;
            $dz = $entity->getLocation()->z - $center->z;
            if (($dx * $dx) + ($dz * $dz) <= $radiusSquared) {
                $entity->flagForDespawn();
            }
        }
    }

    private function ejectOutsiders(Player $challenger, Player $target): void
    {
        $center = $this->getArenaCenter();
        if ($center === null) {
            return;
        }

        $radius = $this->getArenaRadius();
        foreach (Server::getInstance()->getOnlinePlayers() as $online) {
            if ($online->getUniqueId()->equals($challenger->getUniqueId()) || $online->getUniqueId()->equals($target->getUniqueId())) {
                continue;
            }

            if ($online->hasPermission("glacia.admin")) {
                continue;
            }

            if (!$this->isInsideArena($online->getPosition())) {
                continue;
            }

            $destination = new Location(
                $center->x,
                $center->y,
                $center->z + $radius + 3.0,
                $center->getWorld(),
                $online->getLocation()->yaw,
                $online->getLocation()->pitch
            );
            $online->teleport($destination);
            $online->sendMessage(C::colorize("&r&eA duel is starting, so the arena has been cleared."));
        }
    }

    /**
     * @param array<string, float|int|string> $location
     */
    private function decodeLocation(array $location): ?Location
    {
        $worldName = trim((string) ($location["world"] ?? ""));
        $world = $worldName !== "" ? Server::getInstance()->getWorldManager()->getWorldByName($worldName) : null;
        if ($world === null) {
            $world = Server::getInstance()->getWorldManager()->getDefaultWorld();
            if ($world === null) {
                return null;
            }
        }

        return new Location(
            (float) ($location["x"] ?? $world->getSpawnLocation()->x),
            (float) ($location["y"] ?? $world->getSpawnLocation()->y),
            (float) ($location["z"] ?? $world->getSpawnLocation()->z),
            $world,
            (float) ($location["yaw"] ?? 0.0),
            (float) ($location["pitch"] ?? 0.0)
        );
    }

    /**
     * @return array<string, float|string>
     */
    private function encodeLocation(Location $location): array
    {
        return [
            "world" => $location->getWorld()->getFolderName(),
            "x" => round($location->x, 3),
            "y" => round($location->y, 3),
            "z" => round($location->z, 3),
            "yaw" => round($location->yaw, 3),
            "pitch" => round($location->pitch, 3),
        ];
    }

    private function flushSettings(): void
    {
        $this->config->setAll($this->settings);
        $this->config->save();
    }

    private function getOnlinePlayerByUuidString(string $uuid): ?Player
    {
        $uuid = trim($uuid);
        if ($uuid === "") {
            return null;
        }

        try {
            return Server::getInstance()->getPlayerByUUID(Uuid::fromString($uuid));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return list<mixed>
     */
    private function getConfiguredAllowedGkits(): array
    {
        $nested = array_values((array) ($this->settings["loadouts"]["allowed-gkits"] ?? []));
        $root = array_values((array) ($this->settings["allowed-gkits"] ?? []));
        if ($nested === []) {
            return $root;
        }
        if ($root === []) {
            return $nested;
        }

        $merged = [];
        foreach (array_merge($nested, $root) as $entry) {
            $key = is_array($entry)
                ? strtolower(trim((string) ($entry["id"] ?? json_encode($entry))))
                : strtolower(trim((string) $entry));
            if ($key === "" || isset($merged[$key])) {
                continue;
            }
            $merged[$key] = $entry;
        }

        return array_values($merged);
    }

    /**
     * @return array<string, mixed>
     */
    private function getConfiguredPresetEntries(): array
    {
        $nested = (array) ($this->settings["loadouts"]["preset-sets"] ?? []);
        $root = (array) ($this->settings["preset-sets"] ?? []);

        if ($root !== []) {
            if (count($nested) === 1 && isset($nested["example"])) {
                unset($nested["example"]);
            }

            return array_replace($nested, $root);
        }

        return $nested;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveConfiguredGkit(string $kitId): ?array
    {
        $kitId = strtolower(trim($kitId));
        if ($kitId === "") {
            return null;
        }

        $exact = GKitRepository::getGKitById($kitId);
        if ($exact !== null) {
            return $exact;
        }

        $normalizedNeedle = preg_replace('/[^a-z0-9]/', '', $kitId) ?? $kitId;
        $bestMatch = null;
        $bestDistance = PHP_INT_MAX;

        foreach (GKitRepository::getGKits() as $kit) {
            $candidateId = strtolower(trim((string) ($kit["id"] ?? "")));
            if ($candidateId === "") {
                continue;
            }

            $normalizedCandidate = preg_replace('/[^a-z0-9]/', '', $candidateId) ?? $candidateId;
            if ($normalizedCandidate === $normalizedNeedle || str_starts_with($normalizedCandidate, $normalizedNeedle)) {
                return $kit;
            }

            $distance = levenshtein($normalizedNeedle, $normalizedCandidate);
            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $bestMatch = $kit;
            }
        }

        return $bestDistance <= 2 ? $bestMatch : null;
    }

    /**
     * @param array<string, mixed> $preset
     * @return list<Item>
     */
    private function buildPresetItems(array $preset, Player $player): array
    {
        $items = [];
        $customSet = strtolower(trim((string) ($preset["custom-set"] ?? "")));
        if ($customSet !== "") {
            $canonicalSet = GlaciaItemFactory::resolveCustomArmorSetAlias($customSet);
            foreach (GlaciaItemFactory::customArmor($canonicalSet, "set", (bool) ($preset["enchanted"] ?? true)) as $armorPiece) {
                if ($armorPiece instanceof Item && !$armorPiece->isNull()) {
                    $items[] = $armorPiece;
                }
            }
        }

        foreach ((array) ($preset["items"] ?? []) as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            if ($customSet !== "" && $this->isCustomSetArmorPieceEntry($entry, $customSet)) {
                continue;
            }

            $item = $this->resolvePresetItemEntry($entry, $player);
            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function resolvePresetItemEntry(array $entry, Player $player): ?Item
    {
        $custom = strtolower(trim((string) ($entry["custom"] ?? "")));
        if ($custom !== "") {
            $resolved = $this->resolvePresetCustomItem($custom, $entry);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return RewardResolver::resolveItem($entry, $player);
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function resolvePresetCustomItem(string $customId, array $entry): ?Item
    {
        $parsed = $this->parsePresetCustomIdentifier($customId);
        if ($parsed === null) {
            return null;
        }

        [$set, $piece, $isWeapon] = $parsed;
        if ($isWeapon) {
            return GlaciaItemFactory::customWeapon($set, $piece);
        }

        return GlaciaItemFactory::customArmor($set, $piece, (bool) ($entry["enchanted"] ?? true));
    }

    /**
     * @return array{string,string,bool}|null
     */
    private function parsePresetCustomIdentifier(string $customId): ?array
    {
        $customId = strtolower(trim($customId));
        return match ($customId) {
            "dreadveil_helmet", "dreadveil_hood", "phantom_helmet" => ["dreadveil", "helmet", false],
            "dreadveil_chestplate", "dreadveil_shroud", "phantom_chestplate" => ["dreadveil", "chestplate", false],
            "dreadveil_leggings", "dreadveil_legwraps", "phantom_leggings" => ["dreadveil", "leggings", false],
            "dreadveil_boots", "dreadveil_treads", "phantom_boots" => ["dreadveil", "boots", false],
            "dreadveil_sword", "dreadveil_reaper", "phantom_sword" => ["dreadveil", "sword", true],
            "frostbound_helmet", "frostbound_visage", "yeti_helmet" => ["frostbound", "helmet", false],
            "frostbound_chestplate", "frostbound_carapace", "yeti_chestplate" => ["frostbound", "chestplate", false],
            "frostbound_leggings", "yeti_leggings" => ["frostbound", "leggings", false],
            "frostbound_boots", "frostbound_greaves", "yeti_boots" => ["frostbound", "boots", false],
            "frostbound_sword", "frostbound_maul", "yeti_sword" => ["frostbound", "sword", true],
            "stormcaller_helmet", "stormcaller_mask", "yijki_helmet" => ["stormcaller", "helmet", false],
            "stormcaller_chestplate", "stormcaller_mantle", "yijki_chestplate" => ["stormcaller", "chestplate", false],
            "stormcaller_leggings", "stormcaller_vestments", "yijki_leggings" => ["stormcaller", "leggings", false],
            "stormcaller_boots", "stormcaller_striders", "yijki_boots" => ["stormcaller", "boots", false],
            "stormcaller_sword", "stormcaller_worldsplitter", "yijki_sword" => ["stormcaller", "sword", true],
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function isCustomSetArmorPieceEntry(array $entry, string $customSet): bool
    {
        $custom = strtolower(trim((string) ($entry["custom"] ?? "")));
        if ($custom === "") {
            return false;
        }

        $parsed = $this->parsePresetCustomIdentifier($custom);
        return $parsed !== null
            && !$parsed[2]
            && GlaciaItemFactory::resolveCustomArmorSetAlias($parsed[0]) === GlaciaItemFactory::resolveCustomArmorSetAlias($customSet);
    }

    /**
     * @param array<string, mixed> $preset
     */
    private function applyPresetFillEmpty(Player $player, array $preset): void
    {
        $entry = $preset["fill-empty"] ?? null;
        if (!is_array($entry)) {
            return;
        }

        $fillItem = $this->resolvePresetItemEntry($entry, $player);
        if ($fillItem === null || $fillItem->isNull()) {
            return;
        }

        $inventory = $player->getInventory();
        $size = $inventory->getSize();
        for ($slot = 0; $slot < $size; $slot++) {
            if ($inventory->getItem($slot)->isNull()) {
                $inventory->setItem($slot, clone $fillItem);
            }
        }
    }

    /**
     * @param array<string, mixed> $preset
     */
    private function applyPresetEffects(Player $player, array $preset): void
    {
        foreach ((array) ($preset["effects"] ?? []) as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $effect = $this->resolvePresetEffect((string) ($entry["effect"] ?? ""));
            if ($effect === null) {
                continue;
            }

            $level = max(1, (int) ($entry["level"] ?? 1));
            $seconds = max(30, (int) ($entry["seconds"] ?? 24 * 60 * 60));
            $player->getEffects()->add(new EffectInstance($effect, $seconds * 20, $level - 1, false));
        }
    }

    private function resolvePresetEffect(string $effectId): ?\pocketmine\entity\effect\Effect
    {
        return match (strtolower(trim($effectId))) {
            "speed" => VanillaEffects::SPEED(),
            "fire_resistance" => VanillaEffects::FIRE_RESISTANCE(),
            "strength" => VanillaEffects::STRENGTH(),
            "night_vision" => VanillaEffects::NIGHT_VISION(),
            "jump_boost" => VanillaEffects::JUMP_BOOST(),
            "water_breathing" => VanillaEffects::WATER_BREATHING(),
            default => StringToEffectParser::getInstance()->parse($effectId),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function getDefaultConfig(): array
    {
        return [
            "enabled" => true,
            "request-expire-seconds" => 30,
            "default-duration-seconds" => 180,
            "duration-options-seconds" => [60, 120, 180, 240, 300],
            "max-wager" => 100000000,
            "max-wager-money" => 100000000,
            "max-wager-xp" => 1000000,
            "allowed-commands" => ["duel", "heal", "feed", "bless", "msg", "r", "reply", "tell", "w", "whisper"],
            "arena" => [
                "world" => "",
                "center" => ["x" => 0.5, "y" => 64.0, "z" => 0.5],
                "radius" => 32,
                "min-y" => 64,
                "max-y" => 83,
                "spawn1" => ["x" => -8.5, "y" => 64.0, "z" => 0.5, "yaw" => 90.0, "pitch" => 0.0],
                "spawn2" => ["x" => 8.5, "y" => 64.0, "z" => 0.5, "yaw" => -90.0, "pitch" => 0.0],
                "cleanup-radius-padding" => 4.0,
            ],
            "loadouts" => [
                "allowed-gkits" => [],
                "preset-sets" => [
                    "example" => [
                        "display" => "&dExample Duel Set",
                        "items" => [
                            ["material" => "diamond_sword", "count" => 1],
                            ["material" => "diamond_helmet", "count" => 1],
                            ["material" => "diamond_chestplate", "count" => 1],
                            ["material" => "diamond_leggings", "count" => 1],
                            ["material" => "diamond_boots", "count" => 1],
                            ["material" => "golden_apple", "count" => 16],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, int|float|string>
     */
    private function defaultStats(string $name): array
    {
        return [
            "name" => $name,
            "wins" => 0,
            "losses" => 0,
            "kills" => 0,
            "deaths" => 0,
            "forfeits" => 0,
            "draws" => 0,
        ];
    }

    private function recordStats(string $uuid, string $name, bool $won, bool $addKill, bool $addDeath, bool $forfeit, bool $draw = false): void
    {
        $entry = $this->stats[$uuid] ?? $this->defaultStats($name);
        $entry["name"] = $name;
        if ($won) {
            $entry["wins"]++;
        }
        if ($addKill) {
            $entry["kills"]++;
        }
        if ($addDeath) {
            $entry["losses"]++;
            $entry["deaths"]++;
        }
        if ($forfeit) {
            $entry["forfeits"]++;
        }
        if ($draw) {
            $entry["draws"]++;
        }
        $this->stats[$uuid] = $entry;
    }

    /**
     * @return array<string, int|float|string>|null
     */
    private function findStatsEntry(string $playerName): ?array
    {
        $needle = strtolower(trim($playerName));
        if ($needle === "") {
            return null;
        }

        $online = PlayerUtils::getPlayerByPrefix($needle);
        if ($online instanceof Player) {
            return $this->stats[$online->getUniqueId()->toString()] ?? $this->defaultStats($online->getName());
        }

        $session = Loader::getPlayerManager()->getSessionByPrefix($needle);
        if ($session instanceof GlaciaPlayer) {
            return $this->stats[$session->getUuid()->toString()] ?? $this->findStatsEntryByName($session->getUsername());
        }

        return $this->findStatsEntryByName($needle);
    }

    /**
     * @return array<string, int|float|string>|null
     */
    private function findStatsEntryByName(string $name): ?array
    {
        $needle = strtolower(trim($name));
        foreach ($this->stats as $entry) {
            if (str_starts_with(strtolower((string) ($entry["name"] ?? "")), $needle)) {
                return $entry;
            }
        }

        return null;
    }
}
