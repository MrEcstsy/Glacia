<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\items;

use pocketmine\block\VanillaBlocks;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;

final class GlaciaItems {

    public static function init(): void {
        GlaciaItemFactory::$definitions = [];

        self::initNotes();
        self::initEnchantItems();
        self::initBossItems();
        self::initChests();
        self::initSpawnerItems();
        self::initDustItems();
        self::initExplosives();
        self::initHomesAndMisc();
        self::initCustomArmor(); 
    }

    private static function initNotes(): void {
        GlaciaItemFactory::$definitions += [
            'bank_note' => [
                'material' => VanillaItems::PAPER(),
                'name' => "&r&l&bBank Note &r&7(Right Click)",
                'lore' => [
                    '&r&8▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬',
                    "&r&fValue: &a$" . "{amount}",
                    "&r&fSigner: &b{signer}",
                    "&r&8▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬",
                    "&r&7Redeem this note to receive money.",
                ],
                'nbt' => ['glaciaItem' => 'econote', 'worth' => '{rawAmount}'],
            ],
            'xpnote' => [
                'material' => VanillaItems::EXPERIENCE_BOTTLE(),
                'name' => "&r&l&aExperience Bottle &r&7(Right Click)",
                'lore' => [
                    '&r&8▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬',
                    '&r&fExperience: &a{amount} EXP',
                    '&r&fSigner: &a{signer}',
                    "&r&8▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬",
                    "&r&7Redeem this note to receive exp.",
                ],
                'nbt' => ['glaciaItem' => 'xpnote', 'worth' => '{rawAmount}'],
            ],
            'bloody_note' => [
                'material' => VanillaItems::PAPER(),
                'name' => '&r&l&cBloody Note &r&7(Right Click)',
                'lore' => [
                    "&r&7Uncover this Bloody Note to gain either",
                    "&r&7a small fortune or nothing at all.",
                    "",
                    "&r&l&c* &r&7Note Worth: &r&c&kahwn31&r",
                    "&r&l&c(!) &r&cClick to uncover this Bloody Note"
                ],
                'nbt' => ['glaciaItem' => 'bloodynote'],
            ],
        ];
    }

    private static function initEnchantItems(): void {
        GlaciaItemFactory::$definitions += [
            "enchantment_book" => [
                'material' => VanillaItems::ENCHANTED_BOOK(),
                'name' => '&r{group-color}&l{enchantment} {roman-level}',
                'lore' => [
                    '&r&a{success}% Success Rate',
                    '&r&c{destroy}% Destroy Rate',
                    '&r&e{description}',
                    '&r&7{applies-to} Enchantment',
                    "&r&7Drag n' Drop onto item to enchant.",
                ],
                'nbt' => ['glaciaItem' => 'enchantment_book', 'enchant_book' => '{enchant-no-color}', 'level' => '{level}', 'success' => '{success}', 'destroy' => '{destroy}'],
            ],
            'orb' => [
                'material' => StringToItemParser::getInstance()->parse("ender_eye"),
                'name' => '&r&6&l{type} Enchantment Orb &6[&a{max}&6]',
                'lore' => [
                    "&r&a{success}% Success Rate",
                    "",
                    "&r&6+{new} Enchantment slots",
                    "&r&6{max} Max Enchantment Slots",
                    "",
                    "&r&eIncreases the # of enchantment",
                    "&r&eslots on a {type} by {new},",
                    "&r&eup to a maximum of {max}",
                    "&r&7Drag n'' Drop onto an item to apply."
                ],
                'nbt' => ['glaciaItem' => '{low_type}_orb', "max" => "{max}", "new" => "{new}", "success" => "{success}"],
            ],
            'whitescroll' => [
                'material' => StringToItemParser::getInstance()->parse("empty_map"),
                'name' => '&r&fWhite Scroll',
                'lore' => [
                    "&r&fPrevents an item from being destroyed",
                    "&r&fdue to a failed Enchantment Book.",
                    "&r&ePlace scroll on item to apply."
                ],
                'nbt' => ["glaciaItem" => 'whitescroll'],
            ],
            'blackscroll' => [
                'material' => VanillaItems::INK_SAC(),
                'name' => '&r&f&lBlack Scroll',
                'lore' => [
                    "&r&7Removes a random enchantment",
                    "&r&7from an item and converts",
                    "&r&7it into a &f{success}% &r&7success book.",
                    "&r&fPlace scroll onto item to extract."
                ],
                'nbt' => ["glaciaItem" => 'blackscroll', "success" => "{success}"],
            ],
            'renametag' => [
                'material' => VanillaItems::NAME_TAG(),
                'name' => '&r&6&lItem Rename Tag &r&7(Right Click)',
                'lore' => [
                    "&r&7Rename and customize your equipment"
                ],
                'nbt' => ['glaciaItem' => 'renametag'],
            ],
            'random_book' => [
                'material' => VanillaItems::BOOK(),
                'name' => '&r{group-color}{group-name}&l Enchantment Book &r&7(Right Click)',
                'lore' => [
                    "&r&7Right Click while holding to receive a random",
                    "&r{group-color}{group-name} &r&7enchantment book.",
                ],
                'nbt' => ['glaciaItem' => 'random_book', 'group' => '{group}'],
            ],
        ];
    }

