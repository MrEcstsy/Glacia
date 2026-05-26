<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\factions\guardian;

use ecstsy\Glacia\entity\FactionGuardianEntity;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\factions\claims\ClaimManager;
use ecstsy\Glacia\server\factions\Faction;
use ecstsy\Glacia\server\factions\relations\RelationManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\Glacia\utils\StorageUtils;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\entity\Location;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\utils\TextFormat as C;
use pocketmine\world\Position;
use pocketmine\world\World;

final class FactionGuardianManager
{
    public const ITEM_ID = "faction_guardian_egg";

    /** @var array<string, array<string, mixed>> */
    private static array $states = [];
    /** @var array<string, FactionGuardianEntity> */
    private static array $activeEntities = [];
    /** @var array<string, mixed> */
    private static array $settings = [];
    private static string $dataPath = "";

    public static function init(Loader $plugin): void
    {
        self::$dataPath = $plugin->getDataFolder() . "faction_guardians.json";
        self::loadSettings();
        self::load();
        self::ensureLoadedFactionStates();

        $plugin->getScheduler()->scheduleRepeatingTask(new ClosureTask(static function(): void {
            self::tick();
        }), 40);
    }

    public static function save(): void
    {
        if (self::$dataPath === "") {
            return;
        }

        StorageUtils::writeJsonFile(self::$dataPath, self::$states, "faction guardians");
    }

    public static function registerFactionCreated(Faction $faction, ?Player $leader = null): void
    {
        self::ensureState($faction->getFactionId(), time(), Loader::getGraceInstance()->isGraceActive());
        self::save();
    }

    public static function deleteFaction(string $factionId): void
    {
        self::despawnEntity($factionId);
        unset(self::$states[$factionId]);
        self::save();
        self::removeOnlineEggsForFaction($factionId);
    }

    public static function resetSeasonState(): void
    {
        foreach (array_keys(self::$activeEntities) as $factionId) {
            self::despawnEntity($factionId);
        }
        self::$states = [];
        self::$activeEntities = [];
        self::save();
    }

    public static function createEgg(Faction $faction): Item
    {
        $item = StringToItemParser::getInstance()->parse("ender_dragon_spawn_egg")
            ?? StringToItemParser::getInstance()->parse("mob_spawn_egg")
            ?? VanillaItems::EGG();
        $item->setCustomName(C::colorize("&r&l&bChunk Guardian Egg"));
        $item->setLore([
            C::colorize("&r&7Bound to faction: &f" . $faction->getName()),
            C::colorize("&r&7Leader-bound. Cannot be traded, dropped,"),
            C::colorize("&r&7stored, sold, or used outside your claims."),
            "",
            C::colorize("&r&eRight-click a block in your claim to place."),
        ]);

        $root = $item->getNamedTag();
        $tag = $root->getCompoundTag("Glacia") ?? new CompoundTag();
        $tag->setString("glaciaItem", self::ITEM_ID);
        $tag->setString("faction_id", $faction->getFactionId());
        $tag->setString("faction_name", $faction->getName());
        $tag->setString("leader_uuid", $faction->getLeaderUuid());
        $root->setTag("Glacia", $tag);
        $item->setNamedTag($root);

        return $item;
    }

    public static function isGuardianEgg(Item $item): bool
    {
        return self::getEggFactionId($item) !== null;
    }

    public static function getEggFactionId(Item $item): ?string
    {
        $tag = $item->getNamedTag()->getCompoundTag("Glacia");
        if ($tag === null || $tag->getString("glaciaItem", "") !== self::ITEM_ID) {
            return null;
        }

        $factionId = trim($tag->getString("faction_id", ""));
        return $factionId !== "" ? $factionId : null;
    }

