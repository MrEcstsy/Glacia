<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils;

use CortexPE\Commando\PacketHooker;
use ecstsy\Glacia\blocks\tiles\SpawnerTile;
use ecstsy\Glacia\commands\AdminModeCommand;
use ecstsy\Glacia\commands\AuctionHouseCommand;
use ecstsy\Glacia\commands\BackCommand;
use ecstsy\Glacia\commands\SellCommand;
use ecstsy\Glacia\commands\BalanceCommand;
use ecstsy\Glacia\commands\BalTopCommand;
use ecstsy\Glacia\commands\BountyCommand;
use ecstsy\Glacia\commands\BanCommand;
use ecstsy\Glacia\commands\BlackMarketCommand;
use ecstsy\Glacia\commands\CreateHomeCommand;
use ecstsy\Glacia\commands\CreateWarpCommand;
use ecstsy\Glacia\commands\EcoCommand;
use ecstsy\Glacia\commands\EnchanterCommand;
use ecstsy\Glacia\commands\EnderChestCommand;
use ecstsy\Glacia\commands\EnderSeeCommand;
use ecstsy\Glacia\commands\EnchantmentsCommand;
use ecstsy\Glacia\commands\EnvoyCommand;
use ecstsy\Glacia\commands\ExpCommand;
use ecstsy\Glacia\commands\FactionCommand;
use ecstsy\Glacia\commands\FixAllCommand;
use ecstsy\Glacia\commands\FixCommand;
use ecstsy\Glacia\commands\FlySpeedCommand;
use ecstsy\Glacia\commands\GiveItemCommand;
use ecstsy\Glacia\commands\GraceCommand;
use ecstsy\Glacia\commands\HomeCommand;
use ecstsy\Glacia\commands\HomesCommand;
use ecstsy\Glacia\commands\InvSeeCommand;
use ecstsy\Glacia\commands\KickCommand;
use ecstsy\Glacia\commands\ListWarpsCommand;
use ecstsy\Glacia\commands\LootboxTestCommand;
use ecstsy\Glacia\commands\KothCommand;
use ecstsy\Glacia\commands\OutpostCommand;
use ecstsy\Glacia\commands\PayCommand;
use ecstsy\Glacia\commands\NearCommand;
use ecstsy\Glacia\commands\NetherCommand;
use ecstsy\Glacia\commands\PlayerVaultCommand;
use ecstsy\Glacia\commands\RankCommand;
use ecstsy\Glacia\commands\RemoveHomeCommand;
use ecstsy\Glacia\commands\RemoveWarpCommand;
use ecstsy\Glacia\commands\SellAllCommand;
use ecstsy\Glacia\commands\SetSellMultiplierCommand;
use ecstsy\Glacia\commands\SpawnCommand;
use ecstsy\Glacia\commands\TPAAcceptCommand;
use ecstsy\Glacia\commands\TPACommand;
use ecstsy\Glacia\commands\TPADenyCommand;
use ecstsy\Glacia\commands\TPAHereCommand;
use ecstsy\Glacia\commands\TrashCommand;
use ecstsy\Glacia\commands\ReapplyCommand;
use ecstsy\Glacia\commands\WithdrawCommand;
use ecstsy\Glacia\commands\WarpCommand;
use ecstsy\Glacia\commands\WildCommand;
use ecstsy\Glacia\commands\ZonesCommand;
use ecstsy\Glacia\commands\XpBottleCommand;
use ecstsy\Glacia\commands\BlessCommand;
use ecstsy\Glacia\commands\CoinFlipCommand;
use ecstsy\Glacia\commands\CollectCommand;
use ecstsy\Glacia\commands\BroadcastCommand;
use ecstsy\Glacia\commands\DupeCommand;
use ecstsy\Glacia\commands\ClearChatCommand;
use ecstsy\Glacia\commands\ClearInventoryCommand;
use ecstsy\Glacia\commands\FeedCommand;
use ecstsy\Glacia\commands\FundCommand;
use ecstsy\Glacia\commands\FlyCommand;
use ecstsy\Glacia\commands\GamemodeCommand;
use ecstsy\Glacia\commands\HatCommand;
use ecstsy\Glacia\commands\HealCommand;
use ecstsy\Glacia\commands\JackpotCommand;
use ecstsy\Glacia\commands\MoreCommand;
use ecstsy\Glacia\commands\PingCommand;
use ecstsy\Glacia\commands\SeeBragCommand;
use ecstsy\Glacia\commands\SeeItemCommand;
use ecstsy\Glacia\commands\SettingsCommand;
use ecstsy\Glacia\commands\ShopCommand;
use ecstsy\Glacia\commands\SlotbotCommand;
use ecstsy\Glacia\commands\GlaciaReloadCommand;
use ecstsy\Glacia\commands\KitCommand;
use ecstsy\Glacia\commands\LevelCommand;
use ecstsy\Glacia\entity\boss\bosses\AstralDevourer;
use ecstsy\Glacia\entity\CombatLoggerEntity;
use ecstsy\Glacia\entity\envoys\EnvoyBabyZombie;
use ecstsy\Glacia\entity\envoys\EnvoyBlaze;
use ecstsy\Glacia\entity\envoys\EnvoyGhast;
use ecstsy\Glacia\entity\envoys\EnvoySkeleton;
use ecstsy\Glacia\entity\envoys\EnvoySlime;
use ecstsy\Glacia\entity\envoys\EnvoyZombie;
use ecstsy\Glacia\entity\envoys\EnvoyZombiePigman;
use ecstsy\Glacia\entity\FloatingTextEntity;
use ecstsy\Glacia\entity\GlaciaExplosiveCreeper;
use ecstsy\Glacia\entity\mobs\GlaciaLightningStrike;
use ecstsy\Glacia\entity\GlaciaPrimedTNT;
use ecstsy\Glacia\entity\RiftEntity;
use ecstsy\Glacia\entity\RiftWalkerEntity;
use ecstsy\Glacia\listeners\CustomArmorListener;
use ecstsy\Glacia\listeners\CollectionChestListener;
use ecstsy\Glacia\listeners\CustomTNTListener;
use ecstsy\Glacia\listeners\EnchantmentListener;
use ecstsy\Glacia\listeners\EnvoyListener;
use ecstsy\Glacia\listeners\FactionListener;
use ecstsy\Glacia\listeners\GlaciaListener;
use ecstsy\Glacia\listeners\HealingPotionListener;
use ecstsy\Glacia\listeners\ItemListener;
use ecstsy\Glacia\listeners\LevelListener;
use ecstsy\Glacia\listeners\QuestListener;
use ecstsy\Glacia\listeners\RegionListener;
use ecstsy\Glacia\listeners\WorldBorderListener;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\combat\potions\HealingPotionSettings;
use ecstsy\Glacia\server\economy\SellHandler;
use ecstsy\Glacia\server\enchantments\CustomEnchantments;
use ecstsy\Glacia\server\enchantments\enchants\armor\AngelicEnchant;
use ecstsy\Glacia\server\enchantments\enchants\armor\ArmoredEnchant;
use ecstsy\Glacia\server\enchantments\enchants\armor\CurseEnchant;
use ecstsy\Glacia\server\enchantments\enchants\armor\DeathbringerEnchant;
use ecstsy\Glacia\server\enchantments\enchants\armor\DiminishEnchant;
use ecstsy\Glacia\server\enchantments\enchants\armor\DodgeEnchant;
use ecstsy\Glacia\server\enchantments\enchants\armor\EnlightedEnchant;
use ecstsy\Glacia\server\enchantments\enchants\armor\FrostbiteEnchant;
use ecstsy\Glacia\server\enchantments\enchants\armor\HardenedEnchant;
use ecstsy\Glacia\server\enchantments\enchants\armor\StickyEnchant;
use ecstsy\Glacia\server\enchantments\enchants\armor\TankEnchant;
use ecstsy\Glacia\server\enchantments\enchants\armor\TricksterEnchant;
use ecstsy\Glacia\server\enchantments\enchants\armor\ValorEnchant;
use ecstsy\Glacia\server\enchantments\enchants\axe\BerserkEnchant;
use ecstsy\Glacia\server\enchantments\enchants\helmet\WellFedEnchant;
use ecstsy\Glacia\server\enchantments\enchants\axe\BleedEnchant;
use ecstsy\Glacia\server\enchantments\enchants\axe\BossSlayerEnchant;
use ecstsy\Glacia\server\enchantments\enchants\axe\CleaveEnchant;
use ecstsy\Glacia\server\enchantments\enchants\axe\ConfusionEnchant;
use ecstsy\Glacia\server\enchantments\enchants\axe\FearEnchant;
use ecstsy\Glacia\server\enchantments\enchants\axe\HolyEnchant;
use ecstsy\Glacia\server\enchantments\enchants\axe\PummelEnchant;
use ecstsy\Glacia\server\enchantments\enchants\boots\EnderWalkerEnchant;
use ecstsy\Glacia\server\enchantments\enchants\boots\MetaphysicalEnchant;
use ecstsy\Glacia\server\enchantments\enchants\bow\VirusEnchant;
use ecstsy\Glacia\server\enchantments\enchants\chestplate\BlazedEnchant;
use ecstsy\Glacia\server\enchantments\enchants\chestplate\BloodLustEnchant;
use ecstsy\Glacia\server\enchantments\enchants\helmet\ClarityEnchant;
use ecstsy\Glacia\server\enchantments\enchants\helmet\EndershiftEnchant;
use ecstsy\Glacia\server\enchantments\enchants\helmet\HeavyEnchant;
use ecstsy\Glacia\server\enchantments\enchants\helmet\ImplantsEnchant;
use ecstsy\Glacia\server\enchantments\enchants\hoe\AutoPlanterEnchant;
use ecstsy\Glacia\server\enchantments\enchants\leggings\JellyLegsEnchant;
use ecstsy\Glacia\server\enchantments\enchants\pickaxe\AutoSmeltEnchant;
use ecstsy\Glacia\server\enchantments\enchants\pickaxe\LuckyEnchant;
use ecstsy\Glacia\server\enchantments\enchants\pickaxe\ObsidianDestroyerEnchant;
use ecstsy\Glacia\server\enchantments\enchants\sword\BlindEnchant;
use ecstsy\Glacia\server\enchantments\enchants\sword\DoubleStrikeEnchant;
use ecstsy\Glacia\server\enchantments\enchants\sword\ExecuteEnchant;
use ecstsy\Glacia\server\enchantments\enchants\sword\FeatherweightEnchant;
use ecstsy\Glacia\server\enchantments\enchants\sword\GreatswordEnchant;
use ecstsy\Glacia\server\enchantments\enchants\sword\InquisitiveEnchant;
use ecstsy\Glacia\server\enchantments\enchants\sword\InsomniaEnchant;
use ecstsy\Glacia\server\enchantments\enchants\sword\KillAuraEnchant;
use ecstsy\Glacia\server\enchantments\enchants\sword\LifestealEnchant;
use ecstsy\Glacia\server\enchantments\enchants\sword\RageEnchant;
use ecstsy\Glacia\server\enchantments\enchants\sword\SilenceEnchant;
use ecstsy\Glacia\server\enchantments\enchants\sword\SlownessEnchant;
use ecstsy\Glacia\server\enchantments\enchants\sword\VampireEnchant;
use ecstsy\Glacia\server\enchantments\manager\EnchantEventRegistry;
use ecstsy\Glacia\server\factions\claims\FactionClaim;
use ecstsy\Glacia\server\factions\relations\RelationManager;
use ecstsy\Glacia\server\FloatingTextInstance;
use ecstsy\Glacia\server\items\GlaciaItemFactory;
use ecstsy\Glacia\server\items\GlaciaItems;
use ecstsy\Glacia\server\items\GlaciaSpawnerItem;
use ecstsy\Glacia\server\items\genbuckets\GenBucketManager;
use ecstsy\Glacia\server\lootboxes\LootboxRepository;
use ecstsy\Glacia\server\levels\PlayerLevelManager;
use ecstsy\Glacia\server\quests\QuestInstance;
use ecstsy\Glacia\server\ranks\RankManager;
use ecstsy\Glacia\server\ranks\RankProgressionManager;
use ecstsy\Glacia\server\regions\Region;
use ecstsy\Glacia\server\regions\RegionPermissions;
use ecstsy\Glacia\server\scoreboard\ScoreboardHelper;
use ecstsy\Glacia\server\spawners\SpawnerWorthManager;
use ecstsy\Glacia\server\worldborder\WorldBorderManager;
use ecstsy\Glacia\server\zones\ZoneManager;
use ecstsy\Glacia\tasks\AutoPlanterTask;
use ecstsy\Glacia\tasks\BlackMarketAuctionTickTask;
use ecstsy\Glacia\tasks\CombatTickTask;
use ecstsy\Glacia\utils\lang\MessageTemplates;
use fenomeno\libWebhook\dto\WebhookJobResult;
use fenomeno\libWebhook\exceptions\WebhookPoolException;
use fenomeno\libWebhook\model\WebhookMessage;
use fenomeno\libWebhook\WebhookPool;
use muqsit\invmenu\InvMenuHandler;
use muqsit\invmenu\type\util\InvMenuTypeBuilders;
use pocketmine\block\Beetroot;
use pocketmine\block\Block;
use pocketmine\block\Carrot;
use pocketmine\block\NetherWartPlant;
use pocketmine\block\Potato;
use pocketmine\block\tile\TileFactory;
use pocketmine\block\Wheat;
use pocketmine\data\bedrock\EnchantmentIdMap;
use pocketmine\data\bedrock\item\SavedItemData;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\Entity;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\Location;
use pocketmine\inventory\Inventory;
use pocketmine\item\Durable;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\inventory\WindowTypes;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\Task;
use pocketmine\Server;
use pocketmine\utils\TextFormat as C;
use pocketmine\world\format\io\GlobalItemDataHandlers;
use pocketmine\world\particle\PortalParticle;
use pocketmine\world\sound\EndermanTeleportSound;
use pocketmine\world\World;
use SOFe\AwaitGenerator\Await;

