<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\items;

use ecstsy\Glacia\server\disguises\DisguiseManager;
use ecstsy\Glacia\server\items\runes\RuneManager;
use ecstsy\Glacia\server\ranks\RankProgressionManager;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\PotionType;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;

final class GlaciaItems {

    /** @var array<string, array{display: string, items: string[]}> */
    private static array $categories = [];

    public static function init(): void {
        GlaciaItemFactory::$definitions = [];
        self::$categories = [];

        self::initNotes();
        self::initEnchantItems();
        self::initBossItems();
        self::initChests();
        self::initSpawnerItems();
        self::initDustItems();
        self::initExplosives();
        self::initHomesAndMisc();
        self::initRankItems();
        self::initQuestItems();
        self::initCustomArmor(); 
        self::initDisguiseItems();
        self::initRuneItems();
        self::initRingItems();
        self::initCategories();
        AdminConfiguredItemManager::loadFromConfig();
        CustomMobItemManager::loadFromConfig();
    }

    public static function getCategoryItems(string $category): array {
        $key = strtolower(trim($category));
        return self::$categories[$key]["items"] ?? [];
    }

    public static function getCategoryDisplay(string $category): ?string {
        $key = strtolower(trim($category));
        return self::$categories[$key]["display"] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public static function getCategoryDisplays(): array {
        $displays = [];
        foreach (self::$categories as $key => $data) {
            $displays[$key] = $data["display"];
        }

        return $displays;
    }

    /**
     * @param string[] $items
     */
    public static function registerCategory(string $category, string $display, array $items): void
    {
        self::$categories[strtolower(trim($category))] = [
            "display" => $display,
            "items" => array_values(array_unique(array_filter($items, static fn(string $id): bool => $id !== ""))),
        ];
    }

    /**
     * @param string[] $items
     */
    public static function appendCategoryItems(string $category, string $display, array $items): void
    {
        $key = strtolower(trim($category));
        $existing = self::$categories[$key]["items"] ?? [];
        self::$categories[$key] = [
            "display" => $display !== "" ? $display : (self::$categories[$key]["display"] ?? ucfirst($key)),
            "items" => array_values(array_unique(array_merge(
                $existing,
                array_values(array_filter($items, static fn(string $id): bool => $id !== ""))
            ))),
        ];
    }

    private static function initCategories(): void {
        self::$categories = [
            "pets" => [
                "display" => "Pets",
                "items" => [
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
                    "pet_tamer",
                ],
            ],
            "trinkets" => [
                "display" => "Trinkets",
                "items" => [
                    "soul_conduit",
                    "efficiency_conduit",
                    "overlord_conduit",
                    "soul_gem",
                    "efficiency_gem",
                    "overlord_gem",
                    "blackscroll_dust",
                    "soul_compass",
                    "wraith_pearl",
                    "angel_potion",
                    "bath_bomb",
                    "frostwake_trident",
                    "stormflare_torch",
                    "glacial_blessing",
                    "tempest_gateway",
                ],
            ],
            "economy" => [
                "display" => "Economy",
                "items" => [
                    "bank_note",
                    "xpnote",
                    "bloody_note",
                    "admin_coin_note",
                ],
            ],
            "quests" => [
                "display" => "Quest Sigils",
                "items" => [
                    "dormant_sigil",
                    "awakened_sigil",
                    "ascendent_sigil",
                    "eternal_sigil",
                    "quest_reroll",
                ],
            ],
            "enchants" => [
                "display" => "Enchant Items",
                "items" => [
                    "enchantment_book",
                    "orb",
                    "armor_orb_script",
                    "weapon_orb_script",
                    "whitescroll",
                    "holy_whitescroll",
                    "holy_water",
                    "blackscroll",
                    "holy_blackscroll",
                    "renametag",
                    "transmog_scroll",
                    "transmog",
                    "soul_tracker",
                    "block_tracker",
                    "kill_tracker",
                    "mob_tracker",
                    "xp_tracker",
                    "random_book",
                    "charm_shard",
                    "charm_dust",
                    "conjuration_oracle",
                    "simple_randomization_scroll",
                    "unique_randomization_scroll",
                    "elite_randomization_scroll",
                    "exotic_randomization_scroll",
                    "legendary_randomization_scroll",
                    "godly_randomization_scroll",
                    "exclusive_amplifier",
                    "magicdust",
                    "primal_dust",
                    "random_magicdust",
                    "mysterydust",
                    "passive_rune_pouch",
                ],
            ],
            "crates" => [
                "display" => "Crates & Chests",
                "items" => [
                    "ethereal_simple_chest",
                    "ethereal_unique_chest",
                    "ethereal_elite_chest",
                    "ethereal_exotic_chest",
                    "ethereal_legendary_chest",
                    "ethereal_godly_chest",
                    "mysterymobspawner",
                    "heroic_mysterymobspawner",
                    "collection_chest",
                    "harvester_chest",
                ],
            ],
            "tools" => [
                "display" => "Tools & Utilities",
                "items" => [
                    "max_home_increase",
                    "slot_ticket",
                    "heroic_slot_ticket",
                    "item_flip_credit_voucher",
                    "faction_xp_booster",
                    "zone_xp_booster",
                    "zone_fishing_booster",
                    "envoy_beacon",
                    "zone_barrier_bucket",
                    "chunk_buster",
                    "ocean_buster",
                    "spawner_augment",
                ],
            ],
            "bosses" => [
                "display" => "Boss & Events",
                "items" => [
                    "boss_astral_devourer",
                    "boss_titan_knight",
                    "boss_glacial_hydra",
                    "rift_summoner",
                ],
            ],
            "disguises" => [
                "display" => "Disguises",
                "items" => array_map(
                    static fn(string $id): string => "disguise_" . $id,
                    DisguiseManager::getIds()
                ),
            ],
            "runes" => [
                "display" => "Runes",
                "items" => [
                    "slotkeeper_rune",
                    "celerity_rune",
                    "colossus_rune",
                    "bloodfang_rune",
                    "verdant_rune",
                    "pet_duration_rune",
                    "scholar_rune",
                    "envoy_vacuum_rune",
                    "raider_edge_rune",
                    "null_field_rune",
                    "echo_cache_rune",
                    "hemoguard_rune",
                    "chronoshift_rune",
                    "merchant_sigil_rune",
                    "tidehook_rune",
                    "solitary_hunt_rune",
                    "envoy_echo_rune",
                    "swiftbane_rune",
                    "sigil_of_surge_rune",
                    "reaper_oath_rune",
                    "bottomless_gullet_rune",
                    "stormward_rune",
                    "beastclock_rune",
                    "harvester_mark_rune",
                    "rune_remover",
                ],
            ],
            "rings" => [
                "display" => "Rings",
                "items" => [
                    "disarmor_ring",
                    "lifesteal_ring",
                    "guardian_ring",
                ],
            ],
            "watchdogs" => [
                "display" => "Watchdogs",
                "items" => [
                    "chunk_watchdog_ravenger",
                    "chunk_watchdog_allay",
                    "chunk_watchdog_nautilus",
                    "chunk_watchdog_fox",
                    "chunk_watchdog_goat",
                    "chunk_watchdog_pillager",
                    "chunk_watchdog_llama",
                    "faction_watchdog_panda",
                    "faction_watchdog_phantom",
                    "faction_watchdog_witch",
                    "faction_watchdog_endermite",
                    "watchman_supplement",
                ],
            ],
            "explosives" => [
                "display" => "Explosives",
                "items" => [
                    "tnt_gigantic",
                    "tnt_lethal",
                    "tnt_lucky",
                    "tnt_tactical",
                    "cegg_gigantic",
                    "cegg_lethal",
                    "cegg_lucky",
                    "cegg_tactical",
                ],
            ],
            "armor_sets" => [
                "display" => "Custom Armor",
                "items" => [
                    "phantom_helmet",
                    "phantom_chestplate",
                    "phantom_leggings",
                    "phantom_boots",
                    "phantom_sword",
                    "yeti_helmet",
                    "yeti_chestplate",
                    "yeti_leggings",
                    "yeti_boots",
                    "yeti_sword",
                    "yijki_helmet",
                    "yijki_chestplate",
                    "yijki_leggings",
                    "yijki_boots",
                    "yijki_sword",
                ],
            ],
        ];
    }

    private static function initRuneItems(): void
    {
        GlaciaItemFactory::$definitions += [
            "slotkeeper_rune" => [
                "material" => VanillaItems::GOLD_NUGGET(),
                "name" => "&r&l&aSlotkeeper Rune",
                "lore" => [
                    "&r&8Passive Rune",
                    "",
                    "&r&7Keep this rune in your inventory",
                    "&r&7for a chance to preserve a",
                    "&r&fSlot Ticket &r&7after a roll.",
                ],
                "nbt" => [
                    "glaciaItem" => "rune",
                    "rune_id" => "slotkeeper",
                    "rune_mode" => "passive",
                ],
            ],
            "celerity_rune" => [
                "material" => VanillaItems::GOLD_NUGGET(),
                "name" => "&r&l&bCelerity Rune",
                "lore" => [
                    "&r&8Applied Rune",
                    "",
                    "&r&7Drag n' drop onto boots to bind",
                    "&r&7the force of &fGears V &r&7to them.",
                    "",
                    "&r&7Only one rune may be bound",
                    "&r&7to an item at a time.",
                ],
                "nbt" => [
                    "glaciaItem" => "rune",
                    "rune_id" => "celerity",
                    "rune_mode" => "applied",
                ],
            ],
            "colossus_rune" => [
                "material" => VanillaItems::GOLD_NUGGET(),         
                "name" => "&r&l&dColossus Rune",
                "lore" => [
                    "&r&8Applied Rune",
                    "",
                    "&r&7Drag n' drop onto armor to bind",
                    "&r&7the strength of &fOverload IV&7.",
                    "",
                    "&r&7Only one rune may be bound",
                    "&r&7to an item at a time.",
                ],
                "nbt" => [
                    "glaciaItem" => "rune",
                    "rune_id" => "colossus",
                    "rune_mode" => "applied",
                ],
            ],
            "bloodfang_rune" => [
                "material" => VanillaItems::GOLD_NUGGET(),         
                "name" => "&r&l&cBloodfang Rune",
                "lore" => [
                    "&r&8Applied Rune",
                    "",
                    "&r&7Drag n' drop onto armor to infuse",
                    "&r&7it with a savage combat seal.",
                    "&r&7Combat tags last longer and",
                    "&r&7incoming lifesteal is blunted.",
                ],
                "nbt" => [
                    "glaciaItem" => "rune",
                    "rune_id" => "bloodfang",
                    "rune_mode" => "applied",
                ],
            ],
            "verdant_rune" => [
                "material" => VanillaItems::GOLD_NUGGET(),         
                "name" => "&r&l&aVerdant Rune",
                "lore" => [
                    "&r&8Passive Rune",
                    "",
                    "&r&7Keep this rune in your inventory",
                    "&r&7to protect crops beneath your feet",
                    "&r&7and stir faster field growth nearby.",
                ],
                "nbt" => [
                    "glaciaItem" => "rune",
                    "rune_id" => "verdant",
                    "rune_mode" => "passive",
                ],
            ],
            "pet_duration_rune" => [
                "material" => VanillaItems::GOLD_NUGGET(),         
                "name" => "&r&l&5Attunement Rune",
                "lore" => [
                    "&r&8Passive Rune",
                    "",
                    "&r&7Keep this rune in your inventory",
                    "&r&7to extend the duration of",
                    "&r&f{pet-display} &r&7by &fx1.5&7.",
                ],
                "nbt" => [
                    "glaciaItem" => "pet_duration_rune",
                    "rune_id" => "pet_duration",
                    "rune_mode" => "passive",
                    "pet" => "{pet}",
                ],
            ],
            "scholar_rune" => [
                "material" => VanillaItems::GOLD_NUGGET(),         
                "name" => "&r&l&eScholar Rune",
                "lore" => [
                    "&r&8Passive Rune",
                    "",
                    "&r&7Keep this rune in your inventory",
                    "&r&7for a chance to preserve",
                    "&r&fEnchantment Books &r&7on apply.",
                ],
                "nbt" => [
                    "glaciaItem" => "rune",
                    "rune_id" => "scholar",
                    "rune_mode" => "passive",
                ],
            ],
            "envoy_vacuum_rune" => [
                "material" => VanillaItems::GOLD_NUGGET(),         
                "name" => "&r&l&3Envoy Vacuum Rune",
                "lore" => [
                    "&r&8Passive Rune",
                    "",
                    "&r&7Keep this rune in your inventory",
                    "&r&7to draw envoy rewards straight",
                    "&r&7into your inventory when claimed.",
                ],
                "nbt" => [
                    "glaciaItem" => "rune",
                    "rune_id" => "envoy_vacuum",
                    "rune_mode" => "passive",
                ],
            ],
            "raider_edge_rune" => [
                "material" => VanillaItems::GOLD_NUGGET(),
                "name" => "&r&l&6Raider's Edge Rune",
                "lore" => ["&r&8Applied Rune","","&r&75% chance to duplicate zone mob drops.","&r&7Apply to weapons."],
                "nbt" => ["glaciaItem" => "rune","rune_id" => "raider_edge","rune_mode" => "applied"],
            ],
            "null_field_rune" => [
                "material" => VanillaItems::GOLD_NUGGET(),
                "name" => "&r&l&5Null Field Rune",
                "lore" => ["&r&8Applied Rune","","&r&71% chance to purge potion effects","&r&7from nearby enemies and deal x2 damage.","&r&7Apply to armor."],
                "nbt" => ["glaciaItem" => "rune","rune_id" => "null_field","rune_mode" => "applied"],
            ],
            "echo_cache_rune" => [
                "material" => VanillaItems::GOLD_NUGGET(),
                "name" => "&r&l&dEcho Cache Rune",
                "lore" => ["&r&8Passive Rune","","&r&71% chance to preserve configured consumables","&r&7such as lootboxes when used."],
                "nbt" => ["glaciaItem" => "rune","rune_id" => "echo_cache","rune_mode" => "passive"],
            ],
            "hemoguard_rune" => [
                "material" => VanillaItems::GOLD_NUGGET(),
                "name" => "&r&l&cHemoguard Rune",
                "lore" => ["&r&8Applied Rune","","&r&7Fully negates incoming lifesteal effects.","&r&7Apply to armor."],
                "nbt" => ["glaciaItem" => "rune","rune_id" => "hemoguard","rune_mode" => "applied"],
            ],
            "chronoshift_rune" => [
                "material" => VanillaItems::GOLD_NUGGET(),
                "name" => "&r&l&bChronoshift Rune",
                "lore" => ["&r&8Passive Rune","","&r&7Faction XP boosters gain +20% duration."],
                "nbt" => ["glaciaItem" => "rune","rune_id" => "chronoshift","rune_mode" => "passive"],
            ],
            "merchant_sigil_rune" => [
                "material" => VanillaItems::GOLD_NUGGET(),
                "name" => "&r&l&eMerchant Sigil Rune",
                "lore" => ["&r&8Passive Rune","","&r&7Doubles /sell payouts while in inventory."],
                "nbt" => ["glaciaItem" => "rune","rune_id" => "merchant_sigil","rune_mode" => "passive"],
            ],
            "tidehook_rune" => [
                "material" => VanillaItems::GOLD_NUGGET(),
                "name" => "&r&l&3Tidehook Rune",
                "lore" => ["&r&8Applied Rune","","&r&7Improves fishing spawn speed by x1.5.","&r&7Apply to rods."],
                "nbt" => ["glaciaItem" => "rune","rune_id" => "tidehook","rune_mode" => "applied"],
            ],
            "solitary_hunt_rune" => [
                "material" => VanillaItems::GOLD_NUGGET(),
                "name" => "&r&l&aSolitary Hunt Rune",
                "lore" => ["&r&8Applied Rune","","&r&7If alone in chunk: zone spawns x2 faster","&r&7and zone mob XP is multiplied by x1.5.","&r&7Apply to armor."],
                "nbt" => ["glaciaItem" => "rune","rune_id" => "solitary_hunt","rune_mode" => "applied"],
            ],
            "envoy_echo_rune" => [
                "material" => VanillaItems::GOLD_NUGGET(),
                "name" => "&r&l&9Envoy Echo Rune",
                "lore" => ["&r&8Passive Rune","","&r&72% chance for envoys to drop one extra reward."],
                "nbt" => ["glaciaItem" => "rune","rune_id" => "envoy_echo","rune_mode" => "passive"],
            ],
            "swiftbane_rune" => [
                "material" => VanillaItems::GOLD_NUGGET(),
                "name" => "&r&l&fSwiftbane Rune",
                "lore" => ["&r&8Applied Rune","","&r&7Immune to slowness.","&r&7Adds +3% custom enchant proc chance.","&r&7Apply to armor."],
                "nbt" => ["glaciaItem" => "rune","rune_id" => "swiftbane","rune_mode" => "applied"],
            ],
            "sigil_of_surge_rune" => [
                "material" => VanillaItems::GOLD_NUGGET(),
                "name" => "&r&l&bSigil of Surge Rune",
                "lore" => ["&r&8Applied Rune","","&r&7All custom enchant activations gain +1% chance.","&r&7Apply to armor."],
                "nbt" => ["glaciaItem" => "rune","rune_id" => "sigil_of_surge","rune_mode" => "applied"],
            ],
            "reaper_oath_rune" => [
                "material" => VanillaItems::GOLD_NUGGET(),
                "name" => "&r&l&4Reaper's Oath Rune",
                "lore" => ["&r&8Applied Rune","","&r&72% chance to execute a zone boss instantly.","&r&7Apply to weapons."],
                "nbt" => ["glaciaItem" => "rune","rune_id" => "reaper_oath","rune_mode" => "applied"],
            ],
            "bottomless_gullet_rune" => [
                "material" => VanillaItems::GOLD_NUGGET(),
                "name" => "&r&l&2Bottomless Gullet Rune",
                "lore" => ["&r&8Passive Rune","","&r&7Prevents hunger loss and reduces","&r&7auction listing tax by 20%."],
                "nbt" => ["glaciaItem" => "rune","rune_id" => "bottomless_gullet","rune_mode" => "passive"],
            ],
            "stormward_rune" => [
                "material" => VanillaItems::GOLD_NUGGET(),
                "name" => "&r&l&7Stormward Rune",
                "lore" => ["&r&8Applied Rune","","&r&7Immune to Stormcaller lightning ability.","&r&7Take 10% less combat damage.","&r&7Apply to armor."],
                "nbt" => ["glaciaItem" => "rune","rune_id" => "stormward","rune_mode" => "applied"],
            ],
            "beastclock_rune" => [
                "material" => VanillaItems::GOLD_NUGGET(),
                "name" => "&r&l&6Beastclock Rune",
                "lore" => ["&r&8Passive Rune","","&r&7All pet cooldowns are reduced by 10%."],
                "nbt" => ["glaciaItem" => "rune","rune_id" => "beastclock","rune_mode" => "passive"],
            ],
            "harvester_mark_rune" => [
                "material" => VanillaItems::GOLD_NUGGET(),
                "name" => "&r&l&dHarvester Mark Rune",
                "lore" => ["&r&8Applied Rune","","&r&7Inquisitive V style rune.","&r&7Zone mob rewards are multiplied by x2.5","&r&7while using this weapon."],
                "nbt" => ["glaciaItem" => "rune","rune_id" => "harvester_mark","rune_mode" => "applied"],
            ],
            "rune_remover" => [
                "material" => VanillaItems::GOLD_NUGGET(),         
                "name" => "&r&l&fRune Scrubber",
                "lore" => [
                    "&r&8Extraction Tool",
                    "",
                    "&r&7Drag n' drop onto a runed item",
                    "&r&7for a chance to strip away",
                    "&r&7its bound rune.",
                ],
                "nbt" => [
                    "glaciaItem" => "rune_remover",
                ],
            ],
        ];
    }

    private static function initRingItems(): void
    {
        GlaciaItemFactory::$definitions += [
            "disarmor_ring" => [
                "material" => VanillaItems::GOLD_NUGGET(),
                "name" => "&r&l&4Disarmor Ring",
                "lore" => [
                    "&r&8Applied Ring",
                    "",
                    "&r&7Drag n' drop onto a piece that can",
                    "&r&7hold &fDisarmor &r&7to permanently",
                    "&r&7block it while that piece is equipped.",
                    "",
                    "&r&7A max of &f2 rings &r&7can be",
                    "&r&7bound to one item.",
                    "",
                    "&r&8- &7Success Rate: &a{success}%",
                ],
                "nbt" => [
                    "glaciaItem" => "ring",
                    "ring_id" => "disarmor",
                    "success" => "{success}",
                ],
            ],
            "lifesteal_ring" => [
                "material" => VanillaItems::GOLD_NUGGET(),
                "name" => "&r&l&cLifesteal Ring",
                "lore" => [
                    "&r&8Applied Ring",
                    "",
                    "&r&7Drag n' drop onto a piece that can",
                    "&r&7hold &fLifesteal &r&7to permanently",
                    "&r&7block it while that piece is equipped.",
                    "",
                    "&r&7A max of &f2 rings &r&7can be",
                    "&r&7bound to one item.",
                    "",
                    "&r&8- &7Success Rate: &a{success}%",
                ],
                "nbt" => [
                    "glaciaItem" => "ring",
                    "ring_id" => "lifesteal",
                    "success" => "{success}",
                ],
            ],
            "guardian_ring" => [
                "material" => VanillaItems::GOLD_NUGGET(),
                "name" => "&r&l&eGuardian Ring",
                "lore" => [
                    "&r&8Applied Ring",
                    "",
                    "&r&7Drag n' drop onto a piece that can",
                    "&r&7hold &fWatchmen &r&7to permanently",
                    "&r&7block guardians from turning on you.",
                    "",
                    "&r&7A max of &f2 rings &r&7can be",
                    "&r&7bound to one item.",
                    "",
                    "&r&8- &7Success Rate: &a{success}%",
                ],
                "nbt" => [
                    "glaciaItem" => "ring",
                    "ring_id" => "guardian",
                    "success" => "{success}",
                ],
            ],
        ];
    }

    private static function initDisguiseItems(): void
    {
        GlaciaItemFactory::$definitions["disguise_unlock"] = [
            "material" => VanillaItems::EGG(),
            "name" => "&r&d&lDisguise: &f{display}",
            "lore" => [
                "&r&8Unlocked Form",
                "&r&7{description}",
                "",
                "&r&7Right-click to permanently unlock",
                "&r&f{display} &r&7for &f/disguise&7.",
            ],
            "nbt" => [
                "glaciaItem" => "disguise_unlock",
                "disguise" => "{disguise}",
            ],
        ];

        foreach (DisguiseManager::getIds() as $id) {
            GlaciaItemFactory::$definitions["disguise_" . $id] = [
                "material" => VanillaItems::EGG(),
                "name" => "&r&d&lDisguise: &f" . DisguiseManager::getDisplayName($id),
                "lore" => [
                    "&r&8Unlocked Form",
                    "&r&7" . DisguiseManager::getDescription($id),
                    "",
                    "&r&7Right-click to permanently unlock",
                    "&r&f" . DisguiseManager::getDisplayName($id) . " &r&7for &f/disguise&7.",
                ],
                "nbt" => [
                    "glaciaItem" => "disguise_unlock",
                    "disguise" => $id,
                ],
            ];
        }
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
                'nbt' => ['glaciaItem' => 'econote', 'worth' => '{rawAmount}', 'signer' => '{signer}'],
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
                'nbt' => ['glaciaItem' => 'xpnote', 'worth' => '{rawAmount}', 'signer' => '{signer}'],
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
            'admin_coin_note' => [
                'material' => StringToItemParser::getInstance()->parse("sunflower") ?? VanillaItems::PAPER(),
                'name' => '&r&l&6Admin Coin Voucher &r&7(Right Click)',
                'lore' => [
                    '&r&8-----------------',
                    '&r&fAdmin Coins: &6{amount}',
                    '&r&fSigner: &e{signer}',
                    '&r&8-----------------',
                    '&r&7Redeem this voucher to receive',
                    '&r&7Admin Coins for special deals.',
                ],
                'nbt' => ['glaciaItem' => 'admin_coin_note', 'amount' => '{rawAmount}', 'signer' => '{signer}'],
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
                'nbt' => ['glaciaItem' => 'enchantment_book', 'enchant_book' => '{enchant-no-color}', 'level' => '{level}', 'success' => '{success}', 'destroy' => '{destroy}', 'book_type' => '{book-type}', 'vanilla_enchant' => '{vanilla-enchant}'],
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
            'armor_orb_script' => [
                'material' => VanillaItems::PAPER(),
                'name' => '&r&b&lArmor Orb Script',
                'lore' => [
                    '&r&7Rerolls the &fsuccess rate &7on an',
                    '&r&7existing &bArmor Orb&7.',
                    '&r&7Keeps the orb slot value the same.',
                    '',
                    "&r&bDrag n' Drop onto an Armor Orb.",
                ],
                'nbt' => ['glaciaItem' => 'armor_orb_script'],
            ],
            'weapon_orb_script' => [
                'material' => VanillaItems::PAPER(),
                'name' => '&r&c&lWeapon Orb Script',
                'lore' => [
                    '&r&7Rerolls the &fsuccess rate &7on an',
                    '&r&7existing &cWeapon Orb&7.',
                    '&r&7Keeps the orb slot value the same.',
                    '',
                    "&r&cDrag n' Drop onto a Weapon Orb.",
                ],
                'nbt' => ['glaciaItem' => 'weapon_orb_script'],
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
            'holy_whitescroll' => [
                'material' => StringToItemParser::getInstance()->parse("paper"),
                'name' => '&r&f&lHoly White Scroll',
                'lore' => [
                    "&r&7Can only be applied to gear that",
                    "&r&7already has a White Scroll.",
                    "",
                    "&r&7Protects the item on death and",
                    "&r&7consumes 1 Holy life.",
                    "",
                    "&r&7Max Holy lives per item: &f3",
                ],
                'nbt' => ["glaciaItem" => 'holy_whitescroll'],
            ],
            'holy_water' => [
                'material' => StringToItemParser::getInstance()->parse("water_bucket"),
                'name' => '&r&b&lHoly Water',
                'lore' => [
                    "&r&7Revitalizes semi-corrupt gear by",
                    "&r&7boosting Holy life cap from &f3 &r&7to &f4&7.",
                    "",
                    "&r&7Apply to eligible gear or weapons.",
                ],
                'nbt' => ["glaciaItem" => 'holy_water'],
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
            'holy_blackscroll' => [
                'material' => VanillaItems::INK_SAC(),
                'name' => '&r&5&lHoly Black Scroll',
                'lore' => [
                    "&r&7Strips every custom enchantment",
                    "&r&7off an item and converts each",
                    "&r&7one into a &f{success}% &r&7success book.",
                    "&r&8Vanilla enchants are preserved.",
                    "&r&5Place scroll onto item to extract."
                ],
                'nbt' => ["glaciaItem" => 'holy_blackscroll', "success" => "{success}"],
            ],
            'renametag' => [
                'material' => VanillaItems::NAME_TAG(),
                'name' => '&r&6&lItem Rename Tag &r&7(Right Click)',
                'lore' => [
                    "&r&7Rename and customize your equipment"
                ],
                'nbt' => ['glaciaItem' => 'renametag'],
            ],
            'transmog_scroll' => [
                'material' => VanillaItems::PAPER(),
                'name' => '&r&e&lTransmog Scroll',
                'lore' => [
                    '&r&7Organizes enchants by &erarity &7on item',
                    '&r&7and adds the lore &bcount &7to name.',
                    '',
                    '&r&ePlace scroll on item to apply.',
                ],
                'nbt' => ['glaciaItem' => 'transmog'],
            ],
            'transmog' => [
                'material' => VanillaItems::PAPER(),
                'name' => '&r&e&lTransmog Scroll',
                'lore' => [
                    '&r&7Organizes enchants by &erarity &7on item',
                    '&r&7and adds the &dlore &bcount &7to name.',
                    '',
                    '&r&ePlace scroll on item to apply.',
                ],
                'nbt' => ['glaciaItem' => 'transmog'],
            ],
            'heroic_upgrade' => [
                'material' => VanillaItems::NETHER_STAR(),
                'name' => '&r&4&lHeroic Upgrade Crystal',
                'lore' => [
                    '&r&7Infuse an armor piece with heroic power.',
                    '&r&7Applies a virtual durability buffer and',
                    '&r&7heroic defense scaling separate from set bonuses.',
                    '',
                    '&r&c{success}% Success Rate',
                    "&r&7Drag n' Drop onto armor to apply.",
                ],
                'nbt' => ['glaciaItem' => 'heroic_upgrade', 'success' => '{success}'],
            ],
            'charm_shard' => [
                'material' => VanillaItems::AMETHYST_SHARD(),
                'name' => '&r&d&lCharm Shard',
                'lore' => [
                    '&r&7Levels an enchantment book up by &f+1',
                    '&r&7with a success-only roll.',
                    '&r&7If it fails, the target book is destroyed.',
                    '',
                    '&r&d{effective_success}% Success Rate',
                    "&r&7Drag n' Drop onto an enchantment book.",
                ],
                'nbt' => ['glaciaItem' => 'charm_shard', 'success' => '{success}', 'bonus_success' => '{bonus_success}'],
            ],
            'charm_dust' => [
                'material' => VanillaItems::GLOWSTONE_DUST(),
                'name' => '&r&e&lCharm Dust',
                'lore' => [
                    '&r&7Adds bonus success to a &dCharm Shard&7.',
                    '&r&7Use to improve shard upgrade odds.',
                    '',
                    '&r&e+{bonus}% Shard Success',
                    "&r&7Drag n' Drop onto a Charm Shard.",
                ],
                'nbt' => ['glaciaItem' => 'charm_dust', 'bonus' => '{bonus}'],
            ],
            'conjuration_oracle' => [
                'material' => StringToItemParser::getInstance()->parse("ender_eye"),
                'name' => '&r&5&lConjuration Oracle',
                'lore' => [
                    '&r&7Drag onto a custom-enchanted item to',
                    '&r&7open a ritual that can push select',
                    '&r&7enchants &fone level past max&7.',
                    '',
                    '&r&8Only approved enchants can be elevated.',
                ],
                'nbt' => ['glaciaItem' => 'conjuration_oracle'],
            ],
            'blackscroll_dust' => [
                'material' => VanillaItems::GLOWSTONE_DUST(),
                'name' => '&r&8&lBlack Scroll Dust',
                'lore' => [
                    '&r&7Adds bonus success to a &fBlack Scroll',
                    '&r&7or &5Holy Black Scroll&7.',
                    '',
                    '&r&8+{bonus}% Scroll Success',
                    "&r&7Drag n' Drop onto a Black Scroll.",
                ],
                'nbt' => ['glaciaItem' => 'blackscroll_dust', 'bonus' => '{bonus}'],
            ],
            'simple_randomization_scroll' => [
                'material' => VanillaItems::PAPER(),
                'name' => '&r&l&7Simple Randomization Scroll',
                'lore' => [
                    '&r&7Keeps the book\'s enchant & level.',
                    '&r&7Rerolls &fsuccess &7and &cdestroy &7rates &7(1–100 each).',
                    '',
                    '&r&7Drag onto a &fSimple &7custom enchant book.',
                ],
                'nbt' => ['glaciaItem' => 'simple_randomization_scroll'],
            ],
            'unique_randomization_scroll' => [
                'material' => VanillaItems::PAPER(),
                'name' => '&r&l&aUnique Randomization Scroll',
                'lore' => [
                    '&r&7Keeps the book\'s enchant & level.',
                    '&r&7Rerolls &fsuccess &7and &cdestroy &7rates &7(1–100 each).',
                    '',
                    '&r&7Drag onto a &fUnique &7custom enchant book.',
                ],
                'nbt' => ['glaciaItem' => 'unique_randomization_scroll'],
            ],
            'elite_randomization_scroll' => [
                'material' => VanillaItems::PAPER(),
                'name' => '&r&l&bElite Randomization Scroll',
                'lore' => [
                    '&r&7Keeps the book\'s enchant & level.',
                    '&r&7Rerolls &fsuccess &7and &cdestroy &7rates &7(1–100 each).',
                    '',
                    '&r&7Drag onto an &fElite &7custom enchant book.',
                ],
                'nbt' => ['glaciaItem' => 'elite_randomization_scroll'],
            ],
            'exotic_randomization_scroll' => [
                'material' => VanillaItems::PAPER(),
                'name' => '&r&l&eExotic Randomization Scroll',
                'lore' => [
                    '&r&7Keeps the book\'s enchant & level.',
                    '&r&7Rerolls &fsuccess &7and &cdestroy &7rates &7(1–100 each).',
                    '',
                    '&r&7Drag onto an &fExotic &7custom enchant book.',
                ],
                'nbt' => ['glaciaItem' => 'exotic_randomization_scroll'],
            ],
            'legendary_randomization_scroll' => [
                'material' => VanillaItems::PAPER(),
                'name' => '&r&l&6Legendary Randomization Scroll',
                'lore' => [
                    '&r&7Keeps the book\'s enchant & level.',
                    '&r&7Rerolls &fsuccess &7and &cdestroy &7rates &7(1–100 each).',
                    '',
                    '&r&7Drag onto a &fLegendary &7custom enchant book.',
                ],
                'nbt' => ['glaciaItem' => 'legendary_randomization_scroll'],
            ],
            'godly_randomization_scroll' => [
                'material' => VanillaItems::PAPER(),
                'name' => '&r&l&4Godly Randomization Scroll',
                'lore' => [
                    '&r&7Keeps the book\'s enchant & level.',
                    '&r&7Rerolls &fsuccess &7and &cdestroy &7rates &7(1–100 each).',
                    '',
                    '&r&7Works on &fany rarity &7custom book; also &fvanilla &7books.',
                ],
                'nbt' => ['glaciaItem' => 'godly_randomization_scroll'],
            ],
            'exclusive_amplifier' => [
                'material' => VanillaItems::PRISMARINE_CRYSTALS(),
                'name' => '&r&l&dExclusive Amplifier',
                'lore' => [
                    '&r&7Adds extra uses to every &dExclusive &r&7enchant',
                    '&r&7already applied on the target item.',
                    '',
                    '&r&d+{uses} Uses',
                    "&r&7Drag n' Drop onto the enchanted item.",
                ],
                'nbt' => ['glaciaItem' => 'exclusive_amplifier', 'uses' => '{uses}'],
            ],
            'block_tracker' => [
                'material' => VanillaItems::PAPER(),
                'name' => '&r&l&bBlocks Broken Tracker',
                'lore' => [
                    '&r&7Tracks every block broken with a tool.',
                    '&r&7Works on &bpickaxes&7, &baxes&7, &bshovels &7and &bhoes&7.',
                    '',
                    "&r&bDrag n' Drop onto a tool to apply.",
                ],
                'nbt' => ['glaciaItem' => 'block_tracker'],
            ],
            'kill_tracker' => [
                'material' => VanillaItems::PAPER(),
                'name' => '&r&l&cPlayer Kill Tracker',
                'lore' => [
                    '&r&7Tracks player kills earned with a weapon.',
                    '&r&7Best used on your favorite PvP weapon.',
                    '',
                    "&r&cDrag n' Drop onto a weapon to apply.",
                ],
                'nbt' => ['glaciaItem' => 'kill_tracker'],
            ],
            'mob_tracker' => [
                'material' => VanillaItems::PAPER(),
                'name' => '&r&l&aMob Kill Tracker',
                'lore' => [
                    '&r&7Tracks the mobs slain using a weapon.',
                    '&r&7Perfect for grinder and PvE gear.',
                    '',
                    "&r&aDrag n' Drop onto a weapon to apply.",
                ],
                'nbt' => ['glaciaItem' => 'mob_tracker'],
            ],
            'xp_tracker' => [
                'material' => VanillaItems::PAPER(),
                'name' => '&r&l&eXP Tracker',
                'lore' => [
                    '&r&7Tracks the XP earned while fighting',
                    '&r&7with the weapon it is applied to.',
                    '',
                    "&r&eDrag n' Drop onto a weapon to apply.",
                ],
                'nbt' => ['glaciaItem' => 'xp_tracker'],
            ],
            'soul_tracker' => [
                'material' => VanillaItems::PAPER(),
                'name' => '&r&l&4Soul Tracker',
                'lore' => [
                    '&r&7Apply this to a weapon to begin',
                    '&r&7tracking souls earned from kills.',
                    '',
                    "&r&4Drag n' Drop onto a weapon to apply.",
                ],
                'nbt' => ['glaciaItem' => 'soul_tracker'],
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
                    '&r&7A warzone entity classified as &cunstable&7.',
                    '',
                    '&r&l&5THREAT PROFILE',
                    '&r&7A remnant of a collapsed star, warped into existence.',
                    '&r&7It feeds on energy, distorts time, and consumes',
                    '&r&7anything caught within its reach.',
                    '',
                    '&r&7Each soul it devours strengthens its presence,',
                    '&r&7pulling reality closer to its collapse',
                    '',
                    '&r&l&5STATUS',
                    '&r&5&lLegendary'
                ],
                'nbt' => ["glaciaItem" => 'bossSpawn', "boss" => 'astral_devourer']
            ],
            "boss_titan_knight" => [
                'material' => VanillaItems::NETHER_STAR(),
                'name' => '&r&l&8Astral Sovereign',
                'lore' => [
                    '&r&7A fragmented cosmic warlord bound to a fractured sigil.',
                    '',
                    '&r&l&8THREAT PROFILE',
                    '&r&7A hovering dark figure of broken armor and a',
                    '&r&7violent astral core, capable of warping gravity',
                    '&r&7and shattering the ground around it.',
                    '',
                    '&r&7Requires the server resource pack and Customies',
                    '&r&7to render its custom boss model.',
                    '',
                    '&r&l&8STATUS',
                    '&r&8&lLegendary'
                ],
                'nbt' => ["glaciaItem" => 'bossSpawn', "boss" => 'titan_knight']
            ],
            "boss_glacial_hydra" => [
                'material' => VanillaItems::NETHER_STAR(),
                'name' => '&r&l&bGlacial Hydra',
                'lore' => [
                    '&r&7An ancient hydra infused with void frost and rage.',
                    '',
                    '&r&l&bTHREAT PROFILE',
                    '&r&7Three fractured heads. One relentless body.',
                    '&r&7Frost slows, void pulls, and rage overwhelms.',
                    '',
                    '&r&7Uses the new multi-head hydra boss logic.',
                    '&r&7A custom Bedrock model can be layered on later.',
                    '',
                    '&r&l&bSTATUS',
                    '&r&b&lGodly'
                ],
                'nbt' => ["glaciaItem" => 'bossSpawn', "boss" => 'glacial_hydra']
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
                    "",
                    "&r&8&oSneak + Right-click to preview loot.",
                ],
                'nbt' => ["glaciaItem" => 'ethereal_simple_chest'],
            ],
            "ethereal_unique_chest" => [
                'material' => VanillaBlocks::TRAPPED_CHEST()->asItem(),
                'name' => '&r&l&aUnique Ethereal Chest &r&7(Right Click)',
                'lore' => [
                    "&r&7A cache of equipment prepared by",
                    "&r&7the Ethereal Intergalactic Station.",
                    "",
                    "&r&8&oSneak + Right-click to preview loot.",
                ],
                'nbt' => ["glaciaItem" => 'ethereal_unique_chest'],
            ],
            "ethereal_elite_chest" => [
                'material' => VanillaBlocks::TRAPPED_CHEST()->asItem(),
                'name' => '&r&l&bElite Ethereal Chest &r&7(Right Click)',
                'lore' => [
                    "&r&7A cache of equipment prepared by",
                    "&r&7the Ethereal Intergalactic Station.",
                    "",
                    "&r&8&oSneak + Right-click to preview loot.",
                ],
                'nbt' => ["glaciaItem" => 'ethereal_elite_chest'],
            ],
            "ethereal_exotic_chest" => [
                'material' => VanillaBlocks::TRAPPED_CHEST()->asItem(),
                'name' => '&r&l&eExotic Ethereal Chest &r&7(Right Click)',
                'lore' => [
                    "&r&7A cache of equipment prepared by",
                    "&r&7the Ethereal Intergalactic Station.",
                    "",
                    "&r&8&oSneak + Right-click to preview loot.",
                ],
                'nbt' => ["glaciaItem" => 'ethereal_exotic_chest'],
            ],
            "ethereal_legendary_chest" => [
                'material' => VanillaBlocks::TRAPPED_CHEST()->asItem(),
                'name' => '&r&l&6Legendary Ethereal Chest &r&7(Right Click)',
                'lore' => [
                    "&r&7A cache of equipment prepared by",
                    "&r&7the Ethereal Intergalactic Station.",
                    "",
                    "&r&8&oSneak + Right-click to preview loot.",
                ],
                'nbt' => ["glaciaItem" => 'ethereal_legendary_chest'],
            ],
            "ethereal_godly_chest" => [
                'material' => VanillaBlocks::TRAPPED_CHEST()->asItem(),
                'name' => '&r&l&cGodly Ethereal Chest &r&7(Right Click)',
                'lore' => [
                    "&r&7A cache of equipment prepared by",
                    "&r&7the Ethereal Intergalactic Station.",
                    "",
                    "&r&8&oSneak + Right-click to preview loot.",
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
                'name' => '&r&3&k||&r &b&lHeroic &r&3&k||&r &f&lMystery Mob Spawner',
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
            ],
            "slot_ticket" => [
                'material' => VanillaItems::PAPER(),
                'name' => '&r&l&dSlot Bot &5Ticket &r&7(/bot)',
                'lore' => [
                    '&r&7Right-Click the Slot Bot or',
                    '&r&7use /bot and insert this ticket',
                    '&r&7into the bot and click spin.',
                ],
                'nbt' => ['glaciaItem' => 'slot_ticket'],
            ],
            "heroic_slot_ticket" => [
                'material' => VanillaItems::PAPER(),
                'name' => '&r&3&k||&r &b&lHeroic &r&3&k||&r &f&lSlot Bot Ticket &r&7(/bot)',
                'lore' => [
                    '&r&7Use this in the Slot Bot to roll',
                    '&r&7exclusively from the jackpot table.',
                    '',
                    '&r&7One spin. &bOnly jackpot loot.',
                ],
                'nbt' => ['glaciaItem' => 'heroic_slot_ticket'],
            ],
            "item_flip_credit_voucher" => [
                'material' => VanillaItems::PAPER(),
                'name' => '&r&l&6Item Flip Credit Voucher',
                'lore' => [
                    '&r&7Right-click to claim &f{credits} &7Item Flip credits.',
                    '',
                    '&r&eUse these in &f/icf &7matches.',
                ],
                'nbt' => ['glaciaItem' => 'item_flip_credit_voucher', 'credits' => '{credits}'],
            ],
            "passive_rune_pouch" => [
                'material' => VanillaBlocks::ENDER_CHEST()->asItem(),
                'name' => '&r&l&aPassive Rune Pouch',
                'lore' => [
                    '&r&7Stores passive runes inside this pouch item.',
                    '&r&7Unlocks and stored runes belong to this pouch only.',
                    '&r&7If you die with it, you risk losing everything in it.',
                    '',
                    '&r&eRight-click to open.',
                ],
                'nbt' => ['glaciaItem' => 'passive_rune_pouch'],
            ],
            "faction_xp_booster" => [
                 "material" => VanillaItems::EMERALD(), 
                 'name' => "&r&l&bFaction XP Booster &r&7(Right Click)",
                 'lore' => [
                    '&r&7Multiplies &fXP &7gained by all',
                    '&r&7faction members while active.',
                    "",
                    "&r&7Right-Click to activate.",
                    "",
                    "&r&l&dMULTIPLIER",
                    "&r&7x{multiplier}",
                    "",
                    "&r&l&dDURATION",
                    "&r&7{duration}",
                  ],
                 'nbt' => ['glaciaItem' => 'faction_xp_booster', 'multiplier' => "{multiplier}", 'duration' => "{duration}"],
            ],
            "zone_xp_booster" => [
                "material" => VanillaItems::GOLD_INGOT(),
                "name" => "&r&l&6Faction Zone XP Booster &r&7(Right Click)",
                "lore" => [
                    "&r&7Multiplies &fzone mob XP &7gained by all",
                    "&r&7players in the zone while active.",
                    "",
                    "&r&7Right-Click to activate.",
                    "",
                    "&r&l&dMULTIPLIER",
                    "&r&7x{multiplier}",
                    "",
                    "&r&l&dDURATION",
                    "&r&7{duration}",
                ],
                "nbt" => ['glaciaItem' => 'zone_xp_booster', 'multiplier' => "{multiplier}", 'duration' => "{duration}"],
            ],
            "zone_fishing_booster" => [
                "material" => VanillaItems::GOLD_INGOT(),
                "name" => "&r&l&6Zone Fishing Booster &r&7(Right Click)",
                "lore" => [
                    "&r&7Multiplies &ffishing rod XP &7earned by all",
                    "&r&7players in that zone while active.",
                    "",
                    "&r&7Right-Click to activate.",
                    "",
                    "&r&l&dZONE",
                    "&r&7{zone}",
                    "",
                    "&r&l&dMULTIPLIER",
                    "&r&7x{multiplier}",
                    "",
                    "&r&l&dDURATION",
                    "&r&7{duration}",
                ],
                "nbt" => ['glaciaItem' => 'zone_fishing_booster', 'zone' => "{zone}", 'zone_id' => "{zone_id}", 'multiplier' => "{multiplier}", 'duration' => "{duration}"],
            ],
            "envoy_beacon" => [
                "material" => VanillaItems::NETHER_STAR(),
                "name" => "&r&l&bCelestial Envoy Beacon",
                "lore" => [
                    "&r&7Tear open the skies and call down",
                    "&r&7an immediate &bEnvoy &7over the warzone.",
                    "",
                    "&r&7Can only be used while no",
                    "&r&7Envoy event is already active.",
                    "",
                    "&r&eRight-click to ignite the signal.",
                ],
                "nbt" => ['glaciaItem' => 'envoy_beacon'],
            ],
            "zone_barrier_bucket" => [
                "material" => VanillaBlocks::BARRIER()->asItem(),
                "name" => "&r&l&cAdmin Zone Barrier Bucket",
                "lore" => [
                    "&r&7Build invisible zone wall columns quickly.",
                    "",
                    "&r&cAdmin Utility",
                    "&r&f * &r&7Right-click marker: columns above",
                    "&r&7  connected same-Y marker blocks.",
                    "&r&f * &r&7Side + diagonal markers chain.",
                    "&r&f * &r&7Sneak right-click: undo last column.",
                    "&r&f * &r&7Stops at solid blocks or zone border.",
                    "",
                    "&r&eRequires &fglacia.admin&e.",
                ],
                "nbt" => ['glaciaItem' => 'zone_barrier_bucket'],
            ],
            "chunk_buster" => [
                "material" => VanillaBlocks::BEACON()->asItem(),
                "name" => "&r&l&bCelestial Chunk Buster",
                "lore" => [
                    "&r&7A radiant beacon forged to tear through",
                    "&r&7an entire chunk for rapid base building.",
                    "",
                    "&r&bRules",
                    "&r&f * &r&7Can only be placed in your own claim.",
                    "&r&f * &r&7Clears downward and stops at bedrock.",
                    "",
                    "&r&ePlace to activate."
                ],
                "nbt" => ['glaciaItem' => 'chunk_buster'],
            ],
            "ocean_buster" => [
                "material" => VanillaBlocks::SPONGE()->asItem(),
                "name" => "&r&l&3Ocean Chunk Buster",
                "lore" => [
                    "&r&7Advanced trenching utility that",
                    "&r&7drains all water in an entire chunk.",
                    "",
                    "&r&3Limitations",
                    "&r&f * &r&7Overworld only.",
                    "&r&f * &r&7Wilderness by default.",
                    "&r&f * &r&7Requires Use Sponges permission in claims.",
                    "",
                    "&r&ePlace to activate."
                ],
                "nbt" => ['glaciaItem' => 'ocean_buster'],
            ],
            "spawner_augment" => [
                "material" => VanillaItems::AMETHYST_SHARD(),
                "name" => "&r&l&bSpawner Augment",
                "lore" => [
                    "&r&7Right-click any placed spawner to open",
                    "&r&7the augment UI and boost its &f/f top",
                    "&r&7value by &f+25% &r&7per augment.",
                    "",
                    "&r&7Stacked spawners need one augment",
                    "&r&7for each spawner you want boosted.",
                ],
                "nbt" => ['glaciaItem' => 'spawner_augment'],
            ],
            "collection_chest" => [
                "material" => VanillaBlocks::CHEST()->asItem(),
                "name" => "&r&l&eCollection Chest",
                "lore" => [
                    "&r&7Create a chest that will",
                    "&r&7automatically collect specified",
                    "&r&7mob drops when a creature dies",
                    "&r&7within the same chunk.",
                    "",
                    "&r&ePlace inside your own claim."
                ],
                "nbt" => ['glaciaItem' => 'collection_chest'],
            ],
            "harvester_chest" => [
                "material" => VanillaBlocks::TRAPPED_CHEST()->asItem(),
                "name" => "&r&l&aHarvester Chest",
                "lore" => [
                    "&r&7Create a chest that collects &fcrops",
                    "&r&7and farm drops &7in this chunk —",
                    "&r&7wheat, melons, cactus, sugar cane,",
                    "&r&7mushrooms, chorus, and similar.",
                    "",
                    "&r&7You may place &fone per chunk&7 alongside",
                    "&r&7a normal Collection Chest.",
                    "",
                    "&r&ePlace inside your own claim."
                ],
                "nbt" => ['glaciaItem' => 'harvester_chest'],
            ],
            "chunk_watchdog_ravenger" => [
                "material" => StringToItemParser::getInstance()->parse("ravager_spawn_egg") ?? VanillaItems::EGG(),
                "name" => "&r&l&fWatchdog: &cChunk Ravenger",
                "lore" => [
                    "&r&7Keeps this chunk active for farm use",
                    "&r&7even without players nearby.",
                    "&r&cSell prices in this chunk are reduced by 30%.",
                    "",
                    "&r&7Default duration: &f7 days",
                    "&r&7Break cost after 30m: &f$10,000,000",
                    "&r&7Limit: &f1 per chunk",
                    "",
                    "&r&eRight-click a block in your own claim to place."
                ],
                "nbt" => ['glaciaItem' => 'chunk_watchdog_ravenger'],
            ],
            "chunk_watchdog_allay" => [
                "material" => StringToItemParser::getInstance()->parse("allay_spawn_egg") ?? VanillaItems::EGG(),
                "name" => "&r&l&fWatchdog: &bChunk Allay",
                "lore" => [
                    "&r&7Doubles mob spawn rates in this chunk.",
                    "&r&7Raises spawner stack cap from &f10 &r&7to &f15&7.",
                    "",
                    "&r&7Default duration: &f3 days",
                    "&r&7Break cost: &f$10,000,000",
                    "&r&7Limit: &f1 per chunk",
                    "",
                    "&r&eRight-click a block in your own claim to place."
                ],
                "nbt" => ['glaciaItem' => 'chunk_watchdog_allay'],
            ],
            "chunk_watchdog_nautilus" => [
                "material" => StringToItemParser::getInstance()->parse("nautilus_shell") ?? VanillaItems::HEART_OF_THE_SEA(),
                "name" => "&r&l&fWatchdog: &3Chunk Nautilus",
                "lore" => [
                    "&r&7Grants permanent &fSpeed IV &r&7inside the chunk.",
                    "&r&75% chance to preserve CC opens in this chunk.",
                    "",
                    "&r&7Default duration: &f3 days",
                    "&r&7Break cost: &f$10,000,000",
                    "&r&7Limit: &f1 per chunk",
                    "",
                    "&r&eRight-click a block in your own claim to place."
                ],
                "nbt" => ['glaciaItem' => 'chunk_watchdog_nautilus'],
            ],
            "chunk_watchdog_fox" => [
                "material" => StringToItemParser::getInstance()->parse("fox_spawn_egg") ?? VanillaItems::EGG(),
                "name" => "&r&l&fWatchdog: &6Chunk Fox",
                "lore" => [
                    "&r&7Snowman drops from this chunk sell for &f+5%&7.",
                    "",
                    "&r&7Default duration: &f2 days",
                    "&r&7Break cost: &f$25,000,000",
                    "&r&7Limit: &f1 per chunk",
                    "",
                    "&r&eRight-click a block in your own claim to place."
                ],
                "nbt" => ['glaciaItem' => 'chunk_watchdog_fox'],
            ],
            "chunk_watchdog_goat" => [
                "material" => StringToItemParser::getInstance()->parse("goat_spawn_egg") ?? VanillaItems::EGG(),
                "name" => "&r&l&fWatchdog: &7Chunk Goat",
                "lore" => [
                    "&r&7Ghast drops from this chunk sell for &f+5%&7.",
                    "",
                    "&r&7Default duration: &f2 days",
                    "&r&7Break cost: &f$25,000,000",
                    "&r&7Limit: &f1 per chunk",
                    "",
                    "&r&eRight-click a block in your own claim to place."
                ],
                "nbt" => ['glaciaItem' => 'chunk_watchdog_goat'],
            ],
            "chunk_watchdog_pillager" => [
                "material" => StringToItemParser::getInstance()->parse("pillager_spawn_egg") ?? VanillaItems::EGG(),
                "name" => "&r&l&fWatchman: &8Chunk Pillager",
                "lore" => [
                    "&r&7Non-faction and non-allied players gain",
                    "&r&7permanent &fWeakness I &r&7inside this chunk.",
                    "",
                    "&r&7Default duration: &f7 days",
                    "&r&7Break cost: &f$1,000,000",
                    "&r&7Limit: &f1 per chunk",
                    "",
                    "&r&eRight-click a block in your own claim to place."
                ],
                "nbt" => ['glaciaItem' => 'chunk_watchdog_pillager'],
            ],
            "chunk_watchdog_llama" => [
                "material" => StringToItemParser::getInstance()->parse("llama_spawn_egg") ?? VanillaItems::EGG(),
                "name" => "&r&l&fWatchman: &dChunk Llama",
                "lore" => [
                    "&r&7Applied enchants have a &f2% &r&7chance per llama",
                    "&r&7to gain &f+1 extra level &r&7up to max level.",
                    "&r&7Two can be placed in one chunk for &f4% total&7.",
                    "",
                    "&r&7Default duration: &f2 days",
                    "&r&7Break cost: &f$2,500,000",
                    "&r&7Limit: &f2 per chunk",
                    "",
                    "&r&eRight-click a block in your own claim to place."
                ],
                "nbt" => ['glaciaItem' => 'chunk_watchdog_llama'],
            ],
            "faction_watchdog_panda" => [
                "material" => StringToItemParser::getInstance()->parse("panda_spawn_egg") ?? VanillaItems::EGG(),
                "name" => "&r&l&fWatchdog: &aFaction Panda",
                "lore" => [
                    "&r&7Faction members have a &f20% &r&7chance",
                    "&r&7to receive an extra daily quest.",
                    "",
                    "&r&7Default duration: &f5 days",
                    "&r&7Break cost: &f$20,000,000",
                    "&r&7Limit: &f1 per faction",
                    "",
                    "&r&eRight-click a block in your own claim to place."
                ],
                "nbt" => ['glaciaItem' => 'faction_watchdog_panda'],
            ],
            "faction_watchdog_phantom" => [
                "material" => StringToItemParser::getInstance()->parse("phantom_spawn_egg") ?? VanillaItems::EGG(),
                "name" => "&r&l&fWatchdog: &5Faction Phantom",
                "lore" => [
                    "&r&750% chance to not consume an exclusive use",
                    "&r&7when an exclusive enchant activates.",
                    "",
                    "&r&7Default duration: &f7 days",
                    "&r&7Break cost: &f$20,000,000",
                    "&r&7Limit: &f1 per faction",
                    "",
                    "&r&eRight-click a block in your own claim to place."
                ],
                "nbt" => ['glaciaItem' => 'faction_watchdog_phantom'],
            ],
            "faction_watchdog_witch" => [
                "material" => StringToItemParser::getInstance()->parse("witch_spawn_egg") ?? VanillaItems::EGG(),
                "name" => "&r&l&fWatchdog: &2Faction Witch",
                "lore" => [
                    "&r&715% chance for healing potions to heal &f50% more&7.",
                    "&r&7Slotbot credit shop prices are &f5% cheaper&7.",
                    "&r&7Faction members gain permanent &fHaste II&7.",
                    "",
                    "&r&7Default duration: &f2 days",
                    "&r&7Break cost: &f$25,000,000",
                    "&r&7Limit: &f2 per faction",
                    "",
                    "&r&eRight-click a block in your own claim to place."
                ],
                "nbt" => ['glaciaItem' => 'faction_watchdog_witch'],
            ],
            "faction_watchdog_endermite" => [
                "material" => StringToItemParser::getInstance()->parse("endermite_spawn_egg") ?? VanillaItems::EGG(),
                "name" => "&r&l&fWatchman: &5Faction Endermite",
                "lore" => [
                    "&r&7Removes the negative debuffs from &fDrunk",
                    "&r&7for every faction member automatically.",
                    "",
                    "&r&7Default duration: &f3 days",
                    "&r&7Break cost: &f$10,000,000",
                    "&r&7Limit: &f1 per faction",
                    "",
                    "&r&eRight-click a block in your own claim to place."
                ],
                "nbt" => ['glaciaItem' => 'faction_watchdog_endermite'],
            ],
            "watchman_supplement" => [
                "material" => VanillaItems::SUGAR(),
                "name" => "&r&l&fWatchdog Supplement &7(+{days}d)",
                "lore" => [
                    "&r&7Use the watchdog manager to apply this",
                    "&r&7to any placed watchdog entity.",
                    "",
                    "&r&7Each item adds &f{days} day(s) &r&7to its timer."
                ],
                "nbt" => ['glaciaItem' => 'watchman_supplement', 'days' => '{days}'],
            ],
            "soul_conduit" => [
                "material" => VanillaItems::EMERALD(),
                "name" => "&r&c&lSoul Conduit &r&7[&f1&7]",
                "lore" => [
                    "&r&8Soul Conduit",
                    "",
                    "&r&7Soul Mode: &aStable",
                    "&r&7Proc soul cost scales by &f×1 &7of each enchant’s base cost.",
                    "&r&7Stored Souls: &a1",
                    "",
                    "&r&8> &r&7Drag onto weapons, tools, bows, or armor to merge",
                    "&r&8> &r&7Merged conduit powers soul-cost custom enchants",
                ],
                "nbt" => ['glaciaItem' => 'soul_conduit', 'souls' => 1, 'group' => "", 'soul_gem_id' => ""],
            ],
            "efficiency_conduit" => [
                "material" => VanillaItems::DIAMOND(),
                "name" => "&r&6&lEfficiency Conduit &r&7[&f1&7]",
                "lore" => [
                    "&r&8Efficiency Conduit",
                    "",
                    "&r&7Soul Mode: &6Efficiency",
                    "&r&7Proc soul cost scales by &f×0.75 &7of each enchant’s base cost.",
                    "&r&7Stored Souls: &a1",
                    "",
                    "&r&8> &r&7Drag onto weapons, tools, bows, or armor to merge",
                    "&r&8> &r&7Merged conduit powers soul-cost custom enchants",
                ],
                "nbt" => ['glaciaItem' => 'efficiency_conduit', 'souls' => 1, 'group' => "", 'soul_gem_id' => ""],
            ],
            "overlord_conduit" => [
                "material" => VanillaItems::NETHER_STAR(),
                "name" => "&r&4&lOverlord Conduit &r&7[&f1&7]",
                "lore" => [
                    "&r&8Overlord Conduit",
                    "",
                    "&r&7Soul Mode: &4Overlord",
                    "&r&7Proc soul cost scales by &f×0.5 &7of each enchant’s base cost.",
                    "&r&7Siphon: &f20% &7chance to refund &f1 &7soul after paying.",
                    "&r&7Stored Souls: &a1",
                    "",
                    "&r&8> &r&7Drag onto weapons, tools, bows, or armor to merge",
                    "&r&8> &r&7Merged conduit powers soul-cost custom enchants",
                ],
                "nbt" => ['glaciaItem' => 'overlord_conduit', 'souls' => 1, 'group' => "", 'soul_gem_id' => ""],
            ],
            "soul_gem" => [
                "material" => VanillaItems::EMERALD(),
                "name" => "&r&c&lSoul Gem &r&7[&f1&7]",
                "lore" => [
                    "&r&8Soul Gem",
                    "",
                    "&r&7Stored Souls: &a1",
                    "",
                    "&r&8> &r&7Drag onto gear with a merged &cSoul Conduit",
                    "&r&8> &r&7Cannot be applied to &6Efficiency &7or &4Overlord &7conduits",
                ],
                "nbt" => ['glaciaItem' => 'soul_gem', 'souls' => 1, 'group' => "", 'soul_gem_id' => ""],
            ],
            "efficiency_gem" => [
                "material" => VanillaItems::DIAMOND(),
                "name" => "&r&6&lEfficiency Gem &r&7[&f1&7]",
                "lore" => [
                    "&r&8Efficiency Gem",
                    "",
                    "&r&7Stored Souls: &a1",
                    "",
                    "&r&8> &r&7Drag onto gear with a merged &6Efficiency Conduit",
                    "&r&8> &r&7Cannot be applied to &cSoul &7or &4Overlord &7conduits",
                ],
                "nbt" => ['glaciaItem' => 'efficiency_gem', 'souls' => 1, 'group' => "", 'soul_gem_id' => ""],
            ],
            "overlord_gem" => [
                "material" => VanillaItems::NETHER_STAR(),
                "name" => "&r&4&lOverlord Gem &r&7[&f1&7]",
                "lore" => [
                    "&r&8Overlord Gem",
                    "",
                    "&r&7Stored Souls: &a1",
                    "",
                    "&r&8> &r&7Drag onto gear with a merged &4Overlord Conduit",
                    "&r&8> &r&7Cannot be applied to &cSoul &7or &6Efficiency &7conduits",
                ],
                "nbt" => ['glaciaItem' => 'overlord_gem', 'souls' => 1, 'group' => "", 'soul_gem_id' => ""],
            ],
            "soul_compass" => [
                "material" => VanillaItems::COMPASS(),
                "name" => "&r&l&bSoul Compass",
                "lore" => [
                    "&r&7Right-click to trace the nearest",
                    "&r&7active &bEnvoy crate &7through the warzone.",
                    "",
                    "&r&7Uses: &f8",
                    "&r&8The signal weakens with each pulse."
                ],
                "nbt" => ['glaciaItem' => 'soul_compass', 'uses' => 8],
            ],
            "wraith_pearl" => [
                "material" => VanillaItems::ENDER_PEARL(),
                "name" => "&r&l&5Wraith Pearl",
                "lore" => [
                    "&r&7Right-click to throw a stored",
                    "&r&7ender pearl charge.",
                    "",
                    "&r&7Uses: &f40",
                    "&r&8Drag ender pearls onto this item to charge it.",
                ],
                "nbt" => ['glaciaItem' => 'wraith_pearl', 'uses' => 40],
            ],
            "angel_potion" => [
                "material" => VanillaItems::SPLASH_POTION()->setType(PotionType::STRONG_HEALING()),
                "name" => "&r&l&dAngel Potion",
                "lore" => [
                    "&r&7Right-click to throw a stored",
                    "&r&7strong healing splash potion.",
                    "",
                    "&r&7Uses: &f40",
                    "&r&8Drag strong heals onto this item to charge it.",
                ],
                "nbt" => ['glaciaItem' => 'angel_potion', 'uses' => 40],
            ],
            "bath_bomb" => [
                "material" => VanillaItems::SNOWBALL(),
                "name" => "&r&l&bBath Bomb",
                "lore" => [
                    "&r&7Throw this at enemies to give them",
                    "&r&7a light knockback pop.",
                    "",
                    "&r&7No cooldown.",
                    "&r&8Stacks to 16 like a normal snowball.",
                ],
                "nbt" => ['glaciaItem' => 'bath_bomb'],
            ],
            "frostwake_trident" => [
                "material" => VanillaItems::TRIDENT(),
                "name" => "&r&l&bFrostwake Trident",
                "lore" => [
                    "&r&7Right-click to restore &f50% durability",
                    "&r&7to all damaged items in your inventory.",
                    "",
                    "&r&bCooldown: &f5m",
                ],
                "nbt" => ['glaciaItem' => 'frostwake_trident'],
            ],
            "stormflare_torch" => [
                "material" => VanillaBlocks::TORCH()->asItem(),
                "name" => "&r&l&eStormflare Torch",
                "lore" => [
                    "&r&7Right-click to combat-tag every player",
                    "&r&7within a &f9 block &r&7radius.",
                    "",
                    "&r&eCooldown: &f10m",
                ],
                "nbt" => ['glaciaItem' => 'stormflare_torch'],
            ],
            "glacial_blessing" => [
                "material" => VanillaItems::GHAST_TEAR(),
                "name" => "&r&l&fGlacial Blessing",
                "lore" => [
                    "&r&7Right-click to instantly heal",
                    "&r&7yourself to full health.",
                    "",
                    "&r&fCooldown: &f30m",
                ],
                "nbt" => ['glaciaItem' => 'glacial_blessing'],
            ],
            "tempest_gateway" => [
                "material" => VanillaItems::HEART_OF_THE_SEA(),
                "name" => "&r&l&3Tempest Gateway",
                "lore" => [
                    "&r&7Right-click to awaken a random",
                    "&r&7armor set ability.",
                    "",
                    "&r&3Cooldown: &f30m",
                ],
                "nbt" => ['glaciaItem' => 'tempest_gateway'],
            ],
            "puritywisp" => [
                "material" => VanillaBlocks::MOB_HEAD()->asItem(),
                "name" => "&r&l&fPurity Wisp Pet",
                "lore" => [
                    "&r&8Pet Item",
                    "",
                    "&r&7Ability: &fBless",
                    "&r&7Right-click to cleanse all",
                    "&r&7negative effects instantly.",
                    "",
                    "&r&7Cooldown: &f10s",
                ],
                "nbt" => ['glaciaItem' => 'puritywisp'],
            ],
            "galefox" => [
                "material" => VanillaBlocks::MOB_HEAD()->asItem(),
                "name" => "&r&l&bGale Fox Pet",
                "lore" => [
                    "&r&8Pet Item",
                    "",
                    "&r&7Ability: &bSonic Burst",
                    "&r&7Right-click to gain a burst",
                    "&r&7of extreme movement speed.",
                    "",
                    "&r&7Cooldown: &f5m",
                ],
                "nbt" => ['glaciaItem' => 'galefox'],
            ],
            "shatterfang" => [
                "material" => VanillaBlocks::MOB_HEAD()->asItem(),
                "name" => "&r&l&8Shatterfang Pet",
                "lore" => [
                    "&r&8Pet Item",
                    "",
                    "&r&7Ability: &8Armor Rend",
                    "&r&7Right-click to empower your strikes",
                    "&r&7with crushing armor wear.",
                    "",
                    "&r&7Cooldown: &f20m",
                ],
                "nbt" => ['glaciaItem' => 'shatterfang'],
            ],
            "hearthbloom" => [
                "material" => VanillaBlocks::MOB_HEAD()->asItem(),
                "name" => "&r&l&eHearthbloom Pet",
                "lore" => [
                    "&r&8Pet Item",
                    "",
                    "&r&7Ability: &eWarmth",
                    "&r&7Right-click to restore your hunger",
                    "&r&7and recover a small burst of health.",
                    "",
                    "&r&7Cooldown: &f90s",
                ],
                "nbt" => ['glaciaItem' => 'hearthbloom'],
            ],
            "ironcladwyrm" => [
                "material" => VanillaBlocks::MOB_HEAD()->asItem(),
                "name" => "&r&l&7Ironclad Wyrm Pet",
                "lore" => [
                    "&r&8Pet Item",
                    "",
                    "&r&7Ability: &7Sunder Bite",
                    "&r&7Right-click to prime a guaranteed",
                    "&r&7random armor disarm on your next",
                    "&r&7nearby enemy.",
                    "",
                    "&r&7Cooldown: &f1h",
                ],
                "nbt" => ['glaciaItem' => 'ironcladwyrm'],
            ],
            "aegisram" => [
                "material" => VanillaBlocks::MOB_HEAD()->asItem(),
                "name" => "&r&l&bAegis Ram Pet",
                "lore" => [
                    "&r&8Pet Item",
                    "",
                    "&r&7Ability: &bNull Charge",
                    "&r&7Right-click to gain no knockback",
                    "&r&7and immunity to negative effects",
                    "&r&7for 10 seconds.",
                    "",
                    "&r&7Cooldown: &f20m",
                ],
                "nbt" => ['glaciaItem' => 'aegisram'],
            ],
            "riftviper" => [
                "material" => VanillaBlocks::MOB_HEAD()->asItem(),
                "name" => "&r&l&5Rift Viper Pet",
                "lore" => [
                    "&r&8Pet Item",
                    "",
                    "&r&7Ability: &5Pearl Lock",
                    "&r&7Right-click to disable ender pearls",
                    "&r&7for enemies within a 3 block radius",
                    "&r&7for 6 seconds.",
                    "",
                    "&r&7Cooldown: &f20m",
                ],
                "nbt" => ['glaciaItem' => 'riftviper'],
            ],
            "sunscorchjackal" => [
                "material" => VanillaBlocks::MOB_HEAD()->asItem(),
                "name" => "&r&l&6Sunscorch Jackal Pet",
                "lore" => [
                    "&r&8Pet Item",
                    "",
                    "&r&7Ability: &6Apple Scorch",
                    "&r&7Right-click to lock golden apples",
                    "&r&7for enemies within a 3 block radius",
                    "&r&7for 10 seconds.",
                    "",
                    "&r&7Cooldown: &f45m",
                ],
                "nbt" => ['glaciaItem' => 'sunscorchjackal'],
            ],
            "runesageowl" => [
                "material" => VanillaBlocks::MOB_HEAD()->asItem(),
                "name" => "&r&l&dRunesage Owl Pet",
                "lore" => [
                    "&r&8Pet Item",
                    "",
                    "&r&7Ability: &dArcane Surge",
                    "&r&7Right-click to gain &f+5% &r&7on",
                    "&r&7enchantment applying rates for",
                    "&r&75 minutes.",
                    "",
                    "&r&7Cooldown: &f12h",
                ],
                "nbt" => ['glaciaItem' => 'runesageowl'],
            ],
            "stormcorebehemoth" => [
                "material" => VanillaBlocks::MOB_HEAD()->asItem(),
                "name" => "&r&l&cStormcore Behemoth Pet",
                "lore" => [
                    "&r&8Pet Item",
                    "",
                    "&r&7Ability: &cOvercharge",
                    "&r&7Right-click to summon a charged",
                    "&r&7giga creeper at your location.",
                    "",
                    "&r&7Cooldown: &f10m",
                ],
                "nbt" => ['glaciaItem' => 'stormcorebehemoth'],
            ],
            "veilmoth" => [
                "material" => VanillaBlocks::MOB_HEAD()->asItem(),
                "name" => "&r&l&8Veilmoth Pet",
                "lore" => [
                    "&r&8Pet Item",
                    "",
                    "&r&7Ability: &8Shade Veil",
                    "&r&7Right-click to hide yourself",
                    "&r&7from &f/near &r&7for 1 minute.",
                    "",
                    "&r&7Cooldown: &f10m",
                ],
                "nbt" => ['glaciaItem' => 'veilmoth'],
            ],
            "blastforgehound" => [
                "material" => VanillaBlocks::MOB_HEAD()->asItem(),
                "name" => "&r&l&4Blastforge Hound Pet",
                "lore" => [
                    "&r&8Pet Item",
                    "",
                    "&r&7Ability: &4Siege Snare",
                    "&r&7Right-click to inflict Slowness X",
                    "&r&7on the nearest enemy for 7 seconds",
                    "&r&7and become immune to explosive damage.",
                    "",
                    "&r&7Cooldown: &f15m",
                ],
                "nbt" => ['glaciaItem' => 'blastforgehound'],
            ],
            "gildedmoth" => [
                "material" => VanillaBlocks::MOB_HEAD()->asItem(),
                "name" => "&r&l&dGilded Moth Pet",
                "lore" => [
                    "&r&8Pet Item",
                    "",
                    "&r&7Ability: &dLoaded Slot",
                    "&r&7Right-click, then open a slot within",
                    "&r&710 minutes to guarantee a jackpot",
                    "&r&7slotbot reward on your next slot.",
                    "",
                    "&r&7Cooldown: &f5d",
                ],
                "nbt" => ['glaciaItem' => 'gildedmoth'],
            ],
            "rafflefinch" => [
                "material" => VanillaBlocks::MOB_HEAD()->asItem(),
                "name" => "&r&l&6Raffle Finch Pet",
                "lore" => [
                    "&r&8Pet Item",
                    "",
                    "&r&7Ability: &6Overcap Buy",
                    "&r&7Right-click to instantly buy",
                    "&r&71,000 bonus jackpot tickets.",
                    "&r&7This can push you above the normal cap.",
                    "",
                    "&r&7Cooldown: &f4h",
                ],
                "nbt" => ['glaciaItem' => 'rafflefinch'],
            ],
            "oathbondbeetle" => [
                "material" => VanillaBlocks::MOB_HEAD()->asItem(),
                "name" => "&r&l&aOathbond Beetle Pet",
                "lore" => [
                    "&r&8Pet Item",
                    "",
                    "&r&7Ability: &aSurecast",
                    "&r&7Right-click to guarantee your next",
                    "&r&7success-rate item apply within 10 minutes.",
                    "",
                    "&r&7Cooldown: &f24h",
                ],
                "nbt" => ['glaciaItem' => 'oathbondbeetle'],
            ],
            "murmurloft" => [
                "material" => VanillaBlocks::MOB_HEAD()->asItem(),
                "name" => "&r&l&5Murmurloft Pet",
                "lore" => [
                    "&r&8Pet Item",
                    "",
                    "&r&7Ability: &5Silence Burst",
                    "&r&7Right-click to empower your Silence",
                    "&r&7enchant for 7 seconds and give kills",
                    "&r&7a chance to negate Holy Whites.",
                    "",
                    "&r&7Cooldown: &f30m",
                ],
                "nbt" => ['glaciaItem' => 'murmurloft'],
            ],
            "patchworksprite" => [
                "material" => VanillaBlocks::MOB_HEAD()->asItem(),
                "name" => "&r&l&bPatchwork Sprite Pet",
                "lore" => [
                    "&r&8Pet Item",
                    "",
                    "&r&7Ability: &bFull Reset",
                    "&r&7Right-click to /fixall, /heal,",
                    "&r&7and /feed yourself instantly.",
                    "",
                    "&r&7Cooldown: &f10m",
                ],
                "nbt" => ['glaciaItem' => 'patchworksprite'],
            ],
            "renderscalewyrm" => [
                "material" => VanillaBlocks::MOB_HEAD()->asItem(),
                "name" => "&r&l&cRenderscale Wyrm Pet",
                "lore" => [
                    "&r&8Pet Item",
                    "",
                    "&r&7Ability: &cRend Window",
                    "&r&7Right-click to bypass enemy defensive",
                    "&r&7armor abilities and gain +5% damage",
                    "&r&7for 8 seconds.",
                    "",
                    "&r&7Cooldown: &f30m",
                ],
                "nbt" => ['glaciaItem' => 'renderscalewyrm'],
            ],
            "skycourierdrake" => [
                "material" => VanillaBlocks::MOB_HEAD()->asItem(),
                "name" => "&r&l&eSkycourier Drake Pet",
                "lore" => [
                    "&r&8Pet Item",
                    "",
                    "&r&7Ability: &eCrate Mutation",
                    "&r&7Right-click to gain a 10% chance",
                    "&r&7for opened envoys to upgrade their",
                    "&r&7reward tier for 30 minutes.",
                    "",
                    "&r&7Cooldown: &f4h",
                ],
                "nbt" => ['glaciaItem' => 'skycourierdrake'],
            ],
            "inkwellfinch" => [
                "material" => VanillaBlocks::MOB_HEAD()->asItem(),
                "name" => "&r&l&eInkwell Finch Pet",
                "lore" => [
                    "&r&8Pet Item",
                    "",
                    "&r&7Ability: &eFlash Sale",
                    "&r&7Right-click to roll a random",
                    "&r&710%, 20%, or 25% /enchanter",
                    "&r&7discount for 10 minutes.",
                    "",
                    "&r&7Cooldown: &f4h",
                ],
                "nbt" => ['glaciaItem' => 'inkwellfinch'],
            ],
            "terracrestjackal" => [
                "material" => VanillaBlocks::MOB_HEAD()->asItem(),
                "name" => "&r&l&3Terracrest Jackal Pet",
                "lore" => [
                    "&r&8Pet Item",
                    "",
                    "&r&7Ability: &3Knowledge Rush",
                    "&r&7Right-click to gain &fx2.5",
                    "&r&7zone mob XP for 5 minutes.",
                    "",
                    "&r&7Cooldown: &f2h",
                ],
                "nbt" => ['glaciaItem' => 'terracrestjackal'],
            ],
            "soulsurge" => [
                "material" => VanillaBlocks::MOB_HEAD()->asItem(),
                "name" => "&r&l&4Soul Surge Pet",
                "lore" => [
                    "&r&8Pet Item",
                    "",
                    "&r&7Ability: &4Soul Surge",
                    "&r&7Right-click to make enchant",
                    "&r&7soul costs &f0 &r&7for 20 minutes.",
                    "",
                    "&r&7Cooldown: &f6h",
                ],
                "nbt" => ['glaciaItem' => 'soulsurge'],
            ],
            "ruinspiremoth" => [
                "material" => VanillaBlocks::MOB_HEAD()->asItem(),
                "name" => "&r&l&cRuinspire Moth Pet",
                "lore" => [
                    "&r&8Pet Item",
                    "",
                    "&r&7Ability: &cForced Calamity",
                    "&r&7Requires &fArmageddon &r&7on armor. Procs",
                    "&r&7a soul-free blessing on the nearest enemy,",
                    "&r&7then shrouds the area in &fWeakness V",
                    "&r&7and &fSlowness III &r&7until it subsides.",
                    "",
                    "&r&7Cooldown: &f4d",
                ],
                "nbt" => ['glaciaItem' => 'ruinspiremoth'],
            ],
            "bellforgecustodian" => [
                "material" => VanillaBlocks::MOB_HEAD()->asItem(),
                "name" => "&r&l&7Bellforge Custodian Pet",
                "lore" => [
                    "&r&8Pet Item",
                    "",
                    "&r&7Ability: &fWatchman's Oath",
                    "&r&7While your Guardians golem fights, ignite",
                    "&r&7its fury (&fStrength III / 8s&7), soothe its",
                    "&r&7wounds passively, and ignore knockback (&f6s&7).",
                    "",
                    "&r&7Cooldown: &f30m",
                ],
                "nbt" => ['glaciaItem' => 'bellforgecustodian'],
            ],
            "arcbindercodex" => [
                "material" => VanillaBlocks::MOB_HEAD()->asItem(),
                "name" => "&r&l&5Arcbinder Codex Pet",
                "lore" => [
                    "&r&8Pet Item",
                    "",
                    "&r&7Ability: &5Sure Inscription",
                    "&r&7Grants &f+5% &r&7success on enchant book",
                    "&r&7applies (&fstacks &r&7with Runesage surge&7)",
                    "&r&7for 30 minutes.",
                    "",
                    "&r&7Cooldown: &f3h",
                ],
                "nbt" => ['glaciaItem' => 'arcbindercodex'],
            ],
            "crownspirekestrel" => [
                "material" => VanillaBlocks::MOB_HEAD()->asItem(),
                "name" => "&r&l&eCrownspire Kestrel Pet",
                "lore" => [
                    "&r&8Pet Item",
                    "",
                    "&r&7Ability: &eRegal Ascension",
                    "&r&7Your &f/next &r&7Gkits claim rolls &f+20%",
                    "&r&7higher chance to spike armor enchant tiers.",
                    "&r&7Expires if unused after &f1 hour&7.",
                    "",
                    "&r&7Cooldown: &f6h",
                ],
                "nbt" => ['glaciaItem' => 'crownspirekestrel'],
            ],
            "shadebrokercrow" => [
                "material" => VanillaBlocks::MOB_HEAD()->asItem(),
                "name" => "&r&l&8Shadebroker Crow Pet",
                "lore" => [
                    "&r&8Pet Item",
                    "",
                    "&r&7Ability: &8Phantom Lot",
                    "&r&7Draws one random &fBlack Auction &r&7listing",
                    "&r&7reward into your stash.",
                    "",
                    "&r&7Cooldown: &f1d",
                ],
                "nbt" => ['glaciaItem' => 'shadebrokercrow'],
            ],
            "pet_tamer" => [
                "material" => StringToItemParser::getInstance()->parse("lead"),
                "name" => "&r&l&6Pet Tamer",
                "lore" => [
                    "&r&7Drag n' drop onto any pet item",
                    "&r&7to protect it from one death.",
                    "",
                    "&r&7If you die, the pet returns to you",
                    "&r&7and the Pet Tamer is consumed.",
                ],
                "nbt" => ['glaciaItem' => 'pet_tamer'],
            ],
        ];

        $materials = [
            "sand" => ["display" => "&eSand", "material" => VanillaItems::BUCKET()],
            "cobble" => ["display" => "&7Cobble", "material" => VanillaItems::BUCKET()],
            "obsidian" => ["display" => "&5Obsidian", "material" => VanillaItems::BUCKET()],
            "lava" => ["display" => "&cLava wall", "material" => VanillaItems::BUCKET()],
        ];
        $directions = [
            "north" => "North",
            "east" => "East",
            "south" => "South",
            "west" => "West",
            "up" => "Up",
            "down" => "Down",
        ];
        $genBucketIds = [];

        foreach ($materials as $materialKey => $materialData) {
            foreach ($directions as $directionKey => $directionName) {
                $bucketId = "{$materialKey}_gen_bucket_{$directionKey}";
                $genBucketIds[] = $bucketId;
                GlaciaItemFactory::$definitions[$bucketId] = [
                    "material" => clone $materialData["material"],
                    "name" => "&r&l" . $materialData["display"] . " &fGen Bucket &r&7(" . $directionName . ")",
                    "lore" => [
                        "&r&7Sneak + right-click a block to generate",
                        "&r" . $materialData["display"] . " &r&7toward &f" . $directionName . "&7.",
                        "&r&7It keeps running until blocked, stopped,",
                        "&r&7or until you run out of money.",
                        "",
                        "&r&eReusable item. Cost is charged per block placed.",
                    ],
                    "nbt" => [
                        "glaciaItem" => "gen_bucket",
                        "material" => $materialKey,
                        "direction" => $directionKey,
                    ],
                ];
            }
        }

        self::appendCategoryItems("gen_buckets", "Gen Buckets", $genBucketIds);
    }

    private static function initCustomArmor(): void {
        GlaciaItemFactory::$definitions += [
            "phantom_helmet" => [
                'material' => VanillaItems::LEATHER_CAP(),
                'name' => '&r&l&5Dreadveil Hood',
                'lore' => [
                    '&r&5&oThe fabled hood of the Dreadveil.',
                    '',
                    '&r&l&5DREADVEIL SET BONUS',
                    '&r&l&5* &r&5Deal +15% damage to all enemies.',
                    '&r&l&5* &r&5Dreadveil Rend: chance to deal true damage.',
                    '&r&7&o(Requires all 4 Dreadveil items.)'
                ],
                'nbt' => [
                    'glaciaItem' => 'phantom_helmet',
                    'armorSet' => 'phantom',
                    'armorPiece' => 'helmet',
                    'customArmor' => 1
                ],
            ],
            "phantom_chestplate" => [
                'material' => VanillaItems::LEATHER_TUNIC(),
                'name' => '&r&l&5Dreadveil Shroud',
                'lore' => [
                    '&r&5&oThe legendary shroud of the Dreadveil.',
                    '',
                    '&r&l&5DREADVEIL SET BONUS',
                    '&r&l&5* &r&5Deal +15% damage to all enemies.',
                    '&r&l&5* &r&5Dreadveil Rend: chance to deal true damage.',
                    '&r&7&o(Requires all 4 Dreadveil items.)'
                ],
                'nbt' => [
                    'glaciaItem' => 'phantom_chestplate',
                    'armorSet' => 'phantom',
                    'armorPiece' => 'chestplate',
                    'customArmor' => 1
                ],
            ],
            'phantom_leggings' => [
                'material' => VanillaItems::LEATHER_PANTS(),
                'name' => '&r&l&5Dreadveil Legwraps',
                'lore' => [
                    '&r&5&oThe shadowwoven legwraps of the Dreadveil.',
                    '',
                    '&r&l&5DREADVEIL SET BONUS',
                    '&r&l&5* &r&5Deal +15% damage to all enemies.',
                    '&r&l&5* &r&5Dreadveil Rend: chance to deal true damage.',
                    '&r&7&o(Requires all 4 Dreadveil items.)'
                ],
                'nbt' => [
                    'glaciaItem' => 'phantom_leggings',
                    'armorSet' => 'phantom',
                    'armorPiece' => 'leggings',
                    'customArmor' => 1
                ],
            ],
            'phantom_boots' => [
                'material' => VanillaItems::LEATHER_BOOTS(),
                'name' => '&r&l&5Dreadveil Treads',
                'lore' => [
                    '&r&5&oThe silent treads of the Dreadveil.',
                    '',
                    '&r&l&5DREADVEIL SET BONUS',
                    '&r&l&5* &r&5Deal +15% damage to all enemies.',
                    '&r&l&5* &r&5Dreadveil Rend: chance to deal true damage.',
                    '&r&7&o(Requires all 4 Dreadveil items.)'
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
                'name' => '&r&l&5Dreadveil Reaper',
                'lore' => [
                    '&r&5&oAn eerie blade forged to',
                    '&r&5&ocleave through the living.',
                    '',
                    '&r&l&5DREADVEIL WEAPON BONUS',
                    '&r&l&5* &r&5Deal +5% damage to all enemies.',
                    '&r&7&o(Requires all 4 Dreadveil items.)'
                ],
                'nbt' => [
                    'glaciaItem' => 'phantom_weapon',
                    'armorSet' => 'phantom',
                    'armorPiece' => 'weapon',
                    'customArmor' => 1
                ],
            ],
            "yeti_helmet" => [
                'material' => VanillaItems::LEATHER_CAP(),
                'name' => '&r&l&bFrostbound Visage',
                'lore' => [
                    '&r&b&oThe savage visage of the Frostbound.',
                    '',
                    '&r&l&bFROSTBOUND SET BONUS',
                    '&r&l&b* &r&bDeal +10% damage to all enemies.',
                    '&r&l&b* &r&bEnemies Deal 5% less damage to you.',
                    '&r&l&b* &r&bPermafrost Pulse: retaliate with true damage and slowness.',
                    '&r&7&o(Requires all 4 Frostbound items.)'
                ],
                'nbt' => [
                    'glaciaItem' => 'yeti_helmet',
                    'armorSet' => 'yeti',
                    'armorPiece' => 'helmet',
                    'customArmor' => 1
                ],
            ],
            "yeti_chestplate" => [
                'material' => VanillaItems::LEATHER_TUNIC(),
                'name' => '&r&l&bFrostbound Carapace',
                'lore' => [
                    '&r&b&oThe impermeable chestplate of the Frostbound.',
                    '',
                    '&r&l&bFROSTBOUND SET BONUS',
                    '&r&l&b* &r&bDeal +10% damage to all enemies.',
                    '&r&l&b* &r&bEnemies Deal 5% less damage to you.',
                    '&r&l&b* &r&bPermafrost Pulse: retaliate with true damage and slowness.',
                    '&r&7&o(Requires all 4 Frostbound items.)'
                ],
                'nbt' => [
                    'glaciaItem' => 'yeti_chestplate',
                    'armorSet' => 'yeti',
                    'armorPiece' => 'chestplate',
                    'customArmor' => 1
                ],
            ],
            'yeti_leggings' => [
                'material' => VanillaItems::LEATHER_PANTS(),
                'name' => '&r&l&bFrostbound Leggings',
                'lore' => [
                    '&r&b&oThe frost-lined leggings of the Frostbound.',
                    '',
                    '&r&l&bFROSTBOUND SET BONUS',
                    '&r&l&b* &r&bDeal +10% damage to all enemies.',
                    '&r&l&b* &r&bEnemies Deal 5% less damage to you.',
                    '&r&l&b* &r&bPermafrost Pulse: retaliate with true damage and slowness.',
                    '&r&7&o(Requires all 4 Frostbound items.)'
                ],
                'nbt' => [
                    'glaciaItem' => 'yeti_leggings',
                    'armorSet' => 'yeti',
                    'armorPiece' => 'leggings',
                    'customArmor' => 1
                ],
            ],
            'yeti_boots' => [
                'material' => VanillaItems::LEATHER_BOOTS(),
                'name' => '&r&l&bFrostbound Greaves',
                'lore' => [
                    '&r&b&oBoots hardened by the deepest frost.',
                    '',
                    '&r&l&bFROSTBOUND SET BONUS',
                    '&r&l&b* &r&bDeal +10% damage to all enemies.',
                    '&r&l&b* &r&bEnemies Deal 5% less damage to you.',
                    '&r&l&b* &r&bPermafrost Pulse: retaliate with true damage and slowness.',
                    '&r&7&o(Requires all 4 Frostbound items.)'
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
                'name' => '&r&l&bFrostbound Maul',
                'lore' => [
                    '&r&b&oA crushing maul wielded by the Frostbound.',
                    '',
                    '&r&l&bFROSTBOUND WEAPON BONUS',
                    '&r&l&b* &r&bDeal +15% durability damage.',
                    '&r&7&o(Requires all 4 Frostbound items.)'
                ],
                'nbt' => [
                    'glaciaItem' => 'yeti_sword',
                    'armorSet' => 'yeti',
                    'armorPiece' => 'sword',
                    'customArmor' => 1
                ],
            ],
            "yijki_helmet" => [
                'material' => VanillaItems::LEATHER_CAP(),
                'name' => '&r&l&3Stormcaller Mask',
                'lore' => [
                    '&r&3&oThe thunderbound mask of the Stormcaller,',
                    '&r&3&oimbued with the force of the heavens.',
                    '',
                    '&r&l&3STORMCALLER SET BONUS',
                    '&r&l&3* &r&3Tempest Surge Passive Ability',
                    '&r&7&o(Requires all 4 Stormcaller items.)'
                ],
                'nbt' => [
                    'glaciaItem' => 'yijki_helmet',
                    'armorSet' => 'yijki',
                    'armorPiece' => 'helmet',
                    'customArmor' => 1
                ],
            ],
            "yijki_chestplate" => [
                'material' => VanillaItems::LEATHER_TUNIC(),
                'name' => '&r&l&3Stormcaller Mantle',
                'lore' => [
                    '&r&3&oThe charged mantle of the Stormcaller,',
                    '&r&3&owoven from stormglass and fallen stars.',
                    '',
                    '&r&l&3STORMCALLER SET BONUS',
                    '&r&l&3* &r&3Tempest Surge Passive Ability',
                    '&r&7&o(Requires all 4 Stormcaller items.)'
                ],
                'nbt' => [
                    'glaciaItem' => 'yijki_chestplate',
                    'armorSet' => 'yijki',
                    'armorPiece' => 'chestplate',
                    'customArmor' => 1
                ],
            ],
            'yijki_leggings' => [
                'material' => VanillaItems::LEATHER_PANTS(),
                'name' => '&r&l&3Stormcaller Vestments',
                'lore' => [
                    '&r&3&oThe crackling vestments of the Stormcaller,',
                    '&r&3&odraped in living static.',
                    '',
                    '&r&l&3STORMCALLER SET BONUS',
                    '&r&l&3* &r&3Tempest Surge Passive Ability',
                    '&r&7&o(Requires all 4 Stormcaller items.)'
                ],
                'nbt' => [
                    'glaciaItem' => 'yijki_leggings',
                    'armorSet' => 'yijki',
                    'armorPiece' => 'leggings',
                    'customArmor' => 1
                ],
            ],
            'yijki_boots' => [
                'material' => VanillaItems::LEATHER_BOOTS(),
                'name' => '&r&l&3Stormcaller Striders',
                'lore' => [
                    '&r&3&oSwift as lightning, steady as thunder.',
                    '&r&3&oThe striders of the Stormcaller endure all.',
                    '',
                    '&r&l&3STORMCALLER SET BONUS',
                    '&r&l&3* &r&3Tempest Surge Passive Ability',
                    '&r&7&o(Requires all 4 Stormcaller items.)'
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
                'name' => "&r&l&3Stormcaller's Worldsplitter",
                'lore' => [
                    '&r&3&oAn immense blade humming with',
                    '&r&3&oraging skies and catastrophic force.',
                    '&r&3&oEach swing carries the storm.',
                    '',
                    '&r&l&3STORMCALLER WEAPON BONUS',
                    '&r&l&3* &r&3Deal +10% damage to all enemies',
                    '&r&l&3* &r&3125% Tempest Surge Ability',
                    '&r&7&o(Requires all 4 Stormcaller items.)'
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

    private static function initRankItems(): void {
        $progressionItems = [];
        foreach (RankProgressionManager::getTiers() as $rank => $data) {
            $questId = $rank . "_quest";
            $voucherId = $rank . "_voucher";
            $progressionItems[] = $questId;
            $progressionItems[] = $voucherId;

            GlaciaItemFactory::$definitions[$questId] = [
                'material' => VanillaItems::MAGMA_CREAM(),
                'name' => '&r&l' . $data["color"] . $data["display"] . ' Rank Quest&r&f: &f&l<&r&f' . GeneralUtils::translateTime($data["duration"]) . '&l&f>',
                'lore' => RankProgressionManager::getQuestLore($rank, $data["duration"], false),
                'nbt' => [
                    'glaciaItem' => $rank . '_quest',
                    'rank' => $rank,
                    'remaining' => (string) $data["duration"],
                    'complete' => '0'
                ],
            ];

            GlaciaItemFactory::$definitions[$voucherId] = [
                'material' => VanillaItems::PAPER(),
                'name' => '&r&l&eRANK "&r&f' . $data["display"] . '&r&l&e"',
                'lore' => [
                    '&r&7Right-Click this item to unlock',
                    '&r&7the ' . $data["display"] . ' Rank on this server.',
                    '',
                    '&r&7Only works if your current rank',
                    '&r&7is lower than this promotion.',
                ],
                'nbt' => [
                    'glaciaItem' => $rank . '_voucher',
                    'rank' => $rank
                ],
            ];
        }

        self::appendCategoryItems("progression", "Rank Progression", $progressionItems);
    }

    private static function initQuestItems(): void
    {
        GlaciaItemFactory::$definitions += [
            "dormant_sigil" => [
                "material" => VanillaItems::AMETHYST_SHARD(),
                "name" => "&r&7* &bDormant Sigil &7*",
                "lore" => [
                    "&r&7Use this to add one extra &bTier 1&r&7 quest",
                    "&r&7to your active &f/quests&r&7 board.",
                ],
                "nbt" => [
                    "glaciaItem" => "quest_sigil",
                    "questTier" => "dormant",
                ],
            ],
            "awakened_sigil" => [
                "material" => StringToItemParser::getInstance()->parse("echo_shard") ?? VanillaItems::AMETHYST_SHARD(),
                "name" => "&r&7+ &5Awakened Sigil &7+",
                "lore" => [
                    "&r&7Use this to add one extra &5Tier 2&r&7 quest",
                    "&r&7to your active &f/quests&r&7 board.",
                ],
                "nbt" => [
                    "glaciaItem" => "quest_sigil",
                    "questTier" => "awakened",
                ],
            ],
            "ascendent_sigil" => [
                "material" => VanillaItems::NETHER_STAR(),
                "name" => "&r&7# &eAscendent Sigil &7#",
                "lore" => [
                    "&r&7Use this to add one extra &eTier 3&r&7 quest",
                    "&r&7to your active &f/quests&r&7 board.",
                ],
                "nbt" => [
                    "glaciaItem" => "quest_sigil",
                    "questTier" => "ascendent",
                ],
            ],
            "eternal_sigil" => [
                "material" => VanillaBlocks::DRAGON_EGG()->asItem(),
                "name" => "&r&7@ &cEternal Sigil &7@",
                "lore" => [
                    "&r&7Use this to add one extra &cEternal&r&7 bonus quest",
                    "&r&7from the highest quest pool in &f/quests&r&7.",
                ],
                "nbt" => [
                    "glaciaItem" => "quest_sigil",
                    "questTier" => "eternal",
                ],
            ],
            "quest_reroll" => [
                "material" => StringToItemParser::getInstance()->parse("echo_shard") ?? VanillaItems::PAPER(),
                "name" => "&r&l&dQuest Reroll",
                "lore" => [
                    "&r&7Right-click to open your &f/quests &r&7board",
                    "&r&7and replace one active quest with a new one",
                    "&r&7from the same tier.",
                    "",
                    "&r&8Consumed when a reroll succeeds.",
                ],
                "nbt" => [
                    "glaciaItem" => "quest_reroll",
                ],
            ],
        ];
    }
}