    public static function handleEggPlacement(Player $player, Item $item, Position $target): bool
    {
        $factionId = self::getEggFactionId($item);
        if ($factionId === null) {
            return false;
        }

        $faction = Loader::getFactionManager()->getFactionById($factionId);
        if ($faction === null) {
            self::consumeHeldEgg($player, $item);
            $player->sendMessage(C::colorize("&r&cThat Chunk Guardian belonged to a faction that no longer exists."));
            return true;
        }

        if ($faction->getLeaderUuid() !== $player->getUniqueId()->toString()) {
            self::consumeHeldEgg($player, $item);
            $player->sendMessage(C::colorize("&r&cOnly the current faction leader can hold or place this Chunk Guardian."));
            return true;
        }

        $session = Loader::getPlayerManager()->getSession($player);
        if ($session === null || $session->getFactionId() !== $factionId) {
            self::consumeHeldEgg($player, $item);
            $player->sendMessage(C::colorize("&r&cThis Chunk Guardian is not bound to your faction anymore."));
            return true;
        }

        self::ensureState($factionId, time(), Loader::getGraceInstance()->isGraceActive());
        if (self::hasPlacedGuardian($factionId)) {
            self::consumeHeldEgg($player, $item);
            $player->sendMessage(C::colorize("&r&cYour faction already has a Chunk Guardian placed."));
            return true;
        }

        $claim = ClaimManager::getInstance()->getClaimAtPosition($target);
        if ($claim === null || $claim->getFactionId() !== $factionId) {
            $player->sendMessage(C::colorize("&r&cYou can only place your Chunk Guardian inside your own faction claim."));
            return true;
        }

        if (!self::canManuallyPlace($factionId)) {
            $player->sendMessage(C::colorize("&r&cThis Chunk Guardian can no longer be moved manually."));
            return true;
        }

        $pos = self::findOpenSpotNear($target->getWorld(), $target->getFloorX(), $target->getFloorY() + 1, $target->getFloorZ())
            ?? new Position($target->getFloorX() + 0.5, $target->getFloorY() + 1.0, $target->getFloorZ() + 0.5, $target->getWorld());

        self::placeGuardian($factionId, $pos, false);
        self::consumeHeldEgg($player, $item);
        $player->sendMessage(C::colorize("&r&bChunk Guardian placed. &7Your faction can now appear on &f/f top&7."));
        GlaciaUtils::logFactionAudit("Guardian Place", [
            "faction" => $faction->getName(),
            "faction_id" => $factionId,
            "leader" => $player->getName(),
            "world" => $pos->getWorld()->getFolderName(),
            "x" => (int) floor($pos->x),
            "y" => (int) floor($pos->y),
            "z" => (int) floor($pos->z),
        ]);
        return true;
    }

    public static function handleGuardianHit(Player $player, FactionGuardianEntity $guardian): bool
    {
        $factionId = $guardian->getFactionId();
        $faction = Loader::getFactionManager()->getFactionById($factionId);
        if ($faction === null) {
            $guardian->flagForDespawn();
            return true;
        }

        if ($faction->getLeaderUuid() !== $player->getUniqueId()->toString()) {
            $player->sendTip(C::colorize("&r&cOnly the faction leader can move this guardian."));
            return true;
        }

        $player->sendTip(C::colorize("&r&cChunk Guardians are tied to your core claim and can no longer be picked up."));
        return true;
    }

    public static function syncGuardianToCoreClaim(string $factionId, World $world, int $chunkX, int $chunkZ, bool $announce = true): void
    {
        self::ensureState($factionId, time(), Loader::getGraceInstance()->isGraceActive());

        $x = ($chunkX << 4) + 8;
        $z = ($chunkZ << 4) + 8;
        $y = $world->getHighestBlockAt($x, $z) + 1;
        $pos = self::findOpenSpotNear($world, $x, $y, $z) ?? new Position($x + 0.5, $y, $z + 0.5, $world);
        $alreadyPlaced = self::hasPlacedGuardian($factionId);

        self::placeGuardian($factionId, $pos, false);

        if ($announce && !$alreadyPlaced) {
            $faction = Loader::getFactionManager()->getFactionById($factionId);
            $factionName = $faction?->getName() ?? "Your faction";
            foreach (Server::getInstance()->getOnlinePlayers() as $online) {
                $session = Loader::getPlayerManager()->getSession($online);
                if ($session?->getFactionId() !== $factionId) {
                    continue;
                }

                $online->sendMessage(C::colorize("&r&bChunk Guardian placed. &7{$factionName}'s guardian is now tied to your Core Chunk."));
            }
        }
    }

    public static function clearGuardianForFaction(string $factionId): void
    {
        if (!isset(self::$states[$factionId])) {
            return;
        }

        self::$states[$factionId]["placed"] = false;
        self::$states[$factionId]["world"] = "";
        self::$states[$factionId]["x"] = 0.0;
        self::$states[$factionId]["y"] = 0.0;
        self::$states[$factionId]["z"] = 0.0;
        self::$states[$factionId]["placed_at"] = 0;
        self::save();
        self::despawnEntity($factionId);
    }

    public static function hasPlacedGuardian(string $factionId): bool
    {
        return (bool) ((self::$states[$factionId] ?? [])["placed"] ?? false);
    }

