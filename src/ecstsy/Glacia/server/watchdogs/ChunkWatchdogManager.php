<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\watchdogs;

use ecstsy\Glacia\entity\watchdogs\ChunkAllayWatchdogEntity;
use ecstsy\Glacia\entity\watchdogs\ChunkFoxWatchdogEntity;
use ecstsy\Glacia\entity\watchdogs\ChunkGoatWatchdogEntity;
use ecstsy\Glacia\entity\watchdogs\ChunkLlamaWatchdogEntity;
use ecstsy\Glacia\entity\watchdogs\ChunkNautilusWatchdogEntity;
use ecstsy\Glacia\entity\watchdogs\ChunkPillagerWatchdogEntity;
use ecstsy\Glacia\entity\watchdogs\ChunkRavengerWatchdogEntity;
use ecstsy\Glacia\entity\watchdogs\ChunkWatchdogEntity;
use ecstsy\Glacia\entity\watchdogs\FactionEndermiteWatchdogEntity;
use ecstsy\Glacia\entity\watchdogs\FactionPandaWatchdogEntity;
use ecstsy\Glacia\entity\watchdogs\FactionPhantomWatchdogEntity;
use ecstsy\Glacia\entity\watchdogs\FactionWitchWatchdogEntity;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\enchantments\manager\EnchantEffectManager;
use ecstsy\Glacia\server\factions\claims\ClaimManager;
use ecstsy\Glacia\server\factions\relations\RelationManager;
use ecstsy\Glacia\server\items\GlaciaItemFactory;
use ecstsy\Glacia\utils\StorageUtils;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\Location;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\item\VanillaItems;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\TextFormat as C;
use pocketmine\world\Position;
use pocketmine\world\World;
use Ramsey\Uuid\Uuid;
use Vecnavium\FormsUI\SimpleForm;

final class ChunkWatchdogManager
{
    use SingletonTrait;

    public const TYPE_RAVENGER = "ravenger";
    public const TYPE_ALLAY = "allay";
    public const TYPE_NAUTILUS = "nautilus";
    public const TYPE_FOX = "fox";
    public const TYPE_GOAT = "goat";
    public const TYPE_PILLAGER = "pillager";
    public const TYPE_LLAMA = "llama";
    public const TYPE_PANDA = "panda";
    public const TYPE_PHANTOM = "phantom";
    public const TYPE_WITCH = "witch";
    public const TYPE_ENDERMITE = "endermite";

    private const SELL_BONUS_TAG = "watchdog_sell_bonus";
    private const SCOPE_CHUNK = "chunk";
    private const SCOPE_FACTION = "faction";
    private const ENDERMITE_SUPPRESSION_SOURCE = "faction_watchdog_endermite";
    private const DRUNK_DEBUFF_IDS = ["slowness", "mining_fatigue"];

    /** @var array<string, array<string, mixed>> */
    private static array $entries = [];
    /** @var array<string, int> */
    private static array $entityIdsByEntry = [];
    private static string $dataPath = "";