final class GlaciaUtils
{

    public const FAKE_ENCH_ID = -1;
    private array $itemSerializers = [];
    public const TYPE_DISPENSER = 'glacia:dispenser';

    public static function initAll(Loader $loader): void
    {
        self::ensureRanks();
        MessageTemplates::load();
        self::initDiscordWebhooks($loader);
        GlaciaItems::init();

        $regions = [
            new Region("Spawn", new Vector3(385, 0, 114), new Vector3(114, 204, 385), new RegionPermissions(false, false, true, false, false, false, false)),
            new Region("Warzone", new Vector3(0, 0, 500), new Vector3(500, 204, 0), new RegionPermissions(true, false, true, false, false, false, false)),
        ];

        foreach ($regions as $region) {
            $loader->getRegionManager()->addRegion($region);
        }

        $unregisterCommands = ["ban", "kick", "xp", "gamemode"];

        foreach ($unregisterCommands as $command) {
            $loader->getServer()->getCommandMap()->unregister($loader->getServer()->getCommandMap()->getCommand($command));
        }

        $loader->getServer()->getCommandMap()->registerAll("Glacia", [
            new FactionCommand($loader, "faction", "Faction commands", ["f", "factions"]),
            new RankCommand($loader, "glaciaranks", "Rank commands", ["r", "ranks"]),
            new SpawnCommand($loader, "spawn", "Teleport to spawn"),
            new ExpCommand($loader, "exp", "Experience commands", ["xp"]),
            new BalanceCommand($loader, "balance", "View your balance or another player's balance", ["bal"]),
            new BalTopCommand($loader, "baltop", "View the richest players on the server"),
            new BountyCommand($loader, "bounty", "Place and view player bounties", ["bounties"]),
            new LevelCommand($loader, "level", "View or manage player levels", ["lvl"]),
            new FundCommand($loader, "fund", "Contribute to the active global fund"),
            new EnvoyCommand($loader, "envoy", "View the next envoy or manage it"),
            new OutpostCommand($loader, "outpost", "Manage outposts"),
            new KothCommand($loader, "koth", "Manage KOTH events"),
            new PayCommand($loader, "pay", "Pay another player", ["p"]),
            new WithdrawCommand($loader, "withdraw", "Withdraw money into a bank note", ["wd"]),
            new BackCommand($loader, "back", "Teleport to your previous location"),
            new NearCommand($loader, "near", "View nearby players"),
            new ZonesCommand($loader, "zones", "Open the zones menu", ["zone"]),
            new NetherCommand($loader, "nether", "Teleport to the nether zone shortcut"),
            new WildCommand($loader, "wild", "Teleport to a random wilderness location"),
            new EnderChestCommand($loader, "enderchest", "Open your ender chest", ["ec"]),
            new EnderSeeCommand($loader, "endersee", "View another player's ender chest", ["ecsee"]),
            new PlayerVaultCommand($loader, "pv", "Open a player vault", ["vault", "playervault"]),
            new TrashCommand($loader, "trash", "Open a trash menu", ["bin", "dispose"]),
            new InvSeeCommand($loader, "invsee", "View another player's inventory"),
            new EcoCommand($loader, "eco", "Economy commands", ["economy"]),
            new HomeCommand($loader, "home", "Home commands"),
            new HomesCommand($loader, "homes", "List your homes"),
            new CreateHomeCommand($loader, "createhome", "Create a new home", ["sethome"]),
            new CreateWarpCommand($loader, "createwarp", "Create a new warp", ["setwarp"]),
            new ListWarpsCommand($loader, "listwarps", "List all warps"),
            new RemoveWarpCommand($loader, "removewarp", "Remove a warp", ["delwarp"]),
            new RemoveHomeCommand($loader, "removehome", "Remove a home", ["delhome"]), 
            new WarpCommand($loader, "warp", "Warp to a location"),
            new TPACommand($loader, "tpa", "Teleport to another player"),
            new TPAAcceptCommand($loader, "tpaccept", "Accept a teleport request"),
            new TPAHereCommand($loader, "tpahere", "Request another player to teleport to you"),
            new TPADenyCommand($loader, "tpadeny", "Deny a teleport request"),
            new AdminModeCommand($loader, "adminmode", "Toggle admin mode"),
            new KickCommand($loader, "kick", "Kick a player from the server"),
            new BanCommand($loader, "ban", "Ban a player from the server"),
            new GraceCommand($loader, "grace", "Grace commands"),
            new GiveItemCommand($loader, "giveitem", "Give yourself a Glacia item", ["gi"]),
            new FlySpeedCommand($loader, "flyspeed", "Set your fly speed", ["fs"]),
            new SellCommand($loader, "sell", "Sell the item in your hand", ["sellhand"]),
            new SellAllCommand($loader, "sellall", "Sell all items in your inventory", ["sellinv"]),
            new SetSellMultiplierCommand($loader, "setsellmultiplier", "Set your sell multiplier", ["ssm"]),
            new EnchantmentsCommand($loader, "glaciaenchants", "Enchantment commands", ["ge"]),
            new XpBottleCommand($loader, "xpbottle", "Xp bottle commands", ["xpb"]),
            new FixAllCommand($loader, "fixall", "Repair all damaged items in your inventory", ["repairall"]),
            new FixCommand($loader, "fix", "Repair the item in your hand", ["repair"]),
            new ReapplyCommand($loader, "reapply", "Reapply equipped item effects"),
            new BlessCommand($loader, "bless", "Remove all negative effects"),
            new CoinFlipCommand($loader, "coinflip", "Flip a coin against another player", ["cf"]),
            new JackpotCommand($loader, "jackpot", "Jackpot commands", ["jp"]),
            new SeeBragCommand($loader, "seebrag", "View the brag screen of a player", ["cbrag"]),
            new SeeItemCommand($loader, "seeitem", "View the recent item of a player", ["citem"]),
            new SettingsCommand($loader, "settings", "Toggle various settings", ["glaciasettings"]),
            new HealCommand($loader, "heal", "Heal yourself or another player"),
            new DupeCommand($loader, "dupe", "Duplicate the item in your hand"),
            new FlyCommand($loader, "fly", "Toggle flight"),
            new GamemodeCommand($loader, "gamemode", "Change your gamemode", ["gm", "gms", "gmc", "gma", "gmsp"]),
            new FeedCommand($loader, "feed", "Feed yourself or another player"),
            new PingCommand($loader, "ping", "View your ping or another player's ping"),
            new HatCommand($loader, "hat", "Wear the item in your hand as a hat"),
            new MoreCommand($loader, "more", "Set your held stack size"),
            new BroadcastCommand($loader, "broadcast", "Broadcast a message", ["bc"]),
            new ClearChatCommand($loader, "clearchat", "Clear the chat", ["cc"]),
            new ClearInventoryCommand($loader, "clearinventory", "Clear a player's inventory", ["ci", "clearinv"]),
            new BlackMarketCommand($loader, "blackmarket", "Black market commands", ["bah"]),
            new AuctionHouseCommand($loader, "auctionhouse", "Auction house commands", ["ah"]),
            new CollectCommand($loader, "collect", "Collect items"),
            new ShopCommand($loader, "shop", "Open the shop"),
            new EnchanterCommand($loader, "enchanter", "Open the enchanter", ["ce"]),
            new SlotbotCommand($loader, "slotbot", "Open the slot bot", ["bot", "slot"]),
            new LootboxTestCommand($loader, "lootboxtest", "Test a configured lootbox animation", ["lbtest"]),
            new GlaciaReloadCommand($loader, "greload", "Reload Glacia configs"),
            new KitCommand($loader, "kit", "Open the kit screen"),
        ]);

        $healingPotionSettings = new HealingPotionSettings();

        $listeners = [ 
            new GlaciaListener(),
            new FactionListener(),
            new RegionListener($loader->getRegionManager()),
            new WorldBorderListener(),
            new ItemListener(),
            new LevelListener(),
            new QuestListener(),
            new EnchantmentListener(),
            new EnvoyListener(),
            new CollectionChestListener(),
            new CustomTNTListener(),
            new CustomArmorListener(),
            //new HealingPotionListener($healingPotionSettings),
        ];

        foreach ($listeners as $listener) {
            $loader->getServer()->getPluginManager()->registerEvents($listener, $loader);
        }

        $entitites = [
            CombatLoggerEntity::class,
            RiftEntity::class,
            RiftWalkerEntity::class,
            FloatingTextEntity::class,
            AstralDevourer::class,
            GlaciaPrimedTNT::class,
            GlaciaExplosiveCreeper::class,
            GlaciaLightningStrike::class,
            EnvoyBlaze::class,
            EnvoyBabyZombie::class,
            EnvoySkeleton::class,
            EnvoyZombiePigman::class,
            EnvoySlime::class,
            EnvoyZombie::class,
            EnvoyGhast::class,
        ];

        foreach ($entitites as $entity) {
            EntityFactory::getInstance()->register($entity, function(World $world, CompoundTag $nbt) use ($entity): Entity {
                return new $entity(EntityDataHelper::parseLocation($nbt, $world), $nbt);
            }, [$entity::getNetworkTypeId()]);
        }

        $loader->getScheduler()->scheduleRepeatingTask(new CombatTickTask(), 20);
        $loader->getScheduler()->scheduleRepeatingTask(new class extends Task {
            public function onRun() : void
            {
                Loader::getFactionXpBoosterManager()->tick();
                Loader::getAuctionHouseManager()->tick();
                Loader::getGlobalFundManager()->tick();
                Loader::getEnvoyManager()->tick();
                Loader::getCaptureManager()->tick();
                foreach (Server::getInstance()->getOnlinePlayers() as $player) {
                    RankProgressionManager::tickPlayer($player);
                    ScoreboardHelper::refreshScoreboard($player);
                }
            }
        }, 20);

        GlaciaSpawnerItem::getAll();
        GlaciaSpawnerItem::initHack();

        self::overrideOrRegisterSimpleItem("monster_spawner", GlaciaSpawnerItem::MONSTER_SPAWNER()->asItem(), ["monster_spawner", "glacia_spawner"]);
        
        TileFactory::getInstance()->register(SpawnerTile::class, ["MobSpawner", "minecraft:mob_spawner"]);

        $world = Server::getInstance()->getWorldManager()->getDefaultWorld();

        $floatingTexts = [
            'main_welcome' => [
                'position' => new Location(247.5, 80.5, 253.5, $world, 0, 0),
                'text' => [
                    '&r&b❄ &lGLACIA&r',
                    '&r&7Welcome to the frozen frontier.',
                    '&r&fForm your faction. Claim. Dominate the lands.',
                    ' ',
                    '&r&bUse &l/f create&r &7to begin your journey.',
                ],
            ],
            'sell_shop' => [
                'position' => new Location(199.5, 76.5, 300.5, $world, 0, 0),
                'text' => [
                    '&r&b🪙 &lSELL SHOP&r',
                    '&7Right-click the NPC to sell items!',
                    '&fEarn money to upgrade your faction.',
                ],
            ],
            'farming_area' => [
                'position' => new Location(302.5, 76.5, 198.5, $world, 0 ,0),
                'text' => [
                    '&r&b🌾 &lFARMING AREA&r',
                    '&7Harvest crops to earn XP!',
                    '&fUpgraded tools give better drops.',
                ],
            ],
            'warzone_north' => [
                'position' => new Location(250.5, 78.5, 140.5, $world, 0, 0),
                'text' => [
                    '&r&c⚔ &lWARZONE AHEAD&r',
                    '&7Once you pass this gate, PvP is enabled!',
                    '&fGlory awaits the bold — or death awaits the weak.',
                ],
            ],
            'warzone_west' => [
                'position' => new Location(140.5, 78.5, 250.5, $world, 0, 0),
                'text' => [
                    '&r&c⚔ &lWARZONE AHEAD&r',
                    '&7Once you pass this gate, PvP is enabled!',
                    '&fGlory awaits the bold — or death awaits the weak.',
                ],
            ],
            'warzone_south' => [
                'position' => new Location(250.5, 78.5, 360.5, $world, 0, 0),
                'text' => [
                    '&r&c⚔ &lWARZONE AHEAD&r',
                    '&7Once you pass this gate, PvP is enabled!',
                    '&fGlory awaits the bold — or death awaits the weak.',
                ],
            ],
            'warzone_east' => [
                'position' => new Location(360.5, 78.5, 250.5, $world, 0, 0),
                'text' => [
                    '&r&c⚔ &lWARZONE AHEAD&r',
                    '&7Once you pass this gate, PvP is enabled!',
                    '&fGlory awaits the bold — or death awaits the weak.',
                ],
            ],
            'black_market' => [
                'position' => new Location(210.5, 73.5, 264.5, $world, 0, 0),
                'text' => [
                    '&r&5🕶 &lBLACK MARKET&r',
                    '&7Shady deals. Limited-time items.',
                    '&fStock resets daily.',
                ],
            ],
        ];

        foreach ($floatingTexts as $key => $data) {
            $entity = new FloatingTextEntity($data['position']);
            $entity->setText(C::colorize(implode("\n", $data['text'])));
            $entity->spawnToAll();
            FloatingTextInstance::$particles[$key] = $entity; 
        }

        QuestInstance::registerDefaults();
        CustomEnchantments::getAll();

        $enchantments = [
            'angelic' => ['handler' => fn(...$args) => AngelicEnchant::handle(...$args), 'event' => 'entity_damage_by_entity'],
            'armored' => ['handler' => fn(...$args) => ArmoredEnchant::handle(...$args), 'event' => 'entity_damage_by_entity'],
            'autosmelt' => ['handler' => fn(...$args) => AutoSmeltEnchant::handle(...$args), 'event' => 'block_break'],
            'autoplanter' => ['handler' => fn(...$args) => AutoPlanterEnchant::handle(...$args), 'event' => 'block_break'],
            'bleed' => ['handler' => fn(...$args) => BleedEnchant::handle(...$args), 'event' => 'entity_damage_by_entity'],
            'blazed' => ['handler' => fn(...$args) => BlazedEnchant::handle(...$args), 'event' => 'entity_damage_by_entity'],
            'blind' => ['handler' => fn(...$args) => BlindEnchant::handle(...$args), 'event' => 'entity_damage_by_entity'],
            'berserk' => ['handler' => fn(...$args) => BerserkEnchant::handle(...$args), 'event' => 'entity_damage_by_entity'],
            'bossslayer' => ['handler' => fn(...$args) => BossSlayerEnchant::handle(...$args), 'event' => 'entity_damage_by_entity'],
            'cleave' => ['handler' => fn(...$args) => CleaveEnchant::handle(...$args), 'event' => 'entity_damage_by_entity'],
            'clarity' => ['handler' => fn(...$args) => ClarityEnchant::handle(...$args), 'event' => 'effect_add'],
            'confusion' => ['handler' => fn(...$args) => ConfusionEnchant::handle(...$args), 'event' => 'entity_damage_by_entity'],
            'curse' => ['handler' => fn(...$args) => CurseEnchant::handle(...$args), 'event' => 'entity_damage_by_entity'],
            'deathbringer' => ['handler' => fn(...$args) => DeathbringerEnchant::handle(...$args), 'event' => 'entity_damage_by_entity'],
            'diminish' => ['handler' => fn(...$args) => DiminishEnchant::handle(...$args), 'event' => 'entity_damage_by_entity'],
            'dodge' => ['handler' => fn(...$args) => DodgeEnchant::handle(...$args), 'event' => 'entity_damage_by_entity'],
            'doublestrike' => ['handler' => fn(...$args) => DoubleStrikeEnchant::handle(...$args), 'event' => 'entity_damage_by_entity'],
            'endershift' => ['handler' => fn(...$args) => EndershiftEnchant::handle(...$args), 'event' => 'entity_damage_by_entity'],
            'execute' => ['handler' => fn(...$args) => ExecuteEnchant::handle(...$args), 'event' => 'entity_damage_by_entity'],
            'enlighted' => ['handler' => fn(...$args) => EnlightedEnchant::handle(...$args), 'event' => 'entity_damage_by_entity'],
            'enderwalker' => ['handler' => fn(...$args) => EnderWalkerEnchant::handle(...$args), 'event' => 'entity_damage'],
            'fear' => ['handler' => fn(...$args) => FearEnchant::handle(...$args), 'event' => 'entity_damage_by_entity'],
            'featherweight' => ['handler' => fn(...$args) => FeatherweightEnchant::handle(...$args), 'event' => 'entity_damage_by_entity'],
            'frostbite' => ['handler' => fn(...$args) => FrostbiteEnchant::handle(...$args), 'event' => 'entity_damage_by_entity'],
            'greatsword' => ['handler' => fn(...$args) => GreatswordEnchant::handle(...$args), 'event' => 'entity_damage_by_entity'],
            'hardened' => ['handler' => fn(...$args) => HardenedEnchant::handle(...$args), 'event' => 'entity_damage_by_entity'],
            'heavy' => ['handler' => fn(...$args) => HeavyEnchant::handle(...$args), 'event' => 'entity_damage_by_entity'],
            'holy' => ['handler' => fn(...$args) => HolyEnchant::handle(...$args), 'event' => 'entity_damage_by_entity'],
            'insomnia' => ['handler' => fn(...$args) => InsomniaEnchant::handle(...$args), 'event' => 'entity_damage_by_entity'],
            'inquisitive' => ['handler' => fn(...$args) => InquisitiveEnchant::handle(...$args), 'event' => 'entity_death'],
            'killaura' => ['handler' => fn(...$args) => KillAuraEnchant::handle(...$args), 'event' => 'entity_death'],
            'jellylegs' => ['handler' => fn(...$args) => JellyLegsEnchant::handle(...$args), 'event' => 'entity_damage'],
            'lifesteal' => ['handler' => fn(...$args) => LifestealEnchant::handle(...$args), 'event' => 'entity_damage_by_entity'],
            'lucky' => ['handler' => fn(...$args) => LuckyEnchant::handle(...$args), 'event' => 'block_break'],
            'metaphysical' => ['handler' => fn(...$args) => MetaphysicalEnchant::handle(...$args), 'event' => 'effect_add'],
            'obsidiandestroyer' => ['handler' => fn(...$args) => ObsidianDestroyerEnchant::handle(...$args), 'event' => 'interact'],
            'pummel' => ['handler' => fn(...$args) => PummelEnchant::handle(...$args), 'event' => 'entity_damage_by_entity'],
            'rage' => ['handler' => fn(...$args) => RageEnchant::handle(...$args), 'event' => 'entity_damage_by_entity'],
            'silence' => ['handler' => fn(...$args) => SilenceEnchant::handle(...$args), 'event' => 'entity_damage_by_entity'],
            'slowness' => ['handler' => fn(...$args) => SlownessEnchant::handle(...$args), 'event' => 'entity_damage_by_entity'],
            'tank' => ['handler' => fn(...$args) => TankEnchant::handle(...$args), 'event' => 'entity_damage_by_entity'],
            'trickster' => ['handler' => fn(...$args) => TricksterEnchant::handle(...$args), 'event' => 'entity_damage_by_entity'],
            'valor' => ['handler' => fn(...$args) => ValorEnchant::handle(...$args), 'event' => 'entity_damage_by_entity'],
            'vampire' => ['handler' => fn(...$args) => VampireEnchant::handle(...$args), 'event' => 'entity_damage_by_entity'],
            'virus' => ['handler' => fn(...$args) => VirusEnchant::handle(...$args), 'event' => 'entity_damage_by_entity'],
            'wellfed' => ['handler' => fn(...$args) => WellFedEnchant::handle(...$args), 'event' => 'player_exhaust'],
        ];

        foreach ($enchantments as $name => $data) {
            EnchantEventRegistry::registerHandler($data['event'], $name, $data['handler']);
        }

        $autoPlantTask = new AutoPlanterTask();
        Loader::getInstance()->getScheduler()->scheduleRepeatingTask($autoPlantTask, 5  );
        AutoPlanterEnchant::setTask($autoPlantTask);

        $files = array_merge(
            ["dps/Simple.yml", "dps/Unique.yml", "dps/Elite.yml", "dps/Exotic.yml", "dps/Legendary.yml", "dps/Godly.yml", "items.yml", "blackmarket.yml", "shop.yml", "sell.yml", "kits.yml", "slotbot.yml", "enchanter.yml", "auctionhouse.yml", "quests.yml", "spawners.yml", "misc.yml", "messages.yml", "faction_upgrades.yml", "pets.yml", "fund.yml", "envoy.yml", "genbuckets.yml", "levels.yml", "zones.yml", "capturepoints.yml"],
            LootboxRepository::getBundledResourceFiles()
        );

        foreach ($files as $file) {
            $loader->saveResource($file);
        }

        QuestInstance::loadFromConfig();
        LootboxRepository::reload();

        SellHandler::registerDefaults();
        SpawnerWorthManager::loadFromConfig();
        GenBucketManager::loadFromConfig();
        WorldBorderManager::loadFromConfig();
        PlayerLevelManager::loadFromConfig();
        ZoneManager::loadFromConfig();
        Loader::getCaptureManager()->load();

        Loader::getInstance()->getScheduler()->scheduleDelayedTask(new class extends Task {
            public function onRun(): void
            {
                Loader::getFactionManager()->recalculateSpawnerWorths();
            }
        }, 40);

        Loader::getInstance()->getScheduler()->scheduleRepeatingTask(new BlackMarketAuctionTickTask(), 20);
    }

