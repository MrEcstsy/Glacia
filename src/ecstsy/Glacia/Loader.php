<?php

declare(strict_types=1);

namespace ecstsy\Glacia;

use CortexPE\Commando\PacketHooker;
use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\auctionhouse\AuctionHouseManager;
use ecstsy\Glacia\server\BountyManager;
use ecstsy\Glacia\server\collectionchests\CollectionChestManager;
use ecstsy\Glacia\server\BlackMarketAuctionInstance;
use ecstsy\Glacia\server\CoinFlipInstance;
use ecstsy\Glacia\server\CombatManagerInstance;
use ecstsy\Glacia\server\envoys\EnvoyManager;
use ecstsy\Glacia\server\GlobalFundManager;
use ecstsy\Glacia\server\factions\FactionManager;
use ecstsy\Glacia\server\factions\FactionXpBoosterManager;
use ecstsy\Glacia\server\factions\relations\RelationManager;
use ecstsy\Glacia\server\factions\role\FactionRoleManager;
use ecstsy\Glacia\server\factions\upgrades\FactionUpgradeManager;
use ecstsy\Glacia\server\GraceInstance;
use ecstsy\Glacia\server\homes\HomeManager;
use ecstsy\Glacia\server\JackpotInstance;
use ecstsy\Glacia\server\ranks\RankManager;
use ecstsy\Glacia\server\regions\RegionManager;
use ecstsy\Glacia\server\vaults\PlayerVaultManager;
use ecstsy\Glacia\server\warps\WarpManager;
use ecstsy\Glacia\utils\ExtraVanillaBlocks;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\Glacia\utils\inventory\CustomSizedInvMenuType;
use ecstsy\Glacia\utils\QueryStmts;
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
use pocketmine\utils\SingletonTrait;
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
    private static BountyManager $bountyManager;
    private static GlobalFundManager $globalFundManager;
    private static EnvoyManager $envoyManager;
    private static BlockData $blockData;

    public function onLoad(): void {
        self::setInstance($this);
    }

    public function onEnable(): void {
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

        self::$regionManager = new RegionManager();
        self::$playerManager = new PlayerManager($this);
        self::$factionManager = new FactionManager($this);
        self::$factionXpBoosterManager = new FactionXpBoosterManager();
        self::$relationManager = new RelationManager();
        self::$factionRoleManager = new FactionRoleManager();
        self::$factionUpgradeManager = new FactionUpgradeManager();
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
        self::$bountyManager = new BountyManager();
        self::$globalFundManager = new GlobalFundManager();
        self::$envoyManager = new EnvoyManager();

        GlaciaUtils::initAll($this);

        if (!InvMenuHandler::isRegistered()) {
            InvMenuHandler::register($this);
        }

        if (!PacketHooker::isRegistered()) {
            PacketHooker::register($this);
        }

        GlaciaUtils::initDispenserMenu();

        //$this->getScheduler()->scheduleRepeatingTask(new PowerRegenTask(), 20 * 60);
        
        $packet = StaticPacketCache::getInstance()->getAvailableActorIdentifiers();
		$tag = $packet->identifiers->getRoot();
		assert($tag instanceof CompoundTag);
		$id_list = $tag->getListTag("idlist");
		assert($id_list !== null);
		$id_list->push(CompoundTag::create()
			->setString("bid", "")
			->setByte("hasspawnegg", 0)
			->setString("id", CustomSizedInvMenuType::ACTOR_NETWORK_ID)
			->setByte("summonable", 0)
		);

        self::$blockData = BlockData::create($this);
    }

    public function onDisable(): void {
        if (isset(self::$collectionChestManager)) {
            self::$collectionChestManager->save();
        }

        if (isset(self::$connector)) {
            self::$connector->waitAll();
            self::$connector->close();
        }
    } 

    public static function getBlockData(): BlockData {
        return self::$blockData;
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
}