    private const DEFINITIONS = [
        self::TYPE_RAVENGER => [
            "item_id" => "chunk_watchdog_ravenger",
            "display" => "&fWatchdog: &cChunk Ravenger",
            "entity" => ChunkRavengerWatchdogEntity::class,
            "scope" => self::SCOPE_CHUNK,
            "default_seconds" => 604800,
            "break_cost" => 10000000,
            "grace_seconds" => 1800,
            "per_chunk" => 1,
        ],
        self::TYPE_ALLAY => [
            "item_id" => "chunk_watchdog_allay",
            "display" => "&fWatchdog: &bChunk Allay",
            "entity" => ChunkAllayWatchdogEntity::class,
            "scope" => self::SCOPE_CHUNK,
            "default_seconds" => 259200,
            "break_cost" => 10000000,
            "grace_seconds" => 0,
            "per_chunk" => 1,
        ],
        self::TYPE_NAUTILUS => [
            "item_id" => "chunk_watchdog_nautilus",
            "display" => "&fWatchdog: &3Chunk Nautilus",
            "entity" => ChunkNautilusWatchdogEntity::class,
            "scope" => self::SCOPE_CHUNK,
            "default_seconds" => 259200,
            "break_cost" => 10000000,
            "grace_seconds" => 0,
            "per_chunk" => 1,
        ],
        self::TYPE_FOX => [
            "item_id" => "chunk_watchdog_fox",
            "display" => "&fWatchdog: &6Chunk Fox",
            "entity" => ChunkFoxWatchdogEntity::class,
            "scope" => self::SCOPE_CHUNK,
            "default_seconds" => 172800,
            "break_cost" => 25000000,
            "grace_seconds" => 0,
            "per_chunk" => 1,
        ],
        self::TYPE_GOAT => [
            "item_id" => "chunk_watchdog_goat",
            "display" => "&fWatchdog: &7Chunk Goat",
            "entity" => ChunkGoatWatchdogEntity::class,
            "scope" => self::SCOPE_CHUNK,
            "default_seconds" => 172800,
            "break_cost" => 25000000,
            "grace_seconds" => 0,
            "per_chunk" => 1,
        ],
        self::TYPE_PILLAGER => [
            "item_id" => "chunk_watchdog_pillager",
            "display" => "&fWatchman: &8Chunk Pillager",
            "entity" => ChunkPillagerWatchdogEntity::class,
            "scope" => self::SCOPE_CHUNK,
            "default_seconds" => 604800,
            "break_cost" => 1000000,
            "grace_seconds" => 0,
            "per_chunk" => 1,
        ],
        self::TYPE_LLAMA => [
            "item_id" => "chunk_watchdog_llama",
            "display" => "&fWatchman: &dChunk Llama",
            "entity" => ChunkLlamaWatchdogEntity::class,
            "scope" => self::SCOPE_CHUNK,
            "default_seconds" => 172800,
            "break_cost" => 2500000,
            "grace_seconds" => 0,
            "per_chunk" => 2,
        ],
        self::TYPE_PANDA => [
            "item_id" => "faction_watchdog_panda",
            "display" => "&fWatchdog: &aFaction Panda",
            "entity" => FactionPandaWatchdogEntity::class,
            "scope" => self::SCOPE_FACTION,
            "default_seconds" => 432000,
            "break_cost" => 20000000,
            "grace_seconds" => 0,
            "per_faction" => 1,
        ],
        self::TYPE_PHANTOM => [
            "item_id" => "faction_watchdog_phantom",
            "display" => "&fWatchdog: &5Faction Phantom",
            "entity" => FactionPhantomWatchdogEntity::class,
            "scope" => self::SCOPE_FACTION,
            "default_seconds" => 604800,
            "break_cost" => 20000000,
            "grace_seconds" => 0,
            "per_faction" => 1,
        ],
        self::TYPE_WITCH => [
            "item_id" => "faction_watchdog_witch",
            "display" => "&fWatchdog: &2Faction Witch",
            "entity" => FactionWitchWatchdogEntity::class,
            "scope" => self::SCOPE_FACTION,
            "default_seconds" => 172800,
            "break_cost" => 25000000,
            "grace_seconds" => 0,
            "per_faction" => 2,
        ],
        self::TYPE_ENDERMITE => [
            "item_id" => "faction_watchdog_endermite",
            "display" => "&fWatchman: &5Faction Endermite",
            "entity" => FactionEndermiteWatchdogEntity::class,
            "scope" => self::SCOPE_FACTION,
            "default_seconds" => 259200,
            "break_cost" => 10000000,
            "grace_seconds" => 0,
            "per_faction" => 1,
        ],
    ];

    public function __construct()
    {
        self::setInstance($this);
    }

    public static function init(Loader $plugin): void
    {
        self::$dataPath = $plugin->getDataFolder() . "chunk_watchdogs.json";
        self::load();
        $plugin->getScheduler()->scheduleRepeatingTask(new ClosureTask(static function(): void {
            self::tick();
        }), 20);
    }

    public static function save(): void
    {
        if (self::$dataPath !== "") {
            StorageUtils::writeJsonFile(self::$dataPath, self::$entries, "chunk watchdogs");
        }
    }

