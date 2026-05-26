<?php

declare(strict_types=1);

namespace ecstsy\Glacia\listeners;

use ecstsy\Glacia\entity\boss\bosses\AstralDevourer;
use ecstsy\Glacia\entity\boss\bosses\GlacialHydra;
use ecstsy\Glacia\entity\boss\bosses\TitanKnight;
use ecstsy\Glacia\entity\boss\BossEntity;
use ecstsy\Glacia\entity\gkits\ForsakenChampion;
use ecstsy\Glacia\entity\GlaciaExplosiveCreeper;
use ecstsy\Glacia\entity\RiftEntity;
use ecstsy\Glacia\entity\WatchmenGolem;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\managers\RiftManager;
use ecstsy\Glacia\server\customtnt\CustomTNTIds;
use ecstsy\Glacia\server\disguises\DisguiseManager;
use ecstsy\Glacia\server\droppackages\DropPackageRewardPool;
use ecstsy\Glacia\server\droppackages\DropPackageSession;
use ecstsy\Glacia\server\duels\DuelManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantments;
use ecstsy\Glacia\server\enchantments\Groups;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\enchantments\manager\AdvancedEnchantState;
use ecstsy\Glacia\server\enchantments\manager\EnchantCombatStateManager;
use ecstsy\Glacia\server\enchantments\manager\ExclusiveEnchantManager;
use ecstsy\Glacia\server\enchantments\manager\NetherPigmanGuard;
use ecstsy\Glacia\server\enchantments\manager\SoulGemSessionManager;
use ecstsy\Glacia\server\boosters\ZoneBoosterManager;
use ecstsy\Glacia\server\factions\FactionXpBoosterManager;
use ecstsy\Glacia\server\factions\contribution\FactionContributionManager;
use ecstsy\Glacia\server\fragments\FragmentManager;
use ecstsy\Glacia\server\blackmarket\BlackMarketAuctionManager;
use ecstsy\Glacia\server\enchantments\enchants\armor\ArmageddonEnchant;
use ecstsy\Glacia\server\enchantments\enchants\chestplate\WatchmenEnchant;
use ecstsy\Glacia\server\gkits\GKitRepository;
use ecstsy\Glacia\server\gkits\WarlordRepository;
use ecstsy\Glacia\server\homes\HomeManager;
use ecstsy\Glacia\server\items\GlaciaItemFactory;
use ecstsy\Glacia\server\items\GlaciaItemStackPolicy;
use ecstsy\Glacia\server\items\armor\abilities\YijkiLightningStrike;
use ecstsy\Glacia\server\items\armor\HeroicArmorDurabilityManager;
use ecstsy\Glacia\server\items\genbuckets\GenBucketManager;
use ecstsy\Glacia\server\items\pets\PetManager;
use ecstsy\Glacia\server\items\rings\RingManager;
use ecstsy\Glacia\server\items\runes\RuneManager;
use ecstsy\Glacia\server\items\trackers\TrackerManager;
use ecstsy\Glacia\server\zones\ZoneManager;
use ecstsy\Glacia\server\items\rename\ItemRenameManager;
use ecstsy\Glacia\server\factions\claims\ClaimManager;
use ecstsy\Glacia\server\lootboxes\CcProgressManager;
use ecstsy\Glacia\server\lootboxes\LootboxRepository;
use ecstsy\Glacia\server\etherealcrates\EtherealCrateRepository;
use ecstsy\Glacia\server\enchantments\essence\EnchantEssenceManager;
use ecstsy\Glacia\server\items\armor\ArmorSetManager;
use ecstsy\Glacia\server\regions\Region;
use ecstsy\Glacia\server\ranks\RankManager;
use ecstsy\Glacia\server\ranks\RankProgressionManager;
use ecstsy\Glacia\server\titles\TitleManager;
use ecstsy\Glacia\server\watchdogs\ChunkWatchdogManager;
use ecstsy\Glacia\tasks\ChunkBusterTask;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\Glacia\utils\NbtLenient;
use ecstsy\Glacia\utils\PermissionTierUtils;
use ecstsy\Glacia\utils\screens\lootboxes\LootboxAnimationScreen;
use ecstsy\Glacia\utils\screens\lootboxes\LootboxAnimationFactory;
use ecstsy\Glacia\utils\screens\etherealcrates\EtherealCrateAnimationScreen;
use ecstsy\Glacia\utils\screens\loot\OpenableLootPreviewScreen;
use ecstsy\Glacia\utils\screens\slotbot\SlotbotMenuScreen;
use ecstsy\Glacia\utils\screens\droppackages\DropPackageScreen;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianUtilities\utils\ItemUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\block\BlockTypeIds;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\Liquid;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\object\FireworkRocket;
use pocketmine\entity\projectile\Projectile;
use pocketmine\entity\projectile\Snowball;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityItemPickupEvent;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\event\entity\EntityTrampleFarmlandEvent;
use pocketmine\event\inventory\InventoryCloseEvent;
use pocketmine\event\inventory\InventoryOpenEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\Inventory;
use pocketmine\inventory\PlayerCursorInventory;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\Armor;
use pocketmine\item\Axe;
use pocketmine\item\Bow;
use pocketmine\item\Durable;
use pocketmine\item\FireworkRocketExplosion;
use pocketmine\item\FireworkRocketType;
use pocketmine\item\FishingRod;
use pocketmine\item\Hoe;
use pocketmine\item\Item;
use pocketmine\item\ItemUseResult;
use pocketmine\item\PotionType;
use pocketmine\item\Pickaxe;
use pocketmine\item\Shovel;
use pocketmine\item\SplashPotion as SplashPotionItem;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\StringToItemParser;
use pocketmine\item\Sword;
use pocketmine\item\Trident as TridentItem;
use pocketmine\item\VanillaItems;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\player\Player;
use pocketmine\player\GameMode;
use pocketmine\scheduler\CancelTaskException;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\TaskHandler;
use pocketmine\world\sound\BlockPlaceSound;
use pocketmine\world\sound\XpCollectSound;
use pocketmine\utils\TextFormat as C;
use pocketmine\world\particle\BlockBreakParticle;
use pocketmine\world\particle\HugeExplodeSeedParticle;
use pocketmine\world\particle\PortalParticle;
use pocketmine\world\Position;
use pocketmine\world\World;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\ItemTypeIds;
use pocketmine\math\AxisAlignedBB;
use pocketmine\Server;
use Ramsey\Uuid\Uuid;
use Vecnavium\FormsUI\SimpleForm;

final class ItemListener implements Listener {
    private const SYNTHETIC_ENCHANTING_LAPIS_TAG = "syntheticEnchantingLapis";
    /** @var string[] */
    private const CONJURATION_ORACLE_ALLOWED_ENCHANTS = [
        "beastbound",
        "blind",
        "deathbringer",
        "doublestrike",
        "execute",
        "greatsword",
        "hexlock",
        "lifesteal",
        "rage",
        "slingshot",
        "trickshot",
        "triggerhappy",
        "vampire",
    ];

    /** @var array<string, int> player UUID string => expiry epoch milliseconds */
    private static array $genBucketCooldowns = [];
    /** @var array<string, array{player:string,world:string,positions:array<string,bool>,handler:TaskHandler|null}> */
    private static array $activeGenBucketChains = [];
    /** @var array<string, array<string, true>> player uuid => active chain keys */
    private static array $activeGenBucketChainKeysByPlayer = [];
    /** @var array<string, string> */
    private static array $generatedGenBucketBlockIndex = [];
    /** @var array<string, array{entityId:int,world:string,spawnedAt:int}> */
    private static array $activeBosses = [];
    /** @var array<int, array{entityId:int,world:string,spawnedAt:int}> */
    private static array $activeWarlords = [];
    /** @var array<string, int> */
    private static array $bossPlayerCooldowns = [];
    private static int $bossGlobalCooldownUntil = 0;
    /** @var array<string, int> */
    private static array $warlordPlayerCooldowns = [];
    /** @var array<string, int> */
    private static array $relicCooldownSecondsCache = [];
    private static ?int $soulCompassDefaultUsesCache = null;
    /** @var array<string, list<string>> player UUID => placed block keys */
    private static array $lastZoneBarrierPlacements = [];
    /** @var array<string, int> player UUID => last zone barrier use epoch milliseconds */
    private static array $zoneBarrierBucketLastUseMs = [];
    private const ZONE_BARRIER_BUCKET_MAX_BLOCKS = 1024;
    private const ZONE_BARRIER_BUCKET_MAX_CONNECTED_MARKERS = 512;
    private const ZONE_BARRIER_BUCKET_DEBOUNCE_MS = 350;
    /** @var array<string, int> */
    private static array $pendingBathBombThrows = [];
    /** @var array<int, true> */
    private static array $bathBombProjectileIds = [];

    public function __construct() {
        Loader::getInstance()->getScheduler()->scheduleRepeatingTask(new ClosureTask(
            function(): void {
                $this->cleanupSpawnTracking();
            }
        ), 20 * 30);
    }

    public static function isBathBombProjectile(Projectile $projectile): bool
    {
        return isset(self::$bathBombProjectileIds[$projectile->getId()]);
    }

    public static function unregisterSpawnedEntity(\pocketmine\entity\Entity $entity): void
    {
        if ($entity instanceof BossEntity) {
            foreach (self::$activeBosses as $key => $entry) {
                if (($entry["entityId"] ?? -1) === $entity->getId()) {
                    unset(self::$activeBosses[$key]);
                    break;
                }
            }
        }

        if ($entity instanceof ForsakenChampion) {
            unset(self::$activeWarlords[$entity->getId()]);
        }
    }