    public static function shouldRequireForFtop(): bool
    {
        return (bool) (self::$settings["require-for-ftop"] ?? true);
    }

    public static function getBreachableFactionAt(Position $center): ?string
    {
        $worldName = $center->getWorld()->getFolderName();
        $chunkX = $center->getFloorX() >> 4;
        $chunkZ = $center->getFloorZ() >> 4;

        foreach (self::$states as $factionId => $state) {
            if (!(bool) ($state["placed"] ?? false) || (string) ($state["world"] ?? "") !== $worldName) {
                continue;
            }

            $guardianChunkX = ((int) floor((float) ($state["x"] ?? 0.0))) >> 4;
            $guardianChunkZ = ((int) floor((float) ($state["z"] ?? 0.0))) >> 4;
            $radius = max(0, (int) (self::$settings["breach-radius-chunks"] ?? 0));
            if (abs($guardianChunkX - $chunkX) > $radius || abs($guardianChunkZ - $chunkZ) > $radius) {
                continue;
            }

            $claim = ClaimManager::getInstance()->getClaimAtPosition($center);
            if ($claim !== null && $claim->getFactionId() === $factionId) {
                return $factionId;
            }
        }

        return null;
    }

    public static function shouldGuardianEntityRemain(string $factionId, Position $position): bool
    {
        if (!self::hasPlacedGuardian($factionId)) {
            return false;
        }

        $faction = Loader::getFactionManager()->getFactionById($factionId);
        if ($faction === null) {
            return false;
        }

        return self::hasNearbyEligibleViewer($position, $factionId, self::getDespawnDistance());
    }

    public static function shouldDisplayTo(Player $player, string $factionId): bool
    {
        $session = Loader::getPlayerManager()->getSession($player);
        if ($session?->getSetting("hide_faction_guardians") === true) {
            return false;
        }

        if ($player->hasPermission("glacia.admin") || $player->hasPermission("glacia.staff")) {
            return true;
        }

        $viewerFactionId = $session?->getFactionId();
        if ($viewerFactionId === null || $viewerFactionId === "") {
            return false;
        }

        if ($viewerFactionId === $factionId) {
            return true;
        }

        return RelationManager::getInstance()->getRelation($viewerFactionId, $factionId) === "ally";
    }

    public static function refreshVisibilityFor(Player $player): void
    {
        foreach (self::$activeEntities as $factionId => $entity) {
            if (!$entity instanceof FactionGuardianEntity || $entity->isClosed()) {
                continue;
            }

            if (self::shouldDisplayTo($player, $factionId)) {
                $entity->spawnTo($player);
            } else {
                $entity->despawnFrom($player);
            }
        }
    }

    public static function getOrbitRadius(): float
    {
        return max(1.0, (float) (self::$settings["entity"]["orbit-radius"] ?? 7.0));
    }

    public static function getOrbitHeight(): float
    {
        return max(1.0, (float) (self::$settings["entity"]["orbit-height"] ?? 8.0));
    }

    public static function getOrbitSpeed(): float
    {
        return max(0.001, (float) (self::$settings["entity"]["orbit-speed"] ?? 0.035));
    }

    public static function getOrbitUpdateTicks(): int
    {
        return max(1, (int) (self::$settings["entity"]["update-ticks"] ?? 2));
    }

    public static function getEntityScale(): float
    {
        return max(0.1, (float) (self::$settings["entity"]["scale"] ?? 0.55));
    }

    public static function cleanPlayerGuardianEggs(Player $player): void
    {
        $changed = false;
        foreach ([$player->getInventory(), $player->getCursorInventory(), $player->getOffHandInventory()] as $inventory) {
            for ($slot = 0; $slot < $inventory->getSize(); ++$slot) {
                $item = $inventory->getItem($slot);
                if (!self::isGuardianEgg($item)) {
                    continue;
                }
                $inventory->setItem($slot, VanillaItems::AIR());
                $changed = true;
            }
        }
        foreach ([$player->getArmorInventory(), $player->getEnderInventory()] as $inventory) {
            for ($slot = 0; $slot < $inventory->getSize(); ++$slot) {
                if (!self::isGuardianEgg($inventory->getItem($slot))) {
                    continue;
                }
                $inventory->setItem($slot, VanillaItems::AIR());
                $changed = true;
            }
        }

        if ($changed) {
            $player->sendMessage(C::colorize("&r&eChunk Guardian eggs were disabled and removed. &7That system is now merged into claiming your Core Chunk."));
        }
    }

