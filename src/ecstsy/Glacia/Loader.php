<?php

declare(strict_types=1);

namespace ecstsy\Glacia;

use CortexPE\Commando\PacketHooker;
use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\auctionhouse\AuctionHouseManager;
use ecstsy\Glacia\server\BountyManager;
use ecstsy\Glacia\server\collectionchests\CollectionChestManager;
use ecstsy\Glacia\server\capture\CaptureManager;
use ecstsy\Glacia\server\challenges\ChallengeManager;
use ecstsy\Glacia\server\BlackMarketAuctionInstance;
use ecstsy\Glacia\server\CoinFlipInstance;
use ecstsy\Glacia\server\CombatManagerInstance;
use ecstsy\Glacia\server\database\SchemaMigrationManager;
use ecstsy\Glacia\server\deathlog\DeathInventoryLogManager;
use ecstsy\Glacia\server\disguises\DisguiseManager;
use ecstsy\Glacia\server\envoys\EnvoyManager;
use ecstsy\Glacia\server\GlobalFundManager;
use ecstsy\Glacia\server\factions\FactionManager;
use ecstsy\Glacia\server\factions\claims\ClaimManager;
use ecstsy\Glacia\server\factions\FactionXpBoosterManager;
use ecstsy\Glacia\server\factions\relations\RelationManager;
use ecstsy\Glacia\server\factions\audit\FactionAuditFeed;
use ecstsy\Glacia\server\factions\contribution\FactionContributionManager;
use ecstsy\Glacia\server\factions\role\FactionRoleManager;
use ecstsy\Glacia\server\factions\upgrades\FactionUpgradeManager;
use ecstsy\Glacia\server\GraceInstance;
use ecstsy\Glacia\server\homes\HomeManager;
use ecstsy\Glacia\server\JackpotInstance;
use ecstsy\Glacia\server\ranks\RankManager;
use ecstsy\Glacia\server\regions\RegionManager;
use ecstsy\Glacia\server\tebex\TebexManager;
use ecstsy\Glacia\server\trade\TradeManager;
use ecstsy\Glacia\server\itemflip\ItemFlipManager;
use ecstsy\Glacia\server\vaults\PlayerVaultManager;
use ecstsy\Glacia\server\vote\VoteManager;
use ecstsy\Glacia\server\warps\WarpManager;
use ecstsy\Glacia\server\zones\ZoneMobManager;
use ecstsy\Glacia\server\world\WorldManagerService;
use ecstsy\Glacia\server\world\generator\EndGenerator;
use ecstsy\Glacia\tasks\PowerRegenTask;
use ecstsy\Glacia\utils\ExtraVanillaBlocks;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\Glacia\utils\inventory\CustomSizedInvMenuType;
use ecstsy\Glacia\utils\QueryStmts;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use muqsit\invmenu\InvMenuHandler;
use NhanAZ\BlockData\BlockData;
use pocketmine\block\Block;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\data\bedrock\block\BlockTypeNames;
use pocketmine\item\StringToItemParser;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\cache\StaticPacketCache;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\AsyncTask;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\generator\GeneratorManager;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use poggit\libasynql\DataConnector;
use poggit\libasynql\libasynql;

final class Loader extends PluginBase {
    use SingletonTrait;

    public const SERVER_PREFIX = "&r&l&bGlacia &fFactions &r&8►";
    public const TYPE_DYNAMIC_PREFIX = "muqsit:customsizedinvmenu_";

