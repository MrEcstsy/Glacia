<?php 

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\items\lore\ItemLoreLayout;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use muqsit\simplepackethandler\SimplePacketHandler;
use pocketmine\data\bedrock\EnchantmentIdMap;
use pocketmine\event\EventPriority;
use pocketmine\item\Armor;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\ItemEnchantmentTags;
use pocketmine\item\enchantment\ItemFlags;
use pocketmine\item\Item;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\InventoryContentPacket;
use pocketmine\network\mcpe\protocol\InventorySlotPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStack;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\utils\RegistryTrait;
use pocketmine\utils\TextFormat;

final class CustomEnchantments {
    use RegistryTrait;
    /** @var array<string, string> legacy id (UPPER) => current id (UPPER) */
    private const LEGACY_ID_MAP = [
        "GLASSBRAND" => "MIRRORBRAND",
        "MURAVIEVA" => "BLACKHAND",
        "TRIADWARD" => "TRIARCHSIGIL",
        "HEARTTIDE" => "LIFETIDE",
        "FURYLASH" => "WRATHBREAK",
        "GLIMMERFADE" => "NULLGLIMMER",
        "FOREWARNED" => "PREMONITION",
        "MALICEWARD" => "HEXPROOF",
        "BLASTWARD" => "SHELLSHOCK",
        "VILEWOUND" => "ROTBRAND",
        "COUPDEGRACE" => "FINALVERDICT",
        "HARMONICSURGE" => "CHOIRGLASS",
        "PHALANX" => "WARBAND",
        "STORMRITE" => "TEMPESTOMEN",
        "AUREATEBURDEN" => "MIDASTOLL",
        "NETHERWARDEN" => "HELLFORGED",
        "FETTER" => "SOULSHACKLE",
        "ASCETICAEGIS" => "OATHBOUND",
        "STRATEGIST" => "WARLORDSTEMPO",
        "AURORAWARD" => "SUNVEIL",
        "STEADFASTFURY" => "BLOODRUSH",
        "PRIMALPACT" => "BEASTBOND",
        "RUNESWELL" => "RUNEVEIN",
        "DISCORDVERDICT" => "SEVERANCE",
        "ALCHEMISTEDGE" => "ALEMBIC",
        "SUPPLYSCOUR" => "MARAUDER",
        "TRIFOLDREND" => "THIRDEYE",
        "IRONMUZZLE" => "LOCKJAW",
        "TRIAD_WARD" => "TRIARCHSIGIL",
        "HARMONIC_SURGE" => "CHOIRGLASS",
        "AUREATE_BURDEN" => "MIDASTOLL",
        "NETHER_WARDEN" => "HELLFORGED",
        "ASCETIC_AEGIS" => "OATHBOUND",
        "AURORA_WARD" => "SUNVEIL",
        "STEADFAST_FURY" => "BLOODRUSH",
        "PRIMAL_PACT" => "BEASTBOND",
        "DISCORD_VERDICT" => "SEVERANCE",
        "ALCHEMIST_EDGE" => "ALEMBIC",
        "SUPPLY_SCOUR" => "MARAUDER",
        "TRIFOLD_REND" => "THIRDEYE",
        "IRON_MUZZLE" => "LOCKJAW",
    ];