    private static function tick(): void
    {
        self::ensureLoadedFactionStates();
        self::syncUnplacedGuardiansToCoreClaims();
        self::spawnNearbyGuardians();
        self::cleanAllOnlineGuardianEggs();
    }

    private static function syncUnplacedGuardiansToCoreClaims(): void
    {
        foreach (self::$states as $factionId => $state) {
            if ((bool) ($state["placed"] ?? false)) {
                continue;
            }

            $core = ClaimManager::getInstance()->getCoreClaimForFaction($factionId);
            if ($core === null) {
                continue;
            }

            $world = Server::getInstance()->getWorldManager()->getWorldByName($core->getWorldName());
            if (!$world instanceof World) {
                continue;
            }

            self::syncGuardianToCoreClaim($factionId, $world, $core->getChunkX(), $core->getChunkZ(), false);
        }
    }

    private static function autoPlaceExpiredGuardians(): void
    {
        if (Loader::getGraceInstance()->isGraceActive()) {
            return;
        }

        $deadlineSeconds = max(60, (int) (self::$settings["place-deadline-seconds"] ?? 86400));
        foreach (self::$states as $factionId => $state) {
            if ((bool) ($state["placed"] ?? false)) {
                continue;
            }
            if ((time() - (int) ($state["created_at"] ?? time())) < $deadlineSeconds) {
                continue;
            }
            self::autoPlaceGuardian($factionId);
        }
    }

    private static function autoPlaceGuardian(string $factionId): bool
    {
        $faction = Loader::getFactionManager()->getFactionById($factionId);
        if ($faction === null) {
            unset(self::$states[$factionId]);
            return false;
        }

        $claim = self::selectAutoPlacementClaim($factionId);
        if ($claim === null) {
            return false;
        }

        $world = Server::getInstance()->getWorldManager()->getWorldByName($claim->getWorldName());
        if (!$world instanceof World) {
            return false;
        }

        $x = ($claim->getChunkX() << 4) + 8;
        $z = ($claim->getChunkZ() << 4) + 8;
        $y = $world->getHighestBlockAt($x, $z) + 1;
        $pos = self::findOpenSpotNear($world, $x, $y, $z) ?? new Position($x + 0.5, $y, $z + 0.5, $world);
        self::placeGuardian($factionId, $pos, true);

        Loader::getInstance()->getServer()->broadcastMessage(C::colorize(
            "&r&b&lChunk Guardian &8| &r&7" . $faction->getName() . "'s guardian was automatically placed."
        ));
        return true;
    }

    private static function selectAutoPlacementClaim(string $factionId): ?\ecstsy\Glacia\server\factions\claims\FactionClaim
    {
        $claims = ClaimManager::getInstance()->getClaimsForFaction($factionId);
        if ($claims === []) {
            return null;
        }

        $best = $claims[0];
        $bestScore = -1;
        foreach ($claims as $claim) {
            $score = 0;
            foreach (Server::getInstance()->getOnlinePlayers() as $player) {
                $session = Loader::getPlayerManager()->getSession($player);
                if ($session === null || $session->getFactionId() !== $factionId) {
                    continue;
                }
                $pos = $player->getPosition();
                if ($pos->getWorld()->getFolderName() === $claim->getWorldName() && ($pos->getFloorX() >> 4) === $claim->getChunkX() && ($pos->getFloorZ() >> 4) === $claim->getChunkZ()) {
                    ++$score;
                }
            }
            if ($score > $bestScore || ($score === $bestScore && $claim->isCore())) {
                $best = $claim;
                $bestScore = $score;
            }
        }

        return $best;
    }

    private static function placeGuardian(string $factionId, Position $pos, bool $automatic): void
    {
        self::$states[$factionId]["placed"] = true;
        self::$states[$factionId]["placed_once"] = true;
        self::$states[$factionId]["auto_placed"] = $automatic;
        self::$states[$factionId]["world"] = $pos->getWorld()->getFolderName();
        self::$states[$factionId]["x"] = $pos->x;
        self::$states[$factionId]["y"] = $pos->y;
        self::$states[$factionId]["z"] = $pos->z;
        self::$states[$factionId]["placed_at"] = time();
        self::save();
        self::despawnEntity($factionId);
    }