    private static DataConnector $connector;
    private static PlayerManager $playerManager;
    private static FactionManager $factionManager;
    private static FactionXpBoosterManager $factionXpBoosterManager;
    private static RankManager $rankManager;
    private static RelationManager $relationManager;
    private static FactionRoleManager $factionRoleManager;
    private static FactionUpgradeManager $factionUpgradeManager;
    private static RegionManager $regionManager;
    private static HomeManager $homeManager;
    private static WarpManager $warpManager;
    private static GraceInstance $graceInstance;
    private static BlackMarketAuctionInstance $blackMarketAuctionInstance;
    private static CombatManagerInstance $combatManager;
    private static JackpotInstance $jackpotInstance;
    private static CoinFlipInstance $coinFlipInstance;
    private static AuctionHouseManager $auctionHouseManager;
    private static PlayerVaultManager $playerVaultManager;
    private static CollectionChestManager $collectionChestManager;
    private static CaptureManager $captureManager;
    private static BountyManager $bountyManager;
    private static GlobalFundManager $globalFundManager;
    private static EnvoyManager $envoyManager;
    private static DisguiseManager $disguiseManager;
    private static TebexManager $tebexManager;
    private static VoteManager $voteManager;
    private static DeathInventoryLogManager $deathInventoryLogManager;
    private static ChallengeManager $challengeManager;
    private static WorldManagerService $worldManagerService;
    private static BlockData $blockData;

    public function onLoad(): void {
        self::setInstance($this);
    }

    public function onEnable(): void {
        $this->registerCustomGenerators();

        self::$connector = libasynql::create($this, ["type" => "sqlite", "sqlite" => ["file" => "sqlite.sql"], "worker-limit" => 2], ["sqlite" => "sqlite.sql"]);

        self::$connector->executeGeneric(QueryStmts::PLAYERS_INIT);
        self::$connector->executeGeneric(QueryStmts::FACTIONS_INIT);
        self::$connector->executeGeneric(QueryStmts::FACTION_HOME_INIT);
        self::$connector->executeGeneric(QueryStmts::FACTION_ROLES_INIT);
        self::$connector->executeGeneric(QueryStmts::FACTION_MEMBERS_INIT);
        self::$connector->executeGeneric(QueryStmts::FACTION_CLAIM_INIT);
        self::$connector->executeGeneric(QueryStmts::FACTION_RELATIONS_INIT);
        self::$connector->executeGeneric(QueryStmts::FACTION_UPGRADE_BANK_INIT);
        self::$connector->executeGeneric(QueryStmts::PLAYER_RANK_INIT);
        self::$connector->executeGeneric(QueryStmts::PLAYER_PERMISSIONS_INIT);
        self::$connector->executeGeneric(QueryStmts::SERVER_HOMES_INIT);
        self::$connector->executeGeneric(QueryStmts::SERVER_WARPS_INIT);
        self::$connector->executeGeneric(QueryStmts::SERVER_GLACIA_INIT);
        self::$connector->executeGeneric(QueryStmts::COINFLIP_INIT);
        self::$connector->executeGeneric(QueryStmts::JACKPOT_INIT);
        self::$connector->executeGeneric(QueryStmts::JACKPOT_STATS_INIT);
        self::$connector->executeGeneric(QueryStmts::BOUNTIES_INIT);
        self::$connector->executeGeneric(QueryStmts::COMBAT_LOGGER_INIT);
        self::$connector->executeGeneric(QueryStmts::AUCTION_HOUSE_LISTINGS_INIT);
        self::$connector->executeGeneric(QueryStmts::AUCTION_HOUSE_COLLECTION_INIT);
        self::$connector->executeGeneric(QueryStmts::PLAYER_VAULTS_INIT);
        self::$connector->waitAll();
        SchemaMigrationManager::migrate($this);

        self::$regionManager = new RegionManager();
        self::$playerManager = new PlayerManager($this);
        self::$factionManager = new FactionManager();
        self::$factionXpBoosterManager = new FactionXpBoosterManager();
        self::$relationManager = new RelationManager();
        self::$factionRoleManager = new FactionRoleManager();
        self::$factionUpgradeManager = new FactionUpgradeManager();
        ClaimManager::getInstance();
        self::$connector->waitAll();
        self::$rankManager = new RankManager();
        self::$homeManager = new HomeManager($this, 3);
        self::$warpManager = new WarpManager($this);
        self::$graceInstance = new GraceInstance();
        self::$blackMarketAuctionInstance = new BlackMarketAuctionInstance($this);
        self::$combatManager = new CombatManagerInstance();
        self::$jackpotInstance = new JackpotInstance();
        self::$coinFlipInstance = new CoinFlipInstance($this);
        self::$auctionHouseManager = new AuctionHouseManager();
        self::$playerVaultManager = new PlayerVaultManager();
        self::$collectionChestManager = new CollectionChestManager();
        self::$captureManager = new CaptureManager();
        self::$bountyManager = new BountyManager();
        self::$globalFundManager = new GlobalFundManager();
        self::$envoyManager = new EnvoyManager();
        self::$disguiseManager = new DisguiseManager($this);
        self::$tebexManager = new TebexManager();
        self::$voteManager = new VoteManager();
        self::$deathInventoryLogManager = new DeathInventoryLogManager();
        self::$challengeManager = new ChallengeManager();
        self::$worldManagerService = new WorldManagerService($this);
        self::$worldManagerService->loadFromConfig();
        FactionAuditFeed::init($this);
        FactionContributionManager::init($this);

        GlaciaUtils::initAll($this);

        if (!InvMenuHandler::isRegistered()) {
            InvMenuHandler::register($this);
        }

        if (!PacketHooker::isRegistered()) {
            PacketHooker::register($this);
        }

        GlaciaUtils::initDispenserMenu();

        $this->getScheduler()->scheduleRepeatingTask(new PowerRegenTask(), 20 * 60);
        
        $this->registerCustomMenuActorIdentifier();

        self::$blockData = BlockData::create($this);

        $worldStats = self::$worldManagerService->ensureConfiguredWorlds();

        ZoneMobManager::beginColdBootZoneMobPurging(20 * 120);
        ZoneMobManager::flagAllSpawnedZoneMobsForDespawn();

        self::$worldManagerService->sendStartupSummary($worldStats);
        $this->logStartupHealthSummary();
        $this->getScheduler()->scheduleDelayedTask(new ClosureTask(
            static function() : void {
                GlaciaUtils::refreshAllOnlineNametags();
            }
        ), 20);
    }