    public static array $definitions = [
        // SIMPLE
        'autosmelt' => [
            'display' => '{group-color}Auto Smelt',
            'description' => 'Automatically smelts ores when mined.',
            'group' => 'SIMPLE',
            'maxLevel' => 3,
            'flags' => [ItemEnchantmentTags::PICKAXE],
        ],
        'autoplanter' => [
            'display' => '{group-color}Auto Planter',
            'description' => 'Automatically replants fully grown crops when harvested.',
            'group' => 'SIMPLE',
            'maxLevel' => 1,
            'flags' => [ItemEnchantmentTags::HOE],
        ],
        'lucky' => [
            'display' => '{group-color}Lucky',
            'description' => 'Chance to get extra xp from mining.',
            'group' => 'SIMPLE',
            'maxLevel' => 3,
            'flags' => [ItemEnchantmentTags::PICKAXE]
        ],
        'decapitation' => [
            'display' => '{group-color}Decapitation',
            'description' => 'Chance to drop player heads upon death.',
            'group' => 'SIMPLE',
            'maxLevel' => 10,
            'flags' => [ItemEnchantmentTags::SWORD, ItemEnchantmentTags::AXE],
        ],
        'insomnia' => [
            'display' => '{group-color}Insomnia',
            'description' => 'Gives slowness, slow swinging and confusion.',
            'group' => 'SIMPLE',
            'maxLevel' => 7,
            'flags' => [ItemEnchantmentTags::SWORD],
        ],
        'confusion' => [
            'display' => '{group-color}Confusion',
            'description' => 'A chance to deal nausea to your victim.',
            'group' => 'SIMPLE',
            'maxLevel' => 3,
            'flags' => [ItemEnchantmentTags::AXE],
        ],
        'haste' => [
            'display' => '{group-color}Haste',
            'description' => 'Increases mining speed.',
            'group' => 'SIMPLE',
            'maxLevel' => 3,
            'flags' => [
                ItemEnchantmentTags::PICKAXE,
                ItemEnchantmentTags::AXE,
                ItemEnchantmentTags::SHOVEL
            ],
        ],
        'aquatic' => [
            'display' => '{group-color}Aquatic',
            'description' => 'Gives permanent water breathing.',
            'group' => 'SIMPLE',
            'maxLevel' => 1,
            'flags' => [ItemEnchantmentTags::HELMET],
        ],
        'glowing' => [
            'display' => '{group-color}Glowing',
            'description' => 'Gives permanent night vision.',
            'group' => 'SIMPLE',
            'maxLevel' => 1,
            'flags' => [ItemEnchantmentTags::HELMET],
        ],

        // UNIQUE
        'blazed' => [
            'display' => '{group-color}Blazed',
            'description' => 'Chance to set your attacker on fire',
            'group' => 'UNIQUE',
            'maxLevel' => 5,
            'flags' => [ItemEnchantmentTags::CHESTPLATE],
        ],
        'jellylegs' => [
            'display' => '{group-color}Jelly Legs',
            'description' => 'Reduces fall damage.',
            'group' => 'UNIQUE',
            'maxLevel' => 3,
            'flags' => [ItemEnchantmentTags::LEGGINGS],
        ],
        'curse' => [
            'display' => '{group-color}Curse',
            'description' => 'Chance to give your enemy mining fatigue. (does not stack)',
            'group' => 'UNIQUE',
            'maxLevel' => 3,
            'flags' => [ItemEnchantmentTags::HELMET, ItemEnchantmentTags::CHESTPLATE, ItemEnchantmentTags::LEGGINGS, ItemEnchantmentTags::BOOTS],
        ],
        'featherweight' => [            
            'display' => '{group-color}Featherweight',
            'description' => 'A chance to give a burst of haste.',
            'group' => 'UNIQUE',
            'maxLevel' => 3,
            'flags' => [ItemEnchantmentTags::SWORD],
        ],
        'obsidiandestroyer' => [
            'display' => '{group-color}Obsidian Destroyer',
            'description' => 'A chance to instantly break obsidian.',
            'group' => 'UNIQUE',
            'maxLevel' => 5,
            'flags' => [ItemEnchantmentTags::PICKAXE],
        ],
        'endershift' => [
            'display' => '{group-color}Ender Shift',
            'description' => 'Gives speed/health boost at low hp.',
            'group' => 'UNIQUE',
            'maxLevel' => 3,
            'flags' => [ItemEnchantmentTags::HELMET],
        ],
        'virus' => [
          'display' => '{group-color}Virus',
          'description' => 'Multiplies all Wither and Poison\ndamage the affected target receives\nand has a chance to remove\nregeneration effects on hit.',
          'group' => 'UNIQUE',
          'maxLevel' => 3,
          'flags' => [ItemEnchantmentTags::BOW],
        ],
        'berserk' => [
            'display' => '{group-color}Berserk',
            'description' => 'Chance of strength and mining\nfatigue.',
            'group' => 'UNIQUE',
            'maxLevel' => 5,
            'flags' => [ItemEnchantmentTags::AXE],
        ],
        'mummification' => [
            'display' => '{group-color}Mummification',
            'description' => 'On proc, summons a mummy clone of your\ngear to fight by your side for a short time.',
            'group' => 'EXCLUSIVE',
            'maxLevel' => 3,
            'flags' => [ItemEnchantmentTags::LEGGINGS],
        ],
        'leviathan' => [
            'display' => '{group-color}Leviathan',
            'description' => 'On proc, disables enemy custom enchants\nand pets while boosting your proc rates.',
            'group' => 'EXCLUSIVE',
            'maxLevel' => 6,
            'flags' => [ItemEnchantmentTags::SWORD],
        ],
        'gloryhunter' => [
            'display' => '{group-color}Gloryhunter',
            'description' => 'Adds an extra chance to pull a configured\nbonus drop from zone mobs you kill.',
            'group' => 'EXCLUSIVE',
            'maxLevel' => 2,
            'flags' => [ItemEnchantmentTags::SWORD, ItemEnchantmentTags::AXE],
        ],
        'armageddon' => [
            'display' => '{group-color}Armageddon',
            'description' => 'Unleashes one or more apocalyptic armor\nabilities when you strike an enemy.',
            'group' => 'EXCLUSIVE',
            'maxLevel' => 8,
            'flags' => [
                ItemEnchantmentTags::HELMET,
                ItemEnchantmentTags::CHESTPLATE,
                ItemEnchantmentTags::LEGGINGS,
                ItemEnchantmentTags::BOOTS,
            ],
        ],
        'aristocratic' => [
            'display' => '{group-color}Aristocratic',
            'description' => 'Counters Armageddon by cutting multi-procs\nand sometimes mirroring the same blessing.',
            'group' => 'EXCLUSIVE',
            'maxLevel' => 5,
            'flags' => [ItemEnchantmentTags::BOOTS],
        ],
        'triggerhappy' => [
            'display' => '{group-color}Triggerhappy',
            'description' => 'On proc, your bow erupts into a short-lived\nAoE disarmor frenzy.',
            'group' => 'EXCLUSIVE',
            'maxLevel' => 5,
            'flags' => [ItemEnchantmentTags::BOW],
        ],

        // ELITE
        'bounce' => [
            'display' => '{group-color}Bounce',
            'description' => 'Gives permanent jump boost.',
            'group' => 'ELITE',
            'maxLevel' => 3,
            'flags' => [ItemEnchantmentTags::BOOTS],
        ],
        'fear' => [
            'display' => '{group-color}Fear',
            'description' => 'Chance to give your enemy weakness.',
            'group' => 'ELITE',
            'maxLevel' => 5,
            'flags' => [ItemEnchantmentTags::AXE],
        ],
        'frostbite' => [
            'display' => '{group-color}Frostbite',
            'description' => 'Chance to slow your attacker.',
            'group' => 'ELITE',
            'maxLevel' => 5,
            'flags' => [ItemEnchantmentTags::HELMET, ItemEnchantmentTags::CHESTPLATE, ItemEnchantmentTags::LEGGINGS, ItemEnchantmentTags::BOOTS],
        ],
        'execute' => [
            'display' => '{group-color}Execute',
            'description' => 'A chance-by-level finishing blow vs players below a configured HP threshold (see ce_values.yml).',
            'group' => 'ELITE',
            'maxLevel' => 5,
            'flags' => [ItemEnchantmentTags::SWORD],
        ],
        'slowness' => [
            'display' => '{group-color}Slowness',
            'description' => 'Chance to slow your victim.',
            'group' => 'ELITE',
            'maxLevel' => 3,
            'flags' => [ItemEnchantmentTags::SWORD],
        ],
        'pummel' => [
            'display' => '{group-color}Pummel',
            'description' => 'Chance to slow nearby enemy players\nfor a short period.',
            'group' => 'ELITE',
            'maxLevel' => 3,
            'flags' => [ItemEnchantmentTags::AXE],    
        ],
        'blind' => [
            'display' => '{group-color}Blind',
            'description' => 'A chance of causing blindness when\nattacking.',
            'group' => 'ELITE',
            'maxLevel' => 3,
            'flags' => [ItemEnchantmentTags::SWORD],
        ],
        'hardened' => [
            'display' => '{group-color}Hardened',
            'description' => 'Armor takes less durability damage.',
            'group' => 'ELITE',
            'maxLevel' => 3,
            'flags' => [ItemEnchantmentTags::HELMET, ItemEnchantmentTags::CHESTPLATE, ItemEnchantmentTags::LEGGINGS, ItemEnchantmentTags::BOOTS],
        ],
        'greatsword' => [
            'display' => '{group-color}Greatsword',
            'description' => 'Multiplies damage against players who\nare wielding a BOW at the time they\nare hit.',
            'group' => 'ELITE',
            'maxLevel' => 5,
            'flags' => [ItemEnchantmentTags::SWORD],
        ],
        'trickster' => [
            'display' => '{group-color}Trickster',
            'description' => 'When hit you have a chance to\nteleport directly behind your\nopponent and take them by surprise.',
            'group' => 'ELITE',
            'maxLevel' => 8,
            'flags' => [ItemEnchantmentTags::HELMET],
        ],
        'vampire' => [
            'display' => '{group-color}Vampire',
            'description' => 'A chance to heal you for up to 3hp a\nfew seconds after you strike.',
            'group' => 'ELITE',
            'maxLevel' => 3,
            'flags' => [ItemEnchantmentTags::SWORD],
        ],

        // EXOTIC
        'gears' => [
            'display' => '{group-color}Gears',
            'description' => 'Gives permanent speed boost.',
            'group' => 'EXOTIC',
            'maxLevel' => 3,
            'flags' => [ItemEnchantmentTags::BOOTS],
        ],
        'doublestrike' => [
            'display' => '{group-color}Double Strike',
            'description' => 'Chance to strike twice.',
            'group' => 'EXOTIC',
            'maxLevel' => 3,
            'flags' => [ItemEnchantmentTags::SWORD],
        ],
        'diminish' => [
            'display' => '{group-color}Diminish',
            'description' => 'Chance to deal extra durability damage to all enemy armor with every attack.',
            'group' => 'EXOTIC',
            'maxLevel' => 4,
            'flags' => [ItemEnchantmentTags::HELMET, ItemEnchantmentTags::CHESTPLATE, ItemEnchantmentTags::LEGGINGS, ItemEnchantmentTags::BOOTS],
        ],
        'cleave' => [
            'display' => '{group-color}Cleave',
            'description' => 'Damages players within a radius that increases with the level of enchant.',
            'group' => 'EXOTIC',
            'maxLevel' => 4,
            'flags' => [ItemEnchantmentTags::AXE],
        ],
        'clarity' => [
            'display' => '{group-color}Clarity',
            'description' => 'Immune to blindnesse and nausea. (depending on level)',
            'group' => 'EXOTIC',
            'maxLevel' => 5,
            'flags' => [ItemEnchantmentTags::HELMET],
        ],
        'lavabound' => [
            'display' => '{group-color}Lavabound',
            'description' => 'Gives permanent fire resistance.',
            'group' => 'EXOTIC',
            'maxLevel' => 1,
            'flags' => [ItemEnchantmentTags::LEGGINGS],
        ],
        'wellfed' => [
            'display' => '{group-color}Well Fed',
            'description' => 'Replenishes hunger',
            'group' => 'EXOTIC',
            'maxLevel' => 5,
            'flags' => [ItemEnchantmentTags::HELMET],
        ],
        'holy' => [
            'display' => '{group-color}Holy',
            'description' => 'Chance to remove debuffs.',
            'group' => 'EXOTIC',
            'maxLevel' => 5,
            'flags' => [ItemEnchantmentTags::AXE],
        ],
        'angelic' => [
            'display' => '{group-color}Angelic',
            'description' => 'Heals health over time when damaged. Healing\nfollows your highest Angelic level; wearing more\npieces only increases activation chance\n(sum of tiers, capped).\nOnly one Angelic heal can run at a time.',
            'group' => 'EXOTIC',
            'maxLevel' => 5,
            'flags' => [ItemEnchantmentTags::HELMET, ItemEnchantmentTags::CHESTPLATE, ItemEnchantmentTags::LEGGINGS, ItemEnchantmentTags::BOOTS],
        ],
        'valor' => [
            'display' => '{group-color}Valor',
            'description' => 'Reduces incoming damage while wielding a sword by\nup to 22.5% this enchantment is stackable',
            'group' => 'EXOTIC',
            'maxLevel' => 5,
            'flags' => [ItemEnchantmentTags::HELMET, ItemEnchantmentTags::CHESTPLATE, ItemEnchantmentTags::LEGGINGS, ItemEnchantmentTags::BOOTS],
        ],
        'dodge' => [
            'display' => '{group-color}Dodge',
            'description' => 'Chance to dodge physical enemy\nattacks, increased chance if\nsneaking.',
            'group' => 'EXOTIC',
            'maxLevel' => 5,
            'flags' => [ItemEnchantmentTags::HELMET, ItemEnchantmentTags::CHESTPLATE, ItemEnchantmentTags::LEGGINGS, ItemEnchantmentTags::BOOTS],
        ],
        'enderwalker' => [
            'display' => '{group-color}Enderwalker',
            'description' => 'Wither and Poison do not injure and\nhave a chance to heal at high levels.',
            'group' => 'EXOTIC',
            'maxLevel' => 5,
            'flags' => [ItemEnchantmentTags::BOOTS],
        ],
        'implants' => [
            'display' => '{group-color}Implants',
            'description' => 'Passivly heals +1 health and\nrestores +1 hunger every few seconds.',
            'group' => 'EXOTIC',
            'maxLevel' => 3,
            'flags' => [ItemEnchantmentTags::HELMET],
        ],
        'metaphysical' => [
            'display' => '{group-color}Metaphysical',
            'description' => 'A chance to resist the slowness given\nby enemy Frostbite, Slowness, and Pummel\nenchantments. At max level, you will\nonly be affected approx. 10% of the\ntime.',
            'group' => 'EXOTIC',
            'maxLevel' => 4,
            'flags' => [ItemEnchantmentTags::BOOTS],
        ],
        "trophyseeker" => [
            'display' => '{group-color}Trophy Seeker',
            'description' => 'Increases chance to catch bigger and\nbetter fish.',
            'group' => 'EXOTIC',
            'maxLevel' => 5,
            'flags' => [ItemEnchantmentTags::FISHING_ROD],
        ],
        'detonate' => [
            'display' => '{group-color}Detonate',
            'description' => "Summons up to a 3x3x3 explosion\naround any block you break.",
            'group' => 'EXOTIC',
            'maxLevel' => 9,
            'flags' => [ItemEnchantmentTags::PICKAXE],
        ],

        // LEGENDARY
        'watchmen' => [
            'display' => '{group-color}Watchmen',
            'description' => "Chance to summon iron golems to\nprotect you",
            'group' => 'LEGENDARY',
            'maxLevel' => 3,
            'flags' => [ItemEnchantmentTags::CHESTPLATE],
        ],
        'tank' => [
            'display' => '{group-color}Tank',
            'description' => 'Take less damage from swords and axes.',
            'group' => 'LEGENDARY',
            'maxLevel' => 5,
            'flags' => [ItemEnchantmentTags::HELMET, ItemEnchantmentTags::CHESTPLATE, ItemEnchantmentTags::LEGGINGS, ItemEnchantmentTags::BOOTS],
        ],
        'reforged' => [
            'display' => '{group-color}Reforged',
            'description' => 'Upon item breaking has chance (10% up to 100%) of item to be repaired fully but removing 1 other enchant from item.',
            'group' => 'LEGENDARY',
            'maxLevel' => 10,
            'flags' => [ItemEnchantmentTags::SWORD, ItemEnchantmentTags::AXE, ItemEnchantmentTags::PICKAXE, ItemEnchantmentTags::SHOVEL, ItemEnchantmentTags::HOE, ItemEnchantmentTags::BOW, ItemEnchantmentTags::HELMET, ItemEnchantmentTags::CHESTPLATE, ItemEnchantmentTags::LEGGINGS, ItemEnchantmentTags::BOOTS],
        ],
        'rage' => [
            'display' => '{group-color}Rage',
            'description' => 'Deals extra combo damage up to x5 damage when you are not attacked.',
            'group' => 'LEGENDARY',
            'maxLevel' => 5,
            'flags' => [ItemEnchantmentTags::SWORD],
        ],
        'bleed' => [
            'display' => '{group-color}Bleed',
            'description' => 'Chance to give your opponent bleed effect.',
            'group' => 'LEGENDARY',
            'maxLevel' => 5,
            'flags' => [ItemEnchantmentTags::AXE],
        ],
        'silence' => [
            'display' => '{group-color}Silence',
            'description' => 'Negate all enemy buffs up to 5 seconds.',
            'group' => 'LEGENDARY',
            'maxLevel' => 5,
            'flags' => [ItemEnchantmentTags::SWORD],
        ],
        'inquisitive' => [
            'display' => '{group-color}Inquisitive',
            'description' => 'Increases the amount of vanilla XP gained from killing mobs.',
            'group' => 'LEGENDARY',
            'maxLevel' => 4,
            'flags' => [ItemEnchantmentTags::SWORD],
        ],
        'drunk' => [
            'display' => '{group-color}Drunk',
            'description' => 'Slowness and slow swinging with a abuffed strength.',
            'group' => 'LEGENDARY',
            'maxLevel' => 4,
            'flags' => [ItemEnchantmentTags::HELMET],
        ],
        'overload' => [
            'display' => '{group-color}Overload',
            'description' => 'Permanent increase of hearts.',
            'group' => 'LEGENDARY',
            'maxLevel' => 3,
            'flags' => [ItemEnchantmentTags::CHESTPLATE],
        ],
        'heavy' => [
            'display' => '{group-color}Heavy',
            'description' => "Decreases damage from enemy bows by\n2% per level, this enchantment is\nstackable",
            'group' => 'LEGENDARY',
            'maxLevel' => 5,
            'flags' => [ItemEnchantmentTags::HELMET],
        ],
        'armored' => [
            'display' => '{group-color}Armored',
            'description' => 'Decreases damage from enemy swords by \n0.75% per level, this enchantment is stackable',
            'group' => 'LEGENDARY',
            'maxLevel' => 5,
            'flags' => [ItemEnchantmentTags::HELMET, ItemEnchantmentTags::CHESTPLATE, ItemEnchantmentTags::LEGGINGS, ItemEnchantmentTags::BOOTS],
        ],
        'bossslayer' => [
            'display' => '{group-color}Boss Slayer',
            'description' => 'Deal an extra (level * 5%) base damage to bosses',
            'group' => 'LEGENDARY',
            'maxLevel' => 5,
            'flags' => [ItemEnchantmentTags::AXE],
        ],
        'bloodlust' => [
            'display' => '{group-color}Bloodlust',
            'description' => 'A chance to heal you whenever an\nenemy player within 7x7 blocks is\ndamaged by the Bleed enchant.',
            'group' => 'LEGENDARY',
            'maxLevel' => 5,
            'flags' => [ItemEnchantmentTags::CHESTPLATE],
        ],
        'deathbringer' => [
            'display' => '{group-color}Deathbringer',
            'description' => 'Chance to deal double damage.',
            'group' => 'LEGENDARY',
            'maxLevel' => 3,
            'flags' => [ItemEnchantmentTags::SWORD],
        ],
        'enlighted' => [
            'display' => '{group-color}Enlighted',
            'description' => 'Can heal hearts while taking damage.',
            'group' => 'LEGENDARY',
            'maxLevel' => 3,
            'flags' => [ItemEnchantmentTags::HELMET, ItemEnchantmentTags::CHESTPLATE, ItemEnchantmentTags::LEGGINGS, ItemEnchantmentTags::BOOTS],
        ],
        'lifesteal' => [
            'display' => '{group-color}Lifesteal',
            'description' => 'Chance to deal bonus damage and heal a portion of it back.',
            'group' => 'LEGENDARY',
            'maxLevel' => 5,
            'flags' => [ItemEnchantmentTags::SWORD],
        ],
        'stormlash' => [
            'display' => '{group-color}Stormlash',
            'description' => 'Chance to call a lightning strike onto your victim for bonus damage.',
            'group' => 'ELITE',
            'maxLevel' => 5,
            'flags' => [ItemEnchantmentTags::SWORD],
        ],
        'divinechains' => [
            'display' => '{group-color}Divine Chains',
            'description' => 'Chance to shackle your victim, stunning them and causing extra damage on your hits for 1.5 seconds.',
            'group' => 'LEGENDARY',
            'maxLevel' => 3,
            'flags' => [ItemEnchantmentTags::SWORD],
        ],
        'dreadanchor' => [
            'display' => '{group-color}Dread Anchor',
            'description' => 'Small chance to deal heavy bonus damage and weigh your victim down with a brutal slow.',
            'group' => 'EXOTIC',
            'maxLevel' => 3,
            'flags' => [ItemEnchantmentTags::SWORD],
        ],
        'anatomy' => [
            'display' => '{group-color}Anatomy',
            'description' => 'Chance to deal extra damage and stop your victim from healing with custom enchants for a short time.',
            'group' => 'LEGENDARY',
            'maxLevel' => 3,
            'flags' => [ItemEnchantmentTags::SWORD],
        ],
        'killaura' => [
            'display' => '{group-color}Kill Aura',
            'description' => "Chance to kill multiple monsters in a\nstack each death event.",
            'group' => 'LEGENDARY',
            'maxLevel' => 5,
            'flags' => [ItemEnchantmentTags::SWORD],
        ],
        'insanity' => [
            'display' => '{group-color}Insanity',
            'description' => "You swing your axe like a maniac.\nMultiplies damage against players who\nare weilding a SWORD at the time they\nare hit.",
            'group' => 'LEGENDARY',
            'maxLevel' => 8,
            'flags' => [ItemEnchantmentTags::AXE],
        ],

        //mastery
        'iceaspect' => [
            'display' => '{group-color}Ice Aspect',
            'description' => 'Chance to freeze your enemy in place for up to 3 seconds.',
            'group' => 'MASTERY',
            'maxLevel' => 5,
            'flags' => [ItemEnchantmentTags::SWORD],
        ],
        'disarmor' => [
            'display' => '{group-color}Disarmor',
            'description' => 'Chance to remove a piece of armor from enemy into their inventory.',
            'group' => 'MASTERY',
            'maxLevel' => 5,
            'flags' => [ItemEnchantmentTags::SWORD],
        ],
        'brotherhood' => [
            'display' => '{group-color}Brotherhood',
            'description' => "When your Disarmor procs, this has a chance\nto empower nearby allies (3x3) who also\nhave this enchant so their next Disarmor\nproc is guaranteed.",
            'group' => 'MASTERY',
            'maxLevel' => 5,
            'flags' => [ItemEnchantmentTags::SWORD],
        ],
        'vampiricdevour' => [
            'display' => '{group-color}Vampiric Devour',
            'description' => 'Small chance to regain all health while attacking.',
            'group' => 'MASTERY',
            'maxLevel' => 1,
            'flags' => [ItemEnchantmentTags::SWORD],
        ],
        'demonicfinisher' => [
            'display' => '{group-color}Demonic Finisher',
            'description' => 'Chance to deal up to 5 hearts of damage when your opponent is below 4 hearts.',
            'group' => 'MASTERY',
            'maxLevel' => 5,
            'flags' => [ItemEnchantmentTags::AXE],
        ],
        'mirrored' => [
            'display' => '{group-color}Mirrored',
            'description' => 'Chance to reflect damage that would be onto you back to your attacker.',
            'group' => 'MASTERY',
            'maxLevel' => 3,
            'flags' => [ItemEnchantmentTags::CHESTPLATE],
        ],
        'ascended' => [
            'display' => '{group-color}Ascended',
            'description' => 'Chance to shoot you up in the air when at low hp.',
            'group' => 'MASTERY',
            'maxLevel' => 5,
            'flags' => [ItemEnchantmentTags::BOOTS],
        ],
        'redeemer' => [
            'display' => '{group-color}Redeemer',
            'description' => 'Chance to buff your damage up to x3 for up to 3 seconds.',
            'group' => 'MASTERY',
            'maxLevel' => 3,
            'flags' => [ItemEnchantmentTags::LEGGINGS],
        ],
        'satanstreat' => [
            'display' => '{group-color}Satan\'s Treat',
            'description' => 'Chance to drop crate keys.',
            'group' => 'MASTERY',
            'maxLevel' => 5,
            'flags' => [ItemEnchantmentTags::PICKAXE],
        ],
        'watchmen' => [
            'display' => '{group-color}Watchmen',
            'description' => 'Chance to summon Iron Golems that defend you by attacking your attacker.',
            'group' => 'MASTERY',
            'maxLevel' => 5,
            'flags' => [ItemEnchantmentTags::CHESTPLATE],
        ],
        'pulse' => [
            'display' => '{group-color}Pulse',
            'description' => 'Chance to knock enemies away when you are hit.',
            'group' => 'UNIQUE',
            'maxLevel' => 5,
            'flags' => [ItemEnchantmentTags::CHESTPLATE],
        ],
        'pellet' => [
            'display' => '{group-color}Pellet',
            'description' => 'Chance to blast enemies back on bow hit.',
            'group' => 'EXOTIC',
            'maxLevel' => 2,
            'flags' => [ItemEnchantmentTags::BOW],
        ],
        'phantom' => [
            'display' => '{group-color}Phantom',
            'description' => 'Chance to deal double damage at night.',
            'group' => 'ELITE',
            'maxLevel' => 5,
            'flags' => [ItemEnchantmentTags::AXE],
        ],
        'mecha' => [
            'display' => '{group-color}Mecha',
            'description' => 'Auto sells your inventory when it is full while mining.',
            'group' => 'SIMPLE',
            'maxLevel' => 1,
            'flags' => [ItemEnchantmentTags::PICKAXE],
        ],
        'flourish' => [
            'display' => '{group-color}Flourish',
            'description' => 'Take less damage when focused by multiple enemies.',
            'group' => 'LEGENDARY',
            'maxLevel' => 1,
            'flags' => [ItemEnchantmentTags::CHESTPLATE],
        ],
        'trickshot' => [
            'display' => '{group-color}Trickshot',
            'description' => 'Headshots deal massive bonus damage.',
            'group' => 'ELITE',
            'maxLevel' => 1,
            'flags' => [ItemEnchantmentTags::BOW],
        ],
        'plague' => [
            'display' => '{group-color}Plague',
            'description' => 'Chance to weaken enemy damage around you when hit.',
            'group' => 'MASTERY',
            'maxLevel' => 5,
            'flags' => [ItemEnchantmentTags::CHESTPLATE],
        ],
        'trixal' => [
            'display' => '{group-color}Trixal',
            'description' => 'Your pickaxe never loses durability.',
            'group' => 'EXOTIC',
            'maxLevel' => 1,
            'flags' => [ItemEnchantmentTags::PICKAXE],
        ],
        'kerbus' => [
            'display' => '{group-color}Kerbus',
            'description' => 'Chance to block disarmor effects on you.',
            'group' => 'MASTERY',
            'maxLevel' => 2,
            'flags' => [ItemEnchantmentTags::CHESTPLATE],
        ],
        'slingshot' => [
            'display' => '{group-color}Slingshot',
            'description' => 'Chance to wipe ender pearls from enemies on bow hit.',
            'group' => 'LEGENDARY',
            'maxLevel' => 5,
            'flags' => [ItemEnchantmentTags::BOW],
        ],
        'jeopardy' => [
            'display' => '{group-color}Jeopardy',
            'description' => 'Chance to disable enemy potion usage.',
            'group' => 'LEGENDARY',
            'maxLevel' => 5,
            'flags' => [ItemEnchantmentTags::AXE],
        ],
        'static' => [
            'display' => '{group-color}Static',
            'description' => 'Chance to increase enemy golden apple cooldown when hit.',
            'group' => 'LEGENDARY',
            'maxLevel' => 3,
            'flags' => [ItemEnchantmentTags::LEGGINGS],
        ],
        'hexlock' => [
            'display' => '{group-color}Hexlock',
            'description' => 'Chance to disable enemy pets and boost your enchant proc chance.',
            'group' => 'MASTERY',
            'maxLevel' => 3,
            'flags' => [ItemEnchantmentTags::SWORD, ItemEnchantmentTags::AXE],
        ],
        'siegebreaker' => [
            'display' => '{group-color}Siegebreaker',
            'description' => 'Chance to heavily damage armor and empower your bow briefly.',
            'group' => 'MASTERY',
            'maxLevel' => 1,
            'flags' => [ItemEnchantmentTags::BOW],
        ],
        'thunderfamine' => [
            'display' => '{group-color}Thunderfamine',
            'description' => 'Chance to call lightning bursts and drain enemy hunger.',
            'group' => 'MASTERY',
            'maxLevel' => 2,
            'flags' => [ItemEnchantmentTags::AXE],
        ],
        'bulwark' => [
            'display' => '{group-color}Bulwark',
            'description' => 'Chance to negate damage and repair armor briefly.',
            'group' => 'MASTERY',
            'maxLevel' => 2,
            'flags' => [ItemEnchantmentTags::BOOTS],
        ],
        'blackhand' => [
            'display' => '{group-color}Blackhand',
            'description' => 'Chance to pull black market loot and trigger x5 mob drops.',
            'group' => 'MASTERY',
            'maxLevel' => 5,
            'flags' => [ItemEnchantmentTags::SWORD],
        ],

        'triarchsigil' => [
            'display' => '{group-color}Triarch Sigil',
            'description' => "Take 5% less damage per level from players wearing\nDreadviel, Stormcaller, or Frostbound (25% at max).\nAt max level you also deal 10% less damage.",
            'group' => 'MASTERY',
            'maxLevel' => 4,
            'flags' => [ItemEnchantmentTags::HELMET],
        ],
        'lifetide' => [
            'display' => '{group-color}Lifetide',
            'description' => "The higher your health, the less damage you take;\nthe lower your health, the more you take.",
            'group' => 'LEGENDARY',
            'maxLevel' => 6,
            'flags' => [ItemEnchantmentTags::HELMET],
        ],
        'wrathbreak' => [
            'display' => '{group-color}Wrathbreak',
            'description' => "Each level adds 1% chance to reset an attacker's\nRage stack to 0 when they hit you.",
            'group' => 'MASTERY',
            'maxLevel' => 10,
            'flags' => [ItemEnchantmentTags::CHESTPLATE],
        ],
        'mirrorbrand' => [
            'display' => '{group-color}Glassbrand',
            'description' => "Each level adds 1% chance to deflect offensive\nweapon enchantments back at the attacker.",
            'group' => 'MASTERY',
            'maxLevel' => 4,
            'flags' => [ItemEnchantmentTags::CHESTPLATE],
        ],
        'nullglimmer' => [
            'display' => '{group-color}Nullglimmer',
            'description' => "On proc, silences the attacker's custom enchants\nfor 2s per level (they must re-apply buffs like Overload).",
            'group' => 'MASTERY',
            'maxLevel' => 3,
            'flags' => [ItemEnchantmentTags::CHESTPLATE],
        ],
        'premonition' => [
            'display' => '{group-color}Premonition',
            'description' => "The next hit from your opponent deals half damage.",
            'group' => 'LEGENDARY',
            'maxLevel' => 3,
            'flags' => [ItemEnchantmentTags::CHESTPLATE],
        ],
        'hexproof' => [
            'display' => '{group-color}Hexproof',
            'description' => "On proc, become immune to harmful custom enchants\nfor 5s per level (up to 25s at level 5).",
            'group' => 'EXOTIC',
            'maxLevel' => 5,
            'flags' => [ItemEnchantmentTags::CHESTPLATE],
        ],
        'shellshock' => [
            'display' => '{group-color}Shellshock',
            'description' => "Grants immunity to explosive damage (TNT, creepers, etc.).",
            'group' => 'ELITE',
            'maxLevel' => 2,
            'flags' => [ItemEnchantmentTags::CHESTPLATE],
        ],
        'rotbrand' => [
            'display' => '{group-color}Rotbrand',
            'description' => "On proc, your attacker suffers bleeding tick damage\nsimilar to the Bleed enchant.",
            'group' => 'UNIQUE',
            'maxLevel' => 5,
            'flags' => [ItemEnchantmentTags::CHESTPLATE],
        ],
        'finalverdict' => [
            'display' => '{group-color}Final Verdict',
            'description' => "When the attacker is below 5 hearts, disable their\narmor enchants for 7s. If you are below 5 hearts on proc,\nnegate all incoming damage for 2s instead.",
            'group' => 'MASTERY',
            'maxLevel' => 1,
            'flags' => [ItemEnchantmentTags::LEGGINGS],
        ],
        'choirglass' => [
            'display' => '{group-color}Choirglass',
            'description' => "Each level adds 1% to randomly trigger an enchant\nfrom your armor. If it rolls a Mastery tier, you instantly\nheal 4 extra hearts.",
            'group' => 'MASTERY',
            'maxLevel' => 3,
            'flags' => [ItemEnchantmentTags::LEGGINGS],
        ],
        'warband' => [
            'display' => '{group-color}Warband',
            'description' => "The more unique players hitting you, the faster\nyour natural health regeneration (scales with attackers).",
            'group' => 'EXOTIC',
            'maxLevel' => 2,
            'flags' => [ItemEnchantmentTags::LEGGINGS],
        ],
        'tempestomen' => [
            'display' => '{group-color}Tempest Omen',
            'description' => "Summons a charged creeper that detonates after 2s,\nshredding enemy armor durability and blocking Gapples\nuntil they leave combat.",
            'group' => 'EXOTIC',
            'maxLevel' => 2,
            'flags' => [ItemEnchantmentTags::LEGGINGS],
        ],
        'midastoll' => [
            'display' => '{group-color}Midas Toll',
            'description' => "Immune to Gapple sickness, but your Gapple\ncooldown is +18s; minus 1s per level (min 15s at L8).",
            'group' => 'UNIQUE',
            'maxLevel' => 8,
            'flags' => [ItemEnchantmentTags::LEGGINGS],
        ],
        'hellforged' => [
            'display' => '{group-color}Hellforged',
            'description' => "Summons a powerful zombie pigman defender.\nPlayers within 5x5 cannot throw pearls until it dies.",
            'group' => 'MASTERY',
            'maxLevel' => 2,
            'flags' => [ItemEnchantmentTags::BOOTS],
        ],
        'soulshackle' => [
            'display' => '{group-color}Soulshackle',
            'description' => "When Disarmor procs on a foe, they cannot re-equip\nthat armor piece for 3s per level.",
            'group' => 'MASTERY',
            'maxLevel' => 2,
            'flags' => [ItemEnchantmentTags::BOOTS],
        ],
        'oathbound' => [
            'display' => '{group-color}Oathbound',
            'description' => "The more enchants the attacker's weapon has, the\nless damage you and allies in a 3x3 take.",
            'group' => 'LEGENDARY',
            'maxLevel' => 5,
            'flags' => [ItemEnchantmentTags::BOOTS],
        ],
        'warlordstempo' => [
            'display' => "{group-color}Warlord's Tempo",
            'description' => "Captures KOTH 2% faster than normal while worn.",
            'group' => 'LEGENDARY',
            'maxLevel' => 1,
            'flags' => [ItemEnchantmentTags::BOOTS],
        ],
        'sunveil' => [
            'display' => '{group-color}Sunveil',
            'description' => "When struck, passively heal allies and yourself in a 9x9.\nHealing is doubled at night.",
            'group' => 'EXOTIC',
            'maxLevel' => 3,
            'flags' => [ItemEnchantmentTags::BOOTS],
        ],
        'bloodrush' => [
            'display' => '{group-color}Bloodrush',
            'description' => "Rage no longer falls off when you are hit. Stacks cap\nat x10 and only reset if you miss an attack entirely.",
            'group' => 'MASTERY',
            'maxLevel' => 1,
            'flags' => [ItemEnchantmentTags::SWORD],
        ],
        'beastbond' => [
            'display' => '{group-color}Beastbond',
            'description' => "On proc, for 10s your next pet use triggers twice, or if\nunused, for 15s you reflect the enemy's pet procs back.",
            'group' => 'MASTERY',
            'maxLevel' => 4,
            'flags' => [ItemEnchantmentTags::SWORD],
        ],
        'runevein' => [
            'display' => '{group-color}Runevein',
            'description' => "Adds +2% proc chance to all axe enchants. After proc,\naxe soul costs are halved for 30 seconds.",
            'group' => 'MASTERY',
            'maxLevel' => 2,
            'flags' => [ItemEnchantmentTags::AXE],
        ],
        'severance' => [
            'display' => '{group-color}Severance',
            'description' => "If the victim has no linked Discord, force Double\nStrike and freeze. Linked accounts only get a short freeze.",
            'group' => 'MASTERY',
            'maxLevel' => 1,
            'flags' => [ItemEnchantmentTags::AXE],
        ],
        'alembic' => [
            'display' => "{group-color}Alembic",
            'description' => "For every 6 potions the enemy carries, gain +0.75 damage.\nIf they have none, you deal 25% less damage.",
            'group' => 'LEGENDARY',
            'maxLevel' => 4,
            'flags' => [ItemEnchantmentTags::AXE],
        ],
        'marauder' => [
            'display' => '{group-color}Marauder',
            'description' => "On proc, randomly remove 1 ender pearl, Gapple, or\nheal potion from the victim's inventory.",
            'group' => 'ELITE',
            'maxLevel' => 7,
            'flags' => [ItemEnchantmentTags::AXE],
        ],
        'thirdeye' => [
            'display' => '{group-color}Third Eye',
            'description' => "Every third hit can deal double damage and double\narmor durability loss.",
            'group' => 'UNIQUE',
            'maxLevel' => 3,
            'flags' => [ItemEnchantmentTags::AXE],
        ],
        'lockjaw' => [
            'display' => '{group-color}Lockjaw',
            'description' => "On proc, the victim cannot use commands, eat, or\ngapple for 3 seconds.",
            'group' => 'SIMPLE',
            'maxLevel' => 1,
            'flags' => [ItemEnchantmentTags::AXE],
        ],
    ];