    public static function bless(Player $player): array {
        $manager = $player->getEffects();
        $removed = [];

        foreach ($manager->all() as $effect) {
            if ($effect->getType()->isBad()) {
                $removed[] = $effect;
                $manager->remove($effect->getType());
            }
        }

        if (empty($removed)) {
            return [];
        }

        foreach ($removed as $effect) {
            $name = $player->getServer()->getLanguage()->translate($effect->getType()->getName());
            $amp = $effect->getAmplifier() + 1;

            $player->sendMessage(
                C::colorize("&r&l&c[-] &8{$name} {$amp}")
            );
        }

        return $removed;
    }

     /**
     * Returns true if the two players are in the same faction or allied.
     */
    public static function isAlly(Player $attacker, Player $target): bool
    {
        $attackerSession = PlayerManager::getInstance()->getSession($attacker);
        $targetSession   = PlayerManager::getInstance()->getSession($target);

        $attackerFac = $attackerSession->getFaction()?->getFactionId();
        $targetFac   = $targetSession->getFaction()?->getFactionId();

        if ($attackerFac === null || $targetFac === null) return false;
        if ($attackerFac === $targetFac) return true;
        if (RelationManager::getInstance()->getRelation($attackerFac, $targetFac) === "ally") return true;

        return false;
    }