    private function registerCustomGenerators(): void {
        $generators = GeneratorManager::getInstance();
        if ($generators->getGenerator("end") === null) {
            $generators->addGenerator(EndGenerator::class, "end", fn(string $preset) => null, fast: true);
        }
        if ($generators->getGenerator("the_end") === null) {
            $generators->addAlias("end", "the_end");
        }
    }

    private function registerCustomMenuActorIdentifier() : void {
        $packet = StaticPacketCache::getInstance()->getAvailableActorIdentifiers();
        $tag = $packet->identifiers->getRoot();
        if (!$tag instanceof CompoundTag) {
            $this->getLogger()->warning("Skipping custom menu actor registration: identifier root tag was missing.");
            return;
        }

        $idList = $tag->getListTag("idlist");
        if ($idList === null) {
            $this->getLogger()->warning("Skipping custom menu actor registration: idlist tag was missing.");
            return;
        }

        foreach ($idList as $entry) {
            if ($entry instanceof CompoundTag && $entry->getString("id", "") === CustomSizedInvMenuType::ACTOR_NETWORK_ID) {
                return;
            }
        }

        $idList->push(CompoundTag::create()
            ->setString("bid", "")
            ->setByte("hasspawnegg", 0)
            ->setString("id", CustomSizedInvMenuType::ACTOR_NETWORK_ID)
            ->setByte("summonable", 0)
        );
    }

    private function logStartupHealthSummary() : void {
        $config = GeneralUtils::getConfiguration($this, "config.yml");
        $misc = GeneralUtils::getConfiguration($this, "misc.yml");
        $worlds = (array) ($config?->get("worlds", []) ?? []);
        $voting = (array) ($config?->get("voting", []) ?? []);
        $tebex = (array) ($config?->get("tebex", []) ?? []);

        $summary = [
            "worlds=" . ((bool) ($worlds["enabled"] ?? true) ? "on" : "off"),
            "vote=" . ((bool) ($voting["enabled"] ?? false) ? "on" : "off"),
            "tebex=" . ((bool) ($tebex["enabled"] ?? false) ? "on" : "off"),
            "wb=" . ((bool) ($misc?->get("world-border-enabled", true) ?? true) ? "on" : "off"),
            "persona-skins=" . ((bool) ($misc?->get("persona-friendly-skins", true) ?? true) ? "on" : "off"),
        ];

        $this->getLogger()->info("[Health] " . implode(" | ", $summary));
    }

