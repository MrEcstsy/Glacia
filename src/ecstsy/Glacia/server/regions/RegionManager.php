<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\regions;

use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\Position;

final class RegionManager {
    use SingletonTrait;

    private const PRESET_SPAWN = "spawn";
    private const PRESET_SAFEZONE = "safezone";
    private const PRESET_WARZONE = "warzone";

    public function __construct()
    {
        self::setInstance($this);
    }

    /** @var Region[] */
    private array $regions = [];
    /** @var array<string, array{pos1?: Position, pos2?: Position}> */
    private array $selections = [];

    public function addRegion(Region $region): void {
        $this->regions[strtolower($region->getName())] = $region;
    }

    public function clearRegions(): void
    {
        $this->regions = [];
    }

    /**
     * @return Region[]
     */
    public function getRegions(): array
    {
        return array_values($this->regions);
    }

    public function getRegionsAt(Vector3 $position): array {
        $found = [];
        foreach ($this->regions as $region) {
            if ($region->isWithinBounds($position)) {
                $found[] = $region;
            }
        }
        return $found;
    }

    public function getFirstRegionAt(Vector3 $position): ?Region {
        foreach ($this->regions as $region) {
            if ($region->isWithinBounds($position)) {
                return $region;
            }
        }
        return null;
    }

    public function getRegionByName(string $name): ?Region {
        return $this->regions[strtolower($name)] ?? null;
    }

    public function getRegionAt(Vector3 $position): ?Region {
        foreach ($this->regions as $region) {
            if ($region->contains($position)) {
                return $region;
            }
        }
        return null;
    }

    public function getRegionAtChunk(int $chunkX, int $chunkZ, string $world): ?Region {
        $pos = new Vector3(($chunkX << 4) + 8, 64, ($chunkZ << 4) + 8);
        foreach ($this->regions as $region) {
            if ($region->getWorld() === $world && $region->isWithinBounds($pos)) {
                return $region;
            }
        }
        return null;
    }

