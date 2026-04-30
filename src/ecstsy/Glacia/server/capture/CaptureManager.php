<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\capture;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\factions\Faction;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as C;
use pocketmine\world\Position;
use pocketmine\world\World;

final class CaptureManager {

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
    /** @var array<string, int> */
    private array $outpostAnnounceCooldown = [];

    public function load() : void
    {
        $this->config = GeneralUtils::getConfiguration(Loader::getInstance(), "capturepoints.yml");
        $this->outposts = [];
        $this->koths = [];
        $this->activeKothId = null;
        $this->activeKothFactionId = null;
        $this->activeKothProgress = 0.0;

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
            "type" => strtolower(trim($type)) === "" ? "economy" : strtolower(trim($type)),
            "world" => "",
            "x" => 0,
            "y" => 80,
            "z" => 0,
            "radius" => 5,
            "capture-time" => 90,
            "owner-faction" => "",
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

        unset($this->outposts[$id], $this->outpostCapturingFaction[$id], $this->outpostCaptureProgress[$id], $this->outpostAnnounceCooldown[$id]);
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
        return true;
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

            if (count($factions) !== 1) {
                continue;
            }

            $faction = reset($factions);
            if (!$faction instanceof Faction) {
                continue;
            }

            $factionId = $faction->getFactionId();
            if ($ownerFactionId !== "" && $ownerFactionId === $factionId) {
                $this->outpostCapturingFaction[$id] = null;
                $this->outpostCaptureProgress[$id] = 0;
                continue;
            }

            if (($this->outpostCapturingFaction[$id] ?? null) !== $factionId) {
                $this->outpostCapturingFaction[$id] = $factionId;
                $this->outpostCaptureProgress[$id] = 0;
                $this->announceOutpostProgress($id, $outpost, $faction, true);
            }

            $this->outpostCaptureProgress[$id] = ($this->outpostCaptureProgress[$id] ?? 0) + 1;
            $captureTime = max(10, (int) ($outpost["capture-time"] ?? 90));
            $progress = $this->outpostCaptureProgress[$id];
            $percent = min(100, (int) round(($progress / $captureTime) * 100));

            foreach ($this->getFactionPlayersInRadius($center, $radius, $factionId) as $player) {
                $player->sendActionBarMessage(C::colorize("&r&6Outpost &8| &r&f" . (string) $outpost["display"] . " &7- &f{$percent}%"));
            }

            if ($progress < $captureTime) {
                continue;
            }

            $this->outposts[$id]["owner-faction"] = $factionId;
            $this->writeOutposts();
            $this->outpostCapturingFaction[$id] = null;
            $this->outpostCaptureProgress[$id] = 0;

            Server::getInstance()->broadcastMessage(C::colorize(
                "&r&6Outpost &8| &r&f" . $faction->getName() . " &7captured &f" . (string) $outpost["display"] . "&7."
            ));
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

        $cap = max(1, EnchantTuningManager::getInt("strategist", "koth-bonus-level-cap", 8));
        $per = max(0.0, EnchantTuningManager::getFloat("strategist", "koth-capture-bonus-per-level", 0.02));
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
            $lv = CustomEnchantmentManager::getLevel($player->getArmorInventory()->getBoots(), "strategist");
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

        return Server::getInstance()->getWorldManager()->getWorldByName($name);
    }

    private function writeOutposts() : void
    {
        if ($this->config === null) {
            return;
        }

        $this->config->set("outposts", $this->outposts);
        $this->config->save();
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