    public function onDisable(): void {
        TradeManager::shutdown();
        ItemFlipManager::shutdown();

        if (isset(self::$collectionChestManager)) {
            self::$collectionChestManager->save();
        }

        FactionAuditFeed::save();
        FactionContributionManager::save();
        \ecstsy\Glacia\server\staff\ModerationManager::getInstance()->save();

        if (isset(self::$connector)) {
            self::$connector->waitAll();
            self::$connector->close();
        }

        if (isset(self::$tebexManager)) {
            self::$tebexManager->shutdown();
        }
    } 

    public static function getBlockData(): BlockData {
        return self::$blockData;
    }

    public static function getDisguiseManager(): DisguiseManager
    {
        return self::$disguiseManager;
    }

    public static function getDatabase(): DataConnector {
        return self::$connector;
    }

    public static function getPlayerManager(): PlayerManager {
        return self::$playerManager;
    }

    public static function getFactionManager(): FactionManager {
        return self::$factionManager;
    }

    public static function getFactionXpBoosterManager(): FactionXpBoosterManager {
        return self::$factionXpBoosterManager;
    }

    public static function getRelationManager(): RelationManager {
        return self::$relationManager;
    }

    public static function getFactionRoleManager(): FactionRoleManager {
        return self::$factionRoleManager;
    }

    public static function getFactionUpgradeManager(): FactionUpgradeManager
    {
        return self::$factionUpgradeManager;
    }

    public static function getRankManager(): RankManager {
        return self::$rankManager;
    }

    public static function getHomeManager(): HomeManager {
        return self::$homeManager;
    }

    public static function getWarpManager(): WarpManager {
        return self::$warpManager;
    }

    public static function getRegionManager(): RegionManager {
        return self::$regionManager;
    }

    public static function getGraceInstance(): GraceInstance {
        return self::$graceInstance;
    }

    public static function getCombatManager(): CombatManagerInstance {
        return self::$combatManager;
    }

    public static function getJackpotInstance(): JackpotInstance {
        return self::$jackpotInstance;
    }

    public static function getCoinFlipInstance(): CoinFlipInstance {
        return self::$coinFlipInstance;
    }

    public static function getBlackMarketAuctionInstance(): BlackMarketAuctionInstance {
        return self::$blackMarketAuctionInstance;
    }

    public static function getAuctionHouseManager(): AuctionHouseManager {
        return self::$auctionHouseManager;
    }

    public static function getPlayerVaultManager(): PlayerVaultManager {
        return self::$playerVaultManager;
    }

    public static function getCollectionChestManager(): CollectionChestManager
    {
        return self::$collectionChestManager;
    }

    public static function getCaptureManager(): CaptureManager
    {
        return self::$captureManager;
    }

    public static function getBountyManager(): BountyManager
    {
        return self::$bountyManager;
    }

    public static function getGlobalFundManager(): GlobalFundManager
    {
        return self::$globalFundManager;
    }

    public static function getEnvoyManager(): EnvoyManager
    {
        return self::$envoyManager;
    }

    public static function getTebexManager() : TebexManager
    {
        return self::$tebexManager;
    }

    public static function getVoteManager() : VoteManager
    {
        return self::$voteManager;
    }

    public static function getDeathInventoryLogManager() : DeathInventoryLogManager
    {
        return self::$deathInventoryLogManager;
    }

    public static function getChallengeManager() : ChallengeManager
    {
        return self::$challengeManager;
    }

    public static function getWorldManagerService(): WorldManagerService
    {
        return self::$worldManagerService;
    }
}