    private static function findOpenSpotNear(World $world, int $x, int $y, int $z): ?Position
    {
        $candidates = [[0, 0], [1, 0], [-1, 0], [0, 1], [0, -1], [1, 1], [-1, -1], [1, -1], [-1, 1]];
        foreach ($candidates as [$dx, $dz]) {
            $cx = $x + $dx;
            $cz = $z + $dz;
            $cy = max(1, min(255, $y));
            for ($tries = 0; $tries < 12; ++$tries, ++$cy) {
                if (!$world->getBlockAt($cx, $cy, $cz)->isSolid() && !$world->getBlockAt($cx, $cy + 1, $cz)->isSolid()) {
                    return new Position($cx + 0.5, $cy, $cz + 0.5, $world);
                }
            }
        }

        return null;
    }

    private static function spawnNearbyGuardians(): void
    {
        $spawnDistanceSq = self::getSpawnDistance() ** 2;
        foreach (self::$states as $factionId => $state) {
            if (!(bool) ($state["placed"] ?? false)) {
                self::despawnEntity($factionId);
                continue;
            }
            $entity = self::$activeEntities[$factionId] ?? null;
            if ($entity instanceof FactionGuardianEntity && !$entity->isClosed()) {
                continue;
            }
            unset(self::$activeEntities[$factionId]);

            $worldName = (string) ($state["world"] ?? "");
            $world = $worldName !== "" ? Server::getInstance()->getWorldManager()->getWorldByName($worldName) : null;
            if (!$world instanceof World) {
                continue;
            }

            $anchor = new Position((float) ($state["x"] ?? 0.0), (float) ($state["y"] ?? 0.0), (float) ($state["z"] ?? 0.0), $world);
            $near = false;
            $near = self::hasNearbyEligibleViewer($anchor, $factionId, self::getSpawnDistance());
            if (!$near) {
                self::despawnEntity($factionId);
                continue;
            }

            $location = new Location($anchor->x, $anchor->y + self::getOrbitHeight(), $anchor->z, $world, 0.0, 0.0);
            $guardian = new FactionGuardianEntity($location, CompoundTag::create());
            $guardian->setGuardianData($factionId, $anchor->x, $anchor->y, $anchor->z);
            $guardian->spawnToAll();
            self::$activeEntities[$factionId] = $guardian;
        }
    }

    private static function despawnEntity(string $factionId): void
    {
        $entity = self::$activeEntities[$factionId] ?? null;
        if ($entity instanceof FactionGuardianEntity && !$entity->isClosed()) {
            $entity->flagForDespawn();
        }
        unset(self::$activeEntities[$factionId]);
    }

    private static function hasNearbyEligibleViewer(Position $position, string $factionId, float $range): bool
    {
        $maxDistanceSq = $range ** 2;
        foreach ($position->getWorld()->getPlayers() as $player) {
            if (!self::shouldDisplayTo($player, $factionId)) {
                continue;
            }

            if ($player->getPosition()->distanceSquared($position) <= $maxDistanceSq) {
                return true;
            }
        }

        return false;
    }

    private static function giveEggToLeader(Player $player, Faction $faction): void
    {
        if ($faction->getLeaderUuid() !== $player->getUniqueId()->toString()) {
            return;
        }
        if (self::hasPlacedGuardian($faction->getFactionId()) || self::playerHasGuardianEgg($player, $faction->getFactionId())) {
            return;
        }

        $leftovers = $player->getInventory()->addItem(self::createEgg($faction));
        if ($leftovers !== []) {
            $player->sendMessage(C::colorize("&r&cYour inventory is full. Clear a slot to receive your Chunk Guardian egg."));
            return;
        }

        $player->sendMessage(C::colorize("&r&bYou received your faction's Chunk Guardian egg. &7Keep it in your inventory and place it in your claim."));
    }

    private static function playerHasGuardianEgg(Player $player, string $factionId): bool
    {
        foreach ([$player->getInventory(), $player->getCursorInventory(), $player->getOffHandInventory()] as $inventory) {
            foreach ($inventory->getContents() as $item) {
                if (self::getEggFactionId($item) === $factionId) {
                    return true;
                }
            }
        }
        return false;
    }

    private static function canManuallyPlace(string $factionId): bool
    {
        if (Loader::getGraceInstance()->isGraceActive()) {
            return true;
        }

        $state = self::$states[$factionId] ?? [];
        if ((bool) ($state["created_during_grace"] ?? false)) {
            return true;
        }

        return !(bool) ($state["placed_once"] ?? false);
    }

    private static function consumeHeldEgg(Player $player, Item $item): void
    {
        if ($player->getGamemode() === GameMode::CREATIVE()) {
            return;
        }
        if ($item->getCount() <= 1) {
            $player->getInventory()->setItemInHand(VanillaItems::AIR());
            return;
        }
        $item->setCount($item->getCount() - 1);
        $player->getInventory()->setItemInHand($item);
    }