    private static function initBossItems(): void {
        GlaciaItemFactory::$definitions += [
            "boss_astral_devourer" => [
                'material' => VanillaItems::NETHER_STAR(),
                'name' => '&r&l&5Astral Devourer',
                'lore' => [
                    '&r&7&oA being born from vollapsed stars',
                    "&r&7&oIt feeds on energy, time, and souls.",
                    '',
                    '&r&l&5DIFFICULTY',
                    '&r&5» &fLEGENDARY'
                ],
                'nbt' => ["glaciaItem" => 'bossSpawn', "boss" => 'astral_devourer']
            ],
            "rift_summoner" => [
                'material' => VanillaItems::PHANTOM_MEMBRANE(),
                'name' => "&r&l&f➥ Rift Summoner &r&7(Right Click)",
                'lore' => [
                    '&r&8▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬',
                    "&r&7Use this item to summon a &bRift &7at a random location.",
                    "&r&8▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬▬",
                ],
                'nbt' => ['glaciaItem' => 'rift_summoner'],
            ],
        ];
    }

    private static function initChests(): void {
        GlaciaItemFactory::$definitions += [
            "ethereal_simple_chest" => [
                'material' => VanillaBlocks::TRAPPED_CHEST()->asItem(),
                'name' => '&r&l&fSimple Ethereal Chest &r&7(Right Click)',
                'lore' => [
                    "&r&7A cache of equipment prepared by",
                    "&r&7the Ethereal Intergalactic Station.",
                ],
                'nbt' => ["glaciaItem" => 'ethereal_simple_chest'],
            ],
            "ethereal_unique_chest" => [
                'material' => VanillaBlocks::TRAPPED_CHEST()->asItem(),
                'name' => '&r&l&aUnique Ethereal Chest &r&7(Right Click)',
                'lore' => [
                    "&r&7A cache of equipment prepared by",
                    "&r&7the Ethereal Intergalactic Station.",
                ],
                'nbt' => ["glaciaItem" => 'ethereal_unique_chest'],
            ],
            "ethereal_elite_chest" => [
                'material' => VanillaBlocks::TRAPPED_CHEST()->asItem(),
                'name' => '&r&l&bElite Ethereal Chest &r&7(Right Click)',
                'lore' => [
                    "&r&7A cache of equipment prepared by",
                    "&r&7the Ethereal Intergalactic Station.",
                ],
                'nbt' => ["glaciaItem" => 'ethereal_elite_chest'],
            ],
            "ethereal_ultimate_chest" => [
                'material' => VanillaBlocks::TRAPPED_CHEST()->asItem(),
                'name' => '&r&l&eUltimate Ethereal Chest &r&7(Right Click)',
                'lore' => [
                    "&r&7A cache of equipment prepared by",
                    "&r&7the Ethereal Intergalactic Station.",
                ],
                'nbt' => ["glaciaItem" => 'ethereal_ultimate_chest'],
            ],
            "ethereal_legendary_chest" => [
                'material' => VanillaBlocks::TRAPPED_CHEST()->asItem(),
                'name' => '&r&l&6Legendary Ethereal Chest &r&7(Right Click)',
                'lore' => [
                    "&r&7A cache of equipment prepared by",
                    "&r&7the Ethereal Intergalactic Station.",
                ],
                'nbt' => ["glaciaItem" => 'ethereal_legendary_chest'],
            ],
            "ethereal_godly_chest" => [
                'material' => VanillaBlocks::TRAPPED_CHEST()->asItem(),
                'name' => '&r&l&cGodly Ethereal Chest &r&7(Right Click)',
                'lore' => [
                    "&r&7A cache of equipment prepared by",
                    "&r&7the Ethereal Intergalactic Station.",
                ],
                'nbt' => ["glaciaItem" => 'ethereal_godly_chest'],
            ],
        ];
    }