    public static function updateGlowEffect(Item $item): void {
        $root = $item->getNamedTag();
        $martianCES = $root->getCompoundTag("GlaciaCES");
        $customCount = $martianCES?->count() ?? 0;
        $vanillaCount = count($item->getEnchantments());

        if ($customCount > 0 || $vanillaCount > 0) {
            self::applyDisplayEnchant($item);
        } else {
            self::removeDisplayEnchant($item);
        }
    }

    public static function applyDisplayEnchant(Item $item): void {
        $item->addEnchantment(new EnchantmentInstance(EnchantmentIdMap::getInstance()->fromId(self::FAKE_ENCH_ID)));
    }

    public static function removeDisplayEnchant(Item $item): void {
        $item->removeEnchantment(EnchantmentIdMap::getInstance()->fromId(self::FAKE_ENCH_ID));
    }

    public static function getRelationColor(string $relation): string
    {
        switch (strtolower($relation)) {
            case "neutral":
                return C::WHITE;    
            case "warzone":
                return C::DARK_RED;
            case "safezone":
                return C::GREEN;
            case "ally":
                return C::LIGHT_PURPLE;
            case "enemy":
                return C::RED;
            case "own":
                return C::GREEN;
            default:
                return C::GRAY;
        }
    }

    public static function handleClaimTitle(Player $player, ?FactionClaim $claim): void
    {
        if ($claim === null) {
            self::sendWildernessTitle($player);
        } else {
            self::sendFactionTitle($player, $claim);
        }
    }