    public function onBlockPlace(BlockPlaceEvent $event): void {
        $player = $event->getPlayer();
        $item = $event->getItem();
        $tag = $item->getNamedTag();

        $glaciaTag = $tag->getCompoundTag("Glacia");
        if ($glaciaTag === null) {
            return;
        }

        $id = $glaciaTag->getString("glaciaItem", "");

        if (CustomTNTIds::isCustomTNT($id)) {
            return;
        }

        if ($id === "chunk_buster") {
            $event->cancel();

            $session = Loader::getPlayerManager()->getSession($player);
            $faction = $session?->getFaction();
            if ($faction === null) {
                $player->sendMessage(C::colorize("&r&cYou must be in a faction to use a Chunk Buster."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $targetPos = null;
            foreach ($event->getTransaction()->getBlocks() as [$x, $y, $z, $block]) {
                $targetPos = new Position($x, $y, $z, $player->getWorld());
                break;
            }

            if ($targetPos === null) {
                $player->sendMessage(C::colorize("&r&cFailed to place the Chunk Buster."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $claim = ClaimManager::getInstance()->getClaimAtPosition($targetPos);
            if ($claim === null || $claim->getFactionId() !== $faction->getFactionId()) {
                $player->sendMessage(C::colorize("&r&cYou can only place a Chunk Buster in your own faction claim."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $chunkX = $targetPos->getFloorX() >> 4;
            $chunkZ = $targetPos->getFloorZ() >> 4;
            if (ChunkBusterTask::isChunkActive($targetPos->getWorld()->getFolderName(), $chunkX, $chunkZ)) {
                $player->sendMessage(C::colorize("&r&cA Chunk Buster is already active in this chunk."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $targetPos->getWorld()->setBlock($targetPos, VanillaBlocks::BEACON());

            if ($player->getGamemode() !== GameMode::CREATIVE()) {
                $item->pop();
                $player->getInventory()->setItemInHand($item);
            }

            Loader::getInstance()->getScheduler()->scheduleRepeatingTask(
                new ChunkBusterTask($player, $targetPos, $chunkX, $chunkZ),
                1
            );

            $player->sendMessage(C::colorize("&r&b&lCHUNK BUSTER &8» &r&7Deployment successful. Priming sequence started."));
            PlayerUtils::playSound($player, "random.fuse");
            return;
        }

        if ($id === "ocean_buster") {
            $event->cancel();

            if (!GenBucketManager::isOceanBusterEnabled()) {
                $player->sendMessage(C::colorize("&r&cOcean busters are currently disabled."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $targetPos = null;
            foreach ($event->getTransaction()->getBlocks() as [$x, $y, $z, $block]) {
                $targetPos = new Position($x, $y, $z, $player->getWorld());
                break;
            }

            if ($targetPos === null) {
                $player->sendMessage(C::colorize("&r&cFailed to place the Ocean Buster."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $world = $targetPos->getWorld();
            if (!GenBucketManager::isOceanBusterOverworld($world)) {
                $player->sendMessage(C::colorize("&r&cOcean busters can only be used in the overworld wilderness."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $claim = ClaimManager::getInstance()->getClaimAtPosition($targetPos);
            if ($claim !== null) {
                $perm = GenBucketManager::getOceanBusterClaimedPermission();
                if ($perm !== "" && !$player->hasPermission($perm)) {
                    $player->sendMessage(C::colorize("&r&cYou need the 'Use Sponges' permission to use this in claims."));
                    PlayerUtils::playSound($player, "note.bass");
                    return;
                }
            }

            $session = Loader::getPlayerManager()->getSession($player);
            $cost = GenBucketManager::getOceanBusterCost();
            if (
                $player->getGamemode() !== GameMode::CREATIVE()
                && $cost > 0
                && ($session === null || $session->getBalance() < $cost)
            ) {
                $player->sendMessage(C::colorize("&r&cYou need &f$" . number_format($cost) . " &cto use an Ocean Buster."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $chunkX = $targetPos->getFloorX() >> 4;
            $chunkZ = $targetPos->getFloorZ() >> 4;
            $cleared = $this->clearChunkWater($world, $chunkX, $chunkZ);
            if ($cleared <= 0) {
                $player->sendMessage(C::colorize("&r&eThere was no water to clear in this chunk."));
                PlayerUtils::playSound($player, "random.pop");
                return;
            }

            if ($player->getGamemode() !== GameMode::CREATIVE()) {
                if ($cost > 0 && $session !== null) {
                    $session->removeBalance($cost);
                }
                $item->pop();
                $player->getInventory()->setItemInHand($item);
            }

            $player->sendMessage(C::colorize("&r&b&lOCEAN BUSTER &8» &r&7Cleared &f" . number_format($cleared) . " &7water blocks in this chunk."));
            PlayerUtils::playSound($player, "random.explode");
            return;
        }

        if ($id === "collection_chest" || $id === "harvester_chest") {
            return;
        }

        $event->cancel();
    }

    public function onItemUse(PlayerItemUseEvent $event): void
    {
        $player = $event->getPlayer();
        $item = $event->getItem();
        $session = Loader::getPlayerManager()->getSession($player);

        if (
            EnchantCombatStateManager::arePotionsDisabled($player) &&
            in_array($item->getTypeId(), [ItemTypeIds::POTION, ItemTypeIds::SPLASH_POTION, ItemTypeIds::LINGERING_POTION], true)
        ) {
            $event->cancel();
            $player->sendMessage(C::colorize("&r&cYour potions are sealed right now."));
            PlayerUtils::playSound($player, "note.bass");
            return;
        }

        if ($item->getTypeId() === ItemTypeIds::ENDER_PEARL && PetManager::isPearlBlocked($player)) {
            $event->cancel();
            $player->sendMessage(C::colorize("&r&cA pet effect is preventing you from using ender pearls right now."));
            PlayerUtils::playSound($player, "note.bass");
            return;
        }

        if ($item->getTypeId() === ItemTypeIds::ENDER_PEARL && NetherPigmanGuard::snapshotBlocking($player)) {
            $event->cancel();
            $player->sendMessage(C::colorize("&r&cNether Warden feral energy is warping your pearls!"));
            PlayerUtils::playSound($player, "note.bass");
            return;
        }

        if ($item->getTypeId() === ItemTypeIds::ENDER_PEARL && $session !== null) {
            if ($session->isOnCooldown("ender_pearl")) {
                $event->cancel();
                $player->sendMessage(C::colorize("&r&cYou cannot throw another pearl for &f" . GeneralUtils::translateTime((int) $session->getCooldown("ender_pearl")) . "&r&c."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $session->addCooldown("ender_pearl", 10);
        }

        $nbt = $item->getNamedTag();
        $glaciaTag = $nbt->getCompoundTag("Glacia");

        if ($glaciaTag === null || !$glaciaTag->getTag("glaciaItem")) {
            return;
        }

        $itemTag = strtolower(trim(GlaciaItemFactory::resolvePetAliasKey($glaciaTag->getString("glaciaItem"))));

        if (DuelManager::getInstance()->isInDuel($player) && $this->isDuelBlockedOpenable($itemTag)) {
            $event->cancel();
            $player->sendMessage(C::colorize("&r&cYou cannot open or claim that item during a duel."));
            PlayerUtils::playSound($player, "note.bass");
            return;
        }

        if ($itemTag === "bath_bomb") {
            $event->cancel();

            $uuid = $player->getUniqueId()->toString();
            self::$pendingBathBombThrows[$uuid] = (self::$pendingBathBombThrows[$uuid] ?? 0) + 1;

            $projectileItem = VanillaItems::SNOWBALL();
            $returnedItems = [];
            $result = $projectileItem->onClickAir($player, $event->getDirectionVector(), $returnedItems);
            if ($result !== ItemUseResult::SUCCESS) {
                self::$pendingBathBombThrows[$uuid] = max(0, (self::$pendingBathBombThrows[$uuid] ?? 1) - 1);
                if (self::$pendingBathBombThrows[$uuid] <= 0) {
                    unset(self::$pendingBathBombThrows[$uuid]);
                }
                return;
            }

            if (!$player->isCreative()) {
                $item->pop();
                $player->getInventory()->setItemInHand($item);
            }
            return;
        }

        if ($itemTag === "wraith_pearl") {
            $event->cancel();

            $totalUses = $this->getStoredThrowableUses($item, "wraith_pearl", 40);
            if ($totalUses <= 0) {
                $session?->clearCooldown("ender_pearl");
                $player->getInventory()->setItemInHand(VanillaItems::AIR());
                $player->sendMessage(C::colorize("&r&cThis Wraith Pearl has no stored charges left."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $projectileItem = VanillaItems::ENDER_PEARL();
            $returnedItems = [];
            $result = $projectileItem->onClickAir($player, $event->getDirectionVector(), $returnedItems);
            if ($result !== ItemUseResult::SUCCESS) {
                $session?->clearCooldown("ender_pearl");
                return;
            }

            $remainingUses = $totalUses - 1;
            $player->getInventory()->setItemInHand($remainingUses > 0 ? GlaciaItemFactory::wraithPearl($remainingUses) : VanillaItems::AIR());
            if ($remainingUses <= 0) {
                $player->sendMessage(C::colorize("&r&5Your Wraith Pearl has been exhausted."));
            }
            return;
        }

        if ($itemTag === "angel_potion") {
            $event->cancel();

            $totalUses = $this->getStoredThrowableUses($item, "angel_potion", 40);
            if ($totalUses <= 0) {
                $player->getInventory()->setItemInHand(VanillaItems::AIR());
                $player->sendMessage(C::colorize("&r&cThis Angel Potion has no stored heals left."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $projectileItem = VanillaItems::SPLASH_POTION()->setType(PotionType::STRONG_HEALING());
            $returnedItems = [];
            $result = $projectileItem->onClickAir($player, $event->getDirectionVector(), $returnedItems);
            if ($result !== ItemUseResult::SUCCESS) {
                return;
            }

            $remainingUses = $totalUses - 1;
            $player->getInventory()->setItemInHand($remainingUses > 0 ? GlaciaItemFactory::angelPotion($remainingUses) : VanillaItems::AIR());
            if ($remainingUses <= 0) {
                $player->sendMessage(C::colorize("&r&dYour Angel Potion has run dry."));
            }
            return;
        }

        if (SoulGemSessionManager::isSoulConduitGemId($itemTag) || SoulGemSessionManager::isLegacyStableConduitItem($item)) {
            $event->cancel();
            $player->sendMessage(C::colorize("&r&7Drag that conduit onto a weapon, tool, bow, or armor piece to merge it."));
            PlayerUtils::playSound($player, "random.click");
            return;
        }

        $petItemTags = [
            "puritywisp",
            "galefox",
            "shatterfang",
            "hearthbloom",
            "ironcladwyrm",
            "aegisram",
            "riftviper",
            "sunscorchjackal",
            "runesageowl",
            "stormcorebehemoth",
            "veilmoth",
            "blastforgehound",
            "gildedmoth",
            "rafflefinch",
            "oathbondbeetle",
            "murmurloft",
            "patchworksprite",
            "renderscalewyrm",
            "skycourierdrake",
            "inkwellfinch",
            "terracrestjackal",
            "soulsurge",
            "ruinspiremoth",
            "bellforgecustodian",
            "arcbindercodex",
            "crownspirekestrel",
            "shadebrokercrow",
        ];
        if (EnchantCombatStateManager::arePetsDisabled($player) && in_array($itemTag, $petItemTags, true)) {
            $event->cancel();
            $player->sendMessage(C::colorize("&r&cYour pets are disabled right now."));
            PlayerUtils::playSound($player, "note.bass");
            return;
        }

        if ($itemTag === "slot_ticket" || $itemTag === "heroic_slot_ticket") {
            $event->cancel();
            (new SlotbotMenuScreen($player))->display($player);
            return;
        }

        if ($itemTag === "lootbox") {
            $event->cancel();

            $lootboxId = $glaciaTag->getString("lootbox", "");
            $box = LootboxRepository::getBoxByLookup($lootboxId);
            if ($box === null) {
                $player->sendMessage(C::colorize("&r&cThis lootbox is invalid."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            if ($player->isSneaking()) {
                OpenableLootPreviewScreen::display(
                    $player,
                    C::clean((string) ($box["name"] ?? $box["id"] ?? "Lootbox")),
                    LootboxRepository::getPreviewGroups($box)
                );
                PlayerUtils::playSound($player, "random.click");
                return;
            }

            if (RuneManager::shouldPreserveConsumable($player, "lootbox") || ChunkWatchdogManager::shouldPreserveCcOpen($player)) {
            } else {
                $item->pop();
            }
            $player->getInventory()->setItemInHand($item);
            FactionContributionManager::addLootbagContribution($player);
            LootboxAnimationFactory::display($player, $box);
            return;
        }

        if ($itemTag === "ethereal_crate") {
            $event->cancel();

            $crateId = $glaciaTag->getString("ethereal_crate", "");
            $crate = EtherealCrateRepository::getCrateByLookup($crateId);
            if ($crate === null) {
                $player->sendMessage(C::colorize("&r&cThis ethereal crate is invalid."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            if ($player->isSneaking()) {
                OpenableLootPreviewScreen::display(
                    $player,
                    C::clean((string) ($crate["name"] ?? $crate["id"] ?? "Ethereal Crate")),
                    [
                        ["name" => "Admin", "entries" => (array) ($crate["loot-tables"]["admin"] ?? [])],
                        ["name" => "Cosmetic", "entries" => (array) ($crate["loot-tables"]["cosmetic"] ?? [])],
                        ["name" => "Treasure", "entries" => (array) ($crate["loot-tables"]["treasure"] ?? [])],
                        ["name" => "Bonus", "entries" => (array) ($crate["loot-tables"]["bonus"] ?? [])],
                    ]
                );
                PlayerUtils::playSound($player, "random.click");
                return;
            }

            $consumeOnFirstRoll = function() use ($player, $crateId, $crate): bool {
                if (RuneManager::shouldPreserveConsumable($player, "ethereal_crate") || ChunkWatchdogManager::shouldPreserveCcOpen($player)) {
                    FactionContributionManager::addLootbagContribution($player);
                    CcProgressManager::handleEtherealCrateOpened($player, $crate);
                    Loader::getQuestBoardManager()->recordProgress($player, "ethereal_crates_opened", 1, [
                        "crate_id" => strtolower($crateId),
                    ]);
                    return true;
                }

                $inHand = $player->getInventory()->getItemInHand();
                $inHandGlacia = $inHand->getNamedTag()->getCompoundTag("Glacia");
                if (
                    $inHandGlacia !== null &&
                    $inHandGlacia->getString("glaciaItem", "") === "ethereal_crate" &&
                    $inHandGlacia->getString("ethereal_crate", "") === $crateId
                ) {
                    $inHand->pop();
                    $player->getInventory()->setItemInHand($inHand);
                    FactionContributionManager::addLootbagContribution($player);
                    CcProgressManager::handleEtherealCrateOpened($player, $crate);
                    Loader::getQuestBoardManager()->recordProgress($player, "ethereal_crates_opened", 1, [
                        "crate_id" => strtolower($crateId),
                    ]);
                    return true;
                }

                // Fallback: consume one matching crate from inventory if hand changed while menu is open.
                foreach ($player->getInventory()->getContents() as $slot => $stack) {
                    $glacia = $stack->getNamedTag()->getCompoundTag("Glacia");
                    if (
                        $glacia !== null &&
                        $glacia->getString("glaciaItem", "") === "ethereal_crate" &&
                        $glacia->getString("ethereal_crate", "") === $crateId
                    ) {
                        $stack->pop();
                        $player->getInventory()->setItem($slot, $stack);
                        FactionContributionManager::addLootbagContribution($player);
                        CcProgressManager::handleEtherealCrateOpened($player, $crate);
                        Loader::getQuestBoardManager()->recordProgress($player, "ethereal_crates_opened", 1, [
                            "crate_id" => strtolower($crateId),
                        ]);
                        return true;
                    }
                }

                return false;
            };

            (new EtherealCrateAnimationScreen($player, $crate, $consumeOnFirstRoll))->display($player);
            return;
        }

        if ($session === null) {
            $event->cancel();
            $player->sendMessage(C::colorize("&r&cYour profile is still loading. Try again in a moment."));
            PlayerUtils::playSound($player, "note.bass");
            return;
        }

        if (RankProgressionManager::isRankVoucher($itemTag)) {
            $event->cancel();

            $rank = RankProgressionManager::extractRankFromIdentifier($itemTag);
            if ($rank === null) {
                $player->sendMessage(C::colorize("&r&cThis rank voucher is invalid."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            if (!RankProgressionManager::canUseVoucher($session->getRank(), $rank)) {
                $player->sendMessage(C::colorize("&r&cYou already have this rank or a higher one."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $session->setRank($rank);
            RankManager::getInstance()->updatePermission($player);

            $item->pop();
            $player->getInventory()->setItemInHand($item);
            $player->sendMessage(C::colorize("&r&l&a[!] &r&aYou unlocked the &f" . ucfirst($rank) . " &arank."));
            PlayerUtils::playSound($player, "random.levelup");
            return;
        }

        if ($itemTag === "title_voucher") {
            $event->cancel();

            $titleId = strtolower(trim($glaciaTag->getString("title_id", "")));
            $titleData = TitleManager::get($titleId);
            if ($titleId === "" || $titleData === null) {
                $player->sendMessage(C::colorize("&r&cThis title voucher is invalid."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            if (TitleManager::hasUnlocked($session, $titleId)) {
                $player->sendMessage(C::colorize("&r&8You already unlocked this title."));
                PlayerUtils::playSound($player, "random.click");
                return;
            }

            if (!TitleManager::unlock($session, $titleId)) {
                $player->sendMessage(C::colorize("&r&cFailed to claim this title voucher."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            RankManager::getInstance()->updatePermission($player);
            $this->consumeHeldItem($player, $item);
            $player->sendTitle(C::colorize("&r&aTitle Unlocked!"), C::colorize("&r" . (string) ($titleData["format"] ?? "")), 5, 35, 10);
            $player->sendMessage(C::colorize("&r&aUnlocked title: &r" . (string) ($titleData["format"] ?? "")));
            PlayerUtils::playSound($player, "random.levelup");
            $player->getWorld()->addSound($player->getPosition(), new XpCollectSound());
            return;
        }

        if ($itemTag === "item_flip_credit_voucher") {
            $event->cancel();
            $credits = max(1, $this->readFlexibleIntTag($glaciaTag, "credits", 1));
            $session->addItemFlipCredits($credits);
            $this->consumeHeldItem($player, $item);
            $player->sendMessage(C::colorize("&r&aClaimed &f" . number_format($credits) . " &aItem Flip credits."));
            PlayerUtils::playSound($player, "random.levelup");
            return;
        }

        if ($itemTag === "passive_rune_pouch") {
            $event->cancel();
            $pouchSlot = $player->getInventory()->getHeldItemIndex();
            $pouch = Loader::getPassiveRunePouchManager()->refreshPouchMeta(
                Loader::getPassiveRunePouchManager()->initializePouchItem(clone $item)
            );
            $player->getInventory()->setItem($pouchSlot, $pouch);
            (new \ecstsy\Glacia\utils\screens\runes\PassiveRunePouchScreen($player, $pouchSlot))->display();
            return;
        }

        if ($itemTag === "admin_coin_note") {
            $event->cancel();
            $amount = $this->readFlexibleIntTag($glaciaTag, "amount", 0);
            if ($amount <= 0) {
                $player->sendMessage(C::colorize("&r&cThis Admin Coin voucher is invalid."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $amount = max(1, $amount);
            $session->addAdminCoins($amount);
            $this->consumeHeldItem($player, $item);
            $player->sendMessage(C::colorize("&r&aClaimed &f" . number_format($amount) . " &aAdmin Coins."));
            PlayerUtils::playSound($player, "random.levelup");
            return;
        }

        if ($itemTag === "fragment") {
            $event->cancel();
            $fragmentId = strtolower($glaciaTag->getString("fragment_id", ""));
            if ($fragmentId === "") {
                $player->sendMessage(C::colorize("&r&cThis fragment is invalid."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            if (FragmentManager::handleUse($player, $fragmentId)) {
                PlayerUtils::playSound($player, "random.levelup");
            }
            return;
        }

        if (RankProgressionManager::isRankQuest($itemTag)) {
            $event->cancel();

            $rank = RankProgressionManager::extractRankFromIdentifier($itemTag);
            if ($rank === null) {
                $player->sendMessage(C::colorize("&r&cThis rank quest is invalid."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $glaciaTag = $item->getNamedTag()->getCompoundTag("Glacia");
            $remaining = $glaciaTag !== null ? $this->readFlexibleIntTag($glaciaTag, "remaining", 0) : 0;
            $completeTag = $glaciaTag?->getTag("complete");
            $complete = ($completeTag instanceof ByteTag || $completeTag instanceof IntTag) && $completeTag->getValue() === 1;

            if (!$complete) {
                $warzone = Loader::getRegionManager()->getRegionByName("Warzone");
                $currentRegion = Loader::getRegionManager()->getRegionAt($player->getPosition());
                if ($warzone === null || $currentRegion !== $warzone) {
                    $player->sendMessage(C::colorize("&r&cYou must be standing in the &4Warzone&r&c for this quest to progress."));
                    PlayerUtils::playSound($player, "note.bass");
                    return;
                }

                $player->sendMessage(C::colorize("&r&7Warzone survival progress: &f" . GeneralUtils::translateTime($remaining) . " &7remaining."));
                PlayerUtils::playSound($player, "random.click");
                return;
            }

            RankProgressionManager::claimQuestRewards($player, $rank);
            $item->pop();
            $player->getInventory()->setItemInHand($item);
            $player->sendMessage(C::colorize("&r&l&a[!] &r&aYou claimed your " . ucfirst($rank) . " quest rewards."));
            PlayerUtils::playSound($player, "random.levelup");
            return;
        }

        if ($itemTag === "disguise_unlock") {
            $event->cancel();

            $disguiseId = DisguiseManager::normalizeId($glaciaTag->getString("disguise", ""));
            if ($disguiseId === "" || !DisguiseManager::hasDisguise($disguiseId)) {
                $player->sendMessage(C::colorize("&r&cThis disguise unlock is invalid."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $permission = DisguiseManager::getPermission($disguiseId);
            if ($player->hasPermission($permission)) {
                $player->sendMessage(C::colorize("&r&7You already unlocked &f" . DisguiseManager::getDisplayName($disguiseId) . "&7."));
                PlayerUtils::playSound($player, "random.click");
                return;
            }

            $session?->addExtraPermission($permission);
            RankManager::getInstance()->updatePermission($player);
            $this->consumeHeldItem($player, $item);
            $player->sendMessage(C::colorize("&r&dUnlocked disguise: &f" . DisguiseManager::getDisplayName($disguiseId) . "&7. Use &f/disguise {$disguiseId}&7."));
            PlayerUtils::playSound($player, "random.levelup");
            return;
        }

        if ($itemTag === "renametag") {
            $event->cancel();
            ItemRenameManager::start($player);
            return;
        }

        if ($itemTag === "econote") {
            $amount = $this->readGlaciaInt($glaciaTag, "worth", 0);
            $count = $item->getCount();

            if ($amount <= 0) {
                $event->cancel();
                $player->sendMessage(C::colorize("&r&cThis bank note is invalid or corrupted."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            if ($session->getSetting("quick_claim") && $player->isSneaking() && $count > 1) {
                $total = $amount * $count;
                $session->addBalance($total);
                $player->sendActionBarMessage(C::colorize("&r&l&a+ &r&a$" . number_format($total) . " added to your balance!"));
                $player->getWorld()->addSound($player->getPosition(), new XpCollectSound());
                $item->pop($item->getCount());
                $player->getInventory()->setItemInHand($item);
            } else {
                if ($amount > 0) {
                    $session->addBalance($amount);
                    $player->sendActionBarMessage(C::colorize("&r&l&a+ &r&a$" . number_format($amount) . " added to your balance!"));
                    $player->getWorld()->addSound($player->getPosition(), new XpCollectSound());
                    $item->pop();
                    $player->getInventory()->setItemInHand($item);
                }
            }

            return;
        }

        if ($itemTag === "xpnote") {
            $event->cancel();
            $amount = $this->readGlaciaInt($glaciaTag, "worth", 0);
            $count = $item->getCount();

            if ($amount <= 0) {
                $player->sendMessage(C::colorize("&r&cThis XP note is invalid or corrupted."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            if ($session->getSetting("quick_claim") && $player->isSneaking() && $count > 1) {
                $total = $amount * $count;
                $player->getXpManager()->addXp($total);
                $player->sendMessage(C::colorize("&r&l&a+ &r&a" . number_format($total) . " EXP added!"));
                $player->getWorld()->addSound($player->getPosition(), new XpCollectSound());
                $item->pop($item->getCount());
                $player->getInventory()->setItemInHand($item);
            } else {
                if ($amount > 0) {
                    $player->getXpManager()->addXp($amount);
                    $player->sendMessage(C::colorize("&r&l&a+ &r&a" . number_format($amount) . " EXP added!"));
                    $player->getWorld()->addSound($player->getPosition(), new XpCollectSound());
                    $item->pop();
                    $player->getInventory()->setItemInHand($item);
                }
            }
            return;
        }

        if ($itemTag === "max_home_increase") {
            $event->cancel();
            $config = GeneralUtils::getConfiguration(Loader::getInstance(), "misc.yml");
            $requiredPermission = (string) ($config?->get("home-increaser-permission", "glacia.home.increaser.use") ?? "glacia.home.increaser.use");
            $maxCap = max(1, (int) ($config?->get("home-max-cap", 50) ?? 50));
            $homeManager = Loader::getHomeManager();
            $currentMax = $homeManager->getMaxHomes($player->getUniqueId());

            if (!$player->hasPermission($requiredPermission)) {
                $player->sendMessage(C::colorize("&r&cYou cannot use this item yet."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            if ($currentMax >= $maxCap) {
                $player->sendMessage(C::colorize("&r&cYou have already reached the max home cap of &f{$maxCap}&r&c."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $homeManager->setMaxHomes($player->getUniqueId(), min($maxCap, $currentMax + 1));
            $item->pop();
            $player->getInventory()->setItemInHand($item);
            $player->sendMessage(C::colorize("&r&aYour max homes increased to &f" . $homeManager->getMaxHomes($player->getUniqueId()) . "&r&a."));
            PlayerUtils::playSound($player, "random.levelup");
            return;
        }

        if ($itemTag === "frostwake_trident") {
            $event->cancel();
            $cooldown = $this->getRelicCooldownSeconds("frostwake_trident", 120);
            if ($this->handleRelicCooldown($player, $session, "relic.frostwake_trident", $cooldown, "Frostwake Trident")) {
                return;
            }

            $repaired = 0;

            foreach ($player->getInventory()->getContents() as $slot => $storedItem) {
                if (!$storedItem instanceof Durable || $storedItem->isNull()) {
                    continue;
                }

                $damageBefore = $storedItem->getDamage();
                $slotChanged = false;
                if ($damageBefore > 0) {
                    $maxDurability = $storedItem->getMaxDurability();
                    if ($maxDurability > 0) {
                        $repair = max(1, (int) ceil($maxDurability * 0.50));
                        $oldDamage = $storedItem->getDamage();
                        $newDamage = max(0, $oldDamage - $repair);
                        $storedItem->setDamage($newDamage);
                        HeroicArmorDurabilityManager::syncAfterRepair($storedItem, $oldDamage, $newDamage);
                        $slotChanged = true;
                    }
                } elseif (HeroicArmorDurabilityManager::replenishHeroicBufferFractionOfMax($storedItem, 0.5)) {
                    $slotChanged = true;
                }
                if (!$slotChanged) {
                    continue;
                }
                $player->getInventory()->setItem($slot, $storedItem);
                $repaired++;
            }

            foreach ($player->getArmorInventory()->getContents() as $slot => $storedItem) {
                if (!$storedItem instanceof Durable || $storedItem->isNull()) {
                    continue;
                }

                $damageBefore = $storedItem->getDamage();
                $slotChanged = false;
                if ($damageBefore > 0) {
                    $maxDurability = $storedItem->getMaxDurability();
                    if ($maxDurability > 0) {
                        $repair = max(1, (int) ceil($maxDurability * 0.50));
                        $oldDamage = $storedItem->getDamage();
                        $newDamage = max(0, $oldDamage - $repair);
                        $storedItem->setDamage($newDamage);
                        HeroicArmorDurabilityManager::syncAfterRepair($storedItem, $oldDamage, $newDamage);
                        $slotChanged = true;
                    }
                } elseif (HeroicArmorDurabilityManager::replenishHeroicBufferFractionOfMax($storedItem, 0.5)) {
                    $slotChanged = true;
                }
                if (!$slotChanged) {
                    continue;
                }
                $player->getArmorInventory()->setItem($slot, $storedItem);
                $repaired++;
            }

            if ($repaired <= 0) {
                $player->sendMessage(C::colorize("&r&cYou have no damaged items to restore."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $session->addCooldown("relic.frostwake_trident", $cooldown);
            $this->consumeHeldItem($player, $item);
            $player->sendMessage(C::colorize("&r&bFrostwake Trident &r&7restored durability throughout your inventory."));
            PlayerUtils::playSound($player, "random.levelup");
            return;
        }

        if ($itemTag === "stormflare_torch") {
            $event->cancel();
            $cooldown = $this->getRelicCooldownSeconds("stormflare_torch", 300);
            if ($this->handleRelicCooldown($player, $session, "relic.stormflare_torch", $cooldown, "Stormflare Torch")) {
                return;
            }

            $tagged = 0;
            $origin = $player->getPosition();
            $maxDistanceSquared = 9 * 9;
            foreach ($player->getWorld()->getPlayers() as $nearby) {
                if ($nearby->getId() === $player->getId()) {
                    continue;
                }

                if ($nearby->getPosition()->distanceSquared($origin) > $maxDistanceSquared) {
                    continue;
                }

                Loader::getCombatManager()->startCombat($player, $nearby);
                $tagged++;
            }

            if ($tagged <= 0) {
                $player->sendMessage(C::colorize("&r&cNo players were close enough to tag."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $session->addCooldown("relic.stormflare_torch", $cooldown);
            $this->consumeHeldItem($player, $item);
            $player->sendMessage(C::colorize("&r&eStormflare Torch &r&7tagged &f{$tagged} &r&7player(s) in combat."));
            PlayerUtils::playSound($player, "ambient.weather.thunder");
            return;
        }

        if ($itemTag === "glacial_blessing") {
            $event->cancel();
            $cooldown = $this->getRelicCooldownSeconds("glacial_blessing", 600);
            if ($this->handleRelicCooldown($player, $session, "relic.glacial_blessing", $cooldown, "Glacial Blessing")) {
                return;
            }

            $player->setHealth($player->getMaxHealth());
            $session->addCooldown("relic.glacial_blessing", $cooldown);
            $this->consumeHeldItem($player, $item);
            $player->sendMessage(C::colorize("&r&fGlacial Blessing &r&7restored you to full health."));
            PlayerUtils::playSound($player, "random.levelup");
            return;
        }

        if ($itemTag === "tempest_gateway") {
            $event->cancel();
            $cooldown = $this->getRelicCooldownSeconds("tempest_gateway", 600);
            if ($this->handleRelicCooldown($player, $session, "relic.tempest_gateway", $cooldown, "Tempest Gateway")) {
                return;
            }

            $targetPlayer = $this->findNearestEnemyPlayer($player, 8.0);
            $strikePosition = $targetPlayer?->getPosition() ?? $player->getPosition();
            YijkiLightningStrike::strike($player, $strikePosition, 4.0, 3.0);

            $session->addCooldown("relic.tempest_gateway", $cooldown);
            $player->sendMessage(C::colorize("&r&3Tempest Gateway &r&7unleashed a Stormcaller ability" . ($targetPlayer !== null ? " on &f" . $targetPlayer->getName() : "") . "&r&7."));
            PlayerUtils::playSound($player, "ambient.weather.thunder");
            return;
        }

        if ($itemTag === "puritywisp") {
            $event->cancel();
            $cooldown = PetManager::getCooldown("puritywisp", 10);
            if ($this->handleRelicCooldown($player, $session, "pet.puritywisp", $cooldown, "Purity Wisp Pet")) {
                return;
            }

            GlaciaUtils::bless($player);
            if (!$this->shouldBypassPetCooldown($player, "pet.puritywisp")) {
                $session->addCooldown("pet.puritywisp", $cooldown);
            }
            $player->sendMessage(C::colorize("&r&fPurity Wisp Pet &r&7washed away your negative effects."));
            PlayerUtils::playSound($player, "random.orb");
            return;
        }

        if ($itemTag === "galefox") {
            $event->cancel();
            $cooldown = PetManager::getCooldown("galefox", 300);
            if ($this->handleRelicCooldown($player, $session, "pet.galefox", $cooldown, "Gale Fox Pet")) {
                return;
            }

            $duration = (int) ceil(PetManager::getDuration("galefox", "duration", 10) * RuneManager::getPetMultiplier($player, "galefox"));
            $speedLevel = max(1, PetManager::getInt("galefox", "speed-level", 5));
            $player->getEffects()->add(new \pocketmine\entity\effect\EffectInstance(\pocketmine\entity\effect\VanillaEffects::SPEED(), $duration * 20, $speedLevel - 1));
            if (!$this->shouldBypassPetCooldown($player, "pet.galefox")) {
                $session->addCooldown("pet.galefox", $cooldown);
            }
            $player->sendMessage(C::colorize("&r&bGale Fox Pet &r&7surged through you with a burst of speed."));
            PlayerUtils::playSound($player, "random.orb");
            return;
        }

        if ($itemTag === "shatterfang") {
            $event->cancel();
            $cooldown = PetManager::getCooldown("shatterfang", 1200);
            if ($this->handleRelicCooldown($player, $session, "pet.shatterfang", $cooldown, "Shatterfang Pet")) {
                return;
            }

            $duration = (int) ceil(PetManager::getDuration("shatterfang", "duration", 5) * RuneManager::getPetMultiplier($player, "shatterfang"));
            PetManager::activateShatterfang($player, $duration);
            if (!$this->shouldBypassPetCooldown($player, "pet.shatterfang")) {
                $session->addCooldown("pet.shatterfang", $cooldown);
            }
            $player->sendMessage(C::colorize("&r&8Shatterfang Pet &r&7empowered your attacks to tear through armor."));
            PlayerUtils::playSound($player, "mob.enderdragon.growl");
            return;
        }

        if ($itemTag === "hearthbloom") {
            $event->cancel();
            $cooldown = PetManager::getCooldown("hearthbloom", 90);
            if ($this->handleRelicCooldown($player, $session, "pet.hearthbloom", $cooldown, "Hearthbloom Pet")) {
                return;
            }

            $food = PetManager::getInt("hearthbloom", "food", 20);
            $saturation = (float) PetManager::getInt("hearthbloom", "saturation", 20);
            $heal = (float) PetManager::getInt("hearthbloom", "heal", 2);
            $player->getHungerManager()->setFood(min(20, $food));
            $player->getHungerManager()->setSaturation($saturation);
            $player->setHealth(min($player->getMaxHealth(), $player->getHealth() + $heal));
            if (!$this->shouldBypassPetCooldown($player, "pet.hearthbloom")) {
                $session->addCooldown("pet.hearthbloom", $cooldown);
            }
            $player->sendMessage(C::colorize("&r&eHearthbloom Pet &r&7restored your hunger and renewed your strength."));
            PlayerUtils::playSound($player, "random.levelup");
            return;
        }

        if ($itemTag === "ironcladwyrm") {
            $event->cancel();
            $cooldown = PetManager::getCooldown("ironcladwyrm", 3600);
            if ($this->handleRelicCooldown($player, $session, "pet.ironcladwyrm", $cooldown, "Ironclad Wyrm Pet")) {
                return;
            }

            $target = $this->findNearestEnemyPlayer($player, 8);
            if ($target === null) {
                $player->sendMessage(C::colorize("&r&cNo enemy was close enough to disarm."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            PetManager::triggerIroncladDisarm($player);
            Loader::getCombatManager()->startCombat($player, $target);
            $this->consumePetCooldownIfNeeded($player, $session, "pet.ironcladwyrm", $cooldown);
            $player->sendMessage(C::colorize("&r&cIronclad Wyrm Pet &r&7is ready to tear away &f" . $target->getName() . "&7's armor."));
            PlayerUtils::playSound($player, "mob.enderdragon.growl");
            return;
        }

        if ($itemTag === "aegisram") {
            $event->cancel();
            $cooldown = PetManager::getCooldown("aegisram", 1200);
            if ($this->handleRelicCooldown($player, $session, "pet.aegisram", $cooldown, "Aegis Ram Pet")) {
                return;
            }

            $duration = (int) ceil(PetManager::getDuration("aegisram", "duration", 10) * RuneManager::getPetMultiplier($player, "aegisram"));
            PetManager::activateAegis($player, $duration);
            GlaciaUtils::bless($player);
            $this->consumePetCooldownIfNeeded($player, $session, "pet.aegisram", $cooldown);
            $player->sendMessage(C::colorize("&r&bAegis Ram Pet &r&7wrapped you in a knockback-nullifying ward."));
            PlayerUtils::playSound($player, "random.levelup");
            return;
        }

        if ($itemTag === "riftviper") {
            $event->cancel();
            $cooldown = PetManager::getCooldown("riftviper", 1200);
            if ($this->handleRelicCooldown($player, $session, "pet.riftviper", $cooldown, "Rift Viper Pet")) {
                return;
            }

            $duration = (int) ceil(PetManager::getDuration("riftviper", "duration", 6) * RuneManager::getPetMultiplier($player, "riftviper"));
            $affected = 0;
            foreach ($this->getNearbyEnemyPlayers($player, 3.5) as $target) {
                PetManager::blockPearls($target, $duration);
                Loader::getCombatManager()->startCombat($player, $target);
                $affected++;
            }

            if ($affected <= 0) {
                $player->sendMessage(C::colorize("&r&cNo enemies were close enough to lock out ender pearls."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $this->consumePetCooldownIfNeeded($player, $session, "pet.riftviper", $cooldown);
            $player->sendMessage(C::colorize("&r&5Rift Viper Pet &r&7sealed ender pearls for &f{$affected} &r&7enemy player(s)."));
            PlayerUtils::playSound($player, "mob.endermen.portal");
            return;
        }

        if ($itemTag === "sunscorchjackal") {
            $event->cancel();
            $cooldown = PetManager::getCooldown("sunscorchjackal", 2700);
            if ($this->handleRelicCooldown($player, $session, "pet.sunscorchjackal", $cooldown, "Sunscorch Jackal Pet")) {
                return;
            }

            $duration = (int) ceil(PetManager::getDuration("sunscorchjackal", "duration", 10) * RuneManager::getPetMultiplier($player, "sunscorchjackal"));
            $affected = 0;
            foreach ($this->getNearbyEnemyPlayers($player, 3.5) as $target) {
                PetManager::blockGapples($target, $duration);
                Loader::getCombatManager()->startCombat($player, $target);
                $affected++;
            }

            if ($affected <= 0) {
                $player->sendMessage(C::colorize("&r&cNo enemies were close enough to scorch their apples."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $this->consumePetCooldownIfNeeded($player, $session, "pet.sunscorchjackal", $cooldown);
            $player->sendMessage(C::colorize("&r&6Sunscorch Jackal Pet &r&7locked golden apples for &f{$affected} &r&7enemy player(s)."));
            PlayerUtils::playSound($player, "fire.ignite");
            return;
        }

        if ($itemTag === "runesageowl") {
            $event->cancel();
            $cooldown = PetManager::getCooldown("runesageowl", 43200);
            if ($this->handleRelicCooldown($player, $session, "pet.runesageowl", $cooldown, "Runesage Owl Pet")) {
                return;
            }

            $duration = (int) ceil(PetManager::getDuration("runesageowl", "duration", 300) * RuneManager::getPetMultiplier($player, "runesageowl"));
            PetManager::activateEnchantSurge($player, $duration);
            $this->consumePetCooldownIfNeeded($player, $session, "pet.runesageowl", $cooldown);
            $player->sendMessage(C::colorize("&r&dRunesage Owl Pet &r&7empowered your enchantment applications by &f+5%&7."));
            PlayerUtils::playSound($player, "random.levelup");
            return;
        }

        if ($itemTag === "stormcorebehemoth") {
            $event->cancel();
            $cooldown = PetManager::getCooldown("stormcorebehemoth", 600);
            if ($this->handleRelicCooldown($player, $session, "pet.stormcorebehemoth", $cooldown, "Stormcore Behemoth Pet")) {
                return;
            }

            $spawnPos = $player->getPosition()->add(0, 1, 0);
            $lightningPos = new Position($spawnPos->x, $spawnPos->y, $spawnPos->z, $player->getWorld());
            $location = new Location(
                $spawnPos->x + 0.5,
                $spawnPos->y,
                $spawnPos->z + 0.5,
                $player->getWorld(),
                $player->getLocation()->yaw,
                $player->getLocation()->pitch
            );
            $creeper = new GlaciaExplosiveCreeper($location);
            $creeper->setExplosiveType(CustomTNTIds::GIGANTIC);
            $creeper->setOwnerUuid($player->getUniqueId()->toString());
            $creeper->setBlockBreakingEnabled(false);
            $creeper->setScale(1.45);
            $creeper->setNameTag(C::colorize("&r&4&lCharged Giga Creeper"));
            $creeper->setNameTagAlwaysVisible(true);
            $creeper->spawnToAll();
            YijkiLightningStrike::strike($player, $lightningPos, 0.0, 0.0);
            $session->setSetting("stormcore_self_immunity_until", time() + 5);
            PlayerUtils::playSound($player, "random.fuse", 1.0, 0.9);

            Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(
                function() use ($creeper): void {
                    if ($creeper->isAlive()) {
                        $creeper->ignite();
                    }
                }
            ), 40);

            $this->consumePetCooldownIfNeeded($player, $session, "pet.stormcorebehemoth", $cooldown);
            $player->sendMessage(C::colorize("&r&cStormcore Behemoth Pet &r&7summoned a charged giga creeper."));
            PlayerUtils::playSound($player, "ambient.weather.thunder");
            return;
        }

        if ($itemTag === "veilmoth") {
            $event->cancel();
            $cooldown = PetManager::getCooldown("veilmoth", 600);
            if ($this->handleRelicCooldown($player, $session, "pet.veilmoth", $cooldown, "Veilmoth Pet")) {
                return;
            }

            $duration = (int) ceil(PetManager::getDuration("veilmoth", "duration", 60) * RuneManager::getPetMultiplier($player, "veilmoth"));
            PetManager::hideFromNear($player, $duration);
            $this->consumePetCooldownIfNeeded($player, $session, "pet.veilmoth", $cooldown);
            $player->sendMessage(C::colorize("&r&8Veilmoth Pet &r&7wrapped your presence from &f/near &r&7for a short time."));
            PlayerUtils::playSound($player, "mob.endermen.portal");
            return;
        }

        if ($itemTag === "blastforgehound") {
            $event->cancel();
            $cooldown = PetManager::getCooldown("blastforgehound", 900);
            if ($this->handleRelicCooldown($player, $session, "pet.blastforgehound", $cooldown, "Blastforge Hound Pet")) {
                return;
            }

            $target = $this->findNearestEnemyPlayer($player, 8);
            if ($target === null) {
                $player->sendMessage(C::colorize("&r&cNo enemy was close enough to cripple."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $duration = (int) ceil(PetManager::getDuration("blastforgehound", "duration", 7) * RuneManager::getPetMultiplier($player, "blastforgehound"));
            $target->getEffects()->add(new \pocketmine\entity\effect\EffectInstance(
                \pocketmine\entity\effect\VanillaEffects::SLOWNESS(),
                $duration * 20,
                9
            ));
            PetManager::activateBlastShield($player, $duration);
            Loader::getCombatManager()->startCombat($player, $target);
            $this->consumePetCooldownIfNeeded($player, $session, "pet.blastforgehound", $cooldown);
            $player->sendMessage(C::colorize("&r&4Blastforge Hound Pet &r&7crippled &f" . $target->getName() . " &r&7and hardened you against explosions."));
            PlayerUtils::playSound($player, "mob.wither.shoot");
            return;
        }

        if ($itemTag === "gildedmoth") {
            $event->cancel();
            $cooldown = PetManager::getCooldown("gildedmoth", 432000);
            if ($this->handleRelicCooldown($player, $session, "pet.gildedmoth", $cooldown, "Gilded Moth Pet")) {
                return;
            }

            $duration = (int) ceil(PetManager::getDuration("gildedmoth", "duration", 600) * RuneManager::getPetMultiplier($player, "gildedmoth"));
            PetManager::activateJackpotSlotGuarantee($player, $duration);
            $this->consumePetCooldownIfNeeded($player, $session, "pet.gildedmoth", $cooldown);
            $player->sendMessage(C::colorize("&r&dGilded Moth Pet &r&7primed your next slotbot slot for a jackpot roll. You have &f10 minutes&r&7."));
            PlayerUtils::playSound($player, "random.levelup");
            return;
        }

        if ($itemTag === "rafflefinch") {
            $event->cancel();
            $cooldown = PetManager::getCooldown("rafflefinch", 14400);
            if ($this->handleRelicCooldown($player, $session, "pet.rafflefinch", $cooldown, "Raffle Finch Pet")) {
                return;
            }

            $bonusTickets = max(1, PetManager::getInt("rafflefinch", "bonus-tickets", 1000));
            if (!Loader::getJackpotInstance()->purchaseTickets($player, $bonusTickets, true)) {
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $this->consumePetCooldownIfNeeded($player, $session, "pet.rafflefinch", $cooldown);
            $player->sendMessage(C::colorize("&r&6Raffle Finch Pet &r&7force-bought &f" . number_format($bonusTickets) . " &r&7bonus jackpot tickets."));
            PlayerUtils::playSound($player, "random.levelup");
            return;
        }

        if ($itemTag === "oathbondbeetle") {
            $event->cancel();
            $cooldown = PetManager::getCooldown("oathbondbeetle", 86400);
            if ($this->handleRelicCooldown($player, $session, "pet.oathbondbeetle", $cooldown, "Oathbond Beetle Pet")) {
                return;
            }

            $duration = (int) ceil(PetManager::getDuration("oathbondbeetle", "duration", 600) * RuneManager::getPetMultiplier($player, "oathbondbeetle"));
            PetManager::activateGuaranteedSuccess($player, $duration);
            $this->consumePetCooldownIfNeeded($player, $session, "pet.oathbondbeetle", $cooldown);
            $player->sendMessage(C::colorize("&r&aOathbond Beetle Pet &r&7will force your next success-rate apply to succeed within &f10 minutes&r&7."));
            PlayerUtils::playSound($player, "random.levelup");
            return;
        }

        if ($itemTag === "murmurloft") {
            $event->cancel();
            $cooldown = PetManager::getCooldown("murmurloft", 1800);
            if ($this->handleRelicCooldown($player, $session, "pet.murmurloft", $cooldown, "Murmurloft Pet")) {
                return;
            }

            if (CustomEnchantmentManager::getLevel($player->getInventory()->getItemInHand(), "silence") <= 0) {
                $player->sendMessage(C::colorize("&r&cYou need to be holding a weapon with &fSilence &r&cto use this pet."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $duration = (int) ceil(PetManager::getDuration("murmurloft", "duration", 7) * RuneManager::getPetMultiplier($player, "murmurloft"));
            PetManager::activateSilenceBurst($player, $duration);
            $this->consumePetCooldownIfNeeded($player, $session, "pet.murmurloft", $cooldown);
            $player->sendMessage(C::colorize("&r&5Murmurloft Pet &r&7supercharged your Silence enchant for &f{$duration} &r&7seconds."));
            PlayerUtils::playSound($player, "mob.endermen.portal");
            return;
        }

        if ($itemTag === "patchworksprite") {
            $event->cancel();
            $cooldown = PetManager::getCooldown("patchworksprite", 600);
            if ($this->handleRelicCooldown($player, $session, "pet.patchworksprite", $cooldown, "Patchwork Sprite Pet")) {
                return;
            }

            $repaired = GlaciaUtils::repairAll($player);
            $player->setHealth($player->getMaxHealth());
            $player->getHungerManager()->setFood(20);
            $player->getHungerManager()->setSaturation(20.0);
            $this->consumePetCooldownIfNeeded($player, $session, "pet.patchworksprite", $cooldown);
            $player->sendMessage(C::colorize("&r&bPatchwork Sprite Pet &r&7repaired &f{$repaired} &r&7item(s), healed you, and fed you."));
            PlayerUtils::playSound($player, "random.levelup");
            return;
        }

        if ($itemTag === "renderscalewyrm") {
            $event->cancel();
            $cooldown = PetManager::getCooldown("renderscalewyrm", 1800);
            if ($this->handleRelicCooldown($player, $session, "pet.renderscalewyrm", $cooldown, "Renderscale Wyrm Pet")) {
                return;
            }

            $duration = (int) ceil(PetManager::getDuration("renderscalewyrm", "duration", 8) * RuneManager::getPetMultiplier($player, "renderscalewyrm"));
            PetManager::activateDefenseBreak($player, $duration);
            $this->consumePetCooldownIfNeeded($player, $session, "pet.renderscalewyrm", $cooldown);
            $player->sendMessage(C::colorize("&r&cRenderscale Wyrm Pet &r&7opened an &f{$duration}s &r&7window to ignore defensive armor abilities and deal extra damage."));
            PlayerUtils::playSound($player, "mob.enderdragon.growl");
            return;
        }

        if ($itemTag === "skycourierdrake") {
            $event->cancel();
            $cooldown = PetManager::getCooldown("skycourierdrake", 14400);
            if ($this->handleRelicCooldown($player, $session, "pet.skycourierdrake", $cooldown, "Skycourier Drake Pet")) {
                return;
            }

            $duration = (int) ceil(PetManager::getDuration("skycourierdrake", "duration", 1800) * RuneManager::getPetMultiplier($player, "skycourierdrake"));
            PetManager::activateEnvoyUpgrade($player, $duration);
            $this->consumePetCooldownIfNeeded($player, $session, "pet.skycourierdrake", $cooldown);
            $player->sendMessage(C::colorize("&r&eSkycourier Drake Pet &r&7will give your envoys a &f10% &r&7upgrade chance for &f30 minutes&r&7."));
            PlayerUtils::playSound($player, "random.levelup");
            return;
        }

        if ($itemTag === "inkwellfinch") {
            $event->cancel();
            $cooldown = PetManager::getCooldown("inkwellfinch", 14400);
            if ($this->handleRelicCooldown($player, $session, "pet.inkwellfinch", $cooldown, "Inkwell Finch Pet")) {
                return;
            }

            $duration = (int) ceil(PetManager::getDuration("inkwellfinch", "duration", 600) * RuneManager::getPetMultiplier($player, "inkwellfinch"));
            $discount = [10, 20, 25][array_rand([10, 20, 25])];
            PetManager::activateEnchanterDiscount($player, $duration, $discount);
            $this->consumePetCooldownIfNeeded($player, $session, "pet.inkwellfinch", $cooldown);
            $player->sendMessage(C::colorize("&r&eInkwell Finch Pet &r&7rolled a &f{$discount}% &r&7/enchanter discount for &f10 minutes&r&7."));
            PlayerUtils::playSound($player, "random.levelup");
            return;
        }

        if ($itemTag === "terracrestjackal") {
            $event->cancel();
            $cooldown = PetManager::getCooldown("terracrestjackal", 7200);
            if ($this->handleRelicCooldown($player, $session, "pet.terracrestjackal", $cooldown, "Terracrest Jackal Pet")) {
                return;
            }

            $duration = (int) ceil(PetManager::getDuration("terracrestjackal", "duration", 300) * RuneManager::getPetMultiplier($player, "terracrestjackal"));
            PetManager::activateZoneXp($player, $duration);
            $this->consumePetCooldownIfNeeded($player, $session, "pet.terracrestjackal", $cooldown);
            $player->sendMessage(C::colorize("&r&3Terracrest Jackal Pet &r&7boosted your zone mob XP by &fx2.5 &r&7for &f5 minutes&r&7."));
            PlayerUtils::playSound($player, "random.levelup");
            return;
        }

        if ($itemTag === "soulsurge") {
            $event->cancel();
            $cooldown = PetManager::getCooldown("soulsurge", 21600);
            if ($this->handleRelicCooldown($player, $session, "pet.soulsurge", $cooldown, "Soul Surge Pet")) {
                return;
            }

            $duration = (int) ceil(PetManager::getDuration("soulsurge", "duration", 1200) * RuneManager::getPetMultiplier($player, "soulsurge"));
            PetManager::activateFreeSoulCost($player, $duration);
            $this->consumePetCooldownIfNeeded($player, $session, "pet.soulsurge", $cooldown);
            $player->sendMessage(C::colorize("&r&4Soul Surge Pet &r&7reduced enchant soul costs to &f0 &r&7for &f20 minutes&r&7."));
            PlayerUtils::playSound($player, "random.levelup");
            return;
        }

        if ($itemTag === "ruinspiremoth") {
            $event->cancel();
            $cooldown = PetManager::getCooldown("ruinspiremoth", 345600);
            if ($this->handleRelicCooldown($player, $session, "pet.ruinspiremoth", $cooldown, "Ruinspire Moth Pet")) {
                return;
            }

            $armLevel = 0;
            foreach ($player->getArmorInventory()->getContents() as $piece) {
                $armLevel = max($armLevel, CustomEnchantmentManager::getLevel($piece, "armageddon"));
            }
            if ($armLevel <= 0) {
                $player->sendMessage(C::colorize("&r&cYour armor needs &fArmageddon &r&cbefore Ruinspire Moth can ignite."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $searchR = max(4, PetManager::getInt("ruinspiremoth", "target-search-radius-blocks", 12));
            $nearest = null;
            $nearestSq = INF;
            $pos = $player->getPosition();
            foreach ($player->getWorld()->getPlayers() as $other) {
                if ($other->getId() === $player->getId()) {
                    continue;
                }
                if (GlaciaUtils::isAlly($player, $other)) {
                    continue;
                }
                $d = $other->getPosition()->distanceSquared($pos);
                if ($d <= ($searchR * $searchR) && $d < $nearestSq) {
                    $nearestSq = $d;
                    $nearest = $other;
                }
            }
            if ($nearest === null) {
                $player->sendMessage(C::colorize("&r&cNo enemy player within range to anchor Armageddon on."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $result = ArmageddonEnchant::triggerPetProc($player, $nearest, $armLevel);
            $aura = max(
                1,
                (int) ceil($result["auraSeconds"] * RuneManager::getPetMultiplier($player, "ruinspiremoth"))
            );

            $debuffR = max(3, PetManager::getInt("ruinspiremoth", "debuff-radius-blocks", 11));
            $fxTicks = max(30, PetManager::getInt("ruinspiremoth", "debuff-effect-duration-ticks", 45));

            $ctx = new \stdClass();
            $ctx->tickIdx = 0;
            $ctx->handler = null;
            $ctx->handler = Loader::getInstance()->getScheduler()->scheduleRepeatingTask(new ClosureTask(
                function () use ($player, $aura, $debuffR, $fxTicks, $ctx): void {
                    $ctx->tickIdx++;
                    if (!$player->isConnected() || !$player->isAlive() || $ctx->tickIdx > $aura) {
                        $ctx->handler?->cancel();
                        return;
                    }

                    $center = $player->getPosition();
                    foreach ($player->getWorld()->getPlayers() as $target) {
                        if ($target->getId() === $player->getId()) {
                            continue;
                        }
                        if (GlaciaUtils::isAlly($player, $target)) {
                            continue;
                        }
                        if ($target->getPosition()->distanceSquared($center) > ($debuffR * $debuffR)) {
                            continue;
                        }
                        $target->getEffects()->add(new EffectInstance(VanillaEffects::WEAKNESS(), $fxTicks, 4, false));
                        $target->getEffects()->add(new EffectInstance(VanillaEffects::SLOWNESS(), $fxTicks, 2, false));
                    }
                }
            ), 20);

            $this->consumePetCooldownIfNeeded($player, $session, "pet.ruinspiremoth", $cooldown);
            $player->sendMessage(C::colorize("&r&cRuinspire Moth Pet &r&7unleashed calamity toward &f" . $nearest->getName() . "&r&7; foes nearby are shackled."));
            PlayerUtils::playSound($player, "ambient.weather.lightning_impact");
            return;
        }

        if ($itemTag === "bellforgecustodian") {
            $event->cancel();
            $cooldown = PetManager::getCooldown("bellforgecustodian", 1800);
            if ($this->handleRelicCooldown($player, $session, "pet.bellforgecustodian", $cooldown, "Bellforge Custodian Pet")) {
                return;
            }

            $golems = WatchmenEnchant::getActiveWatchmenGolems($player);
            if ($golems === []) {
                $player->sendMessage(C::colorize("&r&cYou need an active Guardians golem to ring the Bellforge Custodian."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $strTicks = max(40, PetManager::getDuration("bellforgecustodian", "strength-duration", 8) * 20);
            $noKb = PetManager::getDuration("bellforgecustodian", "no-knockback-duration", 6);

            foreach ($golems as $golem) {
                $golem->getEffects()->add(new EffectInstance(VanillaEffects::STRENGTH(), $strTicks, 2, false));
            }

            PetManager::activateBriefKnockbackImmunity($player, $noKb);

            $healEvery = max(10, PetManager::getInt("bellforgecustodian", "heal-interval-ticks", 40));
            $addHp = max(1, PetManager::getInt("bellforgecustodian", "heal-hp-tenths", 10)) / 10;

            foreach ($golems as $golem) {
                $gRef = $golem;
                $healCtx = new \stdClass();
                $healCtx->handler = null;
                $healCtx->handler = Loader::getInstance()->getScheduler()->scheduleRepeatingTask(new ClosureTask(
                    function () use ($gRef, $addHp, &$healCtx): void {
                        if (!$gRef instanceof WatchmenGolem || $gRef->isClosed() || !$gRef->isAlive()) {
                            $healCtx->handler?->cancel();

                            return;
                        }
                        $max = $gRef->getMaxHealth();
                        $gRef->setHealth(min($max, $gRef->getHealth() + $addHp));
                    }
                ), $healEvery);
            }

            $this->consumePetCooldownIfNeeded($player, $session, "pet.bellforgecustodian", $cooldown);
            $player->sendMessage(C::colorize("&r&7Bellforge Custodian &r&7tempered &f" . count($golems) . " watchman(s)&r&7 and steadied your stance."));
            PlayerUtils::playSound($player, "random.anvil_land");
            return;
        }

        if ($itemTag === "arcbindercodex") {
            $event->cancel();
            $cooldown = PetManager::getCooldown("arcbindercodex", 10800);
            if ($this->handleRelicCooldown($player, $session, "pet.arcbindercodex", $cooldown, "Arcbinder Codex Pet")) {
                return;
            }

            $dur = (int) ceil(PetManager::getDuration("arcbindercodex", "apply-boost-duration", 1800) * RuneManager::getPetMultiplier($player, "arcbindercodex"));
            PetManager::activateArcCodexCharm($player, $dur);

            $this->consumePetCooldownIfNeeded($player, $session, "pet.arcbindercodex", $cooldown);
            $bonus = PetManager::getInt("arcbindercodex", "apply-bonus-percent", 5);
            $player->sendMessage(C::colorize("&r&5Arcbinder Codex Pet &r&7made your inscription rolls &f+" . $bonus . "% &r&7sharper for this window."));
            PlayerUtils::playSound($player, "random.levelup");
            return;
        }

        if ($itemTag === "crownspirekestrel") {
            $event->cancel();
            $cooldown = PetManager::getCooldown("crownspirekestrel", 21600);
            if ($this->handleRelicCooldown($player, $session, "pet.crownspirekestrel", $cooldown, "Crownspire Kestrel Pet")) {
                return;
            }

            PetManager::activateGKitUpgradeCharm($player);

            $this->consumePetCooldownIfNeeded($player, $session, "pet.crownspirekestrel", $cooldown);
            $bonus = PetManager::getInt("crownspirekestrel", "upgrade-roll-bonus-percent", 20);
            $player->sendMessage(C::colorize("&r&eCrownspire Kestrel Pet &r&7promised &f+" . $bonus . "% &r&7tier pressure on your next &f/gkits &r&7pull within its hour."));
            PlayerUtils::playSound($player, "random.levelup");
            return;
        }

        if ($itemTag === "shadebrokercrow") {
            $event->cancel();
            $cooldown = PetManager::getCooldown("shadebrokercrow", 86400);
            if ($this->handleRelicCooldown($player, $session, "pet.shadebrokercrow", $cooldown, "Shadebroker Crow Pet")) {
                return;
            }

            $loot = BlackMarketAuctionManager::getInstance()->resolveRandomPoolReward($player);
            if ($loot === null) {
                $player->sendMessage(C::colorize("&r&cThe Black Auction pool is empty or misconfigured right now."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            foreach ($player->getInventory()->addItem($loot) as $left) {
                $session->addItemToCollection($left);
            }

            $this->consumePetCooldownIfNeeded($player, $session, "pet.shadebrokercrow", $cooldown);
            $label = $loot->getCustomName();
            $pretty = $label !== "" ? C::clean($label) : C::clean($loot->getName());
            $player->sendMessage(C::colorize("&r&8Shadebroker Crow &r&7delivered something from the &fDark Lots&r&7: &f" . $pretty . "&r&7."));
            PlayerUtils::playSound($player, "random.levelup");
            return;
        }

        if ($itemTag === "faction_xp_booster") {
            $event->cancel();

            $faction = $session?->getFaction();
            if ($faction === null) {
                $player->sendMessage(C::colorize("&r&cYou must be in a faction to activate this booster."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $manager = Loader::getFactionXpBoosterManager();
            if ($manager->hasActiveBooster($faction)) {
                $player->sendMessage(C::colorize("&r&cYour faction already has an active XP booster."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $multiplierRaw = $glaciaTag->getTag("multiplier");
            $multiplier = $multiplierRaw !== null ? (float) $multiplierRaw->getValue() : 1.0;
            $durationText = $glaciaTag->getString("duration", "");
            $durationSeconds = GeneralUtils::parseTimeString($durationText);

            if ($multiplier <= 0 || $durationSeconds <= 0) {
                $player->sendMessage(C::colorize("&r&cThis booster is invalid and could not be activated."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $manager->activate($faction, $multiplier, $durationSeconds, $player);
            Loader::getQuestBoardManager()->recordProgress($player, "xp_boosters_activated", 1, [
                "booster" => "faction_xp",
            ]);
            PlayerUtils::playSound($player, "random.levelup");
            $item->pop();
            $player->getInventory()->setItemInHand($item);
            return;
        }

        if ($itemTag === "zone_xp_booster") {
            $event->cancel();

            $zoneId = strtolower(trim($glaciaTag->getString("zone_id", $glaciaTag->getString("zone", ""))));
            if ($zoneId === "" || in_array($zoneId, ["any zone", "any", "universal", "*"], true)) {
                $zone = ZoneManager::getZoneAt($player->getPosition());
                $zoneId = strtolower(trim((string) ($zone["id"] ?? "")));
            }

            if ($zoneId === "" || ZoneManager::getZone($zoneId) === null) {
                $player->sendMessage(C::colorize("&r&cStand in a valid zone to activate this booster."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $zoneBooster = Loader::getZoneBoosterManager();
            if ($zoneBooster->getActiveBooster($zoneId, ZoneBoosterManager::TYPE_ZONE_XP) !== null) {
                $player->sendMessage(C::colorize("&r&cThat zone already has an active " . $zoneBooster->getTypeDisplayName(ZoneBoosterManager::TYPE_ZONE_XP) . "."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $multiplierRaw = $glaciaTag->getTag("multiplier");
            $multiplier = $multiplierRaw !== null ? (float) $multiplierRaw->getValue() : 1.0;
            $durationText = $glaciaTag->getString("duration", "");
            $durationSeconds = GeneralUtils::parseTimeString($durationText);

            if ($multiplier <= 0 || $durationSeconds <= 0) {
                $player->sendMessage(C::colorize("&r&cThis booster is invalid and could not be activated."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            if (!$zoneBooster->activate($zoneId, ZoneBoosterManager::TYPE_ZONE_XP, $multiplier, $durationSeconds, $player->getName())) {
                $player->sendMessage(C::colorize("&r&cCould not activate this booster."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            Loader::getQuestBoardManager()->recordProgress($player, "zone_boosters_activated", 1, [
                "zone_id" => $zoneId,
                "booster_key" => "zone_xp:" . $zoneId,
            ]);
            PlayerUtils::playSound($player, "random.levelup");
            $item->pop();
            $player->getInventory()->setItemInHand($item);
            return;
        }

        if ($itemTag === "zone_fishing_booster") {
            $event->cancel();

            $zoneId = strtolower(trim($glaciaTag->getString("zone_id", $glaciaTag->getString("zone", ""))));
            if ($zoneId === "" || in_array($zoneId, ["any zone", "any", "universal", "*"], true)) {
                $zone = ZoneManager::getZoneAt($player->getPosition());
                $zoneId = strtolower(trim((string) ($zone["id"] ?? "")));
            }

            if ($zoneId === "" || ZoneManager::getZone($zoneId) === null) {
                $player->sendMessage(C::colorize("&r&cStand in a valid zone to activate this booster."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $zoneBooster = Loader::getZoneBoosterManager();
            if ($zoneBooster->getActiveBooster($zoneId, ZoneBoosterManager::TYPE_FISHING) !== null) {
                $player->sendMessage(C::colorize("&r&cThat zone already has an active " . $zoneBooster->getTypeDisplayName(ZoneBoosterManager::TYPE_FISHING) . "."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $multiplierRaw = $glaciaTag->getTag("multiplier");
            $multiplier = $multiplierRaw !== null ? (float) $multiplierRaw->getValue() : 1.0;
            $durationText = $glaciaTag->getString("duration", "");
            $durationSeconds = GeneralUtils::parseTimeString($durationText);

            if ($multiplier <= 0 || $durationSeconds <= 0) {
                $player->sendMessage(C::colorize("&r&cThis booster is invalid and could not be activated."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            if (!$zoneBooster->activate($zoneId, ZoneBoosterManager::TYPE_FISHING, $multiplier, $durationSeconds, $player->getName())) {
                $player->sendMessage(C::colorize("&r&cCould not activate this booster."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            Loader::getQuestBoardManager()->recordProgress($player, "zone_boosters_activated", 1, [
                "zone_id" => $zoneId,
                "booster_key" => "zone_fishing:" . $zoneId,
            ]);
            PlayerUtils::playSound($player, "random.levelup");
            $item->pop();
            $player->getInventory()->setItemInHand($item);
            return;
        }

        if ($itemTag === "gkit_gem") {
            $event->cancel();

            $kitId = strtolower($glaciaTag->getString("kit", ""));
            if ($kitId === "") {
                $player->sendMessage(C::colorize("&r&cThis GKit Gem is missing its kit data."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            if (!GKitRepository::redeemGem($player, $kitId, 1)) {
                $player->sendMessage(C::colorize("&r&cThat GKit Gem could not be redeemed."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $item->pop();
            $player->getInventory()->setItemInHand($item);
            return;
        }

        if ($itemTag === "gkit_summoner") {
            $event->cancel();

            $kitId = strtolower($glaciaTag->getString("kit", ""));
            $kit = $kitId !== "" ? GKitRepository::getGKitById($kitId) : null;
            if ($kit === null) {
                $player->sendMessage(C::colorize("&r&cThis summoner is invalid."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $region = Loader::getRegionManager()->getRegionAt($player->getPosition());
            if ($region === null || strcasecmp($region->getName(), "Warzone") !== 0) {
                $player->sendMessage(C::colorize("&r&cYou can only use this in the &4Warzone&r&c."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }
            $warlordDenyReason = null;
            if (!$this->canSpawnWarlord($player, $warlordDenyReason)) {
                $player->sendMessage(C::colorize("&r&c" . ($warlordDenyReason ?? "You cannot summon a warlord right now.")));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $warlord = WarlordRepository::getForKit($kit);
            $reward = GKitRepository::createChampionReward($kit);
            if ($reward === null) {
                $player->sendMessage(C::colorize("&r&cThis GKit has no champion rewards configured."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $armorPool = [];
            $weaponPool = [];
            foreach (GKitRepository::resolveItemsForLevel($kit, max(1, GKitRepository::getMaxLevel($kit))) as $gearItem) {
                if ($gearItem instanceof Armor) {
                    $armorPool[] = $this->sanitizeChampionArmor(clone $gearItem);
                } elseif ($gearItem instanceof Sword || $gearItem instanceof Axe || $gearItem instanceof Bow) {
                    $weaponPool[] = clone $gearItem;
                }
            }

            $armorTag = new CompoundTag();
            foreach (array_slice($armorPool, 0, 4) as $slot => $armorPiece) {
                $armorPiece->setCount(1);
                $armorTag->setString((string) $slot, ItemUtils::encodeItem($armorPiece));
            }

            $position = $player->getPosition()->add(0, 1, 0);
            $nbt = CompoundTag::create()
                ->setInt("gkit_health", max(20, (int) ($warlord["health"] ?? 80)))
                ->setInt("gkit_damage", max(1, (int) ($warlord["damage"] ?? 9)))
                ->setFloat("gkit_move_speed", (float) ($warlord["move-speed"] ?? 0.24))
                ->setFloat("gkit_attack_reach", (float) ($warlord["attack-reach"] ?? 2.2))
                ->setFloat("gkit_target_range", (float) ($warlord["target-range"] ?? 24.0))
                ->setFloat("gkit_jump_height", (float) ($warlord["jump-height"] ?? 0.42))
                ->setInt("gkit_attack_cooldown", max(4, (int) ($warlord["attack-cooldown"] ?? 9)))
                ->setString("gkit_warlord", (string) ($warlord["id"] ?? $kitId))
                ->setString("gkit_name", C::colorize((string) ($warlord["entity-name"] ?? "&r&8Warlord")))
                ->setString("gkit_reward", ItemUtils::encodeItem($reward))
                ->setTag("gkit_armor", $armorTag);

            if ($weaponPool !== []) {
                $weapon = clone $weaponPool[array_rand($weaponPool)];
                $weapon->setCount(1);
                $nbt->setString("gkit_weapon", ItemUtils::encodeItem($weapon));
            }

            $champion = new ForsakenChampion(new Location(
                $position->x,
                $position->y,
                $position->z,
                $player->getWorld(),
                lcg_value() * 360,
                0
            ), $nbt);
            $champion->spawnToAll();
            $this->registerWarlordSpawn($champion, $player);

            $item->pop();
            $player->getInventory()->setItemInHand($item);
            $spawnMessage = str_replace(
                ["{player}", "{display}", "{gkit}"],
                [$player->getName(), (string) ($warlord["display"] ?? $kit["display"] ?? $kitId), (string) ($warlord["display"] ?? $kit["display"] ?? $kitId)],
                (string) ($warlord["spawn-message"] ?? "&r&8A fractured warlord has emerged in the Warzone.")
            );
            $player->sendTitle(
                C::colorize((string) ($warlord["spawn-title"] ?? "&r&8Warlord Summoned")),
                C::colorize((string) ($warlord["spawn-subtitle"] ?? "&r&7The Warzone trembles."))
            );
            $player->sendMessage(C::colorize($spawnMessage));
            PlayerUtils::playSound($player, "mob.wither.spawn");
            foreach ($player->getWorld()->getPlayers() as $worldPlayer) {
                if ($worldPlayer === $player || $worldPlayer->getPosition()->distanceSquared($position) > 64 * 64) {
                    continue;
                }

                $worldPlayer->sendMessage(C::colorize($spawnMessage));
                PlayerUtils::playSound($worldPlayer, "mob.wither.spawn");
            }
            return;
        }

        if ($itemTag === "soul_compass") {
            $event->cancel();

            $envoyManager = Loader::getEnvoyManager();
            if ($envoyManager->getActive() === null) {
                $player->sendMessage(C::colorize(
                    "&r&bSoul Compass &8| &r&7No active signal. "
                    . "&fNext Envoy &7in &b" . GeneralUtils::translateTime($envoyManager->getTimeLeft()) . "&7."
                ));
                PlayerUtils::playSound($player, "random.click");
                return;
            }

            $nearest = $envoyManager->getNearestCrate($player->getPosition());
            if ($nearest === null) {
                $player->sendMessage(C::colorize("&r&bSoul Compass &8| &r&7The signal is unstable right now."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $uses = max(0, $this->readFlexibleIntTag($glaciaTag, "uses", $this->getSoulCompassDefaultUses()));
            if ($uses <= 0) {
                $player->sendMessage(C::colorize("&r&cThis Soul Compass has lost all of its charge."));
                PlayerUtils::playSound($player, "note.bass");
                $player->getInventory()->setItemInHand(VanillaItems::AIR());
                return;
            }

            $crateX = (int) ($nearest["x"] ?? 0);
            $crateY = (int) ($nearest["y"] ?? 0);
            $crateZ = (int) ($nearest["z"] ?? 0);
            $crateType = (string) ($nearest["type"] ?? "simple");
            $direction = $this->formatCompassDirection(
                ($crateX + 0.5) - $player->getPosition()->x,
                ($crateZ + 0.5) - $player->getPosition()->z
            );
            $distance = (int) round($player->getPosition()->distance(new Vector3($crateX + 0.5, $crateY + 0.5, $crateZ + 0.5)));

            $uses--;
            if ($uses <= 0) {
                $player->getInventory()->setItemInHand(VanillaItems::AIR());
                $useText = "&c0";
            } else {
                  $player->getInventory()->setItemInHand(GlaciaItemFactory::soulCompass($uses));
                  $useText = "&f" . $uses;
              }
  
              $player->sendMessage(C::colorize(
                  "&r&bSoul Compass &8| &r&7A " . ucfirst($crateType) . " signal stirs &8(&b{$direction}&8)&7. "
                  . "&7Follow the soul trail. &7Uses left: {$useText}&7."
              ));
              $this->startSoulCompassTrail($player, new Vector3($crateX + 0.5, $crateY + 0.5, $crateZ + 0.5), $crateType, $distance);
              PlayerUtils::playSound($player, "random.orb");
              return;
          }

        if ($itemTag === "envoy_beacon") {
            $event->cancel();

            $envoyManager = Loader::getEnvoyManager();
            if ($envoyManager->getActive() !== null) {
                $player->sendMessage(C::colorize("&r&cAn envoy is already active."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            if (!$envoyManager->forceStart()) {
                $player->sendMessage(C::colorize("&r&cThe envoy could not be started right now."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $item->pop();
            $player->getInventory()->setItemInHand($item);
            $player->sendMessage(C::colorize("&r&a✔ &r&7You activated an &bEnvoy Beacon&7."));
            PlayerUtils::playSound($player, "random.levelup");
            return;
        }

        if ($itemTag === "rift_summoner") {
            $event->cancel();
            $riftsEnabled = false;

            if (!$riftsEnabled) {
                $player->sendMessage(C::colorize("&r&l&c(!) &r&cRifts are currently disabled."));
                return;
            }

            RiftManager::requestSpawn(
                function(Position $pos, RiftEntity $entity) use ($player, $item) {
                    $item->pop();
                    $player->getInventory()->setItemInHand($item);
                    $player->sendMessage(C::colorize("&r&l&a[!] &r&aYou have summoned a Rift!"));
                },
                function($reason = null) use ($player) {
                    if ($reason === "max_rifts") {
                        $player->sendMessage(C::colorize("&r&cYou cannot summon a Rift right now. Too many active rifts are already open."));
                    } else {
                        $player->sendMessage(C::colorize("&r&cFailed to summon a Rift. Try again in a moment."));
                    }
                }
            );        
            return;
        }

        if ($itemTag === "bossspawn") {
            $event->cancel();

            $bossName = $glaciaTag->getString("boss", "");
            $regionManager = Loader::getRegionManager();

            $currentRegion = $regionManager->getRegionAt($player->getPosition());

            if ($currentRegion === $regionManager->getRegionByName("Spawn")) {
                $this->sendRegionDenied($player);
                return;
            }

            if (!$this->isInWarzoneRegion($player->getPosition())) {
                $player->sendMessage(C::colorize("&r&cYou can only summon that boss in the &4Warzone&r&c."));
                return;
            }

            switch ($bossName) {
                case "astral_devourer":
                    $bossDenyReason = null;
                    if (!$this->canSpawnBoss($player, $bossName, $bossDenyReason)) {
                        $player->sendMessage(C::colorize("&r&c" . ($bossDenyReason ?? "You cannot summon a boss right now.")));
                        PlayerUtils::playSound($player, "note.bass");
                        break;
                    }
                    $boss = new AstralDevourer($player->getLocation());
                    $boss->spawnToAll();
                    $this->registerBossSpawn($boss, $player, $bossName);

                    PlayerUtils::playSound($player, "mob.wither.spawn");
                    $player->sendMessage(C::colorize("&r&l&a[!] &r&aYou have summoned an Astral Devourer!"));

                    $item->pop();
                    $player->getInventory()->setItemInHand($item);
                    break;

                case "titan_knight":
                    $bossDenyReason = null;
                    if (!$this->canSpawnBoss($player, $bossName, $bossDenyReason)) {
                        $player->sendMessage(C::colorize("&r&c" . ($bossDenyReason ?? "You cannot summon a boss right now.")));
                        PlayerUtils::playSound($player, "note.bass");
                        break;
                    }
                    $boss = new TitanKnight($player->getLocation());
                    $boss->spawnToAll();
                    $this->registerBossSpawn($boss, $player, $bossName);

                    PlayerUtils::playSound($player, "mob.wither.spawn");
                    $player->sendMessage(C::colorize("&r&l&a[!] &r&aYou have summoned the &5Astral Sovereign&a!"));

                    $item->pop();
                    $player->getInventory()->setItemInHand($item);
                    break;

                case "glacial_hydra":
                    $bossDenyReason = null;
                    if (!$this->canSpawnBoss($player, $bossName, $bossDenyReason)) {
                        $player->sendMessage(C::colorize("&r&c" . ($bossDenyReason ?? "You cannot summon a boss right now.")));
                        PlayerUtils::playSound($player, "note.bass");
                        break;
                    }
                    $boss = new GlacialHydra($player->getLocation());
                    $boss->spawnToAll();
                    $this->registerBossSpawn($boss, $player, $bossName);

                    PlayerUtils::playSound($player, "mob.enderdragon.growl");
                    $player->sendMessage(C::colorize("&r&l&a[!] &r&aYou have summoned the &bGlacial Hydra&a!"));

                    $item->pop();
                    $player->getInventory()->setItemInHand($item);
                    break;

                default:
                    $player->sendMessage(C::colorize("&r&cThat boss cannot be summoned right now."));
            }

            return;
        }
        
        if ($itemTag === "bloodynote") {
            $event->cancel();
            $config = GeneralUtils::getConfiguration(Loader::getInstance(), "items.yml");
            

            $amount = GeneralUtils::parseRandomNumber($config->getNested("bloodynotes.pool"));
            $session = Loader::getPlayerManager()->getSession($player);

            $session->addBalance($amount);
            $player->sendMessage(C::colorize("&r&aYou have uncovered a &r&l&cBloody Note&r&a worth: $" . number_format($amount)));
            PlayerUtils::playSound($player, "random.levelup");
            $item->pop();
            $player->getInventory()->setItemInHand($item);
            return;
        }
        
        if ($itemTag === "ethereal_simple_chest") {
            $event->cancel();

            if ($player->isSneaking()) {
                $this->displayDropPackagePreview($player, "simple", "Simple Ethereal Chest");
                return;
            }

            $session = new DropPackageSession(
                "simple",
                "Simple Ethereal Chest",
                "&r&l&8Simple Ethereal Chest",
                5
            );

            DropPackageScreen::display($player, $session);

            $item->pop();
            $player->getInventory()->setItemInHand($item);
            return;
        }

        if ($itemTag === "ethereal_unique_chest") {
            $event->cancel();

            if ($player->isSneaking()) {
                $this->displayDropPackagePreview($player, "unique", "Unique Ethereal Chest");
                return;
            }

            $session = new DropPackageSession(
                "unique",
                "Unique Ethereal Chest",
                "&r&l&aUnique Ethereal Chest",
                5
            );

            DropPackageScreen::display($player, $session);

            $item->pop();
            $player->getInventory()->setItemInHand($item);
            return;
        }

        if ($itemTag === "ethereal_elite_chest") {
            $event->cancel();

            if ($player->isSneaking()) {
                $this->displayDropPackagePreview($player, "elite", "Elite Ethereal Chest");
                return;
            }

            $session = new DropPackageSession(
                "elite",
                "Elite Ethereal Chest",
                "&r&l&bElite Ethereal Chest",
                5
            );

            DropPackageScreen::display($player, $session);

            $item->pop();
            $player->getInventory()->setItemInHand($item);
            return;
        }

        if ($itemTag === "ethereal_exotic_chest") {
            $event->cancel();

            if ($player->isSneaking()) {
                $this->displayDropPackagePreview($player, "exotic", "Exotic Ethereal Chest");
                return;
            }

            $session = new DropPackageSession(
                "exotic",
                "Exotic Ethereal Chest",
                "&r&l&eExotic Ethereal Chest",
                5
            );

            DropPackageScreen::display($player, $session);

            $item->pop();
            $player->getInventory()->setItemInHand($item);
            return;
        }

        if ($itemTag === "ethereal_legendary_chest") {
            $event->cancel();

            if ($player->isSneaking()) {
                $this->displayDropPackagePreview($player, "legendary", "Legendary Ethereal Chest");
                return;
            }

            $session = new DropPackageSession(
                "legendary",
                "Legendary Ethereal Chest",
                "&r&l&6Legendary Ethereal Chest",
                5
            );

            DropPackageScreen::display($player, $session);
            $item->pop();
            $player->getInventory()->setItemInHand($item);
            Loader::getQuestBoardManager()->recordProgress($player, "godly_ethereal_chests_opened", 1);
            return;
        }

        if ($itemTag === "ethereal_godly_chest") {
            $event->cancel();

            if ($player->isSneaking()) {
                $this->displayDropPackagePreview($player, "godly", "Godly Ethereal Chest");
                return;
            }

            $session = new DropPackageSession(
                "godly",
                "Godly Ethereal Chest",
                "&r&l&cGodly Ethereal Chest",
                5
            );

            DropPackageScreen::display($player, $session);
            $item->pop();
            $player->getInventory()->setItemInHand($item);
            return;
        }

        if ($itemTag === "mysterymobspawner") {
            $event->cancel();

            // Available spawners from this cache
            $itemsConfig = GeneralUtils::getConfiguration(Loader::getInstance(), "items.yml");
            $spawners = $itemsConfig->getNested("mysterymobspawner.pool");

            $chosen = GlaciaUtils::getWeightedRandomSpawner($spawners);

            $reward = StringToItemParser::getInstance()->parse($chosen . "_spawner");

            if ($player->getInventory()->canAddItem($reward)) {
                $player->getInventory()->addItem($reward);
                PlayerUtils::playSound($player, "random.levelup");
            } else {
                $session->addItemToCollection($reward);
            }

            $player->sendMessage(C::colorize("&r&l&7* &r&fYou have examined a &dMystery Mob Spawner&r&f and found a &b" . str_replace("_", " ", ucfirst($chosen)) . " Spawner&r&f!"));
            $item->pop();
            $player->getInventory()->setItemInHand($item);
            return;
        }

        if ($itemTag === "heroic_mysterymobspawner") {
            $event->cancel();

            // Available spawners from this cache
            $itemsConfig = GeneralUtils::getConfiguration(Loader::getInstance(), "items.yml");
            $spawners = $itemsConfig->getNested("heroic_mysterymobspawner.pool");

            $chosen = GlaciaUtils::getWeightedRandomSpawner($spawners);

            $reward = StringToItemParser::getInstance()->parse($chosen . "_spawner");

            if ($player->getInventory()->canAddItem($reward)) {
                $player->getInventory()->addItem($reward);
                PlayerUtils::playSound($player, "random.levelup");
            } else {
                $session->addItemToCollection($reward);
            }

            $player->sendMessage(C::colorize("&r&l&7* &r&fYou have examined a &dMystery Mob Spawner&r&f and found a &b" . str_replace("_", " ", ucfirst($chosen)) . " Spawner&r&f!"));
            $item->pop();
            $player->getInventory()->setItemInHand($item);
            return;
        }
        
        if ($itemTag === "random_magicdust") {
            $event->cancel();

            $group = $glaciaTag->getString("group", "");
            
            $reward = GlaciaItemFactory::magicDust($group, mt_rand(1, 10));

            if ($player->getInventory()->canAddItem($reward)) {
                $player->getInventory()->addItem($reward);
                PlayerUtils::playSound($player, "random.levelup");
            } else {
                $player->getWorld()->dropItem($player->getPosition(), $reward);
            }

            $player->sendMessage(C::colorize("&r&l&7* &r&fYou have examined a &dRandom Magic Dust&r&f and found &a" . str_replace("_", " ", ucfirst($group)) . " Magic Dust&r&f!"));
            $item->pop();
            $player->getInventory()->setItemInHand($item);
            return;
        }

        if ($itemTag === "enchantment_book") {
            $event->cancel();
            $enchant = $glaciaTag->getString("enchant_book", "");
            $level = NbtLenient::readInt($glaciaTag, "level", 0, 0, 255, null);

            if ($level < 1) {
                $player->sendMessage(C::colorize("&r&cInvalid level!"));
                return;
            }

            $bookType = strtolower($glaciaTag->getString("book_type", "custom"));
            if ($bookType === "vanilla" || $glaciaTag->getString("vanilla_enchant", "") !== "") {
                $vanillaEnchant = $glaciaTag->getString("vanilla_enchant", $enchant);
                $enchantment = GlaciaItemFactory::resolveVanillaEnchantment($vanillaEnchant);
                if ($enchantment === null) {
                    $player->sendMessage(C::colorize("&r&cUnknown enchantment!"));
                    return;
                }

                $player->sendMessage(C::colorize("&r&7 * &eEnchantment &7| &7" . implode(' ', array_map('ucfirst', preg_split('/[_\-\s]+/', $vanillaEnchant) ?: [$vanillaEnchant])) . " " . GeneralUtils::getRomanNumeral($level)));
                $player->sendMessage(C::colorize("&r&7 * &eApplies with &7| &fVanilla enchant drag-and-drop"));
                $player->sendMessage(C::colorize("&r&7 * &eMax Level &7| &f" . GeneralUtils::getRomanNumeral($enchantment->getMaxLevel())));
                $player->sendMessage(C::colorize("&r&7 * &eDescription &7| &f" . GlaciaItemFactory::getVanillaEnchantmentDescription($vanillaEnchant)));
                return;
            }

            $enchantment = CustomEnchantments::getEnchantmentByName($enchant);
            if ($enchantment === null) {
                $player->sendMessage(C::colorize("&r&cUnknown enchantment!"));
                return;
            }

            $color = Groups::translateGroupToColor($enchantment->getRarity());
            $player->sendMessage(C::colorize("&r&7 * &eEnchantment &7| " . str_replace("{group-color}", $color, CustomEnchantments::getEnchantmentDisplayName($enchantment->getName(), $color))));
            $player->sendMessage(C::colorize("&r&7 * &eApplies to &7| &f" . implode(", ", $enchantment->getApplicableItems())));
            $player->sendMessage(C::colorize("&r&7 * &eMax Level &7| &f" . GeneralUtils::getRomanNumeral($enchantment->getMaxLevel())));
            $player->sendMessage(C::colorize("&r&7 * &eDescription &7| &f" . $enchantment->getDescription()));
            return;
        }

        if ($itemTag === "random_book") {
            $event->cancel();
            $group = $glaciaTag->getString("group", "");
            if ($this->isEndfallRestrictedEnchantGroup($group) && $this->isPlayerInsideEndfall($player)) {
                $player->sendMessage(C::colorize("&r&cMastery and Exclusive enchant books are disabled in Endfall."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }
            $groupEnchants = CustomEnchantments::getByGroup($group);
            $color = Groups::translateGroupToColor(Groups::getGroupId(strtolower($group)));

            if (strtolower($group) === "mastery") {
                $blocked = array_values(array_filter(array_map(
                    static fn(mixed $id): string => strtolower(trim((string) $id)),
                    EnchantTuningManager::getSettingsArray("mastery-book-disabled-enchants")
                ), static fn(string $id): bool => $id !== ""));
                if ($blocked !== []) {
                    $groupEnchants = array_values(array_filter($groupEnchants, static function ($enchant) use ($blocked): bool {
                        return !in_array(strtolower($enchant->getName()), $blocked, true);
                    }));
                }
            }

            if (empty($groupEnchants)) {
                $player->sendMessage(C::colorize("&r&4Failed to examine '&fRC Book&r&4'"));
                $player->sendMessage(C::colorize("&r&cThere are no enchantments in the group '&f" . $group . "&r&4'"));   
                return;
            }

            $randomEnchant = $groupEnchants[array_rand($groupEnchants)];

            $level = mt_rand(1, $randomEnchant->getMaxLevel());
            $success = mt_rand(1, 100);
            $destroy = mt_rand(1, 100);
            $enchantBook = GlaciaItemFactory::enchantmentBook($randomEnchant, $level, $success, $destroy);

            if ($player->getInventory()->canAddItem($enchantBook)) {
                $player->getInventory()->addItem($enchantBook);
            } else {
                $player->getWorld()->dropItem($player->getPosition(), $enchantBook);
            }
            
            PlayerUtils::playSound($player, "random.levelup");
            switch ($group) {
                case "simple":
                    $mainColors = [DyeColor::LIGHT_GRAY(), DyeColor::GRAY()];
                    $fadeColors = [DyeColor::GRAY(), DyeColor::LIGHT_GRAY()];
                    $type = FireworkRocketType::LARGE_BALL;
                    break;
                case "unique":
                    $mainColors = [DyeColor::GREEN(), DyeColor::LIME()];
                    $fadeColors = [DyeColor::LIME(), DyeColor::GREEN()];
                    $type = FireworkRocketType::LARGE_BALL;
                    break;
                case "elite":
                    $mainColors = [DyeColor::CYAN(), DyeColor::BLUE()];
                    $fadeColors = [DyeColor::BLUE(), DyeColor::CYAN()];
                    $type = FireworkRocketType::LARGE_BALL;
                    break;
                case "exotic":
                    $mainColors = [DyeColor::YELLOW(), DyeColor::ORANGE()];
                    $fadeColors = [DyeColor::ORANGE(), DyeColor::YELLOW()];
                    $type = FireworkRocketType::LARGE_BALL;
                    break;
                case "legendary":
                    $mainColors = [DyeColor::ORANGE(), DyeColor::RED()];
                    $fadeColors = [DyeColor::RED(), DyeColor::ORANGE()];
                    $type = FireworkRocketType::LARGE_BALL;
                    break;
                default:
                    $mainColors = [DyeColor::WHITE(), DyeColor::LIGHT_GRAY()];
                    $fadeColors = [DyeColor::LIGHT_GRAY(), DyeColor::WHITE()];
                    $type = FireworkRocketType::SMALL_BALL;
                    break;
                }

            $explosions = [];
            for ($i = 0; $i < 3; $i++) {
                $explosions[] = new FireworkRocketExplosion(
                    $type,
                    $mainColors, 
                    $fadeColors, 
                    true,
                    true 
                );
            }

            $firework = new FireworkRocket($player->getLocation(), 10, $explosions);
            $firework->spawnToAll();

            $item->pop();
            $player->getInventory()->setItemInHand($item);
            Loader::getChallengeManager()->incrementProgress($player, "enchant_books_opened", 1);
            Loader::getQuestBoardManager()->recordProgress($player, "enchant_books_opened", 1, [
                "group" => strtolower($group),
            ]);
            if (strtolower($group) === "exclusive") {
                Loader::getQuestBoardManager()->recordProgress($player, "exclusive_books_opened", 1);
            }
            $player->sendMessage(C::colorize(str_replace(["{group-color}", "{group-name}", "{enchant-color}", "{level}"], [$color, $group, CustomEnchantments::getEnchantmentDisplayName($randomEnchant->getName(), $color), GeneralUtils::getRomanNumeral($level)], "&r&7You examined {group-color}{group-name}&r&7 book and found {enchant-color} {level}")));
        }
    }

    public function onProjectileLaunch(ProjectileLaunchEvent $event): void
    {
        $projectile = $event->getEntity();
        if (!$projectile instanceof Snowball) {
            return;
        }

        $owner = $projectile->getOwningEntity();
        if (!$owner instanceof Player) {
            return;
        }

        $uuid = $owner->getUniqueId()->toString();
        $pending = self::$pendingBathBombThrows[$uuid] ?? 0;
        if ($pending <= 0) {
            return;
        }

        self::$bathBombProjectileIds[$projectile->getId()] = true;
        $projectile->setMotion($projectile->getMotion()->multiply(1.18));
        if ($pending === 1) {
            unset(self::$pendingBathBombThrows[$uuid]);
        } else {
            self::$pendingBathBombThrows[$uuid] = $pending - 1;
        }
    }

    public function onProjectileHit(ProjectileHitEvent $event): void
    {
        $projectile = $event->getEntity();
        if ($projectile instanceof Projectile) {
            if (self::isBathBombProjectile($projectile)) {
                $this->detonateBathBomb($projectile);
            }
            unset(self::$bathBombProjectileIds[$projectile->getId()]);
        }
    }

    private function detonateBathBomb(Projectile $projectile): void
    {
        $world = $projectile->getWorld();
        $owner = $projectile->getOwningEntity();
        $impact = $projectile->getPosition();

        $world->addParticle($impact, new HugeExplodeSeedParticle());
        if ($owner !== null) {
            PlayerUtils::playSound($owner, "random.explode", 1.0, 1.15, 12);
        }

        $area = new AxisAlignedBB(
            $impact->x - 3.25,
            $impact->y - 2.0,
            $impact->z - 3.25,
            $impact->x + 3.25,
            $impact->y + 2.0,
            $impact->z + 3.25
        );

        foreach ($world->getNearbyEntities($area, $projectile) as $entity) {
            if (!$entity instanceof Living || !$entity->isAlive()) {
                continue;
            }
            if ($owner !== null && $entity->getId() === $owner->getId()) {
                continue;
            }

            $deltaX = $entity->getPosition()->x - $impact->x;
            $deltaZ = $entity->getPosition()->z - $impact->z;
            $horizontal = new Vector3($deltaX, 0.0, $deltaZ);
            if ($horizontal->lengthSquared() <= 0.0001) {
                $horizontal = new Vector3(0.0, 0.0, 1.0);
            } else {
                $horizontal = $horizontal->normalize();
            }

            $distance = max(0.4, sqrt(max(0.16, ($deltaX * $deltaX) + ($deltaZ * $deltaZ))));
            $strength = max(0.5, 1.35 - ($distance * 0.2));
            $motion = $entity->getMotion();
            $motion->x += $horizontal->x * $strength;
            $motion->z += $horizontal->z * $strength;
            $motion->y = max($motion->y, 0.36);
            $entity->setMotion($motion);
        }
    }

    public function onBlockBreak(BlockBreakEvent $event): void
    {
        if (!GenBucketManager::stopOnGeneratedBreak()) {
            return;
        }

        $block = $event->getBlock();
        $key = self::toWorldBlockKey(
            $block->getPosition()->getWorld(),
            $block->getPosition()->getFloorX(),
            $block->getPosition()->getFloorY(),
            $block->getPosition()->getFloorZ()
        );
        $chainKey = self::$generatedGenBucketBlockIndex[$key] ?? null;
        if ($chainKey === null) {
            return;
        }

        unset(self::$generatedGenBucketBlockIndex[$key]);
        $this->stopGenBucketChain($chainKey, true);
    }

    public function onInteract(PlayerInteractEvent $event): void {
        $player = $event->getPlayer();
        $item = $event->getItem();

        $nbt = $item->getNamedTag();
        $glaciaTag = $nbt->getCompoundTag("Glacia");

        if ($glaciaTag === null || !$glaciaTag->getTag("glaciaItem")) {
            return;
        }

        $itemTag = $glaciaTag->getString("glaciaItem");

        if ($this->isRegionRestrictedUtility($itemTag)) {
            $region = $this->getProtectedInteractRegionAt($player->getPosition());
            if ($region !== null && !$player->hasPermission("glacia.admin")) {
                $event->cancel();
                $this->sendRegionDenied($player);
                return;
            }
        }

        if ($itemTag === "gen_bucket") {
            $event->cancel();

            if (!GenBucketManager::isEnabled()) {
                $player->sendMessage(C::colorize("&r&cGen buckets are currently disabled."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $session = Loader::getPlayerManager()->getSession($player);
            if ($session === null) {
                $player->sendMessage(C::colorize("&r&cYour profile is still loading. Try again in a moment."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $material = GenBucketManager::normalizeMaterial($glaciaTag->getString("material", ""));
            $direction = GenBucketManager::normalizeDirection($glaciaTag->getString("direction", ""));
            $face = GenBucketManager::getFacing($direction);
            $generatedBlock = GenBucketManager::getBlock($material);

            if ($face === null || $generatedBlock === null) {
                $player->sendMessage(C::colorize("&r&cThis gen bucket is invalid."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            if (!GenBucketManager::mayStartWithoutSneakConflict($player)) {
                $player->sendMessage(C::colorize("&r&eSneak while right-clicking to start this gen bucket."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $cooldownMs = GenBucketManager::getUseCooldownMillis();
            $nowMs = (int) floor(microtime(true) * 1000);
            $cooldownKey = $player->getUniqueId()->toString();
            $availableAtMs = self::$genBucketCooldowns[$cooldownKey] ?? 0;
            if ($cooldownMs > 0 && $availableAtMs > $nowMs) {
                $remainingMs = $availableAtMs - $nowMs;
                $waitText = $remainingMs >= 1000
                    ? (string) round($remainingMs / 1000, 1) . "s"
                    : $remainingMs . "ms";
                $player->sendMessage(C::colorize("&r&cYou must wait &f" . $waitText . " &cbefore using another gen bucket."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $costPerBlock = GenBucketManager::getCost($material);
            if ($player->getGamemode() !== GameMode::CREATIVE() && $costPerBlock > 0 && $session->getBalance() < $costPerBlock) {
                $player->sendMessage(C::colorize("&r&cYou need at least &f$" . number_format($costPerBlock) . " &cto start this gen bucket."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $origin = $event->getBlock()->getPosition();
            $start = $origin->getSide($event->getFace());
            $world = $origin->getWorld();
            $maxLength = GenBucketManager::getMaxLength();
            $blocksPerStep = GenBucketManager::getBlocksPerStep();

            $originKey = self::toWorldBlockKey($world, $origin->getFloorX(), $origin->getFloorY(), $origin->getFloorZ());
            if (isset(self::$generatedGenBucketBlockIndex[$originKey])) {
                $player->sendMessage(C::colorize("&r&eWait for this gen bucket line to finish before starting another from it."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

              if (
                  $this->getGenBucketBlockedRegionAt($player->getPosition()) !== null ||
                  $this->getGenBucketBlockedRegionAt($origin) !== null ||
                  $this->getGenBucketBlockedRegionAt($start) !== null
              ) {
                  $this->sendRegionDenied($player);
                  return;
            }

            if (
                $start->getFloorY() < 0 ||
                $start->getFloorY() >= $world->getMaxY() ||
                !GenBucketManager::mayGenReplaceBlock($world->getBlock($start), $material)
            ) {
                $player->sendMessage(C::colorize("&r&cThat path is already blocked."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            if ($cooldownMs > 0) {
                self::$genBucketCooldowns[$cooldownKey] = $nowMs + $cooldownMs;
            }

            $playerUuid = $player->getUniqueId()->toString();
            $running = self::$activeGenBucketChainKeysByPlayer[$playerUuid] ?? [];
            if (count($running) >= GenBucketManager::getMaxConcurrentChainsPerPlayer()) {
                $player->sendMessage(C::colorize("&r&cYou already have &f" . GenBucketManager::getMaxConcurrentChainsPerPlayer() . " &cgen buckets running. Wait for one to finish or break a generated block to stop a line."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            PlayerUtils::playSound($player, "random.click");

            $chainKey = $playerUuid . ":" . $material . ":" . $direction . ":" . microtime(true);
            self::$activeGenBucketChainKeysByPlayer[$playerUuid][$chainKey] = true;
            self::$activeGenBucketChains[$chainKey] = [
                "player" => $playerUuid,
                "world" => strtolower($world->getFolderName()),
                "positions" => [],
                "handler" => null
            ];

            $cursor = $start;
            $placed = 0;
            $interval = GenBucketManager::getStepIntervalTicks();
            $stepSoundCounter = 0;
            $handler = Loader::getInstance()->getScheduler()->scheduleRepeatingTask(new ClosureTask(function() use ($player, $world, $generatedBlock, $material, $maxLength, $blocksPerStep, $face, $costPerBlock, $chainKey, &$cursor, &$placed, &$stepSoundCounter): void {
                if (!isset(self::$activeGenBucketChains[$chainKey])) {
                    return;
                }

                if (!$player->isConnected() || $player->getWorld() !== $world) {
                    $this->stopGenBucketChain($chainKey, false);
                    return;
                }

                $placedThisStep = 0;
                $lastSoundAt = null;
                for ($i = 0; $i < $blocksPerStep; $i++) {
                    if ($placed >= $maxLength) {
                        PlayerUtils::playSound($player, "random.pop");
                        $this->stopGenBucketChain($chainKey, false);
                        return;
                    }

                    if ($cursor->getFloorY() < 0 || $cursor->getFloorY() >= $world->getMaxY()) {
                        PlayerUtils::playSound($player, "random.pop");
                        $this->stopGenBucketChain($chainKey, false);
                        return;
                    }

                    if ($this->getGenBucketBlockedRegionAt($cursor) !== null) {
                        PlayerUtils::playSound($player, "note.bass");
                        $this->stopGenBucketChain($chainKey, false);
                        return;
                    }

                    $targetBlock = $world->getBlock($cursor);
                    if (!GenBucketManager::mayGenReplaceBlock($targetBlock, $material)) {
                        PlayerUtils::playSound($player, "random.pop");
                        $this->stopGenBucketChain($chainKey, false);
                        return;
                    }

                    $session = Loader::getPlayerManager()->getSession($player);
                    if (
                        $player->getGamemode() !== GameMode::CREATIVE()
                        && $costPerBlock > 0
                        && ($session === null || $session->getBalance() < $costPerBlock)
                    ) {
                        $player->sendMessage(C::colorize("&r&cGen bucket stopped because you ran out of money."));
                        PlayerUtils::playSound($player, "note.bass");
                        $this->stopGenBucketChain($chainKey, false);
                        return;
                    }

                    if ($player->getGamemode() !== GameMode::CREATIVE() && $costPerBlock > 0 && $session !== null) {
                        $session->removeBalance($costPerBlock);
                    }

                    $world->setBlock($cursor, clone $generatedBlock);
                    $blockKey = self::toWorldBlockKey($world, $cursor->getFloorX(), $cursor->getFloorY(), $cursor->getFloorZ());
                    self::$activeGenBucketChains[$chainKey]["positions"][$blockKey] = true;
                    self::$generatedGenBucketBlockIndex[$blockKey] = $chainKey;
                    $placed++;
                    $placedThisStep++;
                    $lastSoundAt = new Vector3($cursor->getFloorX() + 0.5, $cursor->getFloorY() + 0.5, $cursor->getFloorZ() + 0.5);
                    $cursor = $this->offsetPosition($cursor, $face);
                }

                if ($placedThisStep > 0 && GenBucketManager::placementStepSoundsEnabled() && $lastSoundAt !== null) {
                    ++$stepSoundCounter;
                    if ($stepSoundCounter % GenBucketManager::getStepSoundIntervalTicks() === 0) {
                        $world->addSound($lastSoundAt, new BlockPlaceSound(clone $generatedBlock));
                    }
                }
            }), $interval);
            self::$activeGenBucketChains[$chainKey]["handler"] = $handler;
            return;
        }

        if ($itemTag === "zone_barrier_bucket") {
            $event->cancel();
            $this->handleZoneBarrierBucket($player, $event->getBlock()->getPosition());
            return;
        }

        $explosiveType = match ($itemTag) {
            "cegg_gigantic" => "tnt_gigantic",
            "cegg_lethal" => "tnt_lethal",
            "cegg_lucky" => "tnt_lucky",
            "cegg_tactical" => "tnt_tactical",
            default => null
        };

        if ($explosiveType === null) {
            return;
        }

        $event->cancel();

        $block = $event->getBlock();
        $face = $event->getFace();

        $spawnPos = $block->getPosition()->getSide($face);
        if ($this->isProtectedCeggRegion($spawnPos)) {
            $player->sendMessage(C::colorize("&r&cYou cannot spawn CEGGs in Spawn or Warzone."));
            PlayerUtils::playSound($player, "note.bass");
            return;
        }

        $location = new \pocketmine\entity\Location(
            $spawnPos->getX() + 0.5,
            $spawnPos->getY(),
            $spawnPos->getZ() + 0.5,
            $spawnPos->getWorld(),
            $player->getLocation()->getYaw(),
            $player->getLocation()->getPitch()
        );

        $creeper = new GlaciaExplosiveCreeper($location);
        $creeper->setExplosiveType($explosiveType);
        $creeper->setRaidSourceFactionId(Loader::getPlayerManager()->getSession($player)?->getFactionId());
        $creeper->spawnToAll();

        $item->pop();
        $player->getInventory()->setItemInHand($item);
    }

    public function onEntityTrampleFarmland(EntityTrampleFarmlandEvent $event): void
    {
        $entity = $event->getEntity();
        if (!$entity instanceof Player) {
            return;
        }

        if (RuneManager::shouldPreventFarmlandTrample($entity)) {
            $event->cancel();
        }
    }

    /**
     * @priority HIGHEST
     */
    public function onEnchantInventoryOpen(InventoryOpenEvent $event): void
    {
        $player = $event->getPlayer();
        $inventory = $event->getInventory();
        if (!$this->isEnchantInventory($inventory)) {
            return;
        }

        $this->queueEnchantingLapisRefill($player, $inventory);
    }

    public function onEnchantInventoryClose(InventoryCloseEvent $event): void
    {
        if (!$this->isEnchantInventory($event->getInventory())) {
            return;
        }

        $this->scrubSyntheticEnchantingLapis($event->getPlayer());
    }

    /**
     * @priority HIGHEST
     */
    public function onAutoLapis(InventoryTransactionEvent $event): void
    {
        $transaction = $event->getTransaction();
        $player = $transaction->getSource();
        $actions = array_values($transaction->getActions());

        if ($this->transactionTouchesSyntheticEnchantingLapis($actions) || $this->isTryingToTakeEnchantingLapis($actions)) {
            $event->cancel();
            $this->scheduleSyntheticEnchantingLapisScrub($player);

            foreach ($actions as $action) {
                if ($action instanceof SlotChangeAction && $this->isEnchantInventory($action->getInventory())) {
                    $this->queueEnchantingLapisRefill($player, $action->getInventory());
                    return;
                }
            }
        }

        foreach ($actions as $action) {
            if (!$action instanceof SlotChangeAction) {
                continue;
            }

            $inventory = $action->getInventory();
            if (!$this->isEnchantInventory($inventory)) {
                continue;
            }

            $this->queueEnchantingLapisRefill($player, $inventory);
            return;
        }
    }

    /**
     * @priority HIGHEST
     */
    public function onDropSyntheticEnchantingLapis(PlayerDropItemEvent $event): void
    {
        if (!$this->isSyntheticEnchantingLapis($event->getItem())) {
            return;
        }

        $event->cancel();
        $this->scheduleSyntheticEnchantingLapisScrub($event->getPlayer());
    }

    /**
     * @priority HIGHEST
     */
    public function onPickupSyntheticEnchantingLapis(EntityItemPickupEvent $event): void
    {
        $collector = $event->getEntity();
        if (!$collector instanceof Player || !$this->isSyntheticEnchantingLapis($event->getItem())) {
            return;
        }

        $event->cancel();
        $this->scheduleSyntheticEnchantingLapisScrub($collector);
    }

    /**
     * @priority HIGHEST
     */
    public function onDragCharms(InventoryTransactionEvent $event): void
    {
        $transaction = $event->getTransaction();
        $actions = $this->getNormalizedDragActions($transaction);
        if ($actions === null) {
            return;
        }
        if ($this->isPlainCursorRearrangementOfSameItem($actions[0], $actions[1])) {
            return;
        }

        foreach ($actions as $i => $action) {
            if (!$action instanceof SlotChangeAction) {
                continue;
            }
            $otherAction = $actions[($i + 1) % 2];
            if (!$otherAction instanceof SlotChangeAction) {
                continue;
            }
            if ($this->isInvMenuSlotAction($action) || $this->isInvMenuSlotAction($otherAction)) {
                continue;
            }

            $itemClickedWith = $action->getTargetItem();
            $itemClicked = $action->getSourceItem();
            if ($itemClickedWith->getCount() !== 1 || $itemClicked->isNull()) {
                continue;
            }

            $withTag = $itemClickedWith->getNamedTag()->getCompoundTag("Glacia");
            $onTag = $itemClicked->getNamedTag()->getCompoundTag("Glacia");
            $withId = $withTag?->getString("glaciaItem", "") ?? "";
            $onId = $onTag?->getString("glaciaItem", "") ?? "";
            $player = $transaction->getSource();
            if (!$player instanceof Player) {
                continue;
            }

            // Charm Dust -> Charm Shard
            if ($withId === "charm_dust" && $onId === "charm_shard" && $withTag !== null && $onTag !== null) {
                $event->cancel();

                $bonus = max(1, NbtLenient::readInt($withTag, "bonus", 5, 1, 1000, $player));
                $currentSuccess = max(1, min(100, NbtLenient::readInt($onTag, "success", 70, 0, 100, $player)));
                $currentBonus = max(0, NbtLenient::readInt($onTag, "bonus_success", 0, 0, 1000, $player));
                $newBonus = $currentBonus + $bonus;

                $updatedShard = GlaciaItemFactory::charmShard($currentSuccess, $newBonus);
                $action->getInventory()->setItem($action->getSlot(), $updatedShard);
                $otherAction->getInventory()->setItem($otherAction->getSlot(), VanillaItems::AIR());

                $player->sendMessage(C::colorize("&r&aCharm Dust applied! &f+{$bonus}% &aadded to shard."));
                PlayerUtils::playSound($player, "random.levelup");
                return;
            }

            if ($withId === "blackscroll_dust" && in_array($onId, ["blackscroll", "holy_blackscroll"], true) && $withTag !== null && $onTag !== null) {
                $event->cancel();

                $bonus = max(1, NbtLenient::readInt($withTag, "bonus", 5, 1, 1000, $player));
                $currentSuccess = max(1, min(100, NbtLenient::readInt($onTag, "success", 100, 0, 100, $player)));
                $updatedSuccess = min(100, $currentSuccess + $bonus);
                $updatedScroll = $onId === "holy_blackscroll"
                    ? GlaciaItemFactory::holyBlackscroll($updatedSuccess)
                    : GlaciaItemFactory::blackscroll($updatedSuccess);

                $action->getInventory()->setItem($action->getSlot(), $updatedScroll);
                $otherAction->getInventory()->setItem($otherAction->getSlot(), VanillaItems::AIR());

                $player->sendMessage(C::colorize("&r&aBlack Scroll Dust applied! &f{$currentSuccess}% &7-> &f{$updatedSuccess}%&a."));
                PlayerUtils::playSound($player, "random.levelup");
                return;
            }

            // Charm Shard -> Enchantment Book
            if ($withId === "charm_shard" && $onId === "enchantment_book" && $withTag !== null && $onTag !== null) {
                $event->cancel();

                $success = max(1, min(100, NbtLenient::readInt($withTag, "success", 70, 0, 100, $player) + NbtLenient::readInt($withTag, "bonus_success", 0, 0, 1000, $player)));
                $enchantName = $onTag->getString("enchant_book", "");
                $bookLevel = max(1, NbtLenient::readInt($onTag, "level", 1, 1, 255, $player));
                $bookType = strtolower($onTag->getString("book_type", "custom"));
                $destroy = max(0, min(100, NbtLenient::readInt($onTag, "destroy", 0, 0, 100, $player)));
                $newLevel = $bookLevel;
                $bookSuccessPct = NbtLenient::readInt($onTag, "success", 50, 0, 100, $player);

                if ($bookType === "vanilla" || $onTag->getString("vanilla_enchant", "") !== "") {
                    $vanillaId = $onTag->getString("vanilla_enchant", $enchantName);
                    $vanilla = GlaciaItemFactory::resolveVanillaEnchantment($vanillaId);
                    if ($vanilla === null) {
                        $player->sendMessage(C::colorize("&r&cInvalid target enchantment book."));
                        return;
                    }
                    if ($bookLevel >= $vanilla->getMaxLevel()) {
                        $player->sendMessage(C::colorize("&r&cThis enchantment book is already at max level."));
                        PlayerUtils::playSound($player, "note.bass");
                        return;
                    }
                    $newLevel = min($vanilla->getMaxLevel(), $bookLevel + 1);
                    $rebuilt = GlaciaItemFactory::vanillaEnchantmentBook($vanillaId, $newLevel, $bookSuccessPct, $destroy);
                } else {
                    $custom = CustomEnchantments::getEnchantmentByName($enchantName);
                    if ($custom === null) {
                        $player->sendMessage(C::colorize("&r&cInvalid target enchantment book."));
                        return;
                    }
                    if ($bookLevel >= $custom->getMaxLevel()) {
                        $player->sendMessage(C::colorize("&r&cThis enchantment book is already at max level."));
                        PlayerUtils::playSound($player, "note.bass");
                        return;
                    }
                    $newLevel = min($custom->getMaxLevel(), $bookLevel + 1);
                    $rebuilt = GlaciaItemFactory::enchantmentBook($custom, $newLevel, $bookSuccessPct, $destroy);
                }

                $otherAction->getInventory()->setItem($otherAction->getSlot(), VanillaItems::AIR());
                if (mt_rand(1, 100) <= $success) {
                    if ($rebuilt !== null) {
                        $action->getInventory()->setItem($action->getSlot(), $rebuilt);
                    }
                    $player->sendMessage(C::colorize("&r&aCharm Shard succeeded! Book upgraded to &f" . GeneralUtils::getRomanNumeral($newLevel) . "&a."));
                    PlayerUtils::playSound($player, "random.levelup");
                } else {
                    $action->getInventory()->setItem($action->getSlot(), VanillaItems::AIR());
                    $player->sendMessage(C::colorize("&r&cCharm Shard failed. The book was destroyed."));
                    PlayerUtils::playSound($player, "note.bass");
                }
                return;
            }
        }
    }

    /**
     * @priority HIGHEST
     */
    public function onDragEnchant(InventoryTransactionEvent $event): void
    {
        if ($event->isCancelled()) {
            return;
        }
        $transaction = $event->getTransaction();
        $actions = $this->getNormalizedDragActions($transaction);
        if ($actions !== null && $this->isPlainCursorRearrangementOfSameItem($actions[0], $actions[1])) {
            return;
        }
        if ($actions !== null) {
            foreach ($actions as $i => $action) {
                if (!$action instanceof SlotChangeAction) continue;

                if (($otherAction = $actions[($i + 1) % 2]) instanceof SlotChangeAction && ($itemClickedWith = $action->getTargetItem())->getTypeId() === VanillaItems::ENCHANTED_BOOK()->getTypeId()) {
                    if (($itemClicked = $action->getSourceItem())->getTypeId() !== VanillaItems::AIR()->getTypeId() && $itemClickedWith->getCount() === 1) {
                        $itemClickedWithTag = $itemClickedWith->getNamedTag()->getCompoundTag("Glacia");
                        $itemClickedTag = $itemClicked->getNamedTag()->getCompoundTag("Glacia");

                        $itemClickedWithGlaciaItem = $itemClickedWithTag?->getString("glaciaItem", "");
                        $itemClickedGlaciaItem = $itemClickedTag?->getString("glaciaItem", "");

                        $shardAction = null;
                        $bookAction = null;
                        $dustAction = null;
                        if ($itemClickedWithGlaciaItem === "charm_shard") {
                            $shardAction = $action;
                        } elseif ($itemClickedGlaciaItem === "charm_shard") {
                            $shardAction = $otherAction;
                        }
                        if ($itemClickedWithGlaciaItem === "enchantment_book") {
                            $bookAction = $action;
                        } elseif ($itemClickedGlaciaItem === "enchantment_book") {
                            $bookAction = $otherAction;
                        }
                        if ($itemClickedWithGlaciaItem === "charm_dust") {
                            $dustAction = $action;
                        } elseif ($itemClickedGlaciaItem === "charm_dust") {
                            $dustAction = $otherAction;
                        }

                        if ($dustAction instanceof SlotChangeAction && $shardAction instanceof SlotChangeAction) {
                            $event->cancel();
                            $player = $transaction->getSource();
                            $dustTag = $dustAction->getTargetItem()->getNamedTag()->getCompoundTag("Glacia");
                            $shardItem = clone $shardAction->getSourceItem();
                            $shardTag = $shardItem->getNamedTag()->getCompoundTag("Glacia");
                            if ($player instanceof Player && $dustTag !== null && $shardTag !== null) {
                                $bonus = max(1, NbtLenient::readInt($dustTag, "bonus", 5, 1, 1000, $player));
                                $currentBonus = max(0, NbtLenient::readInt($shardTag, "bonus_success", 0, 0, 1000, $player));
                                $shardItem = GlaciaItemFactory::charmShard(
                                    NbtLenient::readInt($shardTag, "success", 70, 0, 100, $player),
                                    $currentBonus + $bonus
                                );

                                $shardAction->getInventory()->setItem($shardAction->getSlot(), $shardItem);
                                $dustAction->getInventory()->setItem($dustAction->getSlot(), VanillaItems::AIR());
                                $player->sendMessage(C::colorize("&r&aCharm Dust applied! &f+{$bonus}% &asuccess added to shard."));
                                PlayerUtils::playSound($player, "random.levelup");
                            }
                            return;
                        }

                        $scrollAction = null;
                        if (in_array($itemClickedWithGlaciaItem, ["blackscroll", "holy_blackscroll"], true)) {
                            $scrollAction = $action;
                        } elseif (in_array($itemClickedGlaciaItem, ["blackscroll", "holy_blackscroll"], true)) {
                            $scrollAction = $otherAction;
                        }

                        if ($dustAction instanceof SlotChangeAction && $scrollAction instanceof SlotChangeAction) {
                            $event->cancel();
                            $player = $transaction->getSource();
                            $dustTag = $dustAction->getTargetItem()->getNamedTag()->getCompoundTag("Glacia");
                            $scrollItem = clone $scrollAction->getSourceItem();
                            $scrollTag = $scrollItem->getNamedTag()->getCompoundTag("Glacia");
                            if ($player instanceof Player && $dustTag !== null && $scrollTag !== null && $dustTag->getString("glaciaItem", "") === "blackscroll_dust") {
                                $bonus = max(1, NbtLenient::readInt($dustTag, "bonus", 5, 1, 1000, $player));
                                $currentSuccess = max(1, min(100, NbtLenient::readInt($scrollTag, "success", 100, 0, 100, $player)));
                                $updatedSuccess = min(100, $currentSuccess + $bonus);
                                $updatedScroll = $scrollTag->getString("glaciaItem", "") === "holy_blackscroll"
                                    ? GlaciaItemFactory::holyBlackscroll($updatedSuccess)
                                    : GlaciaItemFactory::blackscroll($updatedSuccess);

                                $scrollAction->getInventory()->setItem($scrollAction->getSlot(), $updatedScroll);
                                $dustAction->getInventory()->setItem($dustAction->getSlot(), VanillaItems::AIR());
                                $player->sendMessage(C::colorize("&r&aBlack Scroll Dust applied! &f{$currentSuccess}% &7-> &f{$updatedSuccess}%&a."));
                                PlayerUtils::playSound($player, "random.levelup");
                            }
                            return;
                        }

                        if ($shardAction instanceof SlotChangeAction && $bookAction instanceof SlotChangeAction) {
                            $event->cancel();
                            $player = $transaction->getSource();
                            if (!$player instanceof Player) {
                                return;
                            }
                            $bookItem = clone $bookAction->getSourceItem();
                            $bookTag = $bookItem->getNamedTag()->getCompoundTag("Glacia");
                            $shardTag = $shardAction->getTargetItem()->getNamedTag()->getCompoundTag("Glacia");
                            if ($bookTag === null || $shardTag === null || $bookTag->getString("glaciaItem", "") !== "enchantment_book") {
                                return;
                            }

                            $success = max(1, min(100, NbtLenient::readInt($shardTag, "success", 70, 0, 100, $player) + NbtLenient::readInt($shardTag, "bonus_success", 0, 0, 1000, $player)));
                            $enchantName = $bookTag->getString("enchant_book", "");
                            $bookLevel = max(1, NbtLenient::readInt($bookTag, "level", 1, 1, 255, $player));
                            $bookType = strtolower($bookTag->getString("book_type", "custom"));
                            $destroy = max(0, min(100, NbtLenient::readInt($bookTag, "destroy", 0, 0, 100, $player)));
                            $newLevel = $bookLevel;
                            $bookSuccessPct = NbtLenient::readInt($bookTag, "success", 50, 0, 100, $player);

                            if ($bookType === "vanilla" || $bookTag->getString("vanilla_enchant", "") !== "") {
                                $vanillaId = $bookTag->getString("vanilla_enchant", $enchantName);
                                $vanilla = GlaciaItemFactory::resolveVanillaEnchantment($vanillaId);
                                if ($vanilla === null) {
                                    $player->sendMessage(C::colorize("&r&cInvalid target book."));
                                    return;
                                }
                                if ($bookLevel >= $vanilla->getMaxLevel()) {
                                    $player->sendMessage(C::colorize("&r&cThis enchantment book is already at max level."));
                                    PlayerUtils::playSound($player, "note.bass");
                                    return;
                                }
                                $newLevel = min($vanilla->getMaxLevel(), $bookLevel + 1);
                                $rebuilt = GlaciaItemFactory::vanillaEnchantmentBook($vanillaId, $newLevel, $bookSuccessPct, $destroy);
                            } else {
                                $custom = CustomEnchantments::getEnchantmentByName($enchantName);
                                if ($custom === null) {
                                    $player->sendMessage(C::colorize("&r&cInvalid target book."));
                                    return;
                                }
                                if ($bookLevel >= $custom->getMaxLevel()) {
                                    $player->sendMessage(C::colorize("&r&cThis enchantment book is already at max level."));
                                    PlayerUtils::playSound($player, "note.bass");
                                    return;
                                }
                                $newLevel = min($custom->getMaxLevel(), $bookLevel + 1);
                                $rebuilt = GlaciaItemFactory::enchantmentBook($custom, $newLevel, $bookSuccessPct, $destroy);
                            }

                            $shardAction->getInventory()->setItem($shardAction->getSlot(), VanillaItems::AIR());
                            $forcedSuccess = PetManager::consumeGuaranteedSuccess($player);
                            if ($forcedSuccess || mt_rand(1, 100) <= $success) {
                                if ($rebuilt !== null) {
                                    $bookAction->getInventory()->setItem($bookAction->getSlot(), $rebuilt);
                                }
                                $player->sendMessage(C::colorize("&r&aCharm Shard succeeded! Book upgraded to &f" . GeneralUtils::getRomanNumeral($newLevel) . "&a."));
                                PlayerUtils::playSound($player, "random.levelup");
                            } else {
                                $bookAction->getInventory()->setItem($bookAction->getSlot(), VanillaItems::AIR());
                                $player->sendMessage(C::colorize("&r&cCharm Shard failed. The book was destroyed."));
                                PlayerUtils::playSound($player, "note.bass");
                            }
                            return;
                        }

                        if(
                            $itemClickedWithGlaciaItem === "magicdust" ||
                            $itemClickedWithGlaciaItem === "primal_dust" ||
                            $itemClickedGlaciaItem === "magicdust" ||
                            $itemClickedGlaciaItem === "primal_dust"
                        ){
                            continue;
                        }

                        if ($this->transactionMixesEnchantmentBookWithDragScroll($itemClickedWith, $itemClicked, $otherAction)) {
                            continue;
                        }

                        $nbt = $itemClickedWith->getNamedTag()->getCompoundTag("Glacia");
                        if ($nbt !== null && $nbt->getTag("enchant_book") !== null) {
                            $event->cancel();

                            $player = $transaction->getSource();
                            if ($this->isBookLikeItem($itemClicked)) {
                                $player->sendMessage(C::colorize("&cYou cannot apply enchantment books to books."));
                                return;
                            }

                            $enchantName = $nbt->getString("enchant_book");
                            $level = NbtLenient::readInt($nbt, "level", 1, 1, 255, $player);
                            $success = NbtLenient::readInt($nbt, "success", 100, 0, 100, $player);
                            $destroy = NbtLenient::readInt($nbt, "destroy", 0, 0, 100, $player);
                            $bookType = strtolower($nbt->getString("book_type", "custom"));

                            $isVanillaBook = $bookType === "vanilla" || $nbt->getString("vanilla_enchant", "") !== "";
                            $vanillaEnchantment = null;
                            $enchantment = null;

                            if ($isVanillaBook) {
                                $vanillaName = $nbt->getString("vanilla_enchant", $enchantName);
                                $vanillaEnchantment = GlaciaItemFactory::resolveVanillaEnchantment($vanillaName);
                                if ($vanillaEnchantment === null) {
                                    $player->sendMessage(C::colorize("&cUnknown enchantment!"));
                                    return;
                                }

                                if (!$this->canApplyVanillaEnchantTo($itemClicked, $vanillaName)) {
                                    $player->sendMessage(C::colorize("&cThat item can't receive this enchant."));
                                    return;
                                }
                            } else {
                                $enchantment = CustomEnchantments::getEnchantmentByName($enchantName);
                                if ($enchantment === null){
                                    $player->sendMessage(C::colorize("&cUnknown enchantment!"));
                                    return;
                                }

                                if ($this->isPlayerInsideEndfall($player) && $this->isEndfallRestrictedEnchantGroup($nbt->getString("group", ""))) {
                                    $player->sendMessage(C::colorize("&r&cMastery and Exclusive enchants cannot be applied in Endfall."));
                                    return;
                                }

                                $itemRoot = $itemClicked->getNamedTag()->getCompoundTag("Glacia");
                                $defaultMaxSlots = $itemClicked instanceof FishingRod ? 3 : 8;
                                $maxSlots = $itemClicked instanceof FishingRod
                                    ? NbtLenient::readInt($itemRoot, "maxEnchantSlots", $defaultMaxSlots, 1, 64, $player)
                                    : $this->getCustomEnchantLimitFor($player);
                                $customEnchantCount = CustomEnchantmentManager::countGlaciaCesEntries($itemClicked);

                                if ($customEnchantCount >= $maxSlots) {
                                    $player->sendMessage(C::colorize("&cYou have reached the maximum amount of custom enchants for this item."));
                                    return;
                                }

                                if (!$enchantment->matches($itemClicked)) {
                                    $player->sendMessage(C::colorize("&cThat item can't receive this enchant."));
                                    return;
                                }

                                if ($this->hasConfiguredEnchantConflict($itemClicked, $enchantName)) {
                                    $player->sendMessage(C::colorize("&cThat enchant conflicts with one already on this item."));
                                    return;
                                }
                            }

                            $bonusSuccess = PetManager::getEnchantBonusPercent($player);
                            $successRoll = PetManager::consumeGuaranteedSuccess($player)
                                || mt_rand(1, 100) <= min(100, $success + $bonusSuccess);

                            if($successRoll){
                                if ($isVanillaBook && $vanillaEnchantment !== null) {
                                    $oldLevel = 0;
                                    foreach ($itemClicked->getEnchantments() as $instance) {
                                        if ($instance->getType() === $vanillaEnchantment) {
                                            $oldLevel = $instance->getLevel();
                                            break;
                                        }
                                    }

                                    $maxLevel = $vanillaEnchantment->getMaxLevel();
                                    $bookLevel = min($level, $maxLevel);
                                    $newLevel = $oldLevel === $bookLevel
                                        ? min($oldLevel + 1, $maxLevel)
                                        : max($oldLevel, $bookLevel);
                                    $llamaBonusChance = ChunkWatchdogManager::getLlamaUpgradeChance($player->getPosition());
                                    if ($llamaBonusChance > 0 && $newLevel < $maxLevel && mt_rand(1, 100) <= $llamaBonusChance) {
                                        $newLevel = min($newLevel + 1, $maxLevel);
                                    }
                                    $itemClicked->addEnchantment(new EnchantmentInstance($vanillaEnchantment, $newLevel));
                                } else {
                                    $existing = CustomEnchantmentManager::getEnchantments($itemClicked);
                                    $oldLevel = $existing[$enchantName] ?? 0;
                                    $maxLevel = $enchantment->getMaxLevel();
                                    $bookLevel = min($level, $maxLevel);
                                    $newLevel = $oldLevel === $bookLevel
                                        ? min($oldLevel + 1, $maxLevel)
                                        : max($oldLevel, $bookLevel);
                                    $llamaBonusChance = ChunkWatchdogManager::getLlamaUpgradeChance($player->getPosition());
                                    if ($llamaBonusChance > 0 && $newLevel < $maxLevel && mt_rand(1, 100) <= $llamaBonusChance) {
                                        $newLevel = min($newLevel + 1, $maxLevel);
                                    }
                                    CustomEnchantmentManager::applyEnchantment($itemClicked, $enchantment, $newLevel);
                                }

                                $player->sendMessage(C::colorize("&aEnchantment applied!"));

                                $saved = false;
                                if($player->hasPermission("glacia.book-save-chance")){
                                    if(mt_rand(1, 100) <= 15){
                                        $saved = true;
                                        $player->sendMessage(C::colorize("&aYour rank saved the book!"));
                                    }
                                }

                                if(!$saved && RuneManager::hasPassiveRune($player, "scholar") && mt_rand(1, 100) <= RuneManager::getPassiveChance("scholar", 12)){
                                    $saved = true;
                                    $player->sendMessage(C::colorize("&r&eScholar Rune &r&7preserved your enchantment book."));
                                }

                                if(!$saved){
                                    $this->consumeEnchantmentBookWithTinkerRoll($player, $otherAction);
                                }

                                $action->getInventory()->setItem($action->getSlot(), $itemClicked);
                                if (strtolower($nbt->getString("group", "")) === "mastery") {
                                    Loader::getQuestBoardManager()->recordProgress($player, "mastery_enchants_applied", 1);
                                }
                                return;
                            }

                            $destroyRoll = mt_rand(1, 100) <= $destroy;

                            $glaciaItemTag = $itemClicked->getNamedTag()->getCompoundTag("Glacia");
                            $isProtected = $glaciaItemTag?->getByte("protected", 0) === 1;
                            if (!$isProtected) {
                                foreach ($itemClicked->getLore() as $line) {
                                    if (str_contains(strtoupper(C::clean($line)), "PROTECTED")) {
                                        $isProtected = true;
                                        break;
                                    }
                                }
                            }

                            if ($destroyRoll) {
                                if ($isProtected) {
                                    GlaciaUtils::consumeWhiteScroll($itemClicked, $player, $action->getInventory(), $action->getSlot());
                                } else {
                                    $action->getInventory()->setItem($action->getSlot(), VanillaItems::AIR());
                                    $player->sendMessage(C::colorize("&cEnchantment failed & your item was destroyed."));
                                }

                                $this->consumeEnchantmentBookWithTinkerRoll($player, $otherAction);
                                return;
                            }

                            $player->sendMessage(C::colorize("&eEnchantment failed, but item survived."));

                            $bookSaved = false;
                            if ($player->hasPermission("glacia.book-save-chance")) {
                                if(mt_rand(1, 100) <= 15){
                                    $bookSaved = true;
                                    $player->sendMessage(C::colorize("&aYour rank saved the book!"));
                                }
                            }

                            if (!$bookSaved && RuneManager::hasPassiveRune($player, "scholar") && mt_rand(1, 100) <= RuneManager::getPassiveChance("scholar", 12)) {
                                $bookSaved = true;
                                $player->sendMessage(C::colorize("&r&eScholar Rune &r&7preserved your enchantment book."));
                            }

                            if (!$bookSaved) {
                                $this->consumeEnchantmentBookWithTinkerRoll($player, $otherAction);
                            }

                            $action->getInventory()->setItem($action->getSlot(), $itemClicked);
                        }
                    }
                }
            }
        }
    }

    private function queueEnchantingLapisRefill(Player $player, Inventory $inventory): void
    {
        Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($player, $inventory): void {
            if (!$player->isConnected() || $player->isClosed()) {
                return;
            }

            $this->refillEnchantingLapis($inventory);
        }), 1);
    }

    private function refillEnchantingLapis(Inventory $inventory): void
    {
        $lapisSlot = 1;
        if (!$this->isEnchantInventory($inventory)) {
            return;
        }

        $lapisInTable = $inventory->getItem($lapisSlot);
        if ($this->isSyntheticEnchantingLapis($lapisInTable) && $lapisInTable->getCount() === 64) {
            return;
        }

        $inventory->setItem($lapisSlot, $this->createSyntheticEnchantingLapis());
    }

    private function isEnchantInventory(Inventory $inventory): bool
    {
        return str_contains(strtolower($inventory::class), "enchantinventory");
    }

    private function createSyntheticEnchantingLapis(): Item
    {
        $lapis = VanillaItems::LAPIS_LAZULI();
        $lapis->setCount(64);

        $tag = $lapis->getNamedTag();
        $glacia = $tag->getCompoundTag("Glacia") ?? new CompoundTag();
        $glacia->setByte(self::SYNTHETIC_ENCHANTING_LAPIS_TAG, 1);
        $tag->setTag("Glacia", $glacia);
        $lapis->setNamedTag($tag);

        return $lapis;
    }

    private function isSyntheticEnchantingLapis(Item $item): bool
    {
        if ($item->getTypeId() !== VanillaItems::LAPIS_LAZULI()->getTypeId()) {
            return false;
        }

        $glacia = $item->getNamedTag()->getCompoundTag("Glacia");
        return $glacia?->getByte(self::SYNTHETIC_ENCHANTING_LAPIS_TAG, 0) === 1;
    }

    /**
     * @param array<int, mixed> $actions
     */
    private function isTryingToTakeEnchantingLapis(array $actions): bool
    {
        $touchesPlayerStorage = false;
        foreach ($actions as $action) {
            if (!$action instanceof SlotChangeAction) {
                continue;
            }

            if ($action->getInventory() instanceof PlayerCursorInventory || str_contains(strtolower($action->getInventory()::class), "playerinventory")) {
                $touchesPlayerStorage = true;
                break;
            }
        }

        if (!$touchesPlayerStorage) {
            return false;
        }

        $lapisTypeId = VanillaItems::LAPIS_LAZULI()->getTypeId();
        foreach ($actions as $action) {
            if (!$action instanceof SlotChangeAction || !$this->isEnchantInventory($action->getInventory()) || $action->getSlot() !== 1) {
                continue;
            }

            $source = $action->getSourceItem();
            $target = $action->getTargetItem();
            if ($source->getTypeId() !== $lapisTypeId) {
                continue;
            }

            if ($target->getTypeId() !== $lapisTypeId || $target->getCount() < $source->getCount()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, mixed> $actions
     */
    private function transactionTouchesSyntheticEnchantingLapis(array $actions): bool
    {
        foreach ($actions as $action) {
            if (!$action instanceof SlotChangeAction) {
                continue;
            }

            if ($this->isSyntheticEnchantingLapis($action->getSourceItem()) || $this->isSyntheticEnchantingLapis($action->getTargetItem())) {
                return true;
            }
        }

        return false;
    }

    private function scheduleSyntheticEnchantingLapisScrub(Player $player): void
    {
        Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($player): void {
            if (!$player->isConnected() || $player->isClosed()) {
                return;
            }

            $this->scrubSyntheticEnchantingLapis($player);
        }), 1);
    }

    private function scrubSyntheticEnchantingLapis(Player $player): void
    {
        $inventory = $player->getInventory();
        for ($slot = 0; $slot < $inventory->getSize(); $slot++) {
            if ($this->isSyntheticEnchantingLapis($inventory->getItem($slot))) {
                $inventory->setItem($slot, VanillaItems::AIR());
            }
        }

        $cursorInventory = $player->getCursorInventory();
        for ($slot = 0; $slot < $cursorInventory->getSize(); $slot++) {
            if ($this->isSyntheticEnchantingLapis($cursorInventory->getItem($slot))) {
                $cursorInventory->setItem($slot, VanillaItems::AIR());
            }
        }
    }

    /**
     * @priority HIGHEST
     */
    public function onDragRunes(InventoryTransactionEvent $event): void
    {
        $transaction = $event->getTransaction();
        $actions = $this->getNormalizedDragActions($transaction);
        if ($actions === null) {
            return;
        }
        if ($this->isPlainCursorRearrangementOfSameItem($actions[0], $actions[1])) {
            return;
        }

        foreach ($actions as $i => $action) {
            if (!$action instanceof SlotChangeAction) {
                continue;
            }

            $otherAction = $actions[($i + 1) % 2];
            if (!$otherAction instanceof SlotChangeAction) {
                continue;
            }
            if ($this->isInvMenuSlotAction($action) || $this->isInvMenuSlotAction($otherAction)) {
                continue;
            }
            if ($this->isInvMenuSlotAction($action) || $this->isInvMenuSlotAction($otherAction)) {
                continue;
            }

            $itemClickedWith = $action->getTargetItem();
            $itemClicked = $action->getSourceItem();
            $player = $transaction->getSource();

            if (!$player instanceof Player || $itemClickedWith->getCount() !== 1 || $itemClicked->isNull()) {
                continue;
            }

            $glaciaTag = $itemClickedWith->getNamedTag()->getCompoundTag("Glacia");
            if ($glaciaTag === null || $glaciaTag->getTag("glaciaItem") === null) {
                continue;
            }

            $itemTag = $glaciaTag->getString("glaciaItem");

            if ($itemTag === "rune_remover") {
                if (!RuneManager::hasAppliedRune($itemClicked)) {
                    continue;
                }

                $event->cancel();
                $appliedRuneId = RuneManager::getAppliedRuneId($itemClicked);
                if (mt_rand(1, 100) <= RuneManager::getRemovalChance()) {
                    $itemClicked = RuneManager::removeRune($itemClicked);
                    $action->getInventory()->setItem($action->getSlot(), $itemClicked);
                    if ($appliedRuneId !== null) {
                        $rune = GlaciaItemFactory::rune($appliedRuneId);
                        foreach ($player->getInventory()->addItem($rune) as $leftover) {
                            $player->getWorld()->dropItem($player->getPosition(), $leftover);
                        }
                    }
                    $player->sendMessage(C::colorize("&r&aRune removed successfully."));
                    PlayerUtils::playSound($player, "random.levelup");
                } else {
                    $player->sendMessage(C::colorize("&r&cThe rune clung to the item. Extraction failed."));
                    PlayerUtils::playSound($player, "note.bass");
                }

                $otherAction->getInventory()->setItem($otherAction->getSlot(), VanillaItems::AIR());
                return;
            }

            if ($itemTag !== "rune" && $itemTag !== "ring") {
                continue;
            }

            $event->cancel();
            if ($itemTag === "ring") {
                $ringId = strtolower($glaciaTag->getString("ring_id", ""));
                if ($ringId === "" || !RingManager::hasDefinition($ringId)) {
                    $player->sendMessage(C::colorize("&r&cThis ring is invalid."));
                    PlayerUtils::playSound($player, "note.bass");
                    return;
                }

                if (RingManager::hasAppliedRing($itemClicked, $ringId)) {
                    $player->sendMessage(C::colorize("&r&cThis item already has that ring bound to it."));
                    PlayerUtils::playSound($player, "note.bass");
                    return;
                }

                if (!RingManager::canApplyTo($itemClicked, $ringId)) {
                    $player->sendMessage(C::colorize("&r&cThat ring cannot be applied to this item."));
                    PlayerUtils::playSound($player, "note.bass");
                    return;
                }

                $success = max(1, min(100, NbtLenient::readInt($glaciaTag, "success", RingManager::getSuccessChance($ringId), 0, 100, $player)));
                if (mt_rand(1, 100) <= $success) {
                    $itemClicked = RingManager::applyRing($itemClicked, $ringId);
                    $action->getInventory()->setItem($action->getSlot(), $itemClicked);
                    $player->sendMessage(C::colorize("&r&aBound the &f" . RingManager::getDisplayName($ringId) . " &aring to your item."));
                    PlayerUtils::playSound($player, "random.levelup");
                } else {
                    $player->sendMessage(C::colorize("&r&cThe ring cracked before it could take hold."));
                    PlayerUtils::playSound($player, "note.bass");
                }

                $otherAction->getInventory()->setItem($otherAction->getSlot(), VanillaItems::AIR());
                return;
            }

            $runeId = strtolower($glaciaTag->getString("rune_id", ""));
            if ($runeId === "" || !RuneManager::hasDefinition($runeId)) {
                $player->sendMessage(C::colorize("&r&cThis rune is invalid."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            if (RuneManager::isPassive($runeId)) {
                $player->sendMessage(C::colorize("&r&7This rune is passive. Keep it in your inventory to activate it."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            if (RuneManager::hasAppliedRune($itemClicked)) {
                $player->sendMessage(C::colorize("&r&cThis item already has a rune bound to it."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            if (!RuneManager::canApplyTo($itemClicked, $runeId)) {
                $player->sendMessage(C::colorize("&r&cThat rune cannot be applied to this item."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            if (mt_rand(1, 100) <= RuneManager::getSuccessChance($runeId)) {
                $itemClicked = RuneManager::applyRune($itemClicked, $runeId);
                $action->getInventory()->setItem($action->getSlot(), $itemClicked);
                $player->sendMessage(C::colorize("&r&aBound the &f" . RuneManager::getDisplayName($runeId) . " &arune to your item."));
                PlayerUtils::playSound($player, "random.levelup");
            } else {
                $player->sendMessage(C::colorize("&r&cThe rune shattered before it could take hold."));
                PlayerUtils::playSound($player, "note.bass");
            }

            $otherAction->getInventory()->setItem($otherAction->getSlot(), VanillaItems::AIR());
            return;
        }
    }

    /**
     * @priority HIGHEST
     */
    public function onDragCcEnhancer(InventoryTransactionEvent $event): void
    {
        $transaction = $event->getTransaction();
        $actions = $this->getNormalizedDragActions($transaction);
        if ($actions === null) {
            return;
        }
        if ($this->isPlainCursorRearrangementOfSameItem($actions[0], $actions[1])) {
            return;
        }

        foreach ($actions as $i => $action) {
            if (!$action instanceof SlotChangeAction) {
                continue;
            }

            $otherAction = $actions[($i + 1) % 2];
            if (!$otherAction instanceof SlotChangeAction) {
                continue;
            }
            if ($this->isInvMenuSlotAction($action) || $this->isInvMenuSlotAction($otherAction)) {
                continue;
            }

            $itemClickedWith = $action->getTargetItem();
            $itemClicked = $action->getSourceItem();
            if ($itemClicked->getTypeId() === BlockTypeIds::AIR) {
                continue;
            }
            if ($itemClickedWith->getCount() !== 1) {
                continue;
            }

            $glaciaTag = $itemClickedWith->getNamedTag()->getCompoundTag("Glacia");
            if ($glaciaTag === null || $glaciaTag->getString("glaciaItem", "") !== CcProgressManager::ENHANCER_DEFINITION) {
                continue;
            }

            $targetTag = $itemClicked->getNamedTag()->getCompoundTag("Glacia");
            $player = $transaction->getSource();
            if ($targetTag === null) {
                continue;
            }

            $targetType = $targetTag->getString("glaciaItem", "");
            $targetId = match ($targetType) {
                "lootbox" => strtolower($targetTag->getString("lootbox", "")),
                "ethereal_crate" => strtolower($targetTag->getString("ethereal_crate", "")),
                default => "",
            };
            if ($targetId === "") {
                continue;
            }

            $event->cancel();
            $currentId = $targetId;
            $nextId = CcProgressManager::getNextRewardId($currentId, $targetType);
            if ($nextId === null) {
                $player->sendMessage(C::colorize("&r&cThis is the max EC, you cannot upgrade it."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            try {
                $nextDescriptor = CcProgressManager::resolveRewardDescriptor($nextId);
                if ($nextDescriptor === null) {
                    throw new \InvalidArgumentException("Unknown configured EC reward: {$nextId}");
                }

                $upgraded = $nextDescriptor["type"] === "ethereal_crate"
                    ? GlaciaItemFactory::etherealCrate($nextDescriptor["id"], $player)
                    : GlaciaItemFactory::lootbox($nextDescriptor["id"], $player);
            } catch (\Throwable) {
                $player->sendMessage(C::colorize("&r&cThis is the max EC, you cannot upgrade it."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $success = max(1, min(100, NbtLenient::readInt($glaciaTag, "success", CcProgressManager::getEnhancerSuccessRate(), 0, 100, $player)));
            $otherAction->getInventory()->setItem($otherAction->getSlot(), VanillaItems::AIR());

            if (mt_rand(1, 100) <= $success) {
                $action->getInventory()->setItem($action->getSlot(), $upgraded);
                $player->sendMessage(C::colorize("&r&aEC Enhancer succeeded! &f" . ucfirst(str_replace("_", " ", $currentId)) . " &awas upgraded."));
                PlayerUtils::playSound($player, "random.levelup");
            } else {
                $action->getInventory()->setItem($action->getSlot(), $itemClicked);
                $player->sendMessage(C::colorize("&r&cEC Enhancer failed, but the crate was preserved."));
                PlayerUtils::playSound($player, "note.bass");
            }
            return;
        }
    }

    /**
     * @priority HIGHEST
     */
    public function onDragEssence(InventoryTransactionEvent $event): void
    {
        $transaction = $event->getTransaction();
        $actions = $this->getNormalizedDragActions($transaction);
        if ($actions === null) {
            return;
        }
        if ($this->isPlainCursorRearrangementOfSameItem($actions[0], $actions[1])) {
            return;
        }

        foreach ($actions as $i => $action) {
            if (!$action instanceof SlotChangeAction) {
                continue;
            }

            $otherAction = $actions[($i + 1) % 2];
            if (!$otherAction instanceof SlotChangeAction) {
                continue;
            }
            if ($this->isInvMenuSlotAction($action) || $this->isInvMenuSlotAction($otherAction)) {
                continue;
            }

            $itemClickedWith = $action->getTargetItem();
            $itemClicked = $action->getSourceItem();
            if ($itemClickedWith->getCount() !== 1) {
                continue;
            }

            $glaciaTag = $itemClickedWith->getNamedTag()->getCompoundTag("Glacia");
            if ($glaciaTag === null || $glaciaTag->getString("glaciaItem", "") !== "enchant_essence") {
                continue;
            }

            $player = $transaction->getSource();
            $essenceId = strtolower($glaciaTag->getString("essence_id", ""));
            if ($essenceId === "") {
                $player->sendMessage(C::colorize("&r&cThis essence is invalid."));
                return;
            }

            $reason = null;
            if (!EnchantEssenceManager::canApply($itemClicked, $essenceId, $reason)) {
                $player->sendMessage(C::colorize("&r&c" . ($reason ?? "This essence cannot be applied here.")));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $successPercent = EnchantEssenceManager::getApplySuccessPercent($itemClickedWith, $essenceId);
            if (mt_rand(1, 100) > $successPercent) {
                $event->cancel();
                $otherAction->getInventory()->setItem($otherAction->getSlot(), VanillaItems::AIR());
                $player->sendMessage(C::colorize("&r&cThe essence failed to bond. (&7{$successPercent}%&c success)"));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $event->cancel();
            $otherAction->getInventory()->setItem($otherAction->getSlot(), VanillaItems::AIR());
            $action->getInventory()->setItem($action->getSlot(), EnchantEssenceManager::apply($itemClicked, $essenceId));
            $player->sendMessage(C::colorize("&r&aApplied the &f" . ucfirst(str_replace("_", " ", $essenceId)) . " Essence&a."));
            PlayerUtils::playSound($player, "random.levelup");
            return;
        }
    }

    /**
     * @priority HIGHEST
     */
    public function onDragExclusiveAmplifier(InventoryTransactionEvent $event): void
    {
        $transaction = $event->getTransaction();
        $actions = $this->getNormalizedDragActions($transaction);
        if ($actions === null) {
            return;
        }
        if ($this->isPlainCursorRearrangementOfSameItem($actions[0], $actions[1])) {
            return;
        }

        foreach ($actions as $i => $action) {
            if (!$action instanceof SlotChangeAction) {
                continue;
            }

            $otherAction = $actions[($i + 1) % 2];
            if (!$otherAction instanceof SlotChangeAction) {
                continue;
            }
            if ($this->isInvMenuSlotAction($action) || $this->isInvMenuSlotAction($otherAction)) {
                continue;
            }

            $itemClickedWith = $action->getTargetItem();
            $itemClicked = $action->getSourceItem();
            if ($itemClickedWith->getCount() !== 1) {
                continue;
            }

            $glaciaTag = $itemClickedWith->getNamedTag()->getCompoundTag("Glacia");
            if ($glaciaTag === null || $glaciaTag->getString("glaciaItem", "") !== "exclusive_amplifier") {
                continue;
            }

            $player = $transaction->getSource();
            if ($this->isPlayerInsideEndfall($player)) {
                $player->sendMessage(C::colorize("&r&cExclusive enchants cannot be modified in Endfall."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }
            $exclusiveEnchants = ExclusiveEnchantManager::getExclusiveEnchantments($itemClicked);
            if ($exclusiveEnchants === []) {
                $player->sendMessage(C::colorize("&r&cThat item does not have an exclusive enchant to amplify."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $uses = max(1, NbtLenient::readInt($glaciaTag, "uses", 8, 1, 10000, $player));
            $event->cancel();
            $otherAction->getInventory()->setItem($otherAction->getSlot(), VanillaItems::AIR());
            $action->getInventory()->setItem($action->getSlot(), ExclusiveEnchantManager::addUsesToItem($itemClicked, $uses));
            $player->sendMessage(C::colorize("&r&aAdded &f+{$uses} uses &ato: &f" . implode("&7, &f", array_map("ucfirst", $exclusiveEnchants)) . "&a."));
            PlayerUtils::playSound($player, "random.levelup");
            return;
        }
    }

    /**
     * @priority HIGHEST
     */
    public function onDragPetTamer(InventoryTransactionEvent $event): void
    {
        $transaction = $event->getTransaction();
        $actions = $this->getNormalizedDragActions($transaction);
        if ($actions === null) {
            return;
        }
        if ($this->isPlainCursorRearrangementOfSameItem($actions[0], $actions[1])) {
            return;
        }

        foreach ($actions as $i => $action) {
            if (!$action instanceof SlotChangeAction) {
                continue;
            }

            $otherAction = $actions[($i + 1) % 2];
            if (!$otherAction instanceof SlotChangeAction) {
                continue;
            }
            if ($this->isInvMenuSlotAction($action) || $this->isInvMenuSlotAction($otherAction)) {
                continue;
            }
            if ($this->isInvMenuSlotAction($action) || $this->isInvMenuSlotAction($otherAction)) {
                continue;
            }

            $itemClickedWith = $action->getTargetItem();
            $itemClicked = $action->getSourceItem();
            $glaciaTag = $itemClickedWith->getNamedTag()->getCompoundTag("Glacia");
            if ($glaciaTag === null || $glaciaTag->getString("glaciaItem", "") !== "pet_tamer") {
                continue;
            }

            $player = $transaction->getSource();
            if (!GlaciaItemStackPolicy::isPetItem($itemClicked)) {
                $player->sendMessage(C::colorize("&cYou can only apply a Pet Tamer to a pet item."));
                continue;
            }

            $event->cancel();
            $itemClicked = $this->setPetTamerState($itemClicked, true);
            $otherAction->getInventory()->setItem($otherAction->getSlot(), VanillaItems::AIR());
            $action->getInventory()->setItem($action->getSlot(), $itemClicked);
            $player->sendMessage(C::colorize("&r&aPet Tamer applied. This pet will return to you one time on death."));
            PlayerUtils::playSound($player, "random.levelup");
            return;
        }
    }

    /**
     * @priority HIGHEST
     */
    public function onDragScrolls(InventoryTransactionEvent $event): void {
        $transaction = $event->getTransaction();
        $actions = $this->getNormalizedDragActions($transaction);
        if($actions === null) return;
        if ($this->isPlainCursorRearrangementOfSameItem($actions[0], $actions[1])) {
            return;
        }

        foreach($actions as $i => $action){
            if(!$action instanceof SlotChangeAction) continue;

            $otherAction = $actions[($i + 1) % 2];
            if(!$otherAction instanceof SlotChangeAction) continue;
            if ($this->isInvMenuSlotAction($action) || $this->isInvMenuSlotAction($otherAction)) continue;

            $player = $transaction->getSource();
            if (!$player instanceof Player) {
                return;
            }

            $randomizationGroupEarly = $this->findRandomizationScrollGroupInMerge($action, $otherAction);
            if ($randomizationGroupEarly !== null) {
                $bookItemEarly = $this->findEnchantmentBookInMerge($action, $otherAction);
                if ($bookItemEarly === null) {
                    // Scroll moved onto air / non-book — not a reroll merge; let vanilla placement finish without chat spam.
                    continue;
                }

                [$bookSlotActionEarly, $scrollSlotActionEarly] = $this->resolveBookAndScrollSlotActionsForRandomization($action, $otherAction);
                $event->cancel();
                $this->handleRandomizationScroll($player, $bookSlotActionEarly, $scrollSlotActionEarly, clone $bookItemEarly, $randomizationGroupEarly);
                return;
            }

            $itemClickedWith = $action->getTargetItem();
            $itemClicked = $action->getSourceItem();

            if($itemClickedWith->getCount() !== 1 || $itemClicked->getTypeId() === VanillaItems::AIR()->getTypeId()) continue;

            $glaciaTag = $itemClickedWith->getNamedTag()->getCompoundTag("Glacia");
            if($glaciaTag === null || $glaciaTag->getTag("glaciaItem") === null) continue;

            $itemTag = $glaciaTag->getString("glaciaItem");

            if (
                $itemTag !== "whitescroll" &&
                $itemTag !== "holy_whitescroll" &&
                $itemTag !== "holy_water" &&
                $itemTag !== "blackscroll" &&
                $itemTag !== "holy_blackscroll" &&
                $itemTag !== "conjuration_oracle" &&
                $itemTag !== "conjuration_oracule" &&
                $itemTag !== "armor_orb_script" &&
                $itemTag !== "weapon_orb_script" &&
                $itemTag !== "transmog" &&
                $itemTag !== "heroic_upgrade" &&
                !str_contains($itemTag, "randomization_scroll") &&
                !str_contains($itemTag, "orb")
            ) {
                continue;
            }

            $randomizationGroup = $this->getRandomizationScrollGroup($itemTag);
            if ($randomizationGroup !== null) {
                if ($itemClicked->getNamedTag()->getCompoundTag("Glacia")?->getString("glaciaItem", "") !== "enchantment_book") {
                    continue;
                }

                $event->cancel();
                [$bookSlotActionLate, $scrollSlotActionLate] = $this->resolveBookAndScrollSlotActionsForRandomization($action, $otherAction);
                $this->handleRandomizationScroll($player, $bookSlotActionLate, $scrollSlotActionLate, $itemClicked, $randomizationGroup);
                return;
            }

            if ($itemTag === "conjuration_oracle" || $itemTag === "conjuration_oracule") {
                $event->cancel();
                [$itemSlotAction, $oracleSlotAction] = $this->resolveBookAndScrollSlotActionsForRandomization($action, $otherAction);
                $this->showConjurationOracleForm(
                    $player,
                    $itemSlotAction->getInventory(),
                    $itemSlotAction->getSlot(),
                    $oracleSlotAction->getInventory(),
                    $oracleSlotAction->getSlot(),
                    clone $itemClicked
                );
                return;
            }

            if ($itemTag === "armor_orb_script" || $itemTag === "weapon_orb_script") {
                $event->cancel();

                $targetTag = $itemClicked->getNamedTag()->getCompoundTag("Glacia");
                $targetId = $targetTag?->getString("glaciaItem", "") ?? "";
                $expectedOrb = $itemTag === "armor_orb_script" ? "armor_orb" : "weapon_orb";
                $label = $itemTag === "armor_orb_script" ? "Armor" : "Weapon";
                if ($targetId !== $expectedOrb) {
                    $player->sendMessage(C::colorize("&cThat script can only be used on a {$label} Orb."));
                    PlayerUtils::playSound($player, "note.bass");
                    return;
                }

                $orbSlots = NbtLenient::readInt($targetTag, "max", 9, 1, 64, $player);
                $orbNew = NbtLenient::readInt($targetTag, "new", max(1, $orbSlots - 9), 1, 64, $player);
                $newSuccess = mt_rand(1, 100);
                $rerolled = GlaciaItemFactory::enchantOrb($itemTag === "armor_orb_script" ? "armor" : "weapon", $orbSlots, $orbNew, $newSuccess);

                $otherAction->getInventory()->setItem($otherAction->getSlot(), VanillaItems::AIR());
                $action->getInventory()->setItem($action->getSlot(), $rerolled);
                $player->sendMessage(C::colorize("&a{$label} Orb rerolled to &f{$newSuccess}% &asuccess."));
                PlayerUtils::playSound($player, "random.levelup");
                return;
            }

            $canApply = $itemClicked instanceof Armor
                    || $itemClicked instanceof Sword
                    || $itemClicked instanceof Axe
                    || $itemClicked instanceof Pickaxe
                    || $itemClicked instanceof Shovel
                    || $itemClicked instanceof Hoe
                    || $itemClicked instanceof Bow
                    || $itemClicked instanceof FishingRod;

            if(!$canApply){
                $player->sendMessage(C::colorize("&cYou cannot apply a scroll to this item!"));
                continue;
            }

            $event->cancel();

            if($itemTag === "whitescroll"){
                $itemTagRoot = $itemClicked->getNamedTag()->getCompoundTag("Glacia") ?? new CompoundTag();

                if($itemTagRoot->getByte("protected", 0) === 1){
                    $player->sendMessage(C::colorize("&cThis item is already protected!"));
                    return;
                }

                $itemTagRoot->setByte("protected", 1);
                $itemClicked->getNamedTag()->setTag("Glacia", $itemTagRoot);

                $lore = $itemClicked->getLore();
                $protectedLine = C::colorize("&r&l&fPROTECTED");
                if(!in_array($protectedLine, $lore)){
                    $lore[] = $protectedLine;
                    $itemClicked->setLore($lore);
                }

                $otherAction->getInventory()->setItem($otherAction->getSlot(), VanillaItems::AIR());
                $player->sendMessage(C::colorize("&aWhite Scroll applied! Your item is now protected."));
                PlayerUtils::playSound($player, "random.levelup");
                $action->getInventory()->setItem($action->getSlot(), $itemClicked);
                Loader::getQuestBoardManager()->recordProgress($player, "white_scrolls_applied", 1);
                return;
            }

            if ($itemTag === "holy_whitescroll") {
                $itemTagRoot = $itemClicked->getNamedTag()->getCompoundTag("Glacia") ?? new CompoundTag();
                if ($itemTagRoot->getByte("protected", 0) !== 1) {
                    $player->sendMessage(C::colorize("&cHoly White Scrolls require a White Scroll first."));
                    PlayerUtils::playSound($player, "note.bass");
                    return;
                }

                $maxHolyLives = max(1, NbtLenient::readInt($itemTagRoot, "holy_white_max", 3, 1, 100, $player));
                $usedHolyLives = max(0, NbtLenient::readInt($itemTagRoot, "holy_white_used", 0, 0, 10000, $player));
                $isActive = $itemTagRoot->getByte("holy_white_active", 0) === 1;
                if ($usedHolyLives >= $maxHolyLives) {
                    $itemTagRoot->setByte("holy_white_corrupted", 1);
                    $itemClicked->getNamedTag()->setTag("Glacia", $itemTagRoot);
                    $this->refreshHolyWhiteLore($itemClicked, $usedHolyLives, $maxHolyLives);
                    $action->getInventory()->setItem($action->getSlot(), $itemClicked);
                    $player->sendMessage(C::colorize("&cThis item is CORRUPTED and cannot take more Holy White Scrolls."));
                    PlayerUtils::playSound($player, "note.bass");
                    return;
                }
                if ($isActive) {
                    $player->sendMessage(C::colorize("&cA Holy White Scroll is already active on this item. It must trigger on death first."));
                    PlayerUtils::playSound($player, "note.bass");
                    return;
                }

                $itemTagRoot->setInt("holy_white_used", $usedHolyLives);
                $itemTagRoot->setInt("holy_white_max", $maxHolyLives);
                $itemTagRoot->setByte("holy_white_active", 1);
                $itemTagRoot->setByte("holy_white_corrupted", 0);
                $itemClicked->getNamedTag()->setTag("Glacia", $itemTagRoot);
                $this->refreshHolyWhiteLore($itemClicked, $usedHolyLives, $maxHolyLives);

                $otherAction->getInventory()->setItem($otherAction->getSlot(), VanillaItems::AIR());
                $action->getInventory()->setItem($action->getSlot(), $itemClicked);
                $remaining = max(0, $maxHolyLives - $usedHolyLives);
                $player->sendMessage(C::colorize("&aHoly White Scroll primed! &f{$remaining} &7possible death-saves remaining."));
                PlayerUtils::playSound($player, "random.levelup");
                return;
            }

            if ($itemTag === "holy_water") {
                $itemTagRoot = $itemClicked->getNamedTag()->getCompoundTag("Glacia") ?? new CompoundTag();
                if ($itemTagRoot->getByte("protected", 0) !== 1) {
                    $player->sendMessage(C::colorize("&cHoly Water can only be used on White-Scrolled items."));
                    PlayerUtils::playSound($player, "note.bass");
                    return;
                }

                $maxHolyLives = max(1, NbtLenient::readInt($itemTagRoot, "holy_white_max", 3, 1, 100, $player));
                $usedHolyLives = max(0, NbtLenient::readInt($itemTagRoot, "holy_white_used", 0, 0, 10000, $player));
                if ($maxHolyLives >= 4) {
                    $player->sendMessage(C::colorize("&cThis item has already been revitalized."));
                    PlayerUtils::playSound($player, "note.bass");
                    return;
                }
                if ($usedHolyLives < $maxHolyLives) {
                    $player->sendMessage(C::colorize("&cHoly Water can only be used once the item is fully corrupted."));
                    PlayerUtils::playSound($player, "note.bass");
                    return;
                }

                $itemTagRoot->setInt("holy_white_max", 4);
                $itemTagRoot->setByte("holy_white_corrupted", 0);
                $itemClicked->getNamedTag()->setTag("Glacia", $itemTagRoot);
                $this->refreshHolyWhiteLore($itemClicked, $usedHolyLives, 4);

                $otherAction->getInventory()->setItem($otherAction->getSlot(), VanillaItems::AIR());
                $action->getInventory()->setItem($action->getSlot(), $itemClicked);
                $player->sendMessage(C::colorize("&bHoly Water applied! Corruption cap boosted to &f4&b."));
                PlayerUtils::playSound($player, "random.levelup");
                return;
            }

            if ($itemTag === "transmog") {
                $itemRoot = $itemClicked->getNamedTag()->getCompoundTag("Glacia") ?? new CompoundTag();

                if ($itemRoot->getByte("transmog", 0) === 1) {
                    $player->sendMessage(C::colorize("&cThis item already has a Transmog Scroll applied."));
                    return;
                }

                $itemRoot->setByte("transmog", 1);
                $itemClicked->getNamedTag()->setTag("Glacia", $itemRoot);
                $itemClicked = $this->syncTransmogDisplayName($itemClicked);

                $otherAction->getInventory()->setItem($otherAction->getSlot(), VanillaItems::AIR());
                $action->getInventory()->setItem($action->getSlot(), $itemClicked);

                $player->sendMessage(C::colorize("&aTransmog Scroll applied! This item's enchants will now be ordered by rarity."));
                PlayerUtils::playSound($player, "random.levelup");
                Loader::getQuestBoardManager()->recordProgress($player, "transmog_scrolls_applied", 1);
                return;
            }

            if ($itemTag === "heroic_upgrade") {
                if (!$itemClicked instanceof Armor || !$itemClicked instanceof Durable) {
                    $player->sendMessage(C::colorize("&cYou can only apply a Heroic Upgrade to armor."));
                    PlayerUtils::playSound($player, "note.bass");
                    return;
                }

                if (HeroicArmorDurabilityManager::isHeroicArmor($itemClicked)) {
                    $player->sendMessage(C::colorize("&cThis armor is already heroic."));
                    PlayerUtils::playSound($player, "note.bass");
                    return;
                }

                $success = max(1, min(100, NbtLenient::readInt($glaciaTag, "success", EnchantTuningManager::getInt("heroicarmor", "upgrade-success-chance", 65), 0, 100, $player)));
                $otherAction->getInventory()->setItem($otherAction->getSlot(), VanillaItems::AIR());
                $forcedSuccess = PetManager::consumeGuaranteedSuccess($player);

                if ($forcedSuccess || mt_rand(1, 100) <= $success) {
                    $itemClicked = HeroicArmorDurabilityManager::upgradeItemToHeroic($itemClicked);
                    $action->getInventory()->setItem($action->getSlot(), $itemClicked);
                    $player->sendMessage(C::colorize("&r&aHeroic Upgrade succeeded! &fThis armor is now heroic."));
                    PlayerUtils::playSound($player, "random.levelup");
                } else {
                    $action->getInventory()->setItem($action->getSlot(), $itemClicked);
                    $player->sendMessage(C::colorize("&r&cHeroic Upgrade failed."));
                    PlayerUtils::playSound($player, "note.bass");
                }
                return;
            }

            if ($itemTag === "blackscroll") {
                $scrollSuccess = NbtLenient::readInt($glaciaTag, "success", 100, 0, 100, $player);

                $removable = $this->getRemovableCustomEnchantments($itemClicked);
                if ($removable === []) {
                    $player->sendMessage(C::colorize("&cThis item has no removable enchantments."));
                    return;
                }

                $removedName = array_rand($removable);
                $removedLevel = $removable[$removedName];
                $removedEnchant = CustomEnchantments::getEnchantmentByName($removedName);

                if ($removedEnchant === null) {
                    $player->sendMessage(C::colorize("&cThat enchantment could not be extracted."));
                    return;
                }

                CustomEnchantmentManager::removeEnchantment($itemClicked, $removedEnchant);
                $itemClicked->setLore($this->stripCustomEnchantLoreLines($itemClicked->getLore(), [$removedName]));

                $destroyRate = mt_rand(0, 100);
                $book = GlaciaItemFactory::enchantmentBook(
                    $removedEnchant,
                    $removedLevel,
                    $scrollSuccess,
                    $destroyRate
                );

                $otherAction->getInventory()->setItem($otherAction->getSlot(), VanillaItems::AIR());
                $action->getInventory()->addItem($book);
                $action->getInventory()->setItem($action->getSlot(), $itemClicked);

                $player->sendMessage(C::colorize("&bRemoved &f{$removedName} {$removedLevel} &bfrom your item!"));
                Loader::getQuestBoardManager()->recordProgress($player, "black_scrolls_used", 1);
                return;
            }

            if ($itemTag === "holy_blackscroll") {
                $scrollSuccess = NbtLenient::readInt($glaciaTag, "success", 100, 0, 100, $player);
                $removable = $this->getRemovableCustomEnchantments($itemClicked);
                if ($removable === []) {
                    $player->sendMessage(C::colorize("&cThis item has no removable enchantments."));
                    return;
                }

                $removedNames = [];
                $books = [];
                foreach ($removable as $removedName => $removedLevel) {
                    $removedEnchant = CustomEnchantments::getEnchantmentByName($removedName);
                    if ($removedEnchant === null) {
                        continue;
                    }

                    CustomEnchantmentManager::removeEnchantment($itemClicked, $removedEnchant);
                    $removedNames[] = $removedName;
                    $books[] = GlaciaItemFactory::enchantmentBook(
                        $removedEnchant,
                        $removedLevel,
                        $scrollSuccess,
                        mt_rand(0, 100)
                    );
                }

                if ($removedNames === []) {
                    $player->sendMessage(C::colorize("&cNone of this item's enchantments could be extracted."));
                    return;
                }

                $itemClicked->setLore($this->stripCustomEnchantLoreLines($itemClicked->getLore(), $removedNames));
                $otherAction->getInventory()->setItem($otherAction->getSlot(), VanillaItems::AIR());
                foreach ($books as $book) {
                    if ($book === null) {
                        continue;
                    }
                    foreach ($player->getInventory()->addItem($book) as $leftover) {
                        $player->getWorld()->dropItem($player->getPosition(), $leftover);
                    }
                }
                $action->getInventory()->setItem($action->getSlot(), $itemClicked);

                $player->sendMessage(C::colorize("&bHoly Black Scroll extracted &f" . count($removedNames) . " &bcustom enchantments from your item!"));
                PlayerUtils::playSound($player, "random.levelup");
                Loader::getQuestBoardManager()->recordProgress($player, "holy_black_scrolls_used", 1);
                return;
            }

            if (str_contains($itemTag, "orb")) {
                $orbLowType = explode("_", $itemTag)[0];
                $orbSlots = NbtLenient::readInt($glaciaTag, "max", 9, 1, 64, $player);
                $orbSuccess = NbtLenient::readInt($glaciaTag, "success", 100, 0, 100, $player);

                $BASE_SLOTS = $itemClicked instanceof FishingRod ? 3 : 8;
                $orbBonus = max(0, $orbSlots - $BASE_SLOTS);
                $lore = $itemClicked->getLore();
                $existingRoot = $itemClicked->getNamedTag()->getCompoundTag("Glacia");
                $currentMaxSlots = NbtLenient::readInt($existingRoot, "maxEnchantSlots", $BASE_SLOTS, 1, 64, $player);
                $loreLineIndex = null;

                foreach ($lore as $index => $line) {
                    $clean = C::clean($line);

                    if (preg_match('/(\d+)\s*Enchantment Slots/i', $clean, $matches)) {
                        $currentMaxSlots = (int)$matches[1];
                        $loreLineIndex = $index;
                        break;
                    }
                }

                $event->cancel();

                $applies = match ($orbLowType) {
                    'weapon' => ($itemClicked instanceof Sword || $itemClicked instanceof Axe || $itemClicked instanceof Bow),
                    'armor'  => ($itemClicked instanceof Armor),
                    'tool'   => ($itemClicked instanceof Pickaxe || $itemClicked instanceof Shovel || $itemClicked instanceof Axe || $itemClicked instanceof Hoe),
                    'fishing' => ($itemClicked instanceof FishingRod),
                    default  => false,
                };

                if (!$applies) {
                    $player->sendMessage(C::colorize("&cThis orb cannot be applied to that item type!"));
                    return;
                }

                $root = $itemClicked->getNamedTag()->getCompoundTag("Glacia") ?? new CompoundTag();
                $existingOrbType = $root->getString("orb_type", "");

                if ($existingOrbType !== "" && $existingOrbType !== $orbLowType) {
                    $player->sendMessage(C::colorize("&cYou cannot apply a {$orbLowType} orb to an item already infused with a {$existingOrbType} orb!"));
                    return;
                }

                if ($orbSlots <= $currentMaxSlots) {
                    $player->sendMessage(C::colorize("&cThis orb cannot be applied because your item already has equal or higher enchantment slots."));
                    return;
                }

                $forcedSuccess = PetManager::consumeGuaranteedSuccess($player);
                if ($forcedSuccess || mt_rand(1, 100) <= $orbSuccess) {
                    if ($loreLineIndex !== null) {
                        unset($lore[$loreLineIndex]);
                        $lore = array_values($lore);
                    }

                    $newLine = C::colorize("&r&l&a{$orbSlots} Enchantment Slots &r&7([&a+{$orbBonus}&7])");
                    $lore[] = $newLine;
                    $itemClicked->setLore($lore);

                    $root->setInt("maxEnchantSlots", $orbSlots);
                    $root->setString("orb_type", $orbLowType);

                    $customEnchantCount = CustomEnchantmentManager::countGlaciaCesEntries($itemClicked);
                    $root->setInt("currentEnchants", $customEnchantCount);

                    $itemClicked->getNamedTag()->setTag("Glacia", $root);

                    $action->getInventory()->setItem($action->getSlot(), $itemClicked);
                    $otherAction->getInventory()->setItem($otherAction->getSlot(), VanillaItems::AIR());

                    $player->sendMessage(C::colorize("&aYour item has been upgraded to &f{$orbSlots} &aenchantment slots!"));
                    PlayerUtils::playSound($player, "random.levelup");

                } else {
                    $player->sendMessage(C::colorize("&cThe orb failed!"));
                    $otherAction->getInventory()->setItem($otherAction->getSlot(), VanillaItems::AIR());
                    Loader::getQuestBoardManager()->recordProgress($player, "orb_failures", 1);
                }

                return;
            }
        }
    }

    /**
     * @priority HIGHEST
     */
    public function onDragDusts(InventoryTransactionEvent $event): void {
        $transaction = $event->getTransaction();
        $actions = $this->getNormalizedDragActions($transaction);
        if($actions === null) return;
        if ($this->isPlainCursorRearrangementOfSameItem($actions[0], $actions[1])) {
            return;
        }

        foreach($actions as $i => $action){
            if(!$action instanceof SlotChangeAction) continue;

            $otherAction = $actions[($i + 1) % 2];
            if(!$otherAction instanceof SlotChangeAction) continue;
            if ($this->isInvMenuSlotAction($action) || $this->isInvMenuSlotAction($otherAction)) continue;

            $itemClickedWith = $action->getTargetItem();
            $itemClicked = $action->getSourceItem();
            $player = $transaction->getSource();

            if(!$player instanceof Player) continue;
            if($itemClickedWith->getTypeId() === VanillaItems::AIR()->getTypeId()) continue;

            $glaciaTag = $itemClickedWith->getNamedTag()->getCompoundTag("Glacia");
            if($glaciaTag === null || $glaciaTag->getTag("glaciaItem") === null) continue;

            $itemTag = $glaciaTag->getString("glaciaItem");

            $clickedTag = $itemClicked->getNamedTag()->getCompoundTag("Glacia");
            if($clickedTag === null || $clickedTag->getTag("glaciaItem") === null) continue;

            $clickedItemTag = $clickedTag->getString("glaciaItem");

            if($itemTag !== "magicdust" && $itemTag !== "primal_dust") continue;

            if($clickedItemTag !== "enchantment_book"){
                $player->sendMessage(C::colorize("&cYou can only apply dust to enchantment books!"));
                continue;
            }

            $event->cancel();

            $dustGroup = strtolower($glaciaTag->getString("group", ""));
            $dustPercent = NbtLenient::readInt($glaciaTag, "dust-percent", 0, 0, 100, $player);

            $bookEnchant = CustomEnchantmentManager::getEnchantment(strtolower($clickedTag->getString("enchant_book", "")));
            $bookRarity = $bookEnchant->getRarity();
            $bookGroup = strtolower(Groups::getGroupNameById($bookRarity));
            $bookSuccess = NbtLenient::readInt($clickedTag, "success", 0, 0, 100, $player);

            if($dustGroup === "" || $bookGroup === "" || $dustPercent === 0){
                $player->sendMessage(C::colorize("&cThis dust is missing required data."));
                return;
            }

            if(!$this->canApplyDustToBook($dustGroup, $bookGroup)){
                $player->sendMessage(C::colorize("&cThis dust cannot be applied to that enchantment book!"));
                return;
            }

            if($bookSuccess >= 100){
                $player->sendMessage(C::colorize("&cThis enchantment book is already at &f100%&c."));
                return;
            }

            $newSuccess = min(100, $bookSuccess + $dustPercent);

            $book = clone $itemClicked;
            $bookRoot = $book->getNamedTag();
            $bookTag = $bookRoot->getCompoundTag("Glacia") ?? new CompoundTag();
            $bookTag->setInt("success", $newSuccess);
            $bookRoot->setTag("Glacia", $bookTag);
            $book->setNamedTag($bookRoot);

            $book = GlaciaUtils::updateEnchantmentBook($book);

            $dust = clone $itemClickedWith;
            $dust->setCount($dust->getCount() - 1);

            if($dust->getCount() <= 0){
                $otherAction->getInventory()->setItem($otherAction->getSlot(), VanillaItems::AIR());
            }else{
                $otherAction->getInventory()->setItem($otherAction->getSlot(), $dust);
            }

            PlayerUtils::playSound($player, "random.levelup");
            $action->getInventory()->setItem($action->getSlot(), $book);
            Loader::getQuestBoardManager()->recordProgress($player, "enchant_dust_applied", 1);
            if ($bookSuccess < 100 && $newSuccess >= 100) {
                Loader::getQuestBoardManager()->recordProgress($player, "books_dusted_to_100", 1);
            }
            return;
        }
    }

    private function canApplyDustToBook(string $dustGroup, string $bookGroup): bool {
        return $this->getGroupWeight($dustGroup) >= $this->getGroupWeight($bookGroup);
    }

    /**
     * Keep transmog [count] suffix stable after normal inventory moves.
     * @priority MONITOR
     */
    public function onTransmogSlotSync(InventoryTransactionEvent $event): void
    {
        return;
        if ($event->isCancelled()) {
            return;
        }

        $player = $event->getTransaction()->getSource();
        if (!$player instanceof Player) {
            return;
        }

        $shouldSync = false;
        foreach ($event->getTransaction()->getActions() as $action) {
            if (!$action instanceof SlotChangeAction) {
                continue;
            }
            if ($this->isInvMenuSlotAction($action) || $action->getInventory() instanceof PlayerCursorInventory) {
                continue;
            }

            foreach ([$action->getSourceItem(), $action->getTargetItem()] as $item) {
                if ($item->isNull()) {
                    continue;
                }

                $glacia = $item->getNamedTag()->getCompoundTag("Glacia");
                if ($glacia?->getByte("transmog", 0) === 1) {
                    $shouldSync = true;
                    break 2;
                }
            }
        }

        if (!$shouldSync) {
            return;
        }

        Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(
            function() use ($player): void {
                if (!$player->isConnected()) {
                    return;
                }

                foreach ([
                    $player->getInventory(),
                    $player->getArmorInventory(),
                    $player->getOffHandInventory(),
                    $player->getEnderInventory(),
                ] as $inventory) {
                    $this->syncTransmogNamesInInventory($inventory);
                }
            }
        ), 1);
        return;
        foreach ($event->getTransaction()->getActions() as $action) {
            if (!$action instanceof SlotChangeAction) {
                continue;
            }
            if ($this->isInvMenuSlotAction($action)) {
                continue;
            }

            $target = $action->getTargetItem();
            if ($target->isNull()) {
                continue;
            }

            $glacia = $target->getNamedTag()->getCompoundTag("Glacia");
            if ($glacia?->getByte("transmog", 0) !== 1) {
                continue;
            }

            $item = clone $target;
            $enchCount = CustomEnchantmentManager::countGlaciaCesEntries($item);
            $baseName = $item->getCustomName() !== "" ? $item->getCustomName() : $item->getName();
            $baseName = preg_replace('/\s*(?:(?:§|&).)?\[\d+\]\s*$/u', '', $baseName) ?? $baseName;
            $newName = $baseName . C::colorize(" &r&l&b[&f{$enchCount}&b]");
            if ($item->getCustomName() === $newName) {
                continue;
            }
            $item->setCustomName($newName);
            $action->getInventory()->setItem($action->getSlot(), $item);
        }
    }

    private function syncTransmogNamesInInventory(Inventory $inventory): void
    {
        for ($slot = 0; $slot < $inventory->getSize(); $slot++) {
            $item = $inventory->getItem($slot);
            if ($item->isNull()) {
                continue;
            }

            $updated = $this->syncTransmogDisplayName($item);
            if (!$updated->equalsExact($item)) {
                $inventory->setItem($slot, $updated);
            }
        }
    }

    private function syncTransmogDisplayName(Item $item): Item
    {
        $glacia = $item->getNamedTag()->getCompoundTag("Glacia");
        if ($glacia?->getByte("transmog", 0) !== 1) {
            return $item;
        }

        $updated = clone $item;
        $enchCount = CustomEnchantmentManager::countGlaciaCesEntries($updated);
        $baseName = $updated->getCustomName() !== "" ? $updated->getCustomName() : $updated->getName();
        $baseName = CustomEnchantments::stripTransmogEnchantCountSuffixFromName($baseName);
        $newName = $baseName . C::colorize(" &r&l&b[&f{$enchCount}&b]");
        if ($updated->getCustomName() === $newName) {
            return $item;
        }

        $updated->setCustomName($newName);
        return $updated;
    }

    /**
     * @priority HIGHEST
     */
    public function onBlockPetAsHelmet(InventoryTransactionEvent $event): void
    {
        if ($event->isCancelled()) {
            return;
        }
        $source = $event->getTransaction()->getSource();
        if (!$source instanceof Player) {
            return;
        }

        foreach ($event->getTransaction()->getActions() as $action) {
            if (!$action instanceof SlotChangeAction) {
                continue;
            }
            $inventory = $action->getInventory();
            if (!$inventory instanceof ArmorInventory || $inventory->getHolder() !== $source) {
                continue;
            }
            if ($action->getSlot() !== ArmorInventory::SLOT_HEAD) {
                continue;
            }
            if (GlaciaItemStackPolicy::isPetItem($action->getTargetItem())) {
                $event->cancel();
                $source->sendTip(C::colorize("&r&cYou cannot wear pet items on your head."));
                PlayerUtils::playSound($source, "note.bass");
                return;
            }
        }
    }

    /**
     * Fetter: block re-armoring into a recently disarmed slot.
     * @priority HIGHEST
     */
    public function onFetterLockedArmor(InventoryTransactionEvent $event): void
    {
        if ($event->isCancelled()) {
            return;
        }
        $source = $event->getTransaction()->getSource();
        if (!$source instanceof Player) {
            return;
        }
        foreach ($event->getTransaction()->getActions() as $action) {
            if (!$action instanceof SlotChangeAction) {
                continue;
            }
            $inv = $action->getInventory();
            if (!$inv instanceof ArmorInventory || $inv->getHolder() !== $source) {
                continue;
            }
            $slot = $action->getSlot();
            if (!AdvancedEnchantState::isFetterSlotLocked($source, $slot)) {
                continue;
            }
            $newItem = $action->getTargetItem();
            if ($newItem->isNull() || $newItem->getCount() === 0) {
                continue;
            }
            $event->cancel();
            $source->sendMessage(C::colorize("&r&7That armor slot is &cFetter-locked&7!"));
            PlayerUtils::playSound($source, "note.bass");
            return;
        }
    }

    /**
     * Soul Crusher: block inventory movement while the lock is active.
     * @priority HIGHEST
     */
    public function onLockedInventory(InventoryTransactionEvent $event): void
    {
        if ($event->isCancelled()) {
            return;
        }

        $source = $event->getTransaction()->getSource();
        if (!$source instanceof Player) {
            return;
        }

        if (!AdvancedEnchantState::isInventoryLocked($source)) {
            return;
        }

        $event->cancel();
        $source->sendTip(C::colorize("&r&cYour inventory is sealed by Soul Crusher."));
        PlayerUtils::playSound($source, "note.bass");
    }

    /**
     * UIs (InvMenu, etc.) use their own inventory holders, not the player, so this only affects main/ender/cursor.
     * @priority MONITOR
     */
    public function onUnstackLowKeyGlaciaInPlayerStorage(InventoryTransactionEvent $event): void
    {
        if ($event->isCancelled()) {
            return;
        }
        $source = $event->getTransaction()->getSource();
        if (!$source instanceof Player) {
            return;
        }
        $shouldNormalize = false;
        foreach ($event->getTransaction()->getActions() as $action) {
            if (!$action instanceof SlotChangeAction) {
                continue;
            }

            if (
                ($action->getSourceItem()->getCount() > 1 && GlaciaItemStackPolicy::shouldUnstackInPlayerStorage($action->getSourceItem())) ||
                ($action->getTargetItem()->getCount() > 1 && GlaciaItemStackPolicy::shouldUnstackInPlayerStorage($action->getTargetItem()))
            ) {
                $shouldNormalize = true;
                break;
            }
        }
        if (!$shouldNormalize) {
            return;
        }

        Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(
            function() use ($source): void {
                if ($source->isConnected()) {
                    GlaciaItemStackPolicy::normalizePlayerStorages($source);
                }
            }
        ), 2);
    }

    /**
     * @priority MONITOR
     */
    public function onJoinUnstackLowKeyGlacia(PlayerJoinEvent $event): void
    {
        $player = $event->getPlayer();
        Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(
            function() use ($player): void {
                if ($player->isConnected()) {
                    GlaciaItemStackPolicy::normalizePlayerStorages($player);
                    $this->syncTransmogNamesInInventory($player->getInventory());
                    $this->syncTransmogNamesInInventory($player->getArmorInventory());
                    $this->syncTransmogNamesInInventory($player->getOffHandInventory());
                    $this->syncTransmogNamesInInventory($player->getEnderInventory());
                }
            }
        ), 1);
    }

    /**
     * @priority MONITOR
     */
    public function onPickupUnstackLowKeyGlacia(EntityItemPickupEvent $event): void
    {
        if ($event->isCancelled()) {
            return;
        }
        $collector = $event->getEntity();
        if (!$collector instanceof Player) {
            return;
        }
        Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(
            function() use ($collector): void {
                if ($collector->isConnected()) {
                    GlaciaItemStackPolicy::normalizePlayerStorages($collector);
                }
            }
        ), 1);
    }

    /**
     * @priority HIGHEST
     */
    public function onDragTrackers(InventoryTransactionEvent $event): void
    {
        $actions = $this->getNormalizedDragActions($event->getTransaction());
        if ($actions === null) {
            return;
        }
        if ($this->isPlainCursorRearrangementOfSameItem($actions[0], $actions[1])) {
            return;
        }

        foreach ($actions as $i => $action) {
            if (!$action instanceof SlotChangeAction) {
                continue;
            }

            $otherAction = $actions[($i + 1) % 2];
            if (!$otherAction instanceof SlotChangeAction) {
                continue;
            }

            $itemClickedWith = $action->getTargetItem();
            $itemClicked = $action->getSourceItem();
            $player = $event->getTransaction()->getSource();

            if (!$player instanceof Player || $itemClickedWith->getCount() !== 1 || $itemClicked->isNull()) {
                continue;
            }

            $glaciaTag = $itemClickedWith->getNamedTag()->getCompoundTag("Glacia");
            if ($glaciaTag === null || $glaciaTag->getTag("glaciaItem") === null) {
                continue;
            }

            $itemTag = $glaciaTag->getString("glaciaItem");
            if (SoulGemSessionManager::isSoulConduitGemId($itemTag) || SoulGemSessionManager::isLegacyStableConduitItem($itemClickedWith)) {
                $event->cancel();

                if (!SoulGemSessionManager::canBindToItem($itemClicked)) {
                    $player->sendMessage(C::colorize("&r&cThat item cannot be bound to a conduit."));
                    PlayerUtils::playSound($player, "note.bass");
                    return;
                }

                if (SoulGemSessionManager::hasBoundSoulGem($itemClicked)) {
                    $player->sendMessage(C::colorize("&r&cThat item already has a conduit merged into it."));
                    PlayerUtils::playSound($player, "note.bass");
                    return;
                }

                $bound = SoulGemSessionManager::bindGemToItem($itemClicked, $itemClickedWith);
                $mergedItem = $this->syncTransmogDisplayName($bound["item"]);
                $action->getInventory()->setItem($action->getSlot(), $mergedItem);
                $otherAction->getInventory()->setItem($otherAction->getSlot(), $bound["gem"]);
                $player->sendMessage(C::colorize("&r&aMerged that conduit onto your item."));
                PlayerUtils::playSound($player, "random.levelup");
                return;
            }

            if (SoulGemSessionManager::isSoulFuelGemId($itemTag) && !SoulGemSessionManager::isLegacyStableConduitItem($itemClickedWith)) {
                $event->cancel();

                if (!SoulGemSessionManager::hasBoundSoulGem($itemClicked)) {
                    $player->sendMessage(C::colorize("&r&cThat item needs a merged conduit before you can charge it with gems."));
                    PlayerUtils::playSound($player, "note.bass");
                    return;
                }

                $bound = SoulGemSessionManager::applyFuelGemToItem($itemClicked, $itemClickedWith);
                if ($bound === null) {
                    $player->sendMessage(C::colorize("&r&cThat gem does not match this item's conduit type."));
                    PlayerUtils::playSound($player, "note.bass");
                    return;
                }

                $mergedItem = $this->syncTransmogDisplayName($bound["item"]);
                $action->getInventory()->setItem($action->getSlot(), $mergedItem);
                $otherAction->getInventory()->setItem($otherAction->getSlot(), $bound["gem"]);
                $player->sendMessage(C::colorize("&r&aCharged your conduit with that gem."));
                PlayerUtils::playSound($player, "random.levelup");
                return;
            }

            $trackerType = match ($itemTag) {
                "block_tracker" => TrackerManager::BLOCKS,
                "kill_tracker" => TrackerManager::PLAYER_KILLS,
                "mob_tracker" => TrackerManager::MOB_KILLS,
                "xp_tracker" => TrackerManager::XP,
                "soul_tracker" => TrackerManager::SOULS,
                default => null,
            };

            if ($trackerType === null) {
                continue;
            }

            $event->cancel();

            if (!TrackerManager::canApplyTo($itemClicked, $trackerType)) {
                $player->sendMessage(C::colorize("&r&cThat tracker cannot be applied to this item."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            if (TrackerManager::hasTracker($itemClicked, $trackerType)) {
                $player->sendMessage(C::colorize("&r&cThis item already has that tracker applied."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $trackedItem = TrackerManager::applyTracker($itemClicked, $trackerType);
            $action->getInventory()->setItem($action->getSlot(), $trackedItem);
            $otherAction->getInventory()->setItem($otherAction->getSlot(), VanillaItems::AIR());

            $player->sendMessage(C::colorize("&r&aApplied a &f" . TrackerManager::getLabel($trackerType) . " &ato your item."));
            PlayerUtils::playSound($player, "random.levelup");
            return;
        }
    }

    private function isInvMenuSlotAction(SlotChangeAction $action): bool
    {
        return str_contains(strtolower($action->getInventory()::class), "invmenu");
    }

    /**
     * @return array{0: SlotChangeAction, 1: SlotChangeAction}|null
     */
    private function getNormalizedDragActions(object $transaction): ?array
    {
        if (!method_exists($transaction, "getActions")) {
            return null;
        }

        $slotActions = array_values(array_filter(
            $transaction->getActions(),
            static fn(mixed $action): bool => $action instanceof SlotChangeAction
        ));
        if (count($slotActions) < 2) {
            return null;
        }

        $candidates = [];
        foreach ($slotActions as $action) {
            if ($this->isInvMenuSlotAction($action)) {
                continue;
            }
            $candidates[] = $action;
        }
        if (count($candidates) < 2) {
            return null;
        }

        for ($i = 0, $max = count($candidates); $i < $max; $i++) {
            for ($j = $i + 1; $j < $max; $j++) {
                $first = $candidates[$i];
                $second = $candidates[$j];
                $firstCursor = $first->getInventory() instanceof PlayerCursorInventory;
                $secondCursor = $second->getInventory() instanceof PlayerCursorInventory;
                if ($firstCursor xor $secondCursor) {
                    return [$first, $second];
                }
            }
        }

        return [$candidates[0], $candidates[1]];
    }

    private function isPlainCursorRearrangementOfSameItem(SlotChangeAction $first, SlotChangeAction $second): bool
    {
        $firstIsCursor = $first->getInventory() instanceof PlayerCursorInventory;
        $secondIsCursor = $second->getInventory() instanceof PlayerCursorInventory;
        if (!($firstIsCursor xor $secondIsCursor)) {
            return false;
        }

        $items = [];
        foreach ([
            $first->getSourceItem(),
            $first->getTargetItem(),
            $second->getSourceItem(),
            $second->getTargetItem(),
        ] as $item) {
            if ($item->isNull()) {
                continue;
            }
            $items[] = $item;
        }

        if (count($items) < 2) {
            return false;
        }

        $prototype = clone $items[0];
        $prototype->setCount(1);
        foreach ($items as $item) {
            $candidate = clone $item;
            $candidate->setCount(1);
            if (!$prototype->equals($candidate, true, true)) {
                return false;
            }
        }

        return true;
    }

    private function isProtectedCeggRegion(Position $position): bool
    {
        foreach (Loader::getRegionManager()->getRegionsAt($position) as $region) {
            $name = strtolower($region->getName());
            if ($name === "spawn" || $name === "warzone" || str_starts_with($name, "warzone_")) {
                return true;
            }
        }

        return false;
    }

    private function getGroupWeight(string $group): int {
        return match(strtolower($group)){
            "simple" => 1,
            "unique" => 2,
            "elite" => 3,
            "exotic" => 4,
            "legendary" => 5,
            "mastery", "godly" => 6,
            default => 0
        };
    }

    /**
     * Enchant-book apply handler treats {@see SlotChangeAction::getTargetItem()} as the book when it is an enchanted book.
     * When the client sends a swap (book on cursor, scroll in slot), that looks like applying the book to the scroll — defer to scroll handlers.
     *
     * @see onDragScrolls
     */
    private function transactionMixesEnchantmentBookWithDragScroll(Item $targetItem, Item $sourceItem, SlotChangeAction $otherAction): bool
    {
        $hasBook = false;
        $hasDragScroll = false;
        foreach ([$targetItem, $sourceItem, $otherAction->getSourceItem(), $otherAction->getTargetItem()] as $it) {
            $id = $it->getNamedTag()->getCompoundTag("Glacia")?->getString("glaciaItem", "") ?? "";
            if ($id === "enchantment_book") {
                $hasBook = true;
            }
            if ($this->isGlaciaDragScrollConsumableForScrollHandler($id)) {
                $hasDragScroll = true;
            }
        }

        return $hasBook && $hasDragScroll;
    }

    /**
     * Glacia scroll/orb items handled by {@see onDragScrolls} (not charm shard/dust — those use {@see onDragEnchant}).
     */
    private function isGlaciaDragScrollConsumableForScrollHandler(string $glaciaItemId): bool
    {
        if ($glaciaItemId === "") {
            return false;
        }

        return in_array($glaciaItemId, [
            "whitescroll",
            "holy_whitescroll",
            "holy_water",
            "blackscroll",
            "holy_blackscroll",
            "transmog",
            "heroic_upgrade",
        ], true)
            || str_contains($glaciaItemId, "randomization_scroll")
            || str_contains($glaciaItemId, "orb");
    }

    private function findRandomizationScrollGroupInMerge(SlotChangeAction $a, SlotChangeAction $b): ?string
    {
        foreach ([$a->getSourceItem(), $a->getTargetItem(), $b->getSourceItem(), $b->getTargetItem()] as $it) {
            $id = strtolower($it->getNamedTag()->getCompoundTag("Glacia")?->getString("glaciaItem", "") ?? "");
            $group = $this->getRandomizationScrollGroup($id);
            if ($group !== null) {
                return $group;
            }
        }

        return null;
    }

    private function findEnchantmentBookInMerge(SlotChangeAction $a, SlotChangeAction $b): ?Item
    {
        foreach ([$a->getSourceItem(), $a->getTargetItem(), $b->getSourceItem(), $b->getTargetItem()] as $it) {
            $id = $it->getNamedTag()->getCompoundTag("Glacia")?->getString("glaciaItem", "") ?? "";
            if ($id === "enchantment_book") {
                return $it;
            }
        }

        return null;
    }

    /**
     * Result book is written to the non-cursor inventory action; scroll is consumed from the cursor action when present.
     *
     * @return array{0: SlotChangeAction, 1: SlotChangeAction}
     */
    private function resolveBookAndScrollSlotActionsForRandomization(SlotChangeAction $a, SlotChangeAction $b): array
    {
        $cursorAction = null;
        $slotAction = null;
        foreach ([$a, $b] as $act) {
            if ($act->getInventory() instanceof PlayerCursorInventory) {
                $cursorAction = $act;
            } else {
                $slotAction = $act;
            }
        }

        if ($cursorAction instanceof SlotChangeAction && $slotAction instanceof SlotChangeAction) {
            return [$slotAction, $cursorAction];
        }

        return [$a, $b];
    }

    private function getRandomizationScrollGroup(string $itemTag): ?string
    {
        return match (strtolower($itemTag)) {
            "simple_randomization_scroll" => "simple",
            "unique_randomization_scroll" => "unique",
            "elite_randomization_scroll" => "elite",
            "exotic_randomization_scroll" => "exotic",
            "legendary_randomization_scroll" => "legendary",
            "godly_randomization_scroll" => "godly",
            default => null,
        };
    }

    private function handleRandomizationScroll(Player $player, SlotChangeAction $bookAction, SlotChangeAction $scrollAction, Item $bookItem, string $scrollGroup): void
    {
        $bookTag = $bookItem->getNamedTag()->getCompoundTag("Glacia");
        if ($bookTag === null) {
            $player->sendMessage(C::colorize("&cThat enchantment book is invalid."));
            PlayerUtils::playSound($player, "note.bass");
            return;
        }

        $level = max(1, NbtLenient::readInt($bookTag, "level", 1, 1, 255, $player));
        $bookType = strtolower($bookTag->getString("book_type", "custom"));

        $newSuccess = mt_rand(1, 100);
        $newDestroy = mt_rand(1, 100);

        $rebuilt = null;
        $displayGroup = ucfirst($scrollGroup);

        if ($bookType === "vanilla" || $bookTag->getString("vanilla_enchant", "") !== "") {
            if ($scrollGroup !== "godly") {
                $player->sendMessage(C::colorize("&cOnly a Godly Randomization Scroll can reroll stats on vanilla enchantment books."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $vanillaId = strtolower(trim($bookTag->getString("vanilla_enchant", $bookTag->getString("enchant_book", ""))));
            if ($vanillaId === "") {
                $player->sendMessage(C::colorize("&cThat enchantment book is invalid."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $vanilla = GlaciaItemFactory::resolveVanillaEnchantment($vanillaId);
            if ($vanilla === null) {
                $player->sendMessage(C::colorize("&cThat vanilla enchantment book is invalid."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $rebuilt = GlaciaItemFactory::vanillaEnchantmentBook(
                $vanillaId,
                min($level, $vanilla->getMaxLevel()),
                $newSuccess,
                $newDestroy
            );
            $displayGroup = "Godly";
        } else {
            $currentEnchant = CustomEnchantments::getEnchantmentByName($bookTag->getString("enchant_book", ""));
            if ($currentEnchant === null) {
                $player->sendMessage(C::colorize("&cThat enchantment book is invalid."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $bookGroup = strtolower(Groups::getGroupNameById($currentEnchant->getRarity()));
            if ($scrollGroup !== "godly" && $scrollGroup !== $bookGroup) {
                $player->sendMessage(C::colorize("&cThat Randomization Scroll only works on &f" . ucfirst($scrollGroup) . " &cbooks."));
                PlayerUtils::playSound($player, "note.bass");
                return;
            }

            $rebuilt = GlaciaItemFactory::enchantmentBook(
                $currentEnchant,
                min($level, $currentEnchant->getMaxLevel()),
                $newSuccess,
                $newDestroy
            );
            $displayGroup = $scrollGroup === "godly" ? "Godly" : ucfirst($bookGroup);
        }

        if ($rebuilt === null) {
            $player->sendMessage(C::colorize("&cThat Randomization Scroll could not update this book."));
            PlayerUtils::playSound($player, "note.bass");
            return;
        }

        $rebuilt = GlaciaUtils::updateEnchantmentBook($rebuilt);

        $scrollAction->getInventory()->setItem($scrollAction->getSlot(), VanillaItems::AIR());
        $bookAction->getInventory()->setItem($bookAction->getSlot(), $rebuilt);
        $player->sendMessage(C::colorize(
            "&a{$displayGroup} Randomization Scroll rerolled &fsuccess &7and &cdestroy &7rates &7— enchant unchanged."
        ));
        PlayerUtils::playSound($player, "random.levelup");
    }

    private function showConjurationOracleForm(Player $player, Inventory $itemInventory, int $itemSlot, Inventory $oracleInventory, int $oracleSlot, Item $item): void
    {
        $eligible = $this->getConjurationOracleEligibleEnchants($item);
        $form = new SimpleForm(function (Player $player, ?int $data) use ($itemInventory, $itemSlot, $oracleInventory, $oracleSlot, $eligible): void {
            if ($data === null) {
                return;
            }

            if ($eligible === []) {
                return;
            }

            $choice = $eligible[$data] ?? null;
            if ($choice === null) {
                return;
            }

            $oracleItem = $oracleInventory->getItem($oracleSlot);
            $oracleId = $oracleItem->getNamedTag()->getCompoundTag("Glacia")?->getString("glaciaItem", "") ?? "";
            if (!in_array($oracleId, ["conjuration_oracle", "conjuration_oracule"], true)) {
                $player->sendMessage(C::colorize("&r&cThe Conjuration Oracle is no longer present."));
                return;
            }

            $currentItem = $itemInventory->getItem($itemSlot);
            $currentEligible = $this->getConjurationOracleEligibleEnchants($currentItem);
            $selectedId = $choice["id"];
            $selected = null;
            foreach ($currentEligible as $entry) {
                if ($entry["id"] === $selectedId) {
                    $selected = $entry;
                    break;
                }
            }

            if ($selected === null) {
                $player->sendMessage(C::colorize("&r&cThat enchantment can no longer be elevated on this item."));
                return;
            }

            $enchantment = CustomEnchantments::getEnchantmentByName($selectedId);
            if ($enchantment === null) {
                $player->sendMessage(C::colorize("&r&cThat enchantment is no longer available."));
                return;
            }

            CustomEnchantmentManager::setEnchantmentLevel($currentItem, $enchantment, $selected["new_level"], false);
            $itemInventory->setItem($itemSlot, $currentItem);

            if ($oracleItem->getCount() > 1) {
                $oracleItem->setCount($oracleItem->getCount() - 1);
                $oracleInventory->setItem($oracleSlot, $oracleItem);
            } else {
                $oracleInventory->setItem($oracleSlot, VanillaItems::AIR());
            }

            $player->sendMessage(C::colorize("&r&5Conjuration Oracle &7elevated &f" . $selected["display"] . " &7to &f" . GeneralUtils::getRomanNumeral($selected["new_level"]) . "&7."));
            PlayerUtils::playSound($player, "random.levelup");
        });

        $form->setTitle(C::colorize("&r&5&lConjuration Oracle"));
        if ($eligible === []) {
            $form->setContent(C::colorize("&r&7There are no enchants available for ascension on this item."));
            $form->addButton(C::colorize("&r&8Close"));
            $player->sendForm($form);
            return;
        }

        $form->setContent(C::colorize("&r&7Choose one eligible enchant to push &fone level past its normal max&7."));
        foreach ($eligible as $entry) {
            $form->addButton(C::colorize($entry["display"] . " &r&7" . GeneralUtils::getRomanNumeral($entry["current_level"]) . " &8-> &f" . GeneralUtils::getRomanNumeral($entry["new_level"])));
        }
        $player->sendForm($form);
    }

    /**
     * @return array<int, array{id:string,display:string,current_level:int,new_level:int}>
     */
    private function getConjurationOracleEligibleEnchants(Item $item): array
    {
        $eligible = [];
        $allowedEnchants = $this->getConjurationOracleAllowedEnchants();
        foreach (CustomEnchantmentManager::getEnchantments($item) as $id => $level) {
            $normalized = strtolower($id);
            if (!isset($allowedEnchants[$normalized])) {
                continue;
            }

            $enchantment = CustomEnchantments::getEnchantmentByName($normalized);
            if ($enchantment === null) {
                continue;
            }

            $maxLevel = $enchantment->getMaxLevel();
            if ($level !== $maxLevel) {
                continue;
            }

            $color = C::colorize(Groups::translateGroupToColor($enchantment->getRarity()));
            $eligible[] = [
                "id" => $normalized,
                "display" => CustomEnchantments::getEnchantmentDisplayName($enchantment->getName(), $color),
                "current_level" => $level,
                "new_level" => $maxLevel + 1,
            ];
        }

        usort($eligible, static fn(array $left, array $right): int => strcmp(C::clean($left["display"]), C::clean($right["display"])));
        return $eligible;
    }

    /**
     * @return array<string, true>
     */
    private function getConjurationOracleAllowedEnchants(): array
    {
        $settings = EnchantTuningManager::getSettingsArray("conjuration-oracle");
        $configured = $settings["allowed-enchants"] ?? self::CONJURATION_ORACLE_ALLOWED_ENCHANTS;
        if (!is_array($configured)) {
            $configured = self::CONJURATION_ORACLE_ALLOWED_ENCHANTS;
        }

        $allowed = [];
        foreach ($configured as $entry) {
            $normalized = strtolower(trim((string) $entry));
            if ($normalized === "") {
                continue;
            }

            $allowed[$normalized] = true;
        }

        if ($allowed === []) {
            foreach (self::CONJURATION_ORACLE_ALLOWED_ENCHANTS as $entry) {
                $allowed[$entry] = true;
            }
        }

        return $allowed;
    }

    /**
     * @priority HIGHEST
     */
    public function onCombineNotes(InventoryTransactionEvent $event): void {
        $actions = $this->getNormalizedDragActions($event->getTransaction());
        if ($actions === null) {
            return;
        }

        foreach ($actions as $i => $action) {
            if (!$action instanceof SlotChangeAction) {
                continue;
            }

            $otherAction = $actions[($i + 1) % 2];
            if (!$otherAction instanceof SlotChangeAction) {
                continue;
            }

            $targetItem = $action->getTargetItem();
            $sourceItem = $action->getSourceItem();

            if ($targetItem->isNull() || $sourceItem->isNull()) {
                continue;
            }

            $targetGlacia = $targetItem->getNamedTag()->getCompoundTag("Glacia");
            $sourceGlacia = $sourceItem->getNamedTag()->getCompoundTag("Glacia");
            if ($targetGlacia === null || $sourceGlacia === null) {
                continue;
            }

            $targetType = $targetGlacia->getString("glaciaItem", "");
            $sourceType = $sourceGlacia->getString("glaciaItem", "");
            if ($targetType === "" || $targetType !== $sourceType || !in_array($targetType, ["econote", "xpnote"], true)) {
                continue;
            }

            $event->cancel();

            $sourceWorth = $this->readGlaciaInt($sourceGlacia, "worth", 0);
            $targetWorth = $this->readGlaciaInt($targetGlacia, "worth", 0);
            $sourceCount = max(1, $sourceItem->getCount());
            $targetCount = max(1, $targetItem->getCount());
            $mergedWorth = ($sourceWorth * $sourceCount) + ($targetWorth * $targetCount);
            $signer = $targetGlacia->getString("signer", $this->extractSigner($targetItem));

            if ($sourceWorth <= 0 || $targetWorth <= 0 || $mergedWorth <= 0) {
                $event->getTransaction()->getSource()->sendMessage(C::colorize("&r&cOne of those notes is invalid or corrupted."));
                PlayerUtils::playSound($event->getTransaction()->getSource(), "note.bass");
                return;
            }

            $merged = $targetType === "econote"
                ? GlaciaItemFactory::bankNoteSigned($signer, $mergedWorth)
                : GlaciaItemFactory::xpNoteSigned($signer, $mergedWorth);

            $action->getInventory()->setItem($action->getSlot(), $merged);
            $otherAction->getInventory()->setItem($otherAction->getSlot(), VanillaItems::AIR());
            $event->getTransaction()->getSource()->sendMessage(C::colorize("&r&aMerged your notes into one."));
            PlayerUtils::playSound($event->getTransaction()->getSource(), "random.anvil_use");
            return;
        }
    }

    /**
     * @priority HIGHEST
     */
    public function onCombineStoredThrowables(InventoryTransactionEvent $event): void
    {
        $transaction = $event->getTransaction();
        $actions = $this->getNormalizedDragActions($transaction);
        if ($actions === null) {
            return;
        }

        $matched = $this->matchStoredThrowableActions($actions[0], $actions[1]);
        if ($matched === null) {
            return;
        }

        [$keepAction, $consumeAction, $merged] = $matched;
        $event->cancel();

        $keepAction->getInventory()->setItem($keepAction->getSlot(), clone $merged["item"]);
        $consumeAction->getInventory()->setItem(
            $consumeAction->getSlot(),
            $merged["consumed_all"] ? VanillaItems::AIR() : ($merged["remainder"] ?? VanillaItems::AIR())
        );

        $player = $transaction->getSource();
        if ($player instanceof Player) {
            $player->sendMessage(C::colorize($merged["message"]));
            PlayerUtils::playSound($player, "random.anvil_use");
        }
    }

    /**
     * @return array{0: SlotChangeAction, 1: SlotChangeAction, 2: array{item: Item, message: string, consumed_all: bool, remainder: ?Item}}|null
     */
    private function matchStoredThrowableActions(SlotChangeAction $first, SlotChangeAction $second): ?array
    {
        $firstSource = $first->getSourceItem();
        $secondSource = $second->getSourceItem();
        $firstIsCursor = $first->getInventory() instanceof PlayerCursorInventory;
        $secondIsCursor = $second->getInventory() instanceof PlayerCursorInventory;

        if ($firstIsCursor !== $secondIsCursor) {
            $slotAction = $firstIsCursor ? $second : $first;
            $cursorAction = $firstIsCursor ? $first : $second;
            $slotSource = $slotAction->getSourceItem();
            $cursorSource = $cursorAction->getSourceItem();

            if (!$slotSource->isNull()) {
                $merged = $this->mergeStoredThrowablePair($slotSource, $cursorSource);
                if ($merged !== null) {
                    return [$slotAction, $cursorAction, $merged];
                }
            }

            if (!$cursorSource->isNull()) {
                $merged = $this->mergeStoredThrowablePair($cursorSource, $slotSource);
                if ($merged !== null) {
                    return [$slotAction, $cursorAction, $merged];
                }
            }

            return null;
        }

        if (!$firstSource->isNull()) {
            $merged = $this->mergeStoredThrowablePair($firstSource, $secondSource);
            if ($merged !== null) {
                return [$first, $second, $merged];
            }
        }

        if (!$secondSource->isNull()) {
            $merged = $this->mergeStoredThrowablePair($secondSource, $firstSource);
            if ($merged !== null) {
                return [$second, $first, $merged];
            }
        }

        return null;
    }

    private function extractSigner(\pocketmine\item\Item $item): string
    {
        $glacia = $item->getNamedTag()->getCompoundTag("Glacia");
        if ($glacia !== null) {
            $signer = $glacia->getString("signer", "");
            if ($signer !== "") {
                return $signer;
            }
        }

        foreach ($item->getLore() as $line) {
            $clean = C::clean($line);
            if (str_starts_with($clean, "Signer: ")) {
                return substr($clean, 8);
            }
        }

        return "Ethereal Hub";
    }

    private function readGlaciaInt(CompoundTag $tag, string $key, int $default = 0): int
    {
        $valueTag = $tag->getTag($key);
        if ($valueTag instanceof IntTag || $valueTag instanceof ByteTag) {
            return (int) $valueTag->getValue();
        }

        if ($valueTag instanceof \pocketmine\nbt\tag\StringTag) {
            $raw = trim($valueTag->getValue());
            if ($raw === "") {
                return $default;
            }

            if (is_numeric($raw)) {
                return (int) $raw;
            }

            $parsed = GeneralUtils::parseRandomNumber($raw);
            return is_numeric($parsed) ? (int) $parsed : $default;
        }

        return $default;
    }

    private function consumeEnchantmentBookWithTinkerRoll(Player $player, SlotChangeAction $bookAction): void
    {
        $bookAction->getInventory()->setItem($bookAction->getSlot(), VanillaItems::AIR());

        $settings = EnchantTuningManager::getSettingsArray("charm-upgrades");
        $chance = max(0, min(100, (int) ($settings["tinkerer-roll-chance"] ?? 0)));
        if ($chance <= 0 || mt_rand(1, 100) > $chance) {
            return;
        }

        $shardWeight = max(0, (int) ($settings["tinkerer-shard-weight"] ?? 40));
        $dustWeight = max(0, (int) ($settings["tinkerer-dust-weight"] ?? 60));
        $totalWeight = max(1, $shardWeight + $dustWeight);
        $roll = mt_rand(1, $totalWeight);

        if ($roll <= $shardWeight) {
            $min = max(1, (int) ($settings["shard-success-min"] ?? 50));
            $max = max($min, (int) ($settings["shard-success-max"] ?? 80));
            $reward = GlaciaItemFactory::charmShard(mt_rand($min, $max), 0);
            $name = "Charm Shard";
        } else {
            $min = max(1, (int) ($settings["dust-bonus-min"] ?? 3));
            $max = max($min, (int) ($settings["dust-bonus-max"] ?? 8));
            $reward = GlaciaItemFactory::charmDust(mt_rand($min, $max));
            $name = "Charm Dust";
        }

        foreach ($player->getInventory()->addItem($reward) as $leftover) {
            $player->getWorld()->dropItem($player->getPosition(), $leftover);
        }
        $player->sendMessage(C::colorize("&r&dTinkering residue gave you &f{$name}&d."));
    }

    private function handleRelicCooldown(Player $player, \ecstsy\Glacia\player\GlaciaPlayer $session, string $cooldownKey, int $seconds, string $display): bool
    {
        if ($this->shouldBypassPetCooldown($player, $cooldownKey)) {
            return false;
        }

        if (!$session->isOnCooldown($cooldownKey)) {
            return false;
        }

        $player->sendMessage(C::colorize("&r&c{$display} is on cooldown for &f" . GeneralUtils::translateTime((int) $session->getCooldown($cooldownKey)) . "&r&c."));
        PlayerUtils::playSound($player, "note.bass");
        return true;
    }

    private function shouldBypassPetCooldown(Player $player, string $cooldownKey): bool
    {
        return str_starts_with($cooldownKey, "pet.")
            && ($player->hasPermission("glacia.pet.cooldown.bypass") || Server::getInstance()->isOp($player->getName()));
    }

    private function getRelicCooldownSeconds(string $key, int $fallback): int
    {
        if (isset(self::$relicCooldownSecondsCache[$key])) {
            return self::$relicCooldownSecondsCache[$key];
        }

        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "misc.yml");
        $value = $config?->getNested("relic-cooldowns.{$key}", "{$fallback}s");
        $seconds = max(1, GeneralUtils::parseTimeString((string) $value));
        self::$relicCooldownSecondsCache[$key] = $seconds;
        return $seconds;
    }

    private function getSoulCompassDefaultUses(): int
    {
        if (self::$soulCompassDefaultUsesCache !== null) {
            return self::$soulCompassDefaultUsesCache;
        }

        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "envoy.yml");
        self::$soulCompassDefaultUsesCache = max(1, (int) ($config?->getNested("soul-compass.default-uses", 8) ?? 8));
        return self::$soulCompassDefaultUsesCache;
    }

    private function displayDropPackagePreview(Player $player, string $packageType, string $displayName): void
    {
        OpenableLootPreviewScreen::display(
            $player,
            $displayName,
            DropPackageRewardPool::getPreviewGroups($packageType)
        );
        PlayerUtils::playSound($player, "random.click");
    }

    private function formatCompassDirection(float $deltaX, float $deltaZ): string
    {
        $vertical = "";
        $horizontal = "";

        if ($deltaZ <= -3) {
            $vertical = "N";
        } elseif ($deltaZ >= 3) {
            $vertical = "S";
        }

        if ($deltaX >= 3) {
            $horizontal = "E";
        } elseif ($deltaX <= -3) {
            $horizontal = "W";
        }

        return $vertical . $horizontal ?: "HERE";
    }

    private function startSoulCompassTrail(Player $player, Vector3 $target, string $crateType, int $distance): void
    {
        $ticks = 0;
        Loader::getInstance()->getScheduler()->scheduleRepeatingTask(new ClosureTask(
            function() use ($player, $target, $crateType, $distance, &$ticks): void {
                if (!$player->isConnected() || $player->isClosed() || $player->getWorld() !== Loader::getInstance()->getServer()->getWorldManager()->getDefaultWorld()) {
                    throw new CancelTaskException();
                }

                $ticks++;

                $origin = $player->getEyePos();
                $delta = $target->subtractVector($origin);
                $length = sqrt(($delta->x * $delta->x) + ($delta->y * $delta->y) + ($delta->z * $delta->z));
                if ($length <= 0.001) {
                    throw new CancelTaskException();
                }

                $unitX = $delta->x / $length;
                $unitY = $delta->y / $length;
                $unitZ = $delta->z / $length;
                $segments = min(14, max(6, (int) floor($distance / 7)));

                for ($i = 1; $i <= $segments; $i++) {
                    $step = min($length, $i * 1.75);
                    $particlePos = new Vector3(
                        $origin->x + ($unitX * $step),
                        $origin->y + ($unitY * $step),
                        $origin->z + ($unitZ * $step)
                    );
                    $player->getWorld()->addParticle($particlePos, new BlockBreakParticle(VanillaBlocks::REDSTONE()));
                }

                $leadDistance = min($length, ($segments + 1) * 1.75);
                $leadPos = new Vector3(
                    $origin->x + ($unitX * $leadDistance),
                    $origin->y + ($unitY * $leadDistance),
                    $origin->z + ($unitZ * $leadDistance)
                );

                for ($ring = 0; $ring < 8; $ring++) {
                    $angle = deg2rad(($ring * 45) + ($ticks * 24));
                    $player->getWorld()->addParticle(
                        new Vector3(
                            $leadPos->x + (cos($angle) * 0.65),
                            $leadPos->y + 0.2,
                            $leadPos->z + (sin($angle) * 0.65)
                        ),
                        new BlockBreakParticle(VanillaBlocks::REDSTONE())
                    );
                }

                for ($column = 0; $column < 4; $column++) {
                    $markerPos = new Vector3($leadPos->x, $leadPos->y + ($column * 0.55), $leadPos->z);
                    $player->getWorld()->addParticle($markerPos, new BlockBreakParticle(VanillaBlocks::REDSTONE()));
                }

                if ($ticks % 4 === 0) {
                    $direction = $this->formatCompassDirection($target->x - $origin->x, $target->z - $origin->z);
                    $player->sendActionBarMessage(C::colorize(
                        "&r&bSoul Compass &8| &r&f" . ucfirst($crateType) . " signal &7pulling &b{$direction}"
                    ));
                    PlayerUtils::playSound($player, "random.click");
                }

                if ($ticks >= 40) {
                    throw new CancelTaskException();
                }
            }
        ), 1);
    }

    private function isDuelBlockedOpenable(string $itemTag): bool
    {
        if ($itemTag === "") {
            return false;
        }

        if (
            str_ends_with($itemTag, "_voucher")
            || str_ends_with($itemTag, "_booster")
            || str_ends_with($itemTag, "_note")
            || str_ends_with($itemTag, "_lootbag")
            || str_ends_with($itemTag, "_chest")
            || str_ends_with($itemTag, "_crate")
        ) {
            return true;
        }

        return in_array($itemTag, [
            "lootbox",
            "ethereal_crate",
            "slot_ticket",
            "heroic_slot_ticket",
            "passive_rune_pouch",
            "fragment",
            "disguise_unlock",
            "renametag",
            "bath_bomb_bundle",
        ], true) || RankProgressionManager::isRankVoucher($itemTag) || RankProgressionManager::isRankQuest($itemTag);
    }

    private function consumeHeldItem(Player $player, \pocketmine\item\Item $item): void
    {
        $item->pop();
        $player->getInventory()->setItemInHand($item);
    }

    private function giveOrDrop(Player $player, \pocketmine\item\Item $item): void
    {
        foreach ($player->getInventory()->addItem($item) as $leftover) {
            $player->getWorld()->dropItem($player->getPosition(), $leftover);
        }
    }

    private function consumePetCooldownIfNeeded(Player $player, \ecstsy\Glacia\player\GlaciaPlayer $session, string $cooldownKey, int $seconds): void
    {
        if ($this->shouldBypassPetCooldown($player, $cooldownKey)) {
            return;
        }
        if (str_starts_with($cooldownKey, "pet.") && AdvancedEnchantState::consumePrimalPactFreePet($player)) {
            return;
        }
        $session->addCooldown($cooldownKey, max(1, (int) ceil($seconds * RuneManager::getPetCooldownMultiplier($player))));
    }

    /**
     * @return Player[]
     */
    private function getNearbyEnemyPlayers(Player $player, float $radius): array
    {
        $targets = [];
        $origin = $player->getPosition();
        $maxDistanceSquared = $radius * $radius;
        foreach ($player->getWorld()->getPlayers() as $nearby) {
            if ($nearby->getId() === $player->getId()) {
                continue;
            }

            if ($nearby->getPosition()->distanceSquared($origin) > $maxDistanceSquared) {
                continue;
            }

            $targets[] = $nearby;
        }

        return $targets;
    }

    private function findNearestEnemyPlayer(Player $player, float $radius): ?Player
    {
        $origin = $player->getPosition();
        $maxDistanceSquared = $radius * $radius;
        $bestDistanceSquared = null;
        $bestPlayer = null;

        foreach ($player->getWorld()->getPlayers() as $nearby) {
            if ($nearby->getId() === $player->getId()) {
                continue;
            }

            $distanceSquared = $nearby->getPosition()->distanceSquared($origin);
            if ($distanceSquared > $maxDistanceSquared) {
                continue;
            }

            if ($bestDistanceSquared === null || $distanceSquared < $bestDistanceSquared) {
                $bestDistanceSquared = $distanceSquared;
                $bestPlayer = $nearby;
            }
        }

        return $bestPlayer;
    }

    /**
     * @return array<string, mixed>
     */
    private function getSpawnGuardConfig(): array
    {
        return WarlordRepository::getSpawnGuards();
    }

    private function canSpawnBoss(Player $player, string $bossId, ?string &$reason = null): bool
    {
        $this->cleanupSpawnTracking();
        $cfg = $this->getSpawnGuardConfig();
        $maxActive = max(1, (int) ($cfg["boss.max-active-global"] ?? 1));
        $activeBosses = $this->countLiveBosses();
        if ($activeBosses >= $maxActive) {
            $reason = "A boss is already active globally.";
            return false;
        }

        if ($this->canBypassBossCooldown($player)) {
            return true;
        }

        $now = time();
        if (self::$bossGlobalCooldownUntil > $now) {
            $reason = "Boss summoning is on global cooldown for " . GeneralUtils::translateTime(self::$bossGlobalCooldownUntil - $now) . ".";
            return false;
        }

        $uuid = $player->getUniqueId()->toString();
        $playerUntil = self::$bossPlayerCooldowns[$uuid] ?? 0;
        if ($playerUntil > $now) {
            $reason = "You can summon another boss in " . GeneralUtils::translateTime($playerUntil - $now) . ".";
            return false;
        }
        return true;
    }

    private function registerBossSpawn(BossEntity $boss, Player $player, string $bossId): void
    {
        $cfg = $this->getSpawnGuardConfig();
        $now = time();
        $playerCd = max(1, (int) ($cfg["boss.player-cooldown"] ?? 600));
        $globalCd = max(1, (int) ($cfg["boss.global-cooldown"] ?? 300));
        self::$activeBosses[$bossId] = [
            "entityId" => $boss->getId(),
            "world" => $boss->getWorld()->getFolderName(),
            "spawnedAt" => $now
        ];

        if ($this->canBypassBossCooldown($player)) {
            return;
        }

        self::$bossPlayerCooldowns[$player->getUniqueId()->toString()] = $now + $playerCd;
        self::$bossGlobalCooldownUntil = $now + $globalCd;
    }

    private function canBypassBossCooldown(Player $player): bool
    {
        return $player->hasPermission("glacia.boss.bypasscooldown") || $player->hasPermission("glacia.admin");
    }

    private function canSpawnWarlord(Player $player, ?string &$reason = null): bool
    {
        $this->cleanupSpawnTracking();
        $cfg = $this->getSpawnGuardConfig();
        $maxPerWorld = max(1, (int) ($cfg["warlord.max-active-per-world"] ?? 3));
        $worldName = $player->getWorld()->getFolderName();
        $activeInWorld = 0;
        foreach (self::$activeWarlords as $entry) {
            if (($entry["world"] ?? "") === $worldName) {
                $activeInWorld++;
            }
        }
        if ($activeInWorld >= $maxPerWorld) {
            $reason = "Too many warlords are active in this world right now.";
            return false;
        }

        $now = time();
        $uuid = $player->getUniqueId()->toString();
        $playerUntil = self::$warlordPlayerCooldowns[$uuid] ?? 0;
        if ($playerUntil > $now) {
            $reason = "You can summon another warlord in " . GeneralUtils::translateTime($playerUntil - $now) . ".";
            return false;
        }
        return true;
    }

    private function registerWarlordSpawn(ForsakenChampion $champion, Player $player): void
    {
        $cfg = $this->getSpawnGuardConfig();
        $now = time();
        $playerCd = max(1, (int) ($cfg["warlord.player-cooldown"] ?? 180));
        self::$activeWarlords[$champion->getId()] = [
            "entityId" => $champion->getId(),
            "world" => $champion->getWorld()->getFolderName(),
            "spawnedAt" => $now
        ];
        self::$warlordPlayerCooldowns[$player->getUniqueId()->toString()] = $now + $playerCd;
    }

    private function cleanupSpawnTracking(): void
    {
        $now = time();
        $cfg = $this->getSpawnGuardConfig();
        $maxLifetime = max(60, (int) ($cfg["warlord.max-lifetime"] ?? 900));

        foreach (self::$bossPlayerCooldowns as $uuid => $until) {
            if ($until <= $now) {
                unset(self::$bossPlayerCooldowns[$uuid]);
            }
        }
        foreach (self::$warlordPlayerCooldowns as $uuid => $until) {
            if ($until <= $now) {
                unset(self::$warlordPlayerCooldowns[$uuid]);
            }
        }

        foreach (self::$activeBosses as $key => $entry) {
            $world = Server::getInstance()->getWorldManager()->getWorldByName((string) ($entry["world"] ?? ""));
            if ($world === null || $world->getEntity((int) ($entry["entityId"] ?? -1)) === null) {
                unset(self::$activeBosses[$key]);
            }
        }

        foreach (self::$activeWarlords as $entityId => $entry) {
            $world = Server::getInstance()->getWorldManager()->getWorldByName((string) ($entry["world"] ?? ""));
            $entity = $world?->getEntity((int) ($entry["entityId"] ?? -1));
            if (!$entity instanceof ForsakenChampion) {
                unset(self::$activeWarlords[$entityId]);
                continue;
            }

            if (($entry["spawnedAt"] + $maxLifetime) <= $now) {
                $entity->close();
                unset(self::$activeWarlords[$entityId]);
            }
        }
    }

    private function countLiveBosses(): int
    {
        $count = 0;
        foreach (Server::getInstance()->getWorldManager()->getWorlds() as $world) {
            foreach ($world->getEntities() as $entity) {
                if (!$entity instanceof BossEntity || $entity->isClosed() || !$entity->isAlive()) {
                    continue;
                }

                $count++;
            }
        }

        return $count;
    }

    private function hasConfiguredEnchantConflict(Item $item, string $incomingEnchant): bool
    {
        $incoming = strtolower(trim($incomingEnchant));
        $existing = array_map(
            static fn(string $name): string => strtolower($name),
            array_keys(CustomEnchantmentManager::getEnchantments($item))
        );

        if (in_array($incoming, $existing, true)) {
            return false;
        }

        $incomingConflicts = EnchantTuningManager::getConflicts($incoming);
        foreach ($existing as $current) {
            if (in_array($current, $incomingConflicts, true)) {
                return true;
            }

            if (in_array($incoming, EnchantTuningManager::getConflicts($current), true)) {
                return true;
            }
        }

        return false;
    }

    private function isBookLikeItem(Item $item): bool
    {
        $typeId = $item->getTypeId();

        return $typeId === VanillaItems::BOOK()->getTypeId()
            || $typeId === VanillaItems::ENCHANTED_BOOK()->getTypeId();
    }

    private function refreshHolyWhiteLore(Item $item, int $used, int $max): void
    {
        $lore = [];
        foreach ($item->getLore() as $line) {
            $clean = C::clean($line);
            if (
                str_starts_with($clean, "Holy Lives:") ||
                $clean === "CORRUPTED" ||
                $clean === "SEMI-CORRUPT" ||
                $clean === "HOLY WHITE ACTIVE"
            ) {
                continue;
            }
            $lore[] = $line;
        }

        while ($lore !== [] && C::clean((string) end($lore)) === "") {
            array_pop($lore);
        }
        if ($lore !== []) {
            $lore[] = "";
        }
        $remaining = max(0, $max - $used);
        $lore[] = C::colorize("&r&bHoly Lives: &f{$remaining}/{$max}");
        $active = ($item->getNamedTag()->getCompoundTag("Glacia")?->getByte("holy_white_active", 0) ?? 0) === 1;
        if ($used >= $max) {
            $lore[] = C::colorize("&r&c&lCORRUPTED");
        } elseif ($used > 0) {
            $lore[] = C::colorize("&r&6&lSEMI-CORRUPT");
        }
        if ($active) {
            $lore[] = C::colorize("&r&f&lHOLY WHITE ACTIVE");
        }

        $item->setLore($lore);
    }

    private function setPetTamerState(Item $item, bool $active): Item
    {
        $root = $item->getNamedTag();
        $glacia = $root->getCompoundTag("Glacia") ?? new CompoundTag();
        $glacia->setByte("pet_tamer", $active ? 1 : 0);
        $root->setTag("Glacia", $glacia);
        $item->setNamedTag($root);

        $lore = array_values(array_filter(
            $item->getLore(),
            static fn(string $line): bool => !str_contains(C::clean($line), "Pet Tamer:")
        ));
        if ($active) {
            $lore[] = C::colorize("&r&6Pet Tamer: &aActive");
        }
        $item->setLore($lore);
        return $item;
    }

    private function isRegionRestrictedUtility(string $itemTag): bool
    {
        return in_array($itemTag, [
            "frostwake_trident",
            "stormflare_torch",
            "glacial_blessing",
            "tempest_gateway",
            "puritywisp",
            "galefox",
            "shatterfang",
            "hearthbloom",
            "ironcladwyrm",
            "aegisram",
            "riftviper",
            "sunscorchjackal",
            "runesageowl",
            "stormcorebehemoth",
            "veilmoth",
            "blastforgehound"
        ], true);
    }

    private function getProtectedBuildRegionAt(Vector3 $position): ?Region
    {
        $region = Loader::getRegionManager()->getRegionAt($position);
        return $region !== null && !$region->permissions()->build ? $region : null;
    }

    private function getProtectedInteractRegionAt(Vector3 $position): ?Region
    {
        $region = Loader::getRegionManager()->getRegionAt($position);
        return $region !== null && !$region->permissions()->interact ? $region : null;
    }

    private function getGenBucketBlockedRegionAt(Vector3 $position): ?Region
    {
        $region = Loader::getRegionManager()->getRegionAt($position);
        if ($region === null) {
            return null;
        }

        if (!$region->permissions()->build) {
            return $region;
        }

        return in_array(strtolower($region->getName()), ["spawn", "warzone"], true) ? $region : null;
    }

    private function isInWarzoneRegion(Vector3 $position): bool
    {
        foreach (Loader::getRegionManager()->getRegionsAt($position) as $region) {
            $name = strtolower($region->getName());
            if ($name === "warzone" || str_starts_with($name, "warzone_")) {
                return true;
            }
        }

        return false;
    }

    private function sendRegionDenied(Player $player): void
    {
        $player->sendMessage(C::colorize("&r&cYou can't do that here."));
        PlayerUtils::playSound($player, "note.bass");
    }

    private function stopGenBucketChain(string $chainKey, bool $notifyPlayer): void
    {
        $chain = self::$activeGenBucketChains[$chainKey] ?? null;
        if ($chain === null) {
            return;
        }

        unset(self::$activeGenBucketChains[$chainKey]);
        $pid = $chain["player"] ?? "";
        if ($pid !== "") {
            if (isset(self::$activeGenBucketChainKeysByPlayer[$pid][$chainKey])) {
                unset(self::$activeGenBucketChainKeysByPlayer[$pid][$chainKey]);
            }
            if ((self::$activeGenBucketChainKeysByPlayer[$pid] ?? []) === []) {
                unset(self::$activeGenBucketChainKeysByPlayer[$pid]);
            }
        }

        foreach (array_keys($chain["positions"] ?? []) as $blockKey) {
            if ((self::$generatedGenBucketBlockIndex[$blockKey] ?? null) === $chainKey) {
                unset(self::$generatedGenBucketBlockIndex[$blockKey]);
            }
        }

        $handler = $chain["handler"] ?? null;
        if ($handler instanceof TaskHandler && !$handler->isCancelled()) {
            $handler->cancel();
        }

        if (!$notifyPlayer) {
            return;
        }

        $player = null;
        try {
            $player = Server::getInstance()->getPlayerByUUID(Uuid::fromString((string) $chain["player"]));
        } catch (\Throwable) {
            // Ignore invalid UUIDs from stale chain entries.
        }
        if ($player !== null && $player->isConnected()) {
            $player->sendMessage(C::colorize("&r&eGen bucket chain stopped."));
            PlayerUtils::playSound($player, "random.pop");
        }
    }

    private static function toWorldBlockKey(World $world, int $x, int $y, int $z): string
    {
        return strtolower($world->getFolderName()) . ":" . $x . ":" . $y . ":" . $z;
    }

    private function clearChunkWater(World $world, int $chunkX, int $chunkZ): int
    {
        $cleared = 0;
        $minX = $chunkX << 4;
        $minZ = $chunkZ << 4;
        $maxY = $world->getMaxY();
        for ($x = $minX; $x < $minX + 16; $x++) {
            for ($z = $minZ; $z < $minZ + 16; $z++) {
                for ($y = 0; $y < $maxY; $y++) {
                    $pos = new Vector3($x, $y, $z);
                    $block = $world->getBlock($pos);
                    if (!$block instanceof Liquid) {
                        continue;
                    }

                    $world->setBlock($pos, VanillaBlocks::AIR());
                    $cleared++;
                }
            }
        }

        return $cleared;
    }

    private function handleZoneBarrierBucket(Player $player, Position $clickedBlock): void
    {
        if (!$player->hasPermission("glacia.admin")) {
            $player->sendMessage(C::colorize("&r&cOnly admins can use the Zone Barrier Bucket."));
            PlayerUtils::playSound($player, "note.bass");
            return;
        }

        $uuid = $player->getUniqueId()->toString();
        $nowMs = (int) floor(microtime(true) * 1000);
        if (($nowMs - (self::$zoneBarrierBucketLastUseMs[$uuid] ?? 0)) < self::ZONE_BARRIER_BUCKET_DEBOUNCE_MS) {
            return;
        }
        self::$zoneBarrierBucketLastUseMs[$uuid] = $nowMs;

        if ($player->isSneaking()) {
            $removed = $this->undoLastZoneBarrierPlacement($player);
            $player->sendMessage(C::colorize($removed > 0
                ? "&r&aUndid &f" . number_format($removed) . " &azone barrier blocks."
                : "&r&eNo recent zone barrier placement to undo."
            ));
            PlayerUtils::playSound($player, $removed > 0 ? "random.pop" : "note.bass");
            return;
        }

        $zone = ZoneManager::getZoneAt($clickedBlock);
        if ($zone === null) {
            $player->sendMessage(C::colorize("&r&cStart inside a configured zone."));
            PlayerUtils::playSound($player, "note.bass");
            return;
        }

        $placedPositions = [];
        $placed = 0;
        $markers = $this->collectConnectedZoneBarrierMarkers($clickedBlock, $zone);
        foreach ($markers as $marker) {
            if ($placed >= self::ZONE_BARRIER_BUCKET_MAX_BLOCKS) {
                break;
            }

            $start = new Position(
                $marker->getFloorX(),
                $marker->getFloorY() + 1,
                $marker->getFloorZ(),
                $marker->getWorld()
            );
            $placed += $this->placeZoneBarrierColumnUp($start, $zone, self::ZONE_BARRIER_BUCKET_MAX_BLOCKS - $placed, $placedPositions);
        }

        if ($placed <= 0) {
            $player->sendMessage(C::colorize("&r&eNo barrier blocks were placed. The path may already be blocked."));
            PlayerUtils::playSound($player, "note.bass");
            return;
        }

        self::$lastZoneBarrierPlacements[$player->getUniqueId()->toString()] = $placedPositions;
        $player->sendMessage(C::colorize("&r&aPlaced &f" . number_format($placed) . " &abarrier blocks across &f" . number_format(count($markers)) . " &amarker" . (count($markers) === 1 ? "" : "s") . " for &f" . C::clean((string) ($zone["display"] ?? $zone["id"] ?? "zone")) . "&a."));
        PlayerUtils::playSound($player, "random.orb");
    }

    /**
     * Finds marker blocks connected to the clicked marker on the same Y level.
     * Uses 8-way horizontal adjacency so diagonal outlines chain naturally.
     *
     * @return Position[]
     */
    private function collectConnectedZoneBarrierMarkers(Position $origin, array $zone): array
    {
        $world = $origin->getWorld();
        $y = $origin->getFloorY();
        $marker = $world->getBlockAt($origin->getFloorX(), $y, $origin->getFloorZ());
        if ($marker->canBeReplaced() || $marker instanceof Liquid) {
            return [$origin];
        }

        $queue = [[$origin->getFloorX(), $origin->getFloorZ()]];
        $seen = [];
        $positions = [];
        $offsets = [
            [-1, -1], [-1, 0], [-1, 1],
            [0, -1],           [0, 1],
            [1, -1],  [1, 0],  [1, 1],
        ];

        while ($queue !== [] && count($positions) < self::ZONE_BARRIER_BUCKET_MAX_CONNECTED_MARKERS) {
            [$x, $z] = array_shift($queue);
            $key = $x . ":" . $z;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $position = new Position($x, $y, $z, $world);
            if (!ZoneManager::isInsideZone($position, $zone)) {
                continue;
            }

            if (!$world->getBlockAt($x, $y, $z)->isSameState($marker)) {
                continue;
            }

            $positions[] = $position;
            foreach ($offsets as [$dx, $dz]) {
                $neighborKey = ($x + $dx) . ":" . ($z + $dz);
                if (!isset($seen[$neighborKey])) {
                    $queue[] = [$x + $dx, $z + $dz];
                }
            }
        }

        return $positions === [] ? [$origin] : $positions;
    }

    /**
     * @param list<string> $placedKeys
     */
    private function placeZoneBarrierColumnUp(Position $start, array $zone, int $limit = self::ZONE_BARRIER_BUCKET_MAX_BLOCKS, array &$placedKeys = []): int
    {
        $world = $start->getWorld();
        $placed = 0;
        $x = $start->getFloorX();
        $z = $start->getFloorZ();
        $barrier = VanillaBlocks::BARRIER();

        for ($y = $start->getFloorY(); $y < $world->getMaxY() && $placed < $limit; $y++) {
            $position = new Position($x, $y, $z, $world);
            if (!ZoneManager::isInsideZone($position, $zone)) {
                break;
            }

            $block = $world->getBlockAt($x, $y, $z);
            if (!$this->canReplaceWithZoneBarrier($block)) {
                break;
            }
            if ($block->isSameState($barrier)) {
                continue;
            }

            $world->setBlockAt($x, $y, $z, clone $barrier, true);
            $placedKeys[] = self::toWorldBlockKey($world, $x, $y, $z);
            $placed++;
        }

        return $placed;
    }

    private function undoLastZoneBarrierPlacement(Player $player): int
    {
        $uuid = $player->getUniqueId()->toString();
        $keys = self::$lastZoneBarrierPlacements[$uuid] ?? [];
        unset(self::$lastZoneBarrierPlacements[$uuid]);

        if ($keys === []) {
            return 0;
        }

        $removed = 0;
        $server = Server::getInstance();
        foreach ($keys as $key) {
            $parts = explode(":", $key);
            if (count($parts) !== 4) {
                continue;
            }

            [$worldName, $x, $y, $z] = $parts;
            $world = $server->getWorldManager()->getWorldByName($worldName);
            if (!$world instanceof World) {
                continue;
            }

            $block = $world->getBlockAt((int) $x, (int) $y, (int) $z);
            if (!$block->isSameState(VanillaBlocks::BARRIER())) {
                continue;
            }

            $world->setBlockAt((int) $x, (int) $y, (int) $z, VanillaBlocks::AIR(), true);
            $removed++;
        }

        return $removed;
    }

    private function canReplaceWithZoneBarrier(\pocketmine\block\Block $block): bool
    {
        return $block->isSameState(VanillaBlocks::BARRIER()) || $block->canBeReplaced() || $block instanceof Liquid;
    }

    private function sanitizeChampionArmor(Armor $item): Armor
    {
        $root = $item->getNamedTag();
        $enchTag = $root->getCompoundTag("GlaciaCES");
        if ($enchTag === null) {
            return $item;
        }

        foreach (["drunk", "gears", "bounce", "overload", "aquatic", "glowing", "lavabound"] as $enchant) {
            $enchTag->removeTag($enchant);
        }

        if ($enchTag->count() === 0) {
            $root->removeTag("GlaciaCES");
        } else {
            $root->setTag("GlaciaCES", $enchTag);
        }

        $item->setNamedTag($root);
        return $item;
    }

    private function offsetPosition(Position $position, int $face): Position
    {
        return match ($face) {
            Facing::NORTH => new Position($position->getFloorX(), $position->getFloorY(), $position->getFloorZ() - 1, $position->getWorld()),
            Facing::SOUTH => new Position($position->getFloorX(), $position->getFloorY(), $position->getFloorZ() + 1, $position->getWorld()),
            Facing::EAST => new Position($position->getFloorX() + 1, $position->getFloorY(), $position->getFloorZ(), $position->getWorld()),
            Facing::WEST => new Position($position->getFloorX() - 1, $position->getFloorY(), $position->getFloorZ(), $position->getWorld()),
            Facing::UP => new Position($position->getFloorX(), $position->getFloorY() + 1, $position->getFloorZ(), $position->getWorld()),
            Facing::DOWN => new Position($position->getFloorX(), $position->getFloorY() - 1, $position->getFloorZ(), $position->getWorld()),
        };
    }

    private function readFlexibleIntTag(CompoundTag $tag, string $key, int $default = 0): int
    {
        $raw = $tag->getTag($key);
        if ($raw instanceof IntTag || $raw instanceof ByteTag) {
            return (int) $raw->getValue();
        }
        if ($raw instanceof StringTag && is_numeric($raw->getValue())) {
            return (int) $raw->getValue();
        }

        return $default;
    }

    private function isGlaciaItemId(Item $item, string $id): bool
    {
        $glacia = $item->getNamedTag()->getCompoundTag("Glacia");
        return $glacia !== null && $glacia->getString("glaciaItem", "") === $id;
    }

    private function getStoredThrowableUses(Item $item, string $id, int $defaultUses): int
    {
        if (!$this->isGlaciaItemId($item, $id)) {
            return 0;
        }

        $glacia = $item->getNamedTag()->getCompoundTag("Glacia");
        if ($glacia === null) {
            return 0;
        }

        $usesPerItem = max(0, $this->readFlexibleIntTag($glacia, "uses", $defaultUses));
        return $usesPerItem * max(1, $item->getCount());
    }

    private function getCustomEnchantLimitFor(Player $player): int
    {
        return PermissionTierUtils::getHighestIntPermission($player, "glacia.enchant.limit.", 8, 100);
    }

    /**
     * @return array{item: Item, message: string, consumed_all: bool, remainder: ?Item}|null
     */
    private function mergeStoredThrowablePair(Item $base, Item $addition): ?array
    {
        if ($this->isGlaciaItemId($base, "wraith_pearl")) {
            $baseUses = $this->getStoredThrowableUses($base, "wraith_pearl", 40);

            $addedUses = 0;
            $consumedAll = true;
            $remainder = null;
            if ($this->isGlaciaItemId($addition, "wraith_pearl")) {
                $addedUses = $this->getStoredThrowableUses($addition, "wraith_pearl", 40);
            } elseif ($addition->getTypeId() === ItemTypeIds::ENDER_PEARL) {
                $addedUses = max(1, $addition->getCount());
            }

            if ($addedUses <= 0) {
                return null;
            }

            return [
                "item" => GlaciaItemFactory::wraithPearl($baseUses + $addedUses),
                "message" => "&r&aStored more charges in your Wraith Pearl.",
                "consumed_all" => $consumedAll,
                "remainder" => $remainder,
            ];
        }

        if ($this->isGlaciaItemId($base, "angel_potion")) {
            $baseUses = $this->getStoredThrowableUses($base, "angel_potion", 40);
            $consumedAll = true;
            $remainder = null;
            if ($this->isGlaciaItemId($addition, "angel_potion")) {
                $addedUses = $this->getStoredThrowableUses($addition, "angel_potion", 40);
            } else {
                $addedUses = $this->getStrongHealingSplashCharges($addition, $consumedAll, $remainder);
            }

            if ($addedUses <= 0) {
                return null;
            }

            return [
                "item" => GlaciaItemFactory::angelPotion($baseUses + $addedUses),
                "message" => "&r&aStored more heals in your Angel Potion.",
                "consumed_all" => $consumedAll,
                "remainder" => $remainder,
            ];
        }

        return null;
    }

    private function getStrongHealingSplashCharges(Item $item, bool &$consumedAll = true, ?Item &$remainder = null): int
    {
        if (!$item instanceof SplashPotionItem) {
            return 0;
        }

        if ($item->getType() !== PotionType::STRONG_HEALING()) {
            return 0;
        }

        return max(1, $item->getCount());
    }

    private function isPlayerInsideEndfall(Player $player): bool
    {
        $zone = ZoneManager::getZoneAt($player->getPosition());
        return strtolower((string) ($zone["id"] ?? "")) === "endfall";
    }

    private function isEndfallRestrictedEnchantGroup(string $group): bool
    {
        return in_array(strtolower(trim($group)), ["mastery", "exclusive"], true);
    }

    private function canApplyVanillaEnchantTo(Item $item, string $enchantName): bool
    {
        $compact = strtolower(str_replace(['_', '-', ' '], '', $enchantName));

        return match (true) {
            in_array($compact, ['mending', 'vanishing', 'curseofvanishing', 'unbreaking'], true) => $item instanceof Durable,
            in_array($compact, ['protection', 'fireprotection', 'blastprotection', 'projectileprotection', 'thorns', 'binding', 'curseofbinding'], true) => $item instanceof Armor,
            in_array($compact, ['respiration', 'aquaaffinity'], true) => $item instanceof Armor && $item->getArmorSlot() === ArmorInventory::SLOT_HEAD,
            in_array($compact, ['featherfalling', 'frostwalker', 'soulspeed'], true) => $item instanceof Armor && $item->getArmorSlot() === ArmorInventory::SLOT_FEET,
            $compact === 'swiftsneak' => $item instanceof Armor && $item->getArmorSlot() === ArmorInventory::SLOT_LEGS,
            in_array($compact, ['sharpness', 'smite', 'baneofarthropods', 'knockback', 'fireaspect', 'looting'], true) => $item instanceof Sword,
            in_array($compact, ['power', 'punch', 'flame', 'infinity'], true) => $item instanceof Bow,
            in_array($compact, ['channeling', 'impaling', 'loyalty', 'riptide'], true) => $item instanceof TridentItem,
            in_array($compact, ['efficiency', 'silktouch', 'fortune'], true) => $item instanceof Pickaxe || $item instanceof Axe || $item instanceof Shovel || $item instanceof Hoe,
            in_array($compact, ['luckofthesea', 'lure'], true) => $item instanceof FishingRod,
            default => $item instanceof Durable,
        };
    }

    /**
     * @return array<string, int>
     */
    private function getRemovableCustomEnchantments(Item $item): array
    {
        $validRarities = ["simple", "unique", "elite", "exotic", "legendary"];
        $allEnchants = CustomEnchantmentManager::getEnchantments($item);
        if ($allEnchants === []) {
            return [];
        }

        $removable = [];
        foreach ($validRarities as $rarity) {
            foreach (CustomEnchantments::getByGroup($rarity) as $customEnchant) {
                $enchantName = $customEnchant->getName();
                if (isset($allEnchants[$enchantName])) {
                    $removable[$enchantName] = $allEnchants[$enchantName];
                }
            }
        }

        return $removable;
    }

    /**
     * @param list<string> $lore
     * @param list<string> $enchantNames
     * @return list<string>
     */
    private function stripCustomEnchantLoreLines(array $lore, array $enchantNames): array
    {
        $normalized = array_map(
            static fn(string $name): string => strtolower(trim($name)),
            $enchantNames
        );

        $filtered = [];
        foreach ($lore as $line) {
            $cleanLine = strtolower(C::clean($line));
            $remove = false;
            foreach ($normalized as $name) {
                if ($name !== "" && str_contains($cleanLine, $name)) {
                    $remove = true;
                    break;
                }
            }

            if (!$remove) {
                $filtered[] = $line;
            }
        }

        while ($filtered !== [] && C::clean((string) end($filtered)) === "") {
            array_pop($filtered);
        }

        return array_values($filtered);
    }
}