    private static function initSpawnerItems(): void {
        GlaciaItemFactory::$definitions += [
            "mysterymobspawner" => [
                'material' => VanillaBlocks::MONSTER_SPAWNER()->asItem(),
                'name' => '&r&l&6Mystery Mob Spawner &r&7(Right Click)',
                'lore' => [
                    '&r&7Right-click in hand to reveal one',
                    '&r&7of the following mob spawner types:',
                    '&r&7',
                    '&r&l&f* &r&ePig Zombie',
                    "&r&l&f* &r&eEnderman",
                    "&r&l&f* &r&eBlaze",
                    "&r&l&f* &r&6Slime",
                    "&r&l&f* &r&6Creeper",
                    "&r&l&f* &r&6Iron Golem",
                    "&r&l&f* &r&cMushroom Cow",
                    "&r&l&f* &r&cMagma Cube",
                    "&r&l&f* &r&cGhast",
                ],
                'nbt' => ["glaciaItem" => 'mysterymobspawner'],
            ],
            "heroic_mysterymobspawner" => [
                'material' => VanillaBlocks::MONSTER_SPAWNER()->asItem(),
                'name' => '&r&4&k:::&r&c&lHeroic &r&4&k:::&r&l&6Mystery Mob Spawner',
                'lore' => [
                    "&r&7Right-click in hand to reveal one",
                    "&r&7of the following mob spawner types:",
                    "",
                    "&r&l&f* &r&6Slime",
                    "&r&l&f* &r&6Creeper",
                    "&r&l&f* &r&6Iron Golem",
                    "&r&l&f* &r&cMushroom Cow",
                    "&r&l&f* &r&cMagma Cube",
                    "&r&l&f* &r&cGhast",
                    "&r&l&f* &r&dSnowman",
                ],
                'nbt' => ["glaciaItem" => 'heroic_mysterymobspawner'],
            ],
        ];
    }

    private static function initDustItems(): void {
        GlaciaItemFactory::$definitions += [
            "random_magicdust" => [
                'material' => VanillaItems::FIRE_CHARGE(),
                'name' => '&r&l&l{group-color}{group-name} Magic Dust &r&7(Right Click)',
                'lore' => [
                    '&r&7Contains: &bMagic, &ePrimal, &7or &fMystery &7dust.',
                    '&r&7An unidentified satchel of dust.',
                ],
                'nbt' => ['glaciaItem' => 'random_magicdust', 'group' => "{group}"],
            ],
            "primal_dust" => [
                'material' => VanillaItems::GLOWSTONE_DUST(),
                'name' => '&r&l{group-color}{group-name} Primal Dust',
                'lore' => [
                    '&r&a&l{dust-percent}%&r &aSuccess',
                    '&r&7Apply to a {group-color}{group-name} &7Enchantment Book.',
                    '&r&7to increase its success by {group-color}{dust-percent}%&7.',
                    '',
                    '&r&7Place dust on enchantment book'
                ],
                'nbt' => ['glaciaItem' => 'primal_dust', 'group' => '{group}', 'dust-percent' => '{dust-percent}', 'group-color' => '{group-color}'],
            ],
            "magicdust" => [
                'material' => VanillaItems::SUGAR(),
                'name' => '&r&l{group-color}{group-name} Dust',
                'lore' => [
                    '&r&a&l{dust-percent}%&r &aSuccess',
                    '&r&7Apply to a {group-color}{group-name} &7Enchantment Book.',
                    '&r&7to increase its success by {group-color}{dust-percent}%&7.',
                    '',
                    '&r&7Place dust on enchantment book'
                ],
                'nbt' => ['glaciaItem' => 'magicdust', 'group' => "{group}", 'dust-percent' => '{dust-percent}', 'group-color' => '{group-color}'],
            ],
            "mysterydust" => [
                'material' => VanillaItems::GUNPOWDER(),
                'name' => "&r&fMystery Dust",
                'lore' => [
                    '&r&7A failed by-product of',
                    '&r&7Magic and Primal dust.'
                ],
                'nbt' => ['glaciaItem' => 'mysterydust'],
            ],
        ];
    }