    public static function load(): void
    {
        self::$entries = [];
        $decoded = StorageUtils::readJsonFile(self::$dataPath, "chunk watchdogs");
        if (!is_array($decoded)) {
            return;
        }
        foreach ($decoded as $id => $entry) {
            if (!is_string($id) || !is_array($entry)) {
                continue;
            }
            $type = strtolower((string) ($entry["type"] ?? ""));
            if (!isset(self::DEFINITIONS[$type])) {
                continue;
            }
            self::$entries[$id] = $entry;
        }
    }

    public static function isWatchdogItemId(string $glaciaItemId): bool
    {
        foreach (self::DEFINITIONS as $definition) {
            if (($definition["item_id"] ?? "") === $glaciaItemId) {
                return true;
            }
        }
        return false;
    }

    public static function getTypeFromItemId(string $glaciaItemId): ?string
    {
        foreach (self::DEFINITIONS as $type => $definition) {
            if (($definition["item_id"] ?? "") === $glaciaItemId) {
                return $type;
            }
        }
        return null;
    }

    public static function place(Player $player, Position $target, string $type): bool
    {
        $definition = self::DEFINITIONS[$type] ?? null;
        if ($definition === null) {
            return false;
        }

        $session = Loader::getPlayerManager()->getSession($player);
        $faction = $session?->getFaction();
        if ($faction === null) {
            $player->sendMessage(C::colorize("&r&cYou must be in a faction to place a watchdog."));
            return false;
        }

        $claim = ClaimManager::getInstance()->getClaimAtPosition($target);
        if ($claim === null || $claim->getFactionId() !== $faction->getFactionId()) {
            $player->sendMessage(C::colorize("&r&cYou can only place watchdogs in your own claim."));
            return false;
        }

        $scope = (string) ($definition["scope"] ?? self::SCOPE_CHUNK);
        if ($scope === self::SCOPE_CHUNK) {
            if (self::countInChunk($target, $type) >= (int) ($definition["per_chunk"] ?? 1)) {
                $player->sendMessage(C::colorize("&r&cThat chunk already has the max number of this watchdog."));
                return false;
            }
        } else {
            if (self::countForFaction($faction->getFactionId(), $type) >= (int) ($definition["per_faction"] ?? 1)) {
                $player->sendMessage(C::colorize("&r&cYour faction already has the max number of this watchdog."));
                return false;
            }
        }

        $spawn = self::findSpawnPosition($target);
        $id = Uuid::uuid4()->toString();
        self::$entries[$id] = [
            "id" => $id,
            "type" => $type,
            "owner_faction_id" => $faction->getFactionId(),
            "owner_name" => $player->getName(),
            "world" => $target->getWorld()->getFolderName(),
            "chunk_x" => $target->getFloorX() >> 4,
            "chunk_z" => $target->getFloorZ() >> 4,
            "x" => $spawn->x,
            "y" => $spawn->y,
            "z" => $spawn->z,
            "placed_at" => time(),
            "expires_at" => time() + (int) ($definition["default_seconds"] ?? 0),
        ];
        self::save();
        self::spawnEntity($id);
        $player->sendMessage(C::colorize("&r&aPlaced &f" . ($definition["display"] ?? "Chunk Watchdog") . "&a."));
        return true;
    }