    private static function isValidEggHolder(Player $player, Item $item): bool
    {
        $factionId = self::getEggFactionId($item);
        if ($factionId === null) {
            return false;
        }

        $faction = Loader::getFactionManager()->getFactionById($factionId);
        if ($faction === null || $faction->getLeaderUuid() !== $player->getUniqueId()->toString()) {
            return false;
        }

        $session = Loader::getPlayerManager()->getSession($player);
        return $session !== null && $session->getFactionId() === $factionId && !self::hasPlacedGuardian($factionId);
    }

    private static function cleanAllOnlineGuardianEggs(): void
    {
        foreach (Server::getInstance()->getOnlinePlayers() as $player) {
            self::cleanPlayerGuardianEggs($player);
        }
    }

    private static function removeOnlineEggsForFaction(string $factionId): void
    {
        foreach (Server::getInstance()->getOnlinePlayers() as $player) {
            foreach ([$player->getInventory(), $player->getCursorInventory(), $player->getOffHandInventory(), $player->getArmorInventory(), $player->getEnderInventory()] as $inventory) {
                for ($slot = 0; $slot < $inventory->getSize(); ++$slot) {
                    if (self::getEggFactionId($inventory->getItem($slot)) === $factionId) {
                        $inventory->setItem($slot, VanillaItems::AIR());
                    }
                }
            }
        }
    }

    private static function ensureLoadedFactionStates(): void
    {
        foreach (Loader::getFactionManager()->getAllFactions() as $faction) {
            self::ensureState($faction->getFactionId(), time(), Loader::getGraceInstance()->isGraceActive());
        }
    }

    private static function ensureState(string $factionId, int $createdAt, bool $createdDuringGrace): void
    {
        if (isset(self::$states[$factionId])) {
            return;
        }

        self::$states[$factionId] = [
            "created_at" => $createdAt,
            "created_during_grace" => $createdDuringGrace,
            "placed" => false,
            "placed_once" => false,
            "auto_placed" => false,
            "world" => "",
            "x" => 0.0,
            "y" => 0.0,
            "z" => 0.0,
            "placed_at" => 0,
        ];
    }

    private static function getSpawnDistance(): float
    {
        return max(16.0, (float) (self::$settings["spawn-distance"] ?? 96.0));
    }

    private static function getDespawnDistance(): float
    {
        return max(self::getSpawnDistance(), (float) (self::$settings["despawn-distance"] ?? 112.0));
    }

    private static function loadSettings(): void
    {
        $misc = GeneralUtils::getConfiguration(Loader::getInstance(), "misc.yml");
        $settings = (array) ($misc?->get("faction-guardian", []) ?? []);
        self::$settings = [
            "require-for-ftop" => (bool) ($settings["require-for-ftop"] ?? true),
            "place-deadline-seconds" => (int) ($settings["place-deadline-seconds"] ?? 86400),
            "spawn-distance" => (float) ($settings["spawn-distance"] ?? 96.0),
            "despawn-distance" => (float) ($settings["despawn-distance"] ?? 112.0),
            "breach-radius-chunks" => (int) ($settings["breach-radius-chunks"] ?? 0),
            "entity" => (array) ($settings["entity"] ?? []),
        ];
    }

    private static function load(): void
    {
        if (self::$dataPath === "") {
            return;
        }

        $decoded = StorageUtils::readJsonFile(self::$dataPath, "faction guardians");
        if (!is_array($decoded)) {
            return;
        }

        foreach ($decoded as $factionId => $state) {
            if (!is_string($factionId) || !is_array($state)) {
                continue;
            }
            self::$states[$factionId] = [
                "created_at" => max(0, (int) ($state["created_at"] ?? time())),
                "created_during_grace" => (bool) ($state["created_during_grace"] ?? false),
                "placed" => (bool) ($state["placed"] ?? false),
                "placed_once" => (bool) ($state["placed_once"] ?? false),
                "auto_placed" => (bool) ($state["auto_placed"] ?? false),
                "world" => (string) ($state["world"] ?? ""),
                "x" => (float) ($state["x"] ?? 0.0),
                "y" => (float) ($state["y"] ?? 0.0),
                "z" => (float) ($state["z"] ?? 0.0),
                "placed_at" => max(0, (int) ($state["placed_at"] ?? 0)),
            ];
        }
    }
}