    public static function sendFactionTitle(Player $player, FactionClaim $claim): void
    {
        $faction = $claim->getFaction();
        if ($faction === null) {
            self::sendWildernessTitle($player);
            return;
        }

        $session = Loader::getPlayerManager()->getSession($player);
        $playerFac = $session?->getFaction();
        $targetFacId = $faction->getFactionId();

        if ($playerFac === null) {
            $relation = "neutral";
        } elseif ($playerFac->getFactionId() === $targetFacId) {
            $relation = "own";
        } else {
            $relation = RelationManager::getInstance()
                ->getRelation($playerFac->getFactionId(), $targetFacId);
        }

        $color = GlaciaUtils::getRelationColor($relation);
        $title = "{$color}{$faction->getName()}";
        $subtitle = C::WHITE . $faction->getDescription();

        $player->sendTitle(
            C::colorize($title),
            C::colorize($subtitle),
            10,
            40,
            10
        );
    }

    public static function sendWildernessTitle(Player $player): void
    {
        $player->sendTitle(C::colorize("&l&2WILDERNESS"), C::colorize("&7PvP: &aEnabled"), 10, 40, 10);
    }

    public static function getFactionRoleSymbol(?string $role): string
    {
        if ($role === null) {
            return "";
        }

        switch ($role) {
            case "leader":
                return "***";
            case "officer":
                return "**";
            case "member":
                return "";
            case "recruit":
                return "-";
            default:
                return "";
        }
    }