    public static function openManageForm(Player $player, string $watchdogId): void
    {
        $entry = self::$entries[$watchdogId] ?? null;
        if ($entry === null) {
            $player->sendMessage(C::colorize("&r&cThat watchdog is no longer active."));
            return;
        }

        $definition = self::DEFINITIONS[strtolower((string) $entry["type"])] ?? null;
        if ($definition === null) {
            return;
        }

        $placedAt = (int) ($entry["placed_at"] ?? time());
        $breakCost = self::getBreakCost($entry);
        $freePickup = $breakCost <= 0;
        $remaining = max(0, (int) ($entry["expires_at"] ?? time()) - time());

        $hasSupplements = self::playerHasSupplements($player);
        $supplementIndex = $hasSupplements ? 1 : -1;
        $cancelIndex = $hasSupplements ? 2 : 1;
        $scopeLabel = ((string) ($definition["scope"] ?? self::SCOPE_CHUNK)) === self::SCOPE_FACTION ? "Faction" : "Chunk";

        $form = new SimpleForm(function(Player $submitter, ?int $data) use ($watchdogId, $breakCost, $supplementIndex, $cancelIndex): void {
            if ($data === null || $data === $cancelIndex) {
                return;
            }
            if ($data === 0) {
                self::removeWithCost($submitter, $watchdogId, $breakCost);
                return;
            }
            if ($data === $supplementIndex) {
                self::applySupplementsFromInventory($submitter, $watchdogId);
            }
        });
        $form->setTitle(C::colorize("&r&l&8Watchdog Manager"));
        $form->setContent(C::colorize(
            "&r&fType: &7" . ($definition["display"] ?? "Unknown") .
            "\n&r&fScope: &7" . $scopeLabel .
            "\n&r&fChunk: &7" . $entry["chunk_x"] . ", " . $entry["chunk_z"] .
            "\n&r&fFaction: &7" . ((string) ($entry["owner_faction_id"] ?? "") === "" ? "None" : (string) ($entry["owner_faction_id"] ?? "None")) .
            "\n&r&fOwner: &7" . (string) ($entry["owner_name"] ?? "Unknown") .
            "\n&r&fExpires In: &7" . self::formatSeconds($remaining) .
            "\n&r&fBreak Cost: &7" . ($freePickup ? "Free" : "$" . number_format($breakCost))
        ));
        $form->addButton(C::colorize("&r&aRemove Watchdog"));
        if ($hasSupplements) {
            $form->addButton(C::colorize("&r&bApply Watchman Supplement"));
        }
        $form->addButton(C::colorize("&r&8Cancel"));
        $player->sendForm($form);
    }

    public static function removeWithCost(Player $player, string $watchdogId, int $breakCost): bool
    {
        $entry = self::$entries[$watchdogId] ?? null;
        if ($entry === null) {
            $player->sendMessage(C::colorize("&r&cThat watchdog is no longer active."));
            return false;
        }

        $session = Loader::getPlayerManager()->getSession($player);
        if ($breakCost > 0 && ($session === null || $session->getBalance() < $breakCost)) {
            $player->sendMessage(C::colorize("&r&cYou need &f$" . number_format($breakCost) . " &cto remove this watchdog."));
            return false;
        }

        if ($breakCost > 0) {
            $session?->removeBalance($breakCost);
        }

        $definition = self::DEFINITIONS[strtolower((string) $entry["type"])] ?? null;
        unset(self::$entries[$watchdogId]);
        self::despawnEntity($watchdogId);
        self::save();

        if ($definition !== null) {
            foreach ($player->getInventory()->addItem(GlaciaItemFactory::create((string) $definition["item_id"])) as $leftover) {
                $player->getWorld()->dropItem($player->getPosition(), $leftover);
            }
        }

        $player->sendMessage(C::colorize("&r&aWatchdog removed successfully."));
        return true;
    }

    public static function hasRavengerBypass(Position $position): bool
    {
        return self::hasTypeInChunk($position, self::TYPE_RAVENGER);
    }

    public static function getSpawnerSpawnMultiplier(Position $position): int
    {
        return self::hasTypeInChunk($position, self::TYPE_ALLAY) ? 2 : 1;
    }

    public static function getSpawnerMaxStackBonus(Position $position): int
    {
        return self::hasTypeInChunk($position, self::TYPE_ALLAY) ? 5 : 0;
    }

    public static function getCurrentChunkSellMultiplier(Player $player): float
    {
        return self::hasTypeInChunk($player->getPosition(), self::TYPE_RAVENGER) ? 0.7 : 1.0;
    }

    public static function getPositionSellMultiplier(Position $position): float
    {
        return self::hasTypeInChunk($position, self::TYPE_RAVENGER) ? 0.7 : 1.0;
    }