    public function loadFromConfig(): void
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "regions.yml");
        $configured = (array) ($config?->get("regions", []) ?? []);
        $this->clearRegions();

        foreach ($configured as $name => $regionData) {
            if (!is_string($name) || !is_array($regionData)) {
                continue;
            }

            $world = trim((string) ($regionData["world"] ?? ""));
            $pos1 = is_array($regionData["pos1"] ?? null) ? $regionData["pos1"] : [];
            $pos2 = is_array($regionData["pos2"] ?? null) ? $regionData["pos2"] : [];
            if (
                $world === "" ||
                !isset($pos1["x"], $pos1["y"], $pos1["z"], $pos2["x"], $pos2["y"], $pos2["z"])
            ) {
                continue;
            }

            $permissions = $this->deserializePermissions(
                is_array($regionData["permissions"] ?? null) ? $regionData["permissions"] : [],
                strtolower((string) ($regionData["preset"] ?? "custom"))
            );

            $this->addRegion(new Region(
                (string) ($regionData["display"] ?? $name),
                new Vector3((float) $pos1["x"], (float) $pos1["y"], (float) $pos1["z"]),
                new Vector3((float) $pos2["x"], (float) $pos2["y"], (float) $pos2["z"]),
                $permissions,
                $world
            ));
        }
    }

    public function setSelectionPoint(Player $player, Position $position, string $point): bool
    {
        if (!in_array($point, ["pos1", "pos2"], true)) {
            return false;
        }

        $key = $player->getUniqueId()->toString();
        $selection = $this->selections[$key] ?? [];
        $otherPoint = $point === "pos1" ? "pos2" : "pos1";
        if (
            isset($selection[$otherPoint]) &&
            $selection[$otherPoint] instanceof Position &&
            strtolower($selection[$otherPoint]->getWorld()->getFolderName()) !== strtolower($position->getWorld()->getFolderName())
        ) {
            unset($selection[$otherPoint]);
        }

        $selection[$point] = new Position(
            $position->getX(),
            $position->getY(),
            $position->getZ(),
            $position->getWorld()
        );
        $this->selections[$key] = $selection;
        return true;
    }

    /**
     * @return array{pos1?: Position, pos2?: Position}
     */
    public function getSelection(Player $player): array
    {
        return $this->selections[$player->getUniqueId()->toString()] ?? [];
    }

    public function clearSelection(Player $player): void
    {
        unset($this->selections[$player->getUniqueId()->toString()]);
    }

    public function createOrUpdateRegionFromSelection(Player $player, string $name, string $preset): ?Region
    {
        $selection = $this->getSelection($player);
        $pos1 = $selection["pos1"] ?? null;
        $pos2 = $selection["pos2"] ?? null;
        if (!$pos1 instanceof Position || !$pos2 instanceof Position) {
            return null;
        }

        return $this->createOrUpdateRegion($name, $preset, $pos1, $pos2);
    }

    public function createOrUpdateRegion(string $name, string $preset, Position $pos1, Position $pos2): ?Region
    {
        $name = trim($name);
        if ($name === "") {
            return null;
        }
        if (strtolower($pos1->getWorld()->getFolderName()) !== strtolower($pos2->getWorld()->getFolderName())) {
            return null;
        }

        $permissions = $this->getPresetPermissions($preset);
        if ($permissions === null) {
            return null;
        }

        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "regions.yml");
        if ($config === null) {
            return null;
        }

        $regions = (array) ($config->get("regions", []) ?? []);
        $key = strtolower($name);
        $regions[$key] = [
            "display" => $name,
            "preset" => strtolower($preset),
            "world" => $pos1->getWorld()->getFolderName(),
            "pos1" => [
                "x" => $pos1->getFloorX(),
                "y" => $pos1->getFloorY(),
                "z" => $pos1->getFloorZ(),
            ],
            "pos2" => [
                "x" => $pos2->getFloorX(),
                "y" => $pos2->getFloorY(),
                "z" => $pos2->getFloorZ(),
            ],
            "permissions" => $this->serializePermissions($permissions),
        ];

        $config->set("regions", $regions);
        $config->save();
        $this->loadFromConfig();

        return $this->getRegionByName($key);
    }

    public function deleteRegion(string $name): bool
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "regions.yml");
        if ($config === null) {
            return false;
        }

        $regions = (array) ($config->get("regions", []) ?? []);
        $key = strtolower(trim($name));
        if ($key === "" || !isset($regions[$key])) {
            return false;
        }

        unset($regions[$key]);
        $config->set("regions", $regions);
        $config->save();
        $this->loadFromConfig();
        return true;
    }

    public function isValidPreset(string $preset): bool
    {
        return $this->getPresetPermissions($preset) !== null;
    }

    /**
     * @return string[]
     */
    public function getPresetNames(): array
    {
        return [self::PRESET_SPAWN, self::PRESET_WARZONE, self::PRESET_SAFEZONE];
    }

    private function getPresetPermissions(string $preset): ?RegionPermissions
    {
        return match (strtolower(trim($preset))) {
            self::PRESET_SPAWN, self::PRESET_SAFEZONE => new RegionPermissions(false, false, true, false, false, false, false),
            self::PRESET_WARZONE => new RegionPermissions(true, false, true, false, false, false, false),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $data
     */
    private function deserializePermissions(array $data, string $preset): RegionPermissions
    {
        $defaults = $this->getPresetPermissions($preset) ?? new RegionPermissions(false, false, false, true, false, false, true);
        return new RegionPermissions(
            (bool) ($data["pvp"] ?? $defaults->pvp),
            (bool) ($data["can-break"] ?? $data["break"] ?? $defaults->canBreak),
            (bool) ($data["fly"] ?? $defaults->fly),
            (bool) ($data["fall-damage"] ?? $defaults->fallDamage),
            (bool) ($data["build"] ?? $defaults->build),
            (bool) ($data["interact"] ?? $defaults->interact),
            (bool) ($data["explosions"] ?? $defaults->explosions)
        );
    }

    /**
     * @return array<string, bool>
     */
    private function serializePermissions(RegionPermissions $permissions): array
    {
        return [
            "pvp" => $permissions->pvp,
            "can-break" => $permissions->canBreak,
            "fly" => $permissions->fly,
            "fall-damage" => $permissions->fallDamage,
            "build" => $permissions->build,
            "interact" => $permissions->interact,
            "explosions" => $permissions->explosions,
        ];
    }
}