    public static array $rarities = [];

    /** @var CustomEnchantment[] */
    public static array $enchants = [];

    public static function setup(): void {
        self::applyConfigDefinitionOverrides();

        SimplePacketHandler::createInterceptor(Loader::getInstance(), EventPriority::HIGH)
            ->interceptOutgoing(function (InventoryContentPacket $pk, NetworkSession $destination): bool {
                foreach ($pk->items as $i => $item) {
                    $pk->items[$i] = new ItemStackWrapper($item->getStackId(), self::display($item->getItemStack()));
                }
                return true;
            })
            ->interceptOutgoing(function (InventorySlotPacket $pk, NetworkSession $destination): bool {
                $pk->item = new ItemStackWrapper($pk->item->getStackId(), self::display($pk->item->getItemStack()));
                return true;
            })
            ->interceptOutgoing(function (InventoryTransactionPacket $pk, NetworkSession $destination): bool {
                $transaction = $pk->trData;
    
                foreach ($transaction->getActions() as $action) {
                    $action->oldItem = new ItemStackWrapper($action->oldItem->getStackId(), self::filter($action->oldItem->getItemStack()));
                    $action->newItem = new ItemStackWrapper($action->newItem->getStackId(), self::filter($action->newItem->getItemStack()));
                }
                return true;
            });
    
        EnchantmentIdMap::getInstance()->register(
            GlaciaUtils::FAKE_ENCH_ID,
            new Enchantment("", -1, 1, ItemFlags::ALL, ItemFlags::NONE)
        );

        foreach (self::$definitions as $name => $def) {
            $rarityId = Groups::getGroupId($def['group']);
            $ench = new CustomEnchantment(
                $name,
                $rarityId,
                $def['description'],
                $def['maxLevel'],
                $def['flags']
            );
            self::register($name, $ench); 
            self::$enchants[strtoupper($name)] = $ench;
        }
    }
    