    public static function getLlamaUpgradeChance(Position $position): int
    {
        return min(100, self::countInChunk($position, self::TYPE_LLAMA) * 2);
    }

    public static function shouldGrantExtraDailyQuest(Player $player): bool
    {
        return self::countForPlayerFaction($player, self::TYPE_PANDA) > 0 && mt_rand(1, 100) <= 20;
    }

    public static function shouldPreserveExclusiveUse(Player $player): bool
    {
        return self::countForPlayerFaction($player, self::TYPE_PHANTOM) > 0 && mt_rand(1, 100) <= 50;
    }

    public static function getFactionWitchSlotbotPrice(Player $player, int $basePrice): int
    {
        if (self::countForPlayerFaction($player, self::TYPE_WITCH) <= 0) {
            return max(1, $basePrice);
        }

        return max(1, (int) ceil($basePrice * 0.95));
    }

    public static function getFactionPotionHealMultiplier(Player $player): float
    {
        if (self::countForPlayerFaction($player, self::TYPE_WITCH) <= 0) {
            return 1.0;
        }

        return mt_rand(1, 100) <= 15 ? 1.5 : 1.0;
    }

    public static function shouldPreserveCcOpen(Player $player): bool
    {
        return self::hasTypeInChunk($player->getPosition(), self::TYPE_NAUTILUS) && mt_rand(1, 100) <= 5;
    }

    public static function setDropSellBonus(\pocketmine\item\Item $item, float $multiplier): \pocketmine\item\Item
    {
        $root = $item->getNamedTag();
        $glacia = $root->getCompoundTag("Glacia") ?? new \pocketmine\nbt\tag\CompoundTag();
        $glacia->setFloat(self::SELL_BONUS_TAG, max(1.0, $multiplier));
        $root->setTag("Glacia", $glacia);
        $item->setNamedTag($root);
        return $item;
    }

    public static function getDropSellBonus(\pocketmine\item\Item $item): float
    {
        $glacia = $item->getNamedTag()->getCompoundTag("Glacia");
        if ($glacia === null) {
            return 1.0;
        }
        return max(1.0, (float) $glacia->getFloat(self::SELL_BONUS_TAG, 1.0));
    }

    public static function shouldApplyPillagerWeakness(Player $player): bool
    {
        foreach (self::getEntriesInChunk($player->getPosition(), self::TYPE_PILLAGER) as $entry) {
            $ownerFactionId = (string) ($entry["owner_faction_id"] ?? "");
            $viewerFactionId = Loader::getPlayerManager()->getSession($player)?->getFactionId() ?? "";
            if ($viewerFactionId !== "" && $viewerFactionId === $ownerFactionId) {
                continue;
            }
            if ($viewerFactionId !== "" && $ownerFactionId !== "" && RelationManager::getInstance()->getRelation($viewerFactionId, $ownerFactionId) === "ally") {
                continue;
            }
            return true;
        }
        return false;
    }

    public static function hasNautilusSpeed(Player $player): bool
    {
        return self::hasTypeInChunk($player->getPosition(), self::TYPE_NAUTILUS);
    }

    public static function tick(): void
    {
        $now = time();
        $dirty = false;
        foreach (array_keys(self::$entries) as $id) {
            $entry = self::$entries[$id] ?? null;
            if ($entry === null) {
                continue;
            }
            if ((int) ($entry["expires_at"] ?? 0) <= $now) {
                unset(self::$entries[$id]);
                self::despawnEntity($id);
                $dirty = true;
                continue;
            }
            if (($entry["type"] ?? "") === self::TYPE_RAVENGER) {
                $world = Loader::getInstance()->getServer()->getWorldManager()->getWorldByName((string) $entry["world"]);
                if ($world instanceof World) {
                    $world->loadChunk((int) $entry["chunk_x"], (int) $entry["chunk_z"]);
                }
            }
            self::spawnEntity($id);
        }

        foreach (Loader::getInstance()->getServer()->getOnlinePlayers() as $player) {
            self::applyWatchdogEffects($player);
        }
        if ($dirty) {
            self::save();
        }
    }

