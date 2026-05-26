<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\capture;

use ecstsy\Glacia\entity\FloatingTextEntity;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\factions\Faction;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\entity\Location;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as C;
use pocketmine\world\Position;
use pocketmine\world\World;

final class CaptureManager {
    private const OUTPOST_DECAY_PER_TICK = 2;

    private ?Config $config = null;
    /** @var array<string, array<string, mixed>> */
    private array $outposts = [];
    /** @var array<string, array<string, mixed>> */
    private array $koths = [];

    private ?string $activeKothId = null;
    private ?string $activeKothFactionId = null;
    private float $activeKothProgress = 0.0;
    private int $lastKothAnnounce = 0;

    /** @var array<string, string|null> */
    private array $outpostCapturingFaction = [];
    /** @var array<string, int> */
    private array $outpostCaptureProgress = [];
    /** @var array<string, string|null> */
    private array $outpostActiveFaction = [];
    /** @var array<string, bool> */
    private array $outpostContested = [];
    /** @var array<string, int> */
    private array $outpostAnnounceCooldown = [];
    /** @var array<string, FloatingTextEntity> */
    private array $outpostFloatingTexts = [];

    public function load() : void
    {
        $this->config = GeneralUtils::getConfiguration(Loader::getInstance(), "capturepoints.yml");
        $this->clearOutpostFloatingTexts();
        $this->outposts = [];
        $this->koths = [];
        $this->activeKothId = null;
        $this->activeKothFactionId = null;
        $this->activeKothProgress = 0.0;
        $this->outpostCapturingFaction = [];
        $this->outpostCaptureProgress = [];
        $this->outpostActiveFaction = [];
        $this->outpostContested = [];
        $this->outpostAnnounceCooldown = [];

        foreach ((array) ($this->config?->get("outposts", []) ?? []) as $id => $data) {
            if (is_array($data)) {
                $this->outposts[strtolower((string) $id)] = $data;
            }
        }

        foreach ((array) ($this->config?->get("koths", []) ?? []) as $id => $data) {
            if (is_array($data)) {
                $this->koths[strtolower((string) $id)] = $data;
            }
        }

        $this->refreshOutpostFloatingTexts();
    }