    protected static function register(string $name, CustomEnchantment $enchantment): void {
        $key = strtoupper($name);
        
        self::$enchants[$key] = $enchantment;
        
        self::$rarities[$enchantment->getRarity()][] = $key;
        self::_registryRegister($name, $enchantment);
    }
    
    public static function getIdFromName(string $name) : ?int {
        return self::$ids[$name] ?? null;
    }

    public static function getAll() : array{
        /**
         * @var CustomEnchantment[] $result
         * @phpstan-var array<string, CustomEnchantment> $result
         */
        $result = self::_registryGetAll();
        return $result;
    }

    /**
     * Removes trailing transmog enchant-count brackets (possibly repeated) so display sync applies exactly one suffix.
     */
    public static function stripTransmogEnchantCountSuffixFromName(string $name): string
    {
        $colorCode = '(?:\x{00A7}|&)[0-9a-fk-orA-FK-OR]';
        $pattern = '/(?:\s*(?:' . $colorCode . ')*\[(?:' . $colorCode . ')*\d+(?:' . $colorCode . ')*\]\s*)+$/u';
        $name = preg_replace($pattern, '', $name) ?? $name;
        $name = preg_replace('/(?:\s*\[\d+\]\s*)+$/', '', $name) ?? $name;

        return rtrim($name);
    }