    private static function applyWatchdogEffects(Player $player): void
    {
        if (self::hasNautilusSpeed($player)) {
            $player->getEffects()->add(new EffectInstance(VanillaEffects::SPEED(), 40, 3, false));
        }
        if (self::shouldApplyPillagerWeakness($player)) {
            $player->getEffects()->add(new EffectInstance(VanillaEffects::WEAKNESS(), 40, 0, false));
        }
        if (self::countForPlayerFaction($player, self::TYPE_WITCH) > 0) {
            $player->getEffects()->add(new EffectInstance(VanillaEffects::HASTE(), 40, 1, false));
        }
        if (self::countForPlayerFaction($player, self::TYPE_ENDERMITE) > 0) {
            EnchantEffectManager::setExternalArmorDebuffSuppression($player, self::ENDERMITE_SUPPRESSION_SOURCE, self::DRUNK_DEBUFF_IDS);
        } else {
            EnchantEffectManager::clearExternalArmorDebuffSuppression($player, self::ENDERMITE_SUPPRESSION_SOURCE);
        }
    }

    private static function spawnEntity(string $watchdogId): void
    {
        $entry = self::$entries[$watchdogId] ?? null;
        if ($entry === null) {
            return;
        }

        $world = Loader::getInstance()->getServer()->getWorldManager()->getWorldByName((string) $entry["world"]);
        if (!$world instanceof World || !$world->isLoaded()) {
            return;
        }

        $chunkX = (int) ($entry["chunk_x"] ?? 0);
        $chunkZ = (int) ($entry["chunk_z"] ?? 0);
        if (!$world->isChunkLoaded($chunkX, $chunkZ)) {
            return;
        }

        $entityId = self::$entityIdsByEntry[$watchdogId] ?? null;
        if ($entityId !== null) {
            $entity = $world->getEntity($entityId);
            if ($entity instanceof ChunkWatchdogEntity && !$entity->isClosed()) {
                return;
            }
        }

        $entityClass = self::DEFINITIONS[(string) $entry["type"]]["entity"] ?? null;
        if (!is_string($entityClass) || !class_exists($entityClass)) {
            return;
        }

        $entity = new $entityClass(new Location(
            (float) $entry["x"],
            (float) $entry["y"],
            (float) $entry["z"],
            $world,
            0.0,
            0.0
        ), new CompoundTag());
        if ($entity instanceof ChunkWatchdogEntity) {
            $entity->setWatchdogData($watchdogId, (string) $entry["type"], (int) $entry["expires_at"]);
            $entity->spawnToAll();
            self::$entityIdsByEntry[$watchdogId] = $entity->getId();
        }
    }

    private static function despawnEntity(string $watchdogId): void
    {
        $entityId = self::$entityIdsByEntry[$watchdogId] ?? null;
        unset(self::$entityIdsByEntry[$watchdogId]);
        if ($entityId === null) {
            return;
        }

        foreach (Loader::getInstance()->getServer()->getWorldManager()->getWorlds() as $world) {
            $entity = $world->getEntity($entityId);
            if ($entity instanceof ChunkWatchdogEntity && !$entity->isClosed()) {
                $entity->flagForDespawn();
            }
        }
    }

    private static function hasTypeInChunk(Position $position, string $type): bool
    {
        return self::countInChunk($position, $type) > 0;
    }

    public static function countInChunk(Position $position, string $type): int
    {
        return count(self::getEntriesInChunk($position, $type));
    }

    public static function countForFaction(string $factionId, string $type): int
    {
        if ($factionId === "") {
            return 0;
        }

        $count = 0;
        foreach (self::$entries as $entry) {
            if (
                (string) ($entry["type"] ?? "") === $type &&
                (string) ($entry["owner_faction_id"] ?? "") === $factionId
            ) {
                ++$count;
            }
        }

        return $count;
    }

    public static function playerHasSupplements(Player $player): bool
    {
        foreach ($player->getInventory()->getContents() as $item) {
            if (self::getSupplementDays($item) > 0) {
                return true;
            }
        }

        return false;
    }