    public static function broadcastToAll(string $msg): void
    {
        foreach (Loader::getInstance()->getServer()->getOnlinePlayers() as $p) {
            $p->sendMessage(c::colorize($msg));
        }
    }

    public static function ensureRanks(): void
    {
        $rankManager = RankManager::getInstance();

        $defaults = [
            "frostborn" => [
                "display" => "Frostborn",
                "parent" => null,
                "format" => "&r{fac_relation}{fac_rank}{fac_name}&7<&8Frostborn&7> &8{name}&f: &7{message}",
                "nametag" => "&r&7<&8Frostborn&7>\n&r{fac_relation}{fac_rank}{fac_name} &r&8{name}"
            ],
            "cryoshield" => [
                "display" => "Cryoshield",
                "parent" => "frostborn",
                "format" => "&r{fac_relation}{fac_rank}{fac_name}&f<&3Cryoshield&f> &3{name}&f: {message}",
                "nametag" => "&f<&3Cryoshield&f>\n&r{fac_relation}{fac_rank}{fac_name} &r&3{name}"
            ],
            "winterborn" => [
                "display" => "Winterborn",
                "parent"  => "cryoshield",
                "format" => "&r{fac_relation}{fac_rank}{fac_name}&f<&bWinterborn&f> &b{name}&f: {message}",
                "nametag" => "&r&f<&bWinterborn&f>\n&r{fac_relation}{fac_rank}{fac_name} &r&b{name}"
            ],
            "glacian" => [
                "display" => "Glacian",
                "parent" => "winterborn",
                "format" => "&r{fac_relation}{fac_rank}{fac_name}&f<&aGlacian&f> &a{name}&f: {message}",
                "nametag" => "&r&f<&aGlacian&f>\n&r{fac_relation}{fac_rank}{fac_name} &r&a{name}"
            ],
            "iceheart" => [
                "display" => "Iceheart",
                "parent" => "glacian",
                "format" => "&r{fac_relation}{fac_rank}{fac_name}&f&l<&9Iceheart&f> &r&9{name}&f: {message}",
                "nametag" => "&r&f&l<&9Iceheart&f>\n&r{fac_relation}{fac_rank}{fac_name} &r&9{name}"
            ],
            "frostlord" => [
                "display" => "Frostlord",
                "parent" => "iceheart",
                "format" => "&r{fac_relation}{fac_rank}{fac_name}&f&l<&dFrostlord&f> &r&d{name}&f: {message}",
                "nametag" => "&r&f&l<&dFrostlord&f>\n&r{fac_relation}{fac_rank}{fac_name} &r&d{name}"
            ],
            "admin" => [
                "display" => "Admin",
                "parent" => "frostlord",
                "format" => "&r{fac_relation}{fac_rank}{fac_name}&f&l<&cAdmin&f> &r&c{name}&f: &c{message}",
                "nametag" => "&r&f&l<&cAdmin&f>\n&r{fac_relation}{fac_rank}{fac_name} &r&c{name}"
            ]
        ];

        foreach ($defaults as $key => $config) {
            if ($rankManager->getRank($key) === null) {
                $rankManager->createRank($key, $config["display"], $config["parent"], $config["format"], $config["nametag"]);
            }
        }
    }

    /**
     * Validate a faction name for create/rename.
     *
     * @param string $name
     * @return string|null  Returns an error message, or null if okay.
     */
    public static function validateName(string $name, bool $sanitize = true): ?array
    {
        $original = $name;

        if ($sanitize) {
            $name = preg_replace("/[^A-Za-z0-9]/", "", $name);
        }

        $blacklist = [
            "nazi",
            "kkk",
            "terror",
            "nigger",
            "fag",
            "faggot",
            "fagot",
            "fags"
        ];

        foreach ($blacklist as $word) {
            if (stripos($original, $word) !== false || stripos($name, $word) !== false) {
                return [$name, "That name contains a forbidden word."];
            }
        }

        if (!preg_match("/^[A-Za-z0-9]{3,16}$/", $name)) {
            return [$name, "Faction names must be 3–16 alphanumeric characters (letters and numbers only)."];
        }

        if (str_contains($original, "§") || str_contains($original, "&")) {
            return [$name, "Color codes are not allowed in faction names."];
        }

        foreach (Loader::getFactionManager()->getAllFactions() as $faction) {
            if (strcasecmp($faction->getName(), $name) === 0) {
                return [$name, "A faction named '{$name}' already exists!"];
            }
        }

        return null;
    }

    public static function updatePlayerNametag(Player $target, Player $viewer): void
    {
        $targetSession = Loader::getPlayerManager()->getSession($target);
        $viewerSession = Loader::getPlayerManager()->getSession($viewer);
        if ($targetSession === null || $viewerSession === null) return;

        $rankObj = $targetSession->getRankObject();
        if ($rankObj === null) {
            $target->setNameTag(C::colorize($targetSession->getUsername()));
            return;
        }

        $format = $rankObj->getNametagFormat();

        $targetFaction = $targetSession->getFaction();
        $viewerFaction = $viewerSession->getFaction();

        $facRelation = 'neutral';

        if ($targetFaction !== null && $viewerFaction !== null) {
            if ($targetFaction->getFactionId() === $viewerFaction->getFactionId()) {
                $facRelation = 'own';
            } else {
                $facRelation = RelationManager::getInstance()->getRelation($viewerFaction->getFactionId(), $targetFaction->getFactionId());
            }
        } else {
            $facRelation = 'neutral';
        }

        $formatted = str_replace(
            ['{name}', '{fac_relation}', '{fac_rank}', '{fac_name}'],
            [
                $targetSession->getUsername(),
                GlaciaUtils::getRelationColor($facRelation),
                self::getFactionRoleSymbol($targetSession->getFactionRole() ?? ''),
                $targetFaction?->getName() ?? ''
            ],
            $format
        );

        $target->setNameTag(C::colorize($formatted));
    }