    public static function display(ItemStack $itemStack): ItemStack {
        try {
        $item = TypeConverter::getInstance()->netItemStackToCore($itemStack);
        $root = $item->getNamedTag();
        $martianCES = $root->getCompoundTag("GlaciaCES");
        $glaciaTag = $root->getCompoundTag("Glacia");
        $hasTransmog = $glaciaTag?->getByte("transmog", 0) === 1;

        $customEnchantLore = [];
        $customEnchantEntries = [];

        if ($martianCES !== null) {
            foreach ($martianCES->getValue() as $enchantName => $levelTag) {
                $key = strtoupper((string)$enchantName);
                if (!isset(self::getAll()[$key])) {
                    continue;
                }

                $enchantment = self::getEnchantmentByName((string)$enchantName);
                if (!$enchantment instanceof CustomEnchantment) {
                    continue;
                }

                $level = $levelTag->getValue();
                $groupId = $enchantment->getRarity();
                $color = Groups::translateGroupToColor($groupId);
                $displayName = self::getEnchantmentDisplayName($enchantment->getName(), $color);
                $customEnchantEntries[] = [
                    "rarity" => $groupId,
                    "display" => TextFormat::RESET . $displayName . " " . GeneralUtils::getRomanNumeral($level)
                ];
            }
        }

        if ($hasTransmog) {
            usort($customEnchantEntries, static fn(array $a, array $b): int => $b["rarity"] <=> $a["rarity"]);
        }

        foreach ($customEnchantEntries as $entry) {
            $customEnchantLore[] = $entry["display"];
        }

        self::preserveOriginalDisplayTag($item);

        $existingLore = $item->getLore();
        $existingLore = self::stripInjectedCustomEnchantLore($existingLore);

        if (!empty($customEnchantLore)) {
            $item->setLore(array_merge($customEnchantLore, $existingLore));
        } else {
            $item->setLore($existingLore);
        }

        if ($hasTransmog) {
            $baseName = $item->getCustomName() !== "" ? $item->getCustomName() : $item->getName();
            $baseName = self::stripTransmogEnchantCountSuffixFromName($baseName);
            $enchantTagCount = $martianCES !== null ? count($martianCES->getValue()) : 0;
            $item->setCustomName($baseName . TextFormat::colorize(" &r&l&b[&f" . $enchantTagCount . "&b]"));
        }

        ItemLoreLayout::normalizeForCombatGear($item);
        GlaciaUtils::updateGlowEffect($item);

            return TypeConverter::getInstance()->coreItemStackToNet($item);
        } catch (\Throwable) {
            return $itemStack;
        }
    }