    public static function applySupplementsFromInventory(Player $player, string $watchdogId): bool
    {
        $entry = self::$entries[$watchdogId] ?? null;
        if ($entry === null) {
            $player->sendMessage(C::colorize("&r&cThat watchdog is no longer active."));
            return false;
        }

        $inventory = $player->getInventory();
        $totalDays = 0;
        foreach ($inventory->getContents() as $slot => $item) {
            $daysPerItem = self::getSupplementDays($item);
            if ($daysPerItem <= 0) {
                continue;
            }

            $totalDays += $daysPerItem * $item->getCount();
            $inventory->setItem($slot, VanillaItems::AIR());
        }

        if ($totalDays <= 0) {
            $player->sendMessage(C::colorize("&r&cYou do not have any Watchman Supplements."));
            return false;
        }

        self::$entries[$watchdogId]["expires_at"] = (int) (self::$entries[$watchdogId]["expires_at"] ?? time()) + ($totalDays * 86400);
        self::save();
        self::refreshEntity($watchdogId);
        $player->sendMessage(C::colorize("&r&aAdded &f{$totalDays} day(s) &ato that watchdog."));
        return true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function getEntriesInChunk(Position $position, string $type): array
    {
        $world = $position->getWorld()->getFolderName();
        $chunkX = $position->getFloorX() >> 4;
        $chunkZ = $position->getFloorZ() >> 4;
        $out = [];
        foreach (self::$entries as $entry) {
            if (
                (string) ($entry["type"] ?? "") === $type &&
                (string) ($entry["world"] ?? "") === $world &&
                (int) ($entry["chunk_x"] ?? PHP_INT_MIN) === $chunkX &&
                (int) ($entry["chunk_z"] ?? PHP_INT_MIN) === $chunkZ
            ) {
                $out[] = $entry;
            }
        }
        return $out;
    }

    private static function getBreakCost(array $entry): int
    {
        $definition = self::DEFINITIONS[strtolower((string) $entry["type"])] ?? null;
        if ($definition === null) {
            return 0;
        }
        $placedAt = (int) ($entry["placed_at"] ?? time());
        $graceSeconds = (int) ($definition["grace_seconds"] ?? 0);
        if ($graceSeconds > 0 && (time() - $placedAt) < $graceSeconds) {
            return 0;
        }
        return (int) ($definition["break_cost"] ?? 0);
    }

    private static function findSpawnPosition(Position $target): Vector3
    {
        return new Vector3($target->getFloorX() + 0.5, $target->getFloorY() + 1.0, $target->getFloorZ() + 0.5);
    }

    private static function countForPlayerFaction(Player $player, string $type): int
    {
        $factionId = Loader::getPlayerManager()->getSession($player)?->getFactionId() ?? "";
        return self::countForFaction($factionId, $type);
    }

    private static function getSupplementDays(\pocketmine\item\Item $item): int
    {
        $glacia = $item->getNamedTag()->getCompoundTag("Glacia");
        if ($glacia === null || $glacia->getString("glaciaItem", "") !== "watchman_supplement") {
            return 0;
        }

        return max(0, $glacia->getInt("days", 0));
    }

    private static function refreshEntity(string $watchdogId): void
    {
        $entityId = self::$entityIdsByEntry[$watchdogId] ?? null;
        if ($entityId === null) {
            self::spawnEntity($watchdogId);
            return;
        }

        $entry = self::$entries[$watchdogId] ?? null;
        if ($entry === null) {
            return;
        }

        foreach (Loader::getInstance()->getServer()->getWorldManager()->getWorlds() as $world) {
            $entity = $world->getEntity($entityId);
            if ($entity instanceof ChunkWatchdogEntity && !$entity->isClosed()) {
                $entity->setWatchdogData($watchdogId, (string) $entry["type"], (int) $entry["expires_at"]);
                return;
            }
        }

        self::spawnEntity($watchdogId);
    }

    private static function formatSeconds(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        if ($days > 0) {
            return "{$days}d {$hours}h";
        }
        if ($hours > 0) {
            return "{$hours}h {$minutes}m";
        }
        return "{$minutes}m";
    }
}