    public static function refreshNametagsForTarget(Player $target): void
    {
        $pm = Loader::getPlayerManager();
        foreach ($pm->getSessions() as $viewerSession) {
            $viewer = $viewerSession->getPlayer();
            if ($viewer !== null && $viewer->isOnline()) {
                self::updatePlayerNametag($target, $viewer);
                self::updatePlayerNametag($viewer, $target);
            }
        }
    }

    public static function format(string $key, array $replacements = [], string $prefix = "none"): string
    {
        $prefixes = [
            "error" => "&r&c✘ &r&7",
            "info" => "&r&b• &r&7",
            "success" => "&r&a✔ &r&7",
            "none" => ""
        ];

        $template = MessageTemplates::getMessage($key) ?? "&7A message is missing for this action.";
        foreach ($replacements as $k => $v) {
            $normalizedKey = trim((string) $k, "{}");
            $template = str_replace("{" . $normalizedKey . "}", (string) $v, $template);
        }

        return C::colorize(($prefixes[$prefix] ?? "") . $template);
    }

    public static function timedTeleport(Player $player, Vector3 $target, ?int $seconds = 8, ?callable $onCancel = null): void
    {
        $xp = $player->getXpManager()->getCurrentTotalXp();
        $delay = max(1, $seconds - (int)($xp / 100));

        $msg = C::colorize("&r&e&oTeleporting in {$delay}s...");
        $player->sendActionBarMessage($msg);

        $effectManager = $player->getEffects();
        $nausea = VanillaEffects::NAUSEA();
        $blindness = VanillaEffects::BLINDNESS();
        $duration = $delay * 20;
        $effectManager->add(new EffectInstance($nausea, $duration));
        $effectManager->add(new EffectInstance($blindness, $duration));

        $sound = new EndermanTeleportSound();
        $player->getWorld()->addSound($player->getPosition(), $sound);

        $particlePos = $player->getPosition();
        $world = $player->getWorld();
        for ($i = 0; $i < 20; $i++) {
            $world->addParticle($particlePos->add(mt_rand(-1, 1), mt_rand(0, 2), mt_rand(-1, 1)), new PortalParticle());
        }

        $startPos = $player->getPosition();

        $task = new class($player, $target, $startPos, $onCancel) extends Task {
            private $player;
            private $target;
            private $startPos;
            private $onCancel;
            private $cancelled = false;
            public function __construct($player, $target, $startPos, $onCancel)
            {
                $this->player = $player;
                $this->target = $target;
                $this->startPos = $startPos;
                $this->onCancel = $onCancel;
            }
            public function onRun(): void
            {
                if ($this->cancelled) return;
                $current = $this->player->getPosition();
                if ($current->distanceSquared($this->startPos) > 0.25) {
                    $this->cancelled = true;
                    $this->player->sendMessage(C::RED . "Teleportation cancelled: You moved.");
                    if (is_callable($this->onCancel)) {
                        ($this->onCancel)($this->player);
                    }
                    return;
                }
                $this->player->teleport($this->target);
            }
        };
        Loader::getInstance()->getScheduler()->scheduleDelayedTask($task, $delay * 20);
    }

    public static function updateScoreTag(Player $player): void
    {
        $health = (int)floor($player->getHealth());
        $maxHealth = (int)floor($player->getMaxHealth());
        $percent = $health / $maxHealth;

        if ($percent <= 0.25) {
            $color = "&c";
        } elseif ($percent <= 0.5) {
            $color = "&e";
        } else {
            $color = "&b";
        }

        $player->setScoreTag(C::colorize("&r&l{$color}❤ &r&f{$health}/{$maxHealth}"));
    }

    public static function updateEntityTag(CombatLoggerEntity $entity): void
    {
        $health = (int)floor($entity->getHealth());
        $maxHealth = (int)floor($entity->getMaxHealth());
        $percent = $maxHealth > 0 ? $health / $maxHealth : 0;

        if ($percent <= 0.25) {
            $color = "§c";
        } elseif ($percent <= 0.5) {
            $color = "§e";
        } else {
            $color = "§b";
        }

        $entity->setNameTag($entity->baseTag . "\n{$color}❤ {$health}/{$maxHealth}");
    }

    public static function initDiscordWebhooks(PluginBase $plugin): void
    {
        $webhook = new WebhookPool($plugin);

        Await::g2c(
            $webhook->asyncSubmit(
                "https://discord.com/api/webhooks/1495596161950552091/t_aDEVX3LMjFs0Cexzibz2AfvuVEpF3hapjfEFl0Qfud-y5ATqeGZbLrqTvt2qYQ9Sqq",
                new WebhookMessage("Webhook Connected!")
            ),
            function (WebhookJobResult $r) {
                if ($r->ok) {
                    var_dump("sent");
                } else {
                    var_dump($r->error);
                }
            },
            fn(WebhookPoolException $e) => var_dump("error: " . $e->getMessage())
        );
    }

    public static function formatAdminLog(string $action, array $data): string
    {
        $lines = [];
        $lines[] = "**{$action} Log**";
        $lines[] = " ";

        if (isset($data['admin'])) {
            $lines[] = "**Admin:** `{$data['admin']}`";
        }

        if (isset($data['faction'])) {
            $lines[] = "**Faction:** **❄ {$data['faction']} ❄**";
        }

        if (isset($data['chunk'])) {
            $lines[] = "**Chunk:** `{$data['chunk']}`";
        }

        if (isset($data['world'])) {
            $lines[] = "**World:** `{$data['world']}`";
        }

        if (isset($data['target'])) {
            $lines[] = "**Target:** `{$data['target']}`";
        }

        if (isset($data['amount'])) {
            $lines[] = "**Amount:** `{$data['amount']}`";
        }

        foreach ($data as $key => $value) {
            if (!in_array($key, ['admin', 'faction', 'chunk', 'world', 'target', 'amount'])) {
                $lines[] = "**" . ucfirst($key) . ":** `{$value}`";
            }
        }

        return implode("\n", $lines);
    }

    public static function logAdminAction(string $action, array $data): void
    {
        DiscordLogger::send(self::formatAdminLog($action, $data));
    }

    /**
     * Converts an entity ID to its corresponding mob name
     * @param int $id
     * @return string
     */
    public static function convertMobIdToName(int $id): string
    {
        return match ($id) {
            10 => "chicken",
            11 => "cow",
            12 => "pig",
            13 => "sheep",
            17 => "squid",
            20 => "iron_golem",
            21 => "snowman",
            32 => "zombie",
            36 => "zombie_pigman",
            34 => "skeleton",
            35 => "spider",
            37 => "slime",
            16 => "mooshroom",
            43 => "blaze",
            default => "unknown",
        };
    }