    private static function stripInjectedCustomEnchantLore(array $lore): array {
        $result = [];

        foreach ($lore as $line) {
            $cleanLine = trim(TextFormat::clean($line));
            $remove = false;

            foreach (self::getAll() as $enchantment) {
                if (!$enchantment instanceof CustomEnchantment) {
                    continue;
                }

                $cleanEnchantName = trim(TextFormat::clean($enchantment->getName()));

                if ($cleanEnchantName !== '' && preg_match('/^' . preg_quote($cleanEnchantName, '/') . '\s+[IVXLCDM0-9]+$/', $cleanLine)) {
                    $remove = true;
                    break;
                }
            }

            if (!$remove) {
                $result[] = $line;
            }
        }

        return $result;
    }

    /**
     * Retrieves the enchantment display name from the configuration.
     * 
     * @param string $enchantmentName
     * @param string $color
     * @return string
     */
    public static function getEnchantmentDisplayName(string $enchantmentName, string $color): string {
        $definitions = array_change_key_case(self::$definitions, CASE_LOWER);
        $key = strtolower($enchantmentName);
        
        if (isset($definitions[$key])) {
            $displayName = $definitions[$key]['display'];
            return str_contains($displayName, '{group-color}')
                ? str_replace('{group-color}', $color, $displayName)
                : $color . $displayName;
        }

    return $color . $enchantmentName;
    }