    private static function initExplosives(): void {
        GlaciaItemFactory::$definitions += [
            "tnt_gigantic" => [
                'material' => VanillaBlocks::TNT()->asItem(),
                'name' => '&r&bGigantic TNT',
                'lore' => [
                    '&r&b&oExplodes in a much larger radius.',
                    '',
                    '&r&7Spawns 2 extra explosions at',
                    '&r&7y+3 and y-3 of TNT, and another',
                    '&r&72 explosions at either x+3, z+3, or',
                    '&r&7x-3, z-3.'
                ],
                'nbt' => ['glaciaItem' => 'tnt_gigantic'],
            ],
            "tnt_lethal" => [
                'material' => VanillaBlocks::TNT()->asItem(),
                'name' => '&r&cLethal TNT',
                'lore' => [
                    '&r&c&oA more dense, dangerous explosion',
                    '',
                    '&r&7Creates 4 extra TNT explosions at',
                    '&r&7same location as the original.'
                ],
                'nbt' => ['glaciaItem' => 'tnt_lethal'],
            ],
            "tnt_lucky" => [
                'material' => VanillaBlocks::TNT()->asItem(),
                'name' => '&r&eLucky TNT',
                'lore' => [
                    '&r&e&oIncreases chance of spawner drops.',
                    '',
                    '&r&7200% the normal chance of a',
                    '&r&7spawner dropping from explosion.'
                ],
                'nbt' => ['glaciaItem' => 'tnt_lucky'],
            ],
            "tnt_tactical" => [
                'material' => VanillaBlocks::TNT()->asItem(),
                'name' => '&r&9Tactical TNT',
                'lore' => [
                    '&r&9&oExplosion only affects mob spawners',
                    '',
                    '&r&7No blocks will be affected by',
                    "&r&7this explosion except for",
                    "&r&7Monster Spawners."
                ],
                'nbt' => ['glaciaItem' => 'tnt_tactical'],
            ],
            "cegg_gigantic" => [
                'material' => StringToItemParser::getInstance()->parse("creeper_spawn_egg"),
                'name' => '&r&bGigantic Creeper',
                'lore' => [
                    '&r&7&oExplodes in a much larger radius.'
                ],
                'nbt' => ['glaciaItem' => 'cegg_gigantic'],
            ],
            "cegg_lethal" => [
                'material' => StringToItemParser::getInstance()->parse("creeper_spawn_egg"),
                'name' => '&r&cLethal Creeper',
                'lore' => [
                    '&r&7&oA more dense, dangerous explosion'
                ],
                'nbt' => ['glaciaItem' => 'cegg_lethal'],
            ],
            'cegg_lucky' => [
                'material' => StringToItemParser::getInstance()->parse("creeper_spawn_egg"),
                'name' => '&r&eLucky Creeper',
                'lore' => [
                    '&r&7200% the normal chance of a',
                    '&r&7spawner dropping from explosion.'
                ],
                'nbt' => ['glaciaItem' => 'cegg_lucky'],
            ],
            'cegg_tactical' => [
                'material' => StringToItemParser::getInstance()->parse("creeper_spawn_egg"),
                'name' => '&r&9Tactical Creeper',
                'lore' => [
                    '&r&7&oExplosion only affects mob spawners'
                ],
                'nbt' => ['glaciaItem' => 'cegg_tactical'],
            ],
        ];
    }