    public static function convertNameToMobId(string $name): int
    {
        return match (strtolower($name)) {
            "minecraft:chicken" => 10,
            "minecraft:cow" => 11,
            "minecraft:pig" => 12,
            "minecraft:sheep" => 13,
            "minecraft:squid" => 17,
            "minecraft:iron_golem" => 20,
            "minecraft:snow_golem" => 21,
            "minecraft:snowman" => 21,
            "minecraft:zombie" => 32,
            "minecraft:zombie_pigman" => 36,
            "minecraft:skeleton" => 34,
            "minecraft:spider" => 35,
            "minecraft:slime" => 37,
            "minecraft:mooshroom" => 16,
            "minecraft:blaze" => 43,
            default => -1,
        };
    }

    public static function overrideOrRegisterSimpleItem(string $id, Item $newItem, array $stringToItemParserNames): void
    {
        foreach ($stringToItemParserNames as $name) {
            try {
                StringToItemParser::getInstance()->register($name, fn() => clone $newItem);
            } catch (\InvalidArgumentException) {
                StringToItemParser::getInstance()->override($name, fn() => clone $newItem);
            }
        }

        $deserializer = GlobalItemDataHandlers::getDeserializer();
        $deserializer->map($id, fn() => clone $newItem);

        $serializer = GlobalItemDataHandlers::getSerializer();
        try {
            $serializer->map($newItem, fn() => new SavedItemData($id));
        } catch (\InvalidArgumentException) {
            (function () use ($newItem, $id) {
                /** @noinspection PhpUndefinedFieldInspection */
                $this->itemSerializers[$newItem->getTypeId()] = fn() => new SavedItemData($id);
            })->call($serializer);
        }
    }

     /**
     * Determines the color of the ping based on its value.
     *
     * @param int $ping The ping value.
     * @return string The color code for the ping.
     */
    public static function getPingColor(?int $ping): string
    {
        if ($ping === null) return "&a";

        if ($ping <= 50) {
            return "&a";
        } elseif ($ping <= 150) {
            return "&e";
        } else {
            return "&c";
        }
    }

    /**
     * Generates a network bar representation based on the ping value.
     *
     * @param int $ping The ping value.
     * @return string The network bar string.
     */
    public static function getNetworkBar(?int $ping): string
    {
        if ($ping === null) {
            return "■";
        }

        $bars = 5;
        $filledBars = (int) max(1, min($bars, 6 - ceil($ping / 100)));
        $emptyBars = $bars - $filledBars;

        return str_repeat("&a■", $filledBars) . str_repeat("&7■", $emptyBars);
    }

    public static function isFullyGrownCrop(Block $block): bool {
        if ($block instanceof Wheat) {
            return $block->getAge() >= $block::MAX_AGE;
        }

        if ($block instanceof Carrot) {
            return $block->getAge() >= $block::MAX_AGE;
        }
        
        if ($block instanceof Potato) {
            return $block->getAge() >= $block::MAX_AGE;
        }

        if ($block instanceof Beetroot) {
            return $block->getAge() >= $block::MAX_AGE;
        }

        if ($block instanceof NetherWartPlant) {
            return $block->getAge() >= $block::MAX_AGE;
        }

        return false;
    }

    public static function consumeWhiteScroll(Item $itemClicked, Player $player, Inventory $inv, int $slot): Item {
        $tag = $itemClicked->getNamedTag()->getCompoundTag("Glacia") ?? new CompoundTag();
        $tag->removeTag("protected");
        $itemClicked->getNamedTag()->setTag("Glacia", $tag);

        $newLore = [];
        foreach($itemClicked->getLore() as $line){
            if(strpos(C::clean($line), "PROTECTED") === false){
                $newLore[] = $line;
            }
        }
        $itemClicked->setLore($newLore);

        $inv->setItem($slot, $itemClicked);

        $player->sendMessage(C::colorize("&eEnchantment failed, but your white scroll protected the item."));
        return $itemClicked;
    }

    public static function toggleFlight(Player $player, bool $force = false): void
    {
        if ($force) {
            if (!$player->getAllowFlight()) {
                $player->setAllowFlight(true);
                $player->sendMessage(C::colorize(Loader::SERVER_PREFIX . "&fSet fly mode &aenabled &ffor " . $player->getNameTag()));
            }
        } else {
            if (!$player->getAllowFlight()) {
                $player->setAllowFlight(true);
                $player->sendMessage(C::colorize(Loader::SERVER_PREFIX . "&fSet fly mode &aenabled &ffor " . $player->getNameTag()));
            } else {
                $player->setAllowFlight(false);
                $player->setFlying(false);
                $player->resetFallDistance();
                $player->sendMessage(C::colorize(Loader::SERVER_PREFIX . "&fSet fly mode &cdisabled &ffor " . $player->getNameTag()));
            }
        }
    
        if ($force || $player->getAllowFlight() && !$force) {
            $player->setFlying(true);
            $player->resetFallDistance();
        }
    }

    public static function repairItemInHand(Player $player): bool {
        $item = $player->getInventory()->getItemInHand();

        if (!$item instanceof Durable) {
            return false;
        }

        if ($item->getDamage() <= 0) {
            return false;
        }

        $item->setDamage(0);
        $player->getInventory()->setItemInHand($item);

        return true;
    }

    public static function repairAll(Player $player): int {
        $repaired = 0;

        $inventory = $player->getInventory();
        foreach ($inventory->getContents() as $slot => $item) {
            if ($item instanceof Durable && $item->getDamage() > 0) {
                $item->setDamage(0);
                $inventory->setItem($slot, $item);
                $repaired++;
            }
        }

        $armor = $player->getArmorInventory();
        foreach ($armor->getContents() as $slot => $item) {
            if ($item instanceof Durable && $item->getDamage() > 0) {
                $item->setDamage(0);
                $armor->setItem($slot, $item);
                $repaired++;
            }
        }

        return $repaired;
    }

    public static function getWeightedRandomSpawner(array $spawners): string {
        $totalWeight = 0;
        foreach ($spawners as $spawner) {
            $totalWeight += $spawner['chance'];
        }

        $randomNumber = mt_rand(1, $totalWeight);

        foreach ($spawners as $spawner) {
            if ($randomNumber <= $spawner['chance']) {
                return $spawner['mob'];
            }
            $randomNumber -= $spawner['chance'];
        }

        // Fallback to a random spawner if something goes wrong
        return $spawners[array_rand($spawners)]['mob'];
    }

    public static function updateEnchantmentBook(Item $book): Item {
        $glaciaTag = $book->getNamedTag()->getCompoundTag("Glacia");
        if($glaciaTag === null){
            return $book;
        }

        $enchantName = $glaciaTag->getString("enchant_book", "");
        $level = $glaciaTag->getInt("level", 1);
        $success = $glaciaTag->getInt("success", 0);
        $destroy = $glaciaTag->getInt("destroy", 0);

        if($enchantName === ""){
            return $book;
        }

        $enchantment = CustomEnchantments::getEnchantmentByName($enchantName);
        if($enchantment === null){
            return $book;
        }

        $newBook = GlaciaItemFactory::enchantmentBook($enchantment, $level, $success, $destroy);
        if($newBook === null){
            return $book;
        }

        return $newBook;
    }

    public static function initDispenserMenu(): void {
        InvMenuHandler::getTypeRegistry()->register(self::TYPE_DISPENSER, InvMenuTypeBuilders::BLOCK_ACTOR_FIXED()
                ->setBlock(StringToItemParser::getInstance()->parse("dispenser")->getBlock())
                ->setBlockActorId("Dispenser")
                ->setSize(9)
                ->setNetworkWindowType(WindowTypes::DISPENSER)
                ->build());
    }   

}