    /**
     * Saves the original display tag to prevent overwriting vanilla item properties.
     * 
     * @param Item $item
     * @return void
     */
    private static function preserveOriginalDisplayTag(Item $item): void {
        $namedTag = $item->getNamedTag();
        
        if ($namedTag->getTag(Item::TAG_DISPLAY)) {
            $namedTag->setTag("OriginalDisplayTag", $namedTag->getTag(Item::TAG_DISPLAY)->safeClone());
        }
    }

    public static function filter(ItemStack $itemStack): ItemStack {
        try {
            $item = TypeConverter::getInstance()->netItemStackToCore($itemStack);
            $tag = $item->getNamedTag();
            if (count($item->getEnchantments()) > 0) $tag->removeTag(Item::TAG_DISPLAY);

            if ($tag->getTag("OriginalDisplayTag") instanceof CompoundTag) {
                $tag->setTag(Item::TAG_DISPLAY, $tag->getTag("OriginalDisplayTag"));
                $tag->removeTag("OriginalDisplayTag");
            }
            $item->setNamedTag($tag);
            return TypeConverter::getInstance()->coreItemStackToNet($item);
        } catch (\Throwable) {
            return $itemStack;
        }
    }

    /**
     * @param EnchantmentInstance[] $enchantments
     * @return EnchantmentInstance[]
     */
    public static function sortEnchantmentsByRarity(array $enchantments): array
    {
        usort($enchantments, function (EnchantmentInstance $enchantmentInstance, EnchantmentInstance $enchantmentInstanceB) {
            $type = $enchantmentInstance->getType();
            $typeB = $enchantmentInstanceB->getType();
    
            $rarityA = ($type instanceof CustomEnchantment) ? $type->getRarity() : 0; 
            $rarityB = ($typeB instanceof CustomEnchantment) ? $typeB->getRarity() : 0; 
    
            return $rarityB - $rarityA; 
        });
    
        return $enchantments;
    }

    protected static function getMaxLevel(array $enchantmentData): int {
        if (!isset($enchantmentData['levels']) || empty($enchantmentData['levels'])) {
            throw new \InvalidArgumentException("Enchantment '" . $enchantmentData['display'] . "' does not define any levels.");
        }
    
        $levels = $enchantmentData['levels'];
        $maxLevel = max(array_keys($levels));
        return $maxLevel;
    }

    protected static function parseFlags(string $appliesTo): int {
        switch (strtolower($appliesTo)) {
            case 'Pickaxe':
                return ItemFlags::PICKAXE;
            case 'Sword':
                return ItemFlags::SWORD;
            case 'Chestplate':
                return ItemFlags::TORSO;
            case 'Leggings':
                return ItemFlags::LEGS;
            case 'Boots':
                return ItemFlags::FEET;
            case 'All':
                return ItemFlags::ALL;
            case 'Armor':
                return ItemFlags::ARMOR;
            case 'Axe':
                return ItemFlags::AXE;
            case 'Hoe':
                return ItemFlags::HOE;
            case 'Shovel':
                return ItemFlags::SHOVEL;
            case 'Shears':
                return ItemFlags::SHEARS;
            case 'Bow':
                return ItemFlags::BOW;
            case 'Trident':
                return ItemFlags::TRIDENT;
            default:
                return ItemFlags::NONE;
        }
    }