    private static function initHomesAndMisc(): void {
        GlaciaItemFactory::$definitions += [
            "max_home_increase" => [
                'material' => VanillaBlocks::BED()->asItem(),
                'name' => '&r&l&eMax Home Increase &r&7(Right Click)',
                'lore' => [
                    '&r&7Adds +1 &e/home &7slots to your player.',
                ],
                'nbt' => ['glaciaItem' => 'max_home_increase'],
            ]
        ];
    }

    private static function initCustomArmor(): void {
        GlaciaItemFactory::$definitions += [
            "phantom_helmet" => [
                'material' => VanillaItems::DIAMOND_HELMET(),
                'name' => '&r&l&cPhantom Hood',
                'lore' => [
                    '&r&c&oThe fabled hood of the phantom.',
                    '',
                    '&r&l&cPHANTOM SET BONUS',
                    '&r&l&c* &r&cDeal +15% damage to all enemies.',
                    '&r&7&o(Requires all 4 phantom items.)'
                ],
                'nbt' => [
                    'glaciaItem' => 'phantom_helmet',
                    'armorSet' => 'phantom',
                    'armorPiece' => 'helmet',
                    'customArmor' => 1
                ],
            ],
            "phantom_chestplate" => [
                'material' => VanillaItems::DIAMOND_CHESTPLATE(),
                'name' => '&r&l&cPhantom Shroud',
                'lore' => [
                    '&r&c&oThe legendary shroud of the phantom.',
                    '',
                    '&r&l&cPHANTOM SET BONUS',
                    '&r&l&c* &r&cDeal +15% damage to all enemies.',
                    '&r&7&o(Requires all 4 phantom items.)'
                ],
                'nbt' => [
                    'glaciaItem' => 'phantom_chestplate',
                    'armorSet' => 'phantom',
                    'armorPiece' => 'chestplate',
                    'customArmor' => 1
                ],
            ],
            'phantom_leggings' => [
                'material' => VanillaItems::DIAMOND_LEGGINGS(),
                'name' => '&r&l&cPhantom Robeset',
                'lore' => [
                    '&r&c&oThe demonic robe of the phantom.',
                    '',
                    '&r&l&cPHANTOM SET BONUS',
                    '&r&l&c* &r&cDeal +15% damage to all enemies.',
                    '&r&7&o(Requires all 4 phantom items.)'
                ],
                'nbt' => [
                    'glaciaItem' => 'phantom_leggings',
                    'armorSet' => 'phantom',
                    'armorPiece' => 'leggings',
                    'customArmor' => 1
                ],
            ],
            'phantom_boots' => [
                'material' => VanillaItems::DIAMOND_BOOTS(),
                'name' => '&r&l&cPhantom Sandals',
                'lore' => [
                    '&r&c&oThe silent sandals of the phantom.',
                    '',
                    '&r&l&cPHANTOM SET BONUS',
                    '&r&l&c* &r&cDeal +15% damage to all enemies.',
                    '&r&7&o(Requires all 4 phantom items.)'
                ],
                'nbt' => [
                    'glaciaItem' => 'phantom_boots',
                    'armorSet' => 'phantom',
                    'armorPiece' => 'boots',
                    'customArmor' => 1
                ],
            ],
            'phantom_sword' => [
                'material' => VanillaItems::DIAMOND_SWORD(),
                'name' => '&r&l&cPhantom Scythe',
                'lore' => [
                    '&r&c&oAn eerie blade designed to',
                    '&r&c&ocut through the enemies.',
                    '',
                    '&r&l&cPHANTOM WEAPON BONUS',
                    '&r&l&c* &r&cDeal +5% damage to all enemies.',
                    '&r&7&o(Requires all 4 phantom items.)'
                ],
                'nbt' => [
                    'glaciaItem' => 'phantom_weapon',
                    'armorSet' => 'phantom',
                    'armorPiece' => 'weapon',
                    'customArmor' => 1
                ],
            ],
            "yeti_helmet" => [
                'material' => VanillaItems::DIAMOND_HELMET(),
                'name' => '&r&l&bYeti Facemask',
                'lore' => [
                    '&r&b&oThe savage mask of the yeti.',
                    '',
                    '&r&l&bYETI SET BONUS',
                    '&r&l&b* &r&bDeal +10% damage to all enemies.',
                    '&r&l&b* &r&bEnemies Deal 5% less damage to you.',
                    '&r&7&o(Requires all 4 yeti items.)'
                ],
                'nbt' => [
                    'glaciaItem' => 'yeti_helmet',
                    'armorSet' => 'yeti',
                    'armorPiece' => 'helmet',
                    'customArmor' => 1
                ],
            ],
            "yeti_chestplate" => [
                'material' => VanillaItems::DIAMOND_CHESTPLATE(),
                'name' => '&r&l&bBloody Yeti Torso',
                'lore' => [
                    '&r&bThe Impermeable chestplate of the yeti.',
                    '',
                    '&r&l&bYETI SET BONUS',
                    '&r&l&b* &r&bDeal +10% damage to all enemies.',
                    '&r&l&b* &r&bEnemies Deal 5% less damage to you.',
                    '&r&7&o(Requires all 4 yeti items.)'
                ],
                'nbt' => [
                    'glaciaItem' => 'yeti_chestplate',
                    'armorSet' => 'yeti',
                    'armorPiece' => 'chestplate',
                    'customArmor' => 1
                ],
            ],
            'yeti_leggings' => [
                'material' => VanillaItems::DIAMOND_LEGGINGS(),
                'name' => '&r&l&bFuzzy Yeti Leggings',
                'lore' => [
                    '&r&b&oThe cozy yet monstrous legs of the yeti.',
                    '',
                    '&r&l&bYETI SET BONUS',
                    '&r&l&b* &r&bDeal +10% damage to all enemies.',
                    '&r&l&b* &r&bEnemies Deal 5% less damage to you.',
                    '&r&7&o(Requires all 4 yeti items.)'
                ],
                'nbt' => [
                    'glaciaItem' => 'yeti_leggings',
                    'armorSet' => 'yeti',
                    'armorPiece' => 'leggings',
                    'customArmor' => 1
                ],
            ],
            'yeti_boots' => [
                'material' => VanillaItems::DIAMOND_BOOTS(),
                'name' => '&r&l&bBig-Yeti Boots',
                'lore' => [
                    '&r&b&oBigfoot has nothing on these boots.',
                    '',
                    '&r&l&bYETI SET BONUS',
                    '&r&l&b* &r&bDeal +10% damage to all enemies.',
                    '&r&l&b* &r&bEnemies Deal 5% less damage to you.',
                    '&r&7&o(Requires all 4 yeti items.)'
                ],
                'nbt' => [
                    'glaciaItem' => 'yeti_boots',
                    'armorSet' => 'yeti',
                    'armorPiece' => 'boots',
                    'customArmor' => 1
                ],
            ],
            'yeti_sword' => [
                'material' => VanillaItems::DIAMOND_AXE(),
                'name' => '&r&l&bYeti Maul',
                'lore' => [
                    '&r&b&oA Penetrating axe used by a Yeti.',
                    '',
                    '&r&l&bYETI SET BONUS',
                    '&r&l&b* &r&bDeal +15% durability damage.',
                    '&r&7&o(Requires all 4 yeti items.)'
                ],
                'nbt' => [
                    'glaciaItem' => 'yeti_sword',
                    'armorSet' => 'yeti',
                    'armorPiece' => 'sword',
                    'customArmor' => 1
                ],
            ],
            "yijki_helmet" => [
                'material' => VanillaItems::DIAMOND_HELMET(),
                'name' => '&r&l&fMask of Yijki',
                'lore' => [
                    '&r&f&oThe god-mask of Yijki, imbued with',
                    '&r&f&othe power of absolute destruction.',
                    '',
                    '&r&l&fYIJKI SET BONUS',
                    '&r&l&f* &r&fEnemies deal 20% less damage to you',
                    '&r&l&f* &r&fRevenge of Yijki Passive Ability',
                    '&r&7&o(Requires all 4 yijki items.)'
                ],
                'nbt' => [
                    'glaciaItem' => 'yijki_helmet',
                    'armorSet' => 'yijki',
                    'armorPiece' => 'helmet',
                    'customArmor' => 1
                ],
            ],
            "yijki_chestplate" => [
                'material' => VanillaItems::DIAMOND_CHESTPLATE(),
                'name' => '&r&l&fMantel of Yijki',
                'lore' => [
                    '&r&f&oThe forbidden mantel of Yijki,',
                    '&r&f&owoven from the dust of fallen stars.',
                    '',
                    '&r&l&fYIJKI SET BONUS',
                    '&r&l&f* &r&fEnemies deal 20% less damage to you',
                    '&r&l&f* &r&fRevenge of Yijki Passive Ability',
                    '&r&7&o(Requires all 4 yijki items.)'
                ],
                'nbt' => [
                    'glaciaItem' => 'yijki_chestplate',
                    'armorSet' => 'yijki',
                    'armorPiece' => 'chestplate',
                    'customArmor' => 1
                ],
            ],
            'yijki_leggings' => [
                'material' => VanillaItems::DIAMOND_LEGGINGS(),
                'name' => '&r&l&fRobeset of Yijki',
                'lore' => [
                    '&r&f&oThe fabled robes of Yijki,',
                    '&r&f&ohalf angelic, half demonic.',
                    '',
                    '&r&l&fYIJKI SET BONUS',
                    '&r&l&f* &r&fEnemies deal 20% less damage to you',
                    '&r&l&f* &r&fRevenge of Yijki Passive Ability',
                    '&r&7&o(Requires all 4 yijki items.)'
                ],
                'nbt' => [
                    'glaciaItem' => 'yijki_leggings',
                    'armorSet' => 'yijki',
                    'armorPiece' => 'leggings',
                    'customArmor' => 1
                ],
            ],
            'yijki_boots' => [
                'material' => VanillaItems::DIAMOND_BOOTS(),
                'name' => '&r&l&fFootwraps of Yijki',
                'lore' => [
                    '&r&f&oLight as a feather, heavy as',
                    '&r&f&oas a mountain. - the footwraps of Yijki.',
                    '',
                    '&r&l&fYIJKI SET BONUS',
                    '&r&l&f* &r&fEnemies deal 20% less damage to you',
                    '&r&l&f* &r&fRevenge of Yijki Passive Ability',
                    '&r&7&o(Requires all 4 yijki items.)'
                ],
                'nbt' => [
                    'glaciaItem' => 'yijki_boots',
                    'armorSet' => 'yijki',
                    'armorPiece' => 'boots',
                    'customArmor' => 1
                ],
            ],
            'yijki_sword' => [
                'material' => VanillaItems::DIAMOND_SWORD(),
                'name' => "&r&l&fYijki's World Ender",
                'lore' => [
                    '&r&f&oAn absolutely massive, corrupted',
                    '&r&f&oexecutioner blade imbued with',
                    '&r&f&ohighly destrutive dark magic.',
                    '',
                    '&r&l&fYIJKI WEAPON BONUS',
                    '&r&l&f* &r&fDeal +10% damage to all enemies',
                    '&r&l&f* &r&f125% Revenge Of Yijki Ability',
                    '&r&7&o(Requires all 4 yijki items.)'
                ],
                'nbt' => [
                    'glaciaItem' => 'yijki_sword',
                    'armorSet' => 'yijki',
                    'armorPiece' => 'sword',
                    'customArmor' => 1
                ],
            ],
        ];
    }
}