    public function tick() : void
    {
        $this->tickOutposts();
        $this->tickKoth();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getOutposts() : array
    {
        return $this->outposts;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getKoths() : array
    {
        return $this->koths;
    }

    public function getActiveKothId() : ?string
    {
        return $this->activeKothId;
    }

    public function createOutpost(string $id, string $type) : bool
    {
        $id = strtolower(trim($id));
        if ($id === "" || isset($this->outposts[$id])) {
            return false;
        }

        $this->outposts[$id] = [
            "display" => ucwords(str_replace("_", " ", $id)),
            "type" => $this->normalizeOutpostType($type),
            "world" => "",
            "x" => 0,
            "y" => 80,
            "z" => 0,
            "radius" => 5,
            "capture-time" => 45,
            "owner-faction" => "",
            "benefit-multiplier" => 1.25,
            "hologram-offset-y" => 3.0,
        ];
        $this->writeOutposts();
        return true;
    }

    public function deleteOutpost(string $id) : bool
    {
        $id = strtolower(trim($id));
        if (!isset($this->outposts[$id])) {
            return false;
        }

        unset(
            $this->outposts[$id],
            $this->outpostCapturingFaction[$id],
            $this->outpostCaptureProgress[$id],
            $this->outpostActiveFaction[$id],
            $this->outpostContested[$id],
            $this->outpostAnnounceCooldown[$id]
        );
        $this->removeOutpostFloatingText($id);
        $this->writeOutposts();
        return true;
    }

    public function setOutpostPoint(string $id, Position $position) : bool
    {
        $id = strtolower(trim($id));
        if (!isset($this->outposts[$id])) {
            return false;
        }

        $this->outposts[$id]["world"] = $position->getWorld()->getFolderName();
        $this->outposts[$id]["x"] = $position->getFloorX();
        $this->outposts[$id]["y"] = $position->getFloorY();
        $this->outposts[$id]["z"] = $position->getFloorZ();
        $this->writeOutposts();
        $this->refreshOutpostFloatingText($id);
        return true;
    }

    public function setOutpostRadius(string $id, int $radius) : bool
    {
        $id = strtolower(trim($id));
        if (!isset($this->outposts[$id])) {
            return false;
        }

        $this->outposts[$id]["radius"] = max(1, $radius);
        $this->writeOutposts();
        $this->refreshOutpostFloatingText($id);
        return true;
    }

    public function setOutpostCaptureTime(string $id, int $seconds) : bool
    {
        $id = strtolower(trim($id));
        if (!isset($this->outposts[$id])) {
            return false;
        }

        $this->outposts[$id]["capture-time"] = max(10, $seconds);
        $this->writeOutposts();
        $this->refreshOutpostFloatingText($id);
        return true;
    }

    public function getOutpost(string $id) : ?array
    {
        return $this->outposts[strtolower(trim($id))] ?? null;
    }

    /**
     * @return string[]
     */
    public function getOutpostTypes() : array
    {
        return ["economy", "experience"];
    }

    public function getOutpostTypeMultiplierForPlayer(Player $player, string $type, float $default = 1.0): float
    {
        $factionId = Loader::getPlayerManager()->getSession($player)?->getFactionId();
        if ($factionId === null) {
            return $default;
        }

        $best = $default;
        $type = $this->normalizeOutpostType($type);
        foreach ($this->outposts as $outpost) {
            if (
                $this->normalizeOutpostType((string) ($outpost["type"] ?? "")) !== $type ||
                (string) ($outpost["owner-faction"] ?? "") !== $factionId
            ) {
                continue;
            }

            $best = max($best, max(1.0, (float) ($outpost["benefit-multiplier"] ?? 1.25)));
        }

        return $best;
    }

    public function createKoth(string $id) : bool
    {
        $id = strtolower(trim($id));
        if ($id === "" || isset($this->koths[$id])) {
            return false;
        }

        $this->koths[$id] = [
            "display" => ucwords(str_replace("_", " ", $id)),
            "world" => "",
            "capture-time" => 180,
            "spawn" => ["x" => 0, "y" => 80, "z" => 0],
            "pos1" => null,
            "pos2" => null,
        ];
        $this->writeKoths();
        return true;
    }

    public function deleteKoth(string $id) : bool
    {
        $id = strtolower(trim($id));
        if (!isset($this->koths[$id])) {
            return false;
        }

        if ($this->activeKothId === $id) {
            $this->stopKoth(false);
        }

        unset($this->koths[$id]);
        $this->writeKoths();
        return true;
    }

    public function setKothPosition(string $id, string $which, Position $position) : bool
    {
        $id = strtolower(trim($id));
        if (!isset($this->koths[$id]) || !in_array($which, ["pos1", "pos2"], true)) {
            return false;
        }

        $this->koths[$id]["world"] = $position->getWorld()->getFolderName();
        $this->koths[$id][$which] = [
            "x" => $position->getFloorX(),
            "y" => $position->getFloorY(),
            "z" => $position->getFloorZ(),
        ];
        $this->writeKoths();
        return true;
    }

    public function setKothSpawn(string $id, Position $position) : bool
    {
        $id = strtolower(trim($id));
        if (!isset($this->koths[$id])) {
            return false;
        }

        $this->koths[$id]["world"] = $position->getWorld()->getFolderName();
        $this->koths[$id]["spawn"] = [
            "x" => $position->getFloorX(),
            "y" => $position->getFloorY(),
            "z" => $position->getFloorZ(),
        ];
        $this->writeKoths();
        return true;
    }

    public function setKothCaptureTime(string $id, int $seconds) : bool
    {
        $id = strtolower(trim($id));
        if (!isset($this->koths[$id])) {
            return false;
        }

        $this->koths[$id]["capture-time"] = max(10, $seconds);
        $this->writeKoths();
        return true;
    }

    public function startKoth(string $id) : bool
    {
        $id = strtolower(trim($id));
        $definition = $this->koths[$id] ?? null;
        if ($definition === null || !$this->hasValidKothBounds($definition)) {
            return false;
        }

        $this->activeKothId = $id;
        $this->activeKothFactionId = null;
        $this->activeKothProgress = 0.0;
        $this->lastKothAnnounce = 0;

        Server::getInstance()->broadcastMessage(C::colorize(
            "&r&6KOTH &8| &r&f" . (string) $definition["display"] . " &7has started. Capture the hill with your faction."
        ));
        return true;
    }

    public function stopKoth(bool $broadcast = true) : void
    {
        $active = $this->activeKothId;
        $display = $active !== null ? (string) ($this->koths[$active]["display"] ?? ucfirst($active)) : "KOTH";
        $this->activeKothId = null;
        $this->activeKothFactionId = null;
        $this->activeKothProgress = 0.0;
        $this->lastKothAnnounce = 0;

        if ($broadcast) {
            Server::getInstance()->broadcastMessage(C::colorize("&r&6KOTH &8| &r&7{$display} has ended."));
        }
    }

    private function tickOutposts() : void
    {
        foreach ($this->outposts as $id => $outpost) {
            $world = $this->getWorld((string) ($outpost["world"] ?? ""));
            if ($world === null) {
                continue;
            }

            $center = new Position((float) $outpost["x"], (float) $outpost["y"], (float) $outpost["z"], $world);
            $radius = max(1, (int) ($outpost["radius"] ?? 5));
            $factions = $this->getFactionsInRadius($center, $radius);
            $ownerFactionId = (string) ($outpost["owner-faction"] ?? "");
            $captureTime = max(10, (int) ($outpost["capture-time"] ?? 90));

            if (count($factions) > 1) {
                $this->outpostContested[$id] = true;
                $this->outpostActiveFaction[$id] = null;
                foreach ($this->getPlayersInRadius($center, $radius) as $player) {
                    $player->sendActionBarMessage(C::colorize("&r&6Outpost &8| &r&cContested"));
                }
                $this->refreshOutpostFloatingText($id);
                continue;
            }
            $this->outpostContested[$id] = false;

            if ($factions === []) {
                $this->outpostActiveFaction[$id] = null;
                $this->decayOutpostProgress($id, $outpost, $captureTime);
                continue;
            }

            $faction = reset($factions);
            if (!$faction instanceof Faction) {
                continue;
            }

            $factionId = $faction->getFactionId();
            $this->outpostActiveFaction[$id] = $factionId;

            [$controlFactionId, $progress] = $this->getOutpostControlState($id, $outpost, $captureTime);

            if ($controlFactionId === null) {
                $this->outpostCapturingFaction[$id] = $factionId;
                $this->outpostCaptureProgress[$id] = 0;
                $controlFactionId = $factionId;
                $progress = 0;
                $this->announceOutpostProgress($id, $outpost, $faction, true);
            }

            if ($ownerFactionId !== "" && $ownerFactionId === $factionId && $controlFactionId === $factionId && $progress >= $captureTime) {
                $this->refreshOutpostFloatingText($id);
                continue;
            }

            if ($controlFactionId !== $factionId) {
                if (($this->outpostAnnounceCooldown[$id] ?? 0) <= time()) {
                    $this->announceOutpostProgress($id, $outpost, $faction, true);
                }

                $progress = max(0, $progress - 1);
                $this->outpostCapturingFaction[$id] = $controlFactionId;
                $this->outpostCaptureProgress[$id] = $progress;
                $neutralizePercent = min(100, (int) round((($captureTime - $progress) / $captureTime) * 100));

                foreach ($this->getFactionPlayersInRadius($center, $radius, $factionId) as $player) {
                    $player->sendActionBarMessage(C::colorize("&r&6Outpost &8| &r&f" . (string) $outpost["display"] . " &7- &cNeutralizing &f{$neutralizePercent}%"));
                }

                if ($progress <= 0) {
                    if ($ownerFactionId !== "" && $ownerFactionId === $controlFactionId) {
                        $this->outposts[$id]["owner-faction"] = "";
                        $this->writeOutposts();
                    }
                    $this->outpostCapturingFaction[$id] = null;
                    $this->outpostCaptureProgress[$id] = 0;
                }
                $this->refreshOutpostFloatingText($id);
                continue;
            }

            $progress = min($captureTime, $progress + 1);
            $this->outpostCapturingFaction[$id] = $factionId;
            $this->outpostCaptureProgress[$id] = $progress;
            $percent = min(100, (int) round(($progress / $captureTime) * 100));
            $action = $ownerFactionId === $factionId ? "Stabilizing" : "Capturing";

            foreach ($this->getFactionPlayersInRadius($center, $radius, $factionId) as $player) {
                $player->sendActionBarMessage(C::colorize("&r&6Outpost &8| &r&f" . (string) $outpost["display"] . " &7- &e{$action} &f{$percent}%"));
            }

            if ($progress < $captureTime) {
                $this->refreshOutpostFloatingText($id);
                continue;
            }

            if ($ownerFactionId === $factionId) {
                $this->refreshOutpostFloatingText($id);
                continue;
            }

            $this->outposts[$id]["owner-faction"] = $factionId;
            $this->writeOutposts();
            $this->outpostCapturingFaction[$id] = $factionId;
            $this->outpostCaptureProgress[$id] = $captureTime;
            $this->refreshOutpostFloatingText($id);

            Server::getInstance()->broadcastMessage(C::colorize(
                "&r&6Outpost &8| &r&f" . $faction->getName() . " &7captured &f" . (string) $outpost["display"] . "&7."
            ));
            foreach ($this->getFactionPlayersInRadius($center, $radius, $factionId) as $member) {
                Loader::getQuestBoardManager()->recordProgress($member, "outposts_captured", 1, [
                    "outpost" => strtolower((string) ($outpost["id"] ?? $id)),
                ]);
            }
        }
    }

    private function tickKoth() : void
    {
        if ($this->activeKothId === null) {
            return;
        }

        $definition = $this->koths[$this->activeKothId] ?? null;
        if ($definition === null || !$this->hasValidKothBounds($definition)) {
            $this->stopKoth(false);
            return;
        }

        $world = $this->getWorld((string) $definition["world"]);
        if ($world === null) {
            return;
        }

        $factions = $this->getFactionsInCuboid($world, (array) $definition["pos1"], (array) $definition["pos2"]);
        if (count($factions) !== 1) {
            return;
        }

        $faction = reset($factions);
        if (!$faction instanceof Faction) {
            return;
        }

        $factionId = $faction->getFactionId();
        if ($this->activeKothFactionId !== $factionId) {
            $this->activeKothFactionId = $factionId;
            $this->activeKothProgress = 0.0;

            Server::getInstance()->broadcastMessage(C::colorize(
                "&r&6KOTH &8| &r&f" . $faction->getName() . " &7is now capturing &f" . (string) $definition["display"] . "&7."
            ));
        }

        $strategistBonus = $this->getStrategistKothProgressBonus(
            $world,
            (array) $definition["pos1"],
            (array) $definition["pos2"],
            $factionId
        );
        $this->activeKothProgress += 1.0 + $strategistBonus;
        $captureTime = max(10, (int) ($definition["capture-time"] ?? 180));
        $percent = min(100, (int) round(($this->activeKothProgress / $captureTime) * 100));

        foreach ($this->getFactionPlayersInCuboid($world, (array) $definition["pos1"], (array) $definition["pos2"], $factionId) as $player) {
            $player->sendActionBarMessage(C::colorize("&r&6KOTH &8| &r&f" . (string) $definition["display"] . " &7- &f{$percent}%"));
        }

        if ($this->activeKothProgress < $captureTime) {
            return;
        }

        Server::getInstance()->broadcastMessage(C::colorize(
            "&r&6KOTH &8| &r&f" . $faction->getName() . " &7has won &f" . (string) $definition["display"] . "&7."
        ));
        $this->stopKoth(false);
    }

    /**
     * @return array<string, Faction>
     */
    private function getFactionsInRadius(Position $center, int $radius) : array
    {
        $factions = [];
        foreach ($center->getWorld()->getPlayers() as $player) {
            if ($player->getPosition()->distance($center) > $radius) {
                continue;
            }

            $faction = Loader::getPlayerManager()->getSession($player)?->getFaction();
            if ($faction === null) {
                continue;
            }

            $factions[$faction->getFactionId()] = $faction;
        }

        return $factions;
    }

    /**
     * @return array<int, Player>
     */
    private function getFactionPlayersInRadius(Position $center, int $radius, string $factionId) : array
    {
        $players = [];
        foreach ($center->getWorld()->getPlayers() as $player) {
            if ($player->getPosition()->distance($center) > $radius) {
                continue;
            }

            $playerFactionId = Loader::getPlayerManager()->getSession($player)?->getFactionId();
            if ($playerFactionId === $factionId) {
                $players[] = $player;
            }
        }

        return $players;
    }

    /**
     * @return array<int, Player>
     */
    private function getPlayersInRadius(Position $center, int $radius) : array
    {
        $players = [];
        foreach ($center->getWorld()->getPlayers() as $player) {
            if ($player->getPosition()->distance($center) <= $radius) {
                $players[] = $player;
            }
        }

        return $players;
    }

    /**
     * @return array<string, Faction>
     */
    private function getFactionsInCuboid(World $world, array $pos1, array $pos2) : array
    {
        $minX = min((int) $pos1["x"], (int) $pos2["x"]);
        $maxX = max((int) $pos1["x"], (int) $pos2["x"]);
        $minY = min((int) $pos1["y"], (int) $pos2["y"]);
        $maxY = max((int) $pos1["y"], (int) $pos2["y"]);
        $minZ = min((int) $pos1["z"], (int) $pos2["z"]);
        $maxZ = max((int) $pos1["z"], (int) $pos2["z"]);

        $factions = [];
        foreach ($world->getPlayers() as $player) {
            $pos = $player->getPosition();
            if (
                $pos->getFloorX() < $minX || $pos->getFloorX() > $maxX ||
                $pos->getFloorY() < $minY || $pos->getFloorY() > $maxY ||
                $pos->getFloorZ() < $minZ || $pos->getFloorZ() > $maxZ
            ) {
                continue;
            }

            $faction = Loader::getPlayerManager()->getSession($player)?->getFaction();
            if ($faction === null) {
                continue;
            }

            $factions[$faction->getFactionId()] = $faction;
        }

        return $factions;
    }

    private function getStrategistKothProgressBonus(World $world, array $pos1, array $pos2, string $capturingFactionId) : float
    {
        $minX = min((int) $pos1["x"], (int) $pos2["x"]);
        $maxX = max((int) $pos1["x"], (int) $pos2["x"]);
        $minY = min((int) $pos1["y"], (int) $pos2["y"]);
        $maxY = max((int) $pos1["y"], (int) $pos2["y"]);
        $minZ = min((int) $pos1["z"], (int) $pos2["z"]);
        $maxZ = max((int) $pos1["z"], (int) $pos2["z"]);

        $cap = max(1, EnchantTuningManager::getInt("warlordstempo", "koth-bonus-level-cap", 8));
        $per = max(0.0, EnchantTuningManager::getFloat("warlordstempo", "koth-capture-bonus-per-level", 0.02));
        $best = 0.0;
        foreach ($world->getPlayers() as $player) {
            $pos = $player->getPosition();
            if (
                $pos->getFloorX() < $minX || $pos->getFloorX() > $maxX
                || $pos->getFloorY() < $minY || $pos->getFloorY() > $maxY
                || $pos->getFloorZ() < $minZ || $pos->getFloorZ() > $maxZ
            ) {
                continue;
            }
            if (Loader::getPlayerManager()->getSession($player)?->getFactionId() !== $capturingFactionId) {
                continue;
            }
            $lv = CustomEnchantmentManager::getLevel($player->getArmorInventory()->getBoots(), "warlordstempo");
            if ($lv > 0) {
                $best = max($best, $per * min($cap, $lv));
            }
        }
        return $best;
    }

    /**
     * @return array<int, Player>
     */
    private function getFactionPlayersInCuboid(World $world, array $pos1, array $pos2, string $factionId) : array
    {
        $minX = min((int) $pos1["x"], (int) $pos2["x"]);
        $maxX = max((int) $pos1["x"], (int) $pos2["x"]);
        $minY = min((int) $pos1["y"], (int) $pos2["y"]);
        $maxY = max((int) $pos1["y"], (int) $pos2["y"]);
        $minZ = min((int) $pos1["z"], (int) $pos2["z"]);
        $maxZ = max((int) $pos1["z"], (int) $pos2["z"]);

        $players = [];
        foreach ($world->getPlayers() as $player) {
            $pos = $player->getPosition();
            if (
                $pos->getFloorX() < $minX || $pos->getFloorX() > $maxX ||
                $pos->getFloorY() < $minY || $pos->getFloorY() > $maxY ||
                $pos->getFloorZ() < $minZ || $pos->getFloorZ() > $maxZ
            ) {
                continue;
            }

            if (Loader::getPlayerManager()->getSession($player)?->getFactionId() === $factionId) {
                $players[] = $player;
            }
        }

        return $players;
    }

    private function announceOutpostProgress(string $id, array $outpost, Faction $faction, bool $force = false) : void
    {
        $now = time();
        if (!$force && ($this->outpostAnnounceCooldown[$id] ?? 0) > $now) {
            return;
        }

        $this->outpostAnnounceCooldown[$id] = $now + 20;
        Server::getInstance()->broadcastMessage(C::colorize(
            "&r&6Outpost &8| &r&f" . $faction->getName() . " &7is attacking &f" . (string) $outpost["display"] . "&7."
        ));
    }

    private function hasValidKothBounds(array $definition) : bool
    {
        return is_array($definition["pos1"] ?? null)
            && is_array($definition["pos2"] ?? null)
            && trim((string) ($definition["world"] ?? "")) !== "";
    }

    private function getWorld(string $name) : ?World
    {
        if ($name === "") {
            return null;
        }

        $manager = Server::getInstance()->getWorldManager();
        $world = $manager->getWorldByName($name);
        if (!$world instanceof World && $manager->loadWorld($name)) {
            $world = $manager->getWorldByName($name);
        }
        return $world instanceof World ? $world : null;
    }

    private function writeOutposts() : void
    {
        if ($this->config === null) {
            return;
        }

        $this->config->set("outposts", $this->outposts);
        $this->config->save();
    }

    private function normalizeOutpostType(string $type) : string
    {
        $type = strtolower(trim($type));
        return match ($type) {
            "xp", "exp", "experience" => "experience",
            default => "economy",
        };
    }

    private function getOutpostControlState(string $id, array $outpost, int $captureTime): array
    {
        $controlFactionId = $this->outpostCapturingFaction[$id] ?? null;
        $progress = (int) ($this->outpostCaptureProgress[$id] ?? 0);
        $ownerFactionId = trim((string) ($outpost["owner-faction"] ?? ""));

        if (($controlFactionId === null || $controlFactionId === "") && $ownerFactionId !== "") {
            return [$ownerFactionId, $captureTime];
        }

        if ($controlFactionId === "") {
            $controlFactionId = null;
        }

        return [$controlFactionId, max(0, min($captureTime, $progress))];
    }

    private function decayOutpostProgress(string $id, array $outpost, int $captureTime): void
    {
        [$controlFactionId, $progress] = $this->getOutpostControlState($id, $outpost, $captureTime);
        $ownerFactionId = trim((string) ($outpost["owner-faction"] ?? ""));

        if ($progress <= 0 && $controlFactionId === null) {
            return;
        }

        if ($ownerFactionId !== "" && $controlFactionId === $ownerFactionId) {
            $this->refreshOutpostFloatingText($id);
            return;
        }

        $progress = max(0, $progress - self::OUTPOST_DECAY_PER_TICK);
        $this->outpostCaptureProgress[$id] = $progress;
        if ($progress <= 0) {
            $this->outpostCaptureProgress[$id] = 0;
            $this->outpostCapturingFaction[$id] = null;
        } elseif ($controlFactionId !== null) {
            $this->outpostCapturingFaction[$id] = $controlFactionId;
        }

        $this->refreshOutpostFloatingText($id);
    }

    private function refreshOutpostFloatingTexts(): void
    {
        foreach (array_keys($this->outposts) as $id) {
            $this->refreshOutpostFloatingText($id);
        }
    }

    private function refreshOutpostFloatingText(string $id): void
    {
        $this->removeOutpostFloatingText($id);
        $outpost = $this->outposts[$id] ?? null;
        if (!is_array($outpost)) {
            return;
        }

        $world = $this->getWorld((string) ($outpost["world"] ?? ""));
        if (!$world instanceof World || trim((string) ($outpost["world"] ?? "")) === "") {
            return;
        }

        $x = (float) ($outpost["x"] ?? 0) + 0.5;
        $y = (float) ($outpost["y"] ?? 80) + max(2.2, (float) ($outpost["hologram-offset-y"] ?? 3.0));
        $z = (float) ($outpost["z"] ?? 0) + 0.5;
        $entity = new FloatingTextEntity(new Location($x, $y, $z, $world, 0.0, 0.0));
        $entity->setText($this->buildOutpostFloatingText($id, $outpost));
        $entity->spawnToAll();
        $this->outpostFloatingTexts[$id] = $entity;
    }

    private function removeOutpostFloatingText(string $id): void
    {
        $entity = $this->outpostFloatingTexts[$id] ?? null;
        if ($entity instanceof FloatingTextEntity && !$entity->isClosed()) {
            $entity->flagForDespawn();
        }
        unset($this->outpostFloatingTexts[$id]);
    }

    private function clearOutpostFloatingTexts(): void
    {
        foreach (array_keys($this->outpostFloatingTexts) as $id) {
            $this->removeOutpostFloatingText($id);
        }
    }

    private function buildOutpostFloatingText(string $id, array $outpost): string
    {
        $display = (string) ($outpost["display"] ?? ucfirst($id));
        $type = ucfirst($this->normalizeOutpostType((string) ($outpost["type"] ?? "economy")));
        $radius = max(1, (int) ($outpost["radius"] ?? 5));
        $captureTime = max(10, (int) ($outpost["capture-time"] ?? 45));
        $ownerFactionId = (string) ($outpost["owner-faction"] ?? "");
        $ownerFaction = $ownerFactionId !== "" ? Loader::getFactionManager()->getFaction($ownerFactionId) : null;
        $ownerLine = $ownerFaction instanceof Faction
            ? "&fHeld by: &a" . $ownerFaction->getName()
            : "&fHeld by: &cNo faction";

        $activeFactionId = $this->outpostActiveFaction[$id] ?? null;
        if (($this->outpostContested[$id] ?? false) === true) {
            $statusLine = "&cContested";
        } else {
            [$controlFactionId, $progress] = $this->getOutpostControlState($id, $outpost, $captureTime);
            if ($activeFactionId !== null && $controlFactionId !== null && $activeFactionId !== $controlFactionId) {
                $activeFaction = Loader::getFactionManager()->getFaction($activeFactionId);
                $activeName = $activeFaction?->getName() ?? "Unknown";
                $percent = min(100, (int) round((($captureTime - $progress) / max(1, $captureTime)) * 100));
                $statusLine = "&7Contesting: &e{$activeName} &7({$percent}%)";
            } elseif ($controlFactionId !== null && $progress > 0 && ($ownerFactionId === "" || $progress < $captureTime || $controlFactionId !== $ownerFactionId)) {
                $controlFaction = Loader::getFactionManager()->getFaction($controlFactionId);
                $controlName = $controlFaction?->getName() ?? "Unknown";
                $percent = min(100, (int) round(($progress / max(1, $captureTime)) * 100));
                $label = $ownerFactionId !== "" && $controlFactionId === $ownerFactionId ? "Stabilizing" : "Capturing";
                $statusLine = "&7{$label}: &e{$controlName} &7({$percent}%)";
            } else {
                $statusLine = "&7Stand inside to capture";
            }
        }

        return implode("\n", [
            "&r&6&lOUTPOST",
            "&f{$display} &8({$type}&8)",
            $ownerLine,
            $statusLine,
            "&7Radius: &f{$radius} &8| &7Time: &f{$captureTime}s",
        ]);
    }

    private function writeKoths() : void
    {
        if ($this->config === null) {
            return;
        }

        $this->config->set("koths", $this->koths);
        $this->config->save();
    }
}