    /** @var array<string, CustomEnchantment>|null */
    private static ?array $displayNameIndex = null;

    /**
     * Normalizes any user-typed enchantment string (display name, internal id, legacy id,
     * with or without spaces / Minecraft color codes / underscores) into a stable lookup key.
     */
    private static function normalizeEnchantKey(string $value): string {
        $stripped = preg_replace('/(?:§|&)[0-9a-fk-orA-FK-OR]/', '', $value) ?? $value;
        $alphaNum = preg_replace('/[^A-Za-z0-9]+/', '', $stripped) ?? '';
        return strtoupper($alphaNum);
    }

    /**
     * Builds (lazily) a normalized display-name -> enchantment index so users can use
     * the cosmetic name shown by /ge list (e.g. "Final Verdict") as a lookup key.
     *
     * @return array<string, CustomEnchantment>
     */
    private static function getDisplayNameIndex(): array {
        if (self::$displayNameIndex !== null) {
            return self::$displayNameIndex;
        }

        $index = [];
        foreach (self::$definitions as $id => $def) {
            $upperId = strtoupper((string) $id);
            $enchant = self::$enchants[$upperId] ?? null;
            if (!$enchant instanceof CustomEnchantment) {
                continue;
            }

            $display = (string) ($def['display'] ?? '');
            $display = str_replace('{group-color}', '', $display);
            $key = self::normalizeEnchantKey($display);
            if ($key !== '' && !isset($index[$key])) {
                $index[$key] = $enchant;
            }
        }

        self::$displayNameIndex = $index;
        return $index;
    }

    private static function applyConfigDefinitionOverrides(): void
    {
        foreach (self::$definitions as $id => $definition) {
            self::$definitions[$id] = self::applyConfigDefinitionOverride($id, $definition);
        }
        self::$displayNameIndex = null;
    }

    /**
     * @param array<string, mixed> $definition
     * @return array<string, mixed>
     */
    private static function applyConfigDefinitionOverride(string $id, array $definition): array
    {
        $display = EnchantTuningManager::getString($id, "display", "");
        if ($display !== "") {
            $definition["display"] = $display;
        }

        $descriptionLines = EnchantTuningManager::getStringList($id, "description");
        if ($descriptionLines !== []) {
            $definition["description"] = implode("\n", $descriptionLines);
        } else {
            $description = EnchantTuningManager::getString($id, "description", "");
            if ($description !== "") {
                $definition["description"] = $description;
            }
        }

        $group = strtoupper(EnchantTuningManager::getString($id, "group", ""));
        if ($group !== "" && Groups::getGroupId($group) !== null) {
            $definition["group"] = $group;
        }

        $configuredMax = (int) EnchantTuningManager::getValue($id, "max-level", 0);
        if ($configuredMax <= 0) {
            $configuredMax = (int) EnchantTuningManager::getValue($id, "maxLevel", 0);
        }
        if ($configuredMax > 0) {
            $definition["maxLevel"] = $configuredMax;
        } else {
            $levels = EnchantTuningManager::getValue($id, "levels", []);
            if (is_array($levels) && $levels !== []) {
                $definition["maxLevel"] = max(array_map("intval", array_keys($levels)));
            }
        }

        $applies = EnchantTuningManager::getValue($id, "applies", null);
        if (is_array($applies) && $applies !== []) {
            $tags = self::mapApplicableConfigToTags($applies);
            if ($tags !== []) {
                $definition["flags"] = $tags;
            }
        } else {
            $appliesTo = EnchantTuningManager::getStringList($id, "applies-to");
            if ($appliesTo === []) {
                $single = EnchantTuningManager::getString($id, "applies-to", "");
                if ($single !== "") {
                    $appliesTo = [$single];
                }
            }
            if ($appliesTo !== []) {
                $tags = self::mapApplicableConfigToTags($appliesTo);
                if ($tags !== []) {
                    $definition["flags"] = $tags;
                }
            }
        }

        return $definition;
    }

    /**
     * @param array<int|string, mixed> $applies
     * @return list<string>
     */
    private static function mapApplicableConfigToTags(array $applies): array
    {
        $tags = [];
        foreach ($applies as $raw) {
            $value = strtoupper(trim((string) $raw));
            $resolved = match ($value) {
                "ALL", "ANY" => [ItemEnchantmentTags::ALL],
                "ALL_SWORD", "SWORD", "SWORDS" => [ItemEnchantmentTags::SWORD],
                "ALL_AXE", "AXE", "AXES" => [ItemEnchantmentTags::AXE],
                "ALL_PICKAXE", "PICKAXE", "PICKAXES" => [ItemEnchantmentTags::PICKAXE],
                "ALL_SHOVEL", "ALL_SPADE", "SHOVEL", "SPADE" => [ItemEnchantmentTags::SHOVEL],
                "ALL_HOE", "HOE", "HOES" => [ItemEnchantmentTags::HOE],
                "BOW", "BOWS" => [ItemEnchantmentTags::BOW],
                "TRIDENT", "TRIDENTS" => [ItemEnchantmentTags::TRIDENT],
                "ALL_ARMOR", "ARMOR" => [ItemEnchantmentTags::HELMET, ItemEnchantmentTags::CHESTPLATE, ItemEnchantmentTags::LEGGINGS, ItemEnchantmentTags::BOOTS],
                "ALL_HELMET", "HELMET", "HELMETS" => [ItemEnchantmentTags::HELMET],
                "ALL_CHESTPLATE", "CHESTPLATE", "CHESTPLATES" => [ItemEnchantmentTags::CHESTPLATE],
                "ALL_LEGGINGS", "LEGGINGS" => [ItemEnchantmentTags::LEGGINGS],
                "ALL_BOOTS", "BOOTS" => [ItemEnchantmentTags::BOOTS],
                default => [],
            };
            foreach ($resolved as $tag) {
                $tags[$tag] = $tag;
            }
        }

        return array_values($tags);
    }

    /**
     * Resolve any flavour of enchantment input (internal id, legacy id, or display name
     * shown by /ge list — including multi-word and color-coded variants) to a CustomEnchantment.
     */
    public static function getEnchantmentByName(string $name): ?CustomEnchantment {
        $normalized = self::normalizeEnchantKey($name);
        if ($normalized === '') {
            return null;
        }

        if (isset(self::$enchants[$normalized])) {
            return self::$enchants[$normalized];
        }

        $mapped = self::LEGACY_ID_MAP[$normalized] ?? null;
        if ($mapped !== null && isset(self::$enchants[$mapped])) {
            return self::$enchants[$mapped];
        }

        return self::getDisplayNameIndex()[$normalized] ?? null;
    }

    public static function getLegacyIdForName(string $name): ?string
    {
        $upper = self::normalizeEnchantKey($name);
        foreach (self::LEGACY_ID_MAP as $legacy => $current) {
            if ($current === $upper) {
                return strtolower($legacy);
            }
        }
        return null;
    }
    
    /**
     * @param int $rarity
     * @return CustomEnchantment[]
     */
    public static function getByRarity(int $rarity): array {
        $result = [];

        $names = self::$rarities[$rarity] ?? [];
        foreach ($names as $upperName) {
            if (isset(self::$enchants[$upperName])) {
                $result[] = self::$enchants[$upperName];
            }
        }
        return $result;
    }

    /**
     * @param string $group
     * @return CustomEnchantment[]
     */
    public static function getByGroup(string $group): array {
        $id = Groups::getGroupId($group);
        if ($id === null) {
            return [];
        }
        return self::getByRarity($id);
    }
}
