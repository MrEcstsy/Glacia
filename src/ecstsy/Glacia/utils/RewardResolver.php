<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils;

use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantments;
use ecstsy\Glacia\server\etherealcrates\EtherealCrateRepository;
use ecstsy\Glacia\server\gkits\GKitRepository;
use ecstsy\Glacia\server\items\GlaciaItemFactory;
use ecstsy\Glacia\server\items\armor\HeroicArmorDurabilityManager;
use ecstsy\Glacia\server\items\runes\RuneManager;
use ecstsy\Glacia\server\lootboxes\LootboxRepository;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\color\Color;
use pocketmine\item\Armor;
use pocketmine\item\Durable;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\StringToEnchantmentParser;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class RewardResolver {

    /** @var string[] */
    private const RESERVED_TYPES = [
        "item",
        "money_note",
        "bank_note",
        "econote",
        "xp_note",
        "xpnote",
        "xpbottle",
        "lootbox",
        "ethereal_crate",
        "etherealcrate",
        "enchant_book",
        "enchantment_book",
        "enchanted_book",
        "cebook"
    ];

    /**
     * Rolls a weighted reward entry from a config pool.
     *
     * @param array<int, array<string, mixed>> $pool
     */
    public static function rollEntry(array $pool): ?array{
        $weighted = [];
        $totalWeight = 0;

        foreach($pool as $entry){
            $chance = (int)($entry["chance"] ?? 0);
            if($chance <= 0){
                continue;
            }

            $weighted[] = [
                "weight" => $chance,
                "entry" => $entry
            ];

            $totalWeight += $chance;
        }

        if($totalWeight <= 0){
            return null;
        }

        $roll = mt_rand(1, $totalWeight);
        $current = 0;

        foreach($weighted as $data){
            $current += $data["weight"];
            if($roll <= $current){
                return $data["entry"];
            }
        }

        return null;
    }

    /**
     * Picks a weighted entry and removes it from the pool so the same line cannot be rolled
     * again in the same opening (etereal crates, etc.).
     *
     * @param array<int, array<string, mixed>> $pool
     * @return array<string, mixed>|null
     */
    public static function rollEntryRemove(array &$pool): ?array
    {
        if ($pool === []) {
            return null;
        }
        $idx = self::rollWeightedPoolIndex($pool);
        if ($idx === null) {
            return null;
        }
        $entry = $pool[$idx];
        unset($pool[$idx]);
        $pool = array_values($pool);
        if (!is_array($entry)) {
            return null;
        }
        return $entry;
    }

    /**
     * Stable id for a loot line (after normalizing) to dedupe across main/bonus.
     *
     * @param array<string, mixed> $entry
     */
    public static function fingerprintLootEntry(array $entry): string
    {
        $n = self::normalizeEntry($entry);
        ksort($n);
        $json = json_encode($n, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $json = serialize($n);
        }
        return hash("md5", (string) $json);
    }

    /**
     * @param array<int, array<string, mixed>> $pool
     */
    private static function rollWeightedPoolIndex(array $pool): ?int
    {
        $totalWeight = 0;
        $candidates = [];
        foreach ($pool as $idx => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $chance = (int) ($entry["chance"] ?? 0);
            if ($chance <= 0) {
                continue;
            }
            $candidates[] = ["index" => (int) $idx, "weight" => $chance];
            $totalWeight += $chance;
        }
        if ($totalWeight <= 0 || $candidates === []) {
            return null;
        }
        $roll = mt_rand(1, $totalWeight);
        $current = 0;
        foreach ($candidates as $data) {
            $current += $data["weight"];
            if ($roll <= $current) {
                return $data["index"];
            }
        }
        return $candidates[array_key_last($candidates)]["index"] ?? null;
    }

    /**
     * Resolves a config reward entry into an Item.
     *
     * @param array<string, mixed> $entry
     */
    public static function resolveItem(array $entry, ?Player $player = null): ?Item{
        $entry = self::normalizeEntry($entry);
        $type = strtolower((string)($entry["type"] ?? "item"));

        $item = match($type){
            "item" => self::resolveGenericItem($entry, $player),
            "enchant_book", "enchantment_book", "enchanted_book", "cebook" => self::resolveFactoryItem("enchant_book", $entry, $player),
            "money_note", "bank_note", "econote" => self::resolveFactoryItem("bank_note", $entry, $player),
            "xp_note", "xpnote", "xpbottle" => self::resolveFactoryItem("xp_note", $entry, $player),
            "lootbox" => self::resolveLootboxItem($entry, $player),
            "ethereal_crate", "etherealcrate" => self::resolveEtherealCrateItem($entry),
            default => self::resolveGenericItem($entry, $player)
        };

        if ($item === null) {
            $item = GlaciaItemFactory::invalidConfigItem(self::describeEntry($entry, $type));
        }

        self::notifyInvalidConfigItem($item, $player);
        return $item;
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    public static function normalizeEntry(array $entry): array{
        $normalized = $entry;

        if(isset($normalized["item"]) && !isset($normalized["material"]) && !isset($normalized["id"])){
            $normalized["material"] = $normalized["item"];
        }

        if(isset($normalized["display"]) && !isset($normalized["name"])){
            $normalized["name"] = $normalized["display"];
        }

        if(isset($normalized["identifier"]) && !isset($normalized["id"])){
            $normalized["id"] = $normalized["identifier"];
        }

        if(isset($normalized["box"]) && !isset($normalized["id"])){
            $normalized["id"] = $normalized["box"];
        }

        if(isset($normalized["lootbox"]) && !isset($normalized["id"])){
            $normalized["id"] = $normalized["lootbox"];
        }

        if(isset($normalized["amount"]) && !isset($normalized["count"])){
            $identifier = strtolower((string) ($normalized["id"] ?? $normalized["material"] ?? ""));
            if(!self::usesAmountAsValue($identifier, (string) ($normalized["type"] ?? "item"))){
                $normalized["count"] = $normalized["amount"];
            }
        }

        $type = strtolower((string) ($normalized["type"] ?? "item"));
        if($type !== "" && !in_array($type, self::RESERVED_TYPES, true) && !isset($normalized["id"]) && !isset($normalized["material"])){
            $normalized["id"] = $type;
            $normalized["type"] = "item";
            $type = "item";
        }

        if($type === "lootbox" && !isset($normalized["id"])){
            $normalized["id"] = $normalized["material"] ?? $normalized["item-name"] ?? null;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private static function resolveGenericItem(array $entry, ?Player $player = null): ?Item{
        $item = self::resolveBaseItem($entry, $player);

        if($item === null){
            return null;
        }

        $countInput = $entry["count"] ?? self::getDefaultStackCount($entry);
        $count = GeneralUtils::parseRandomNumber($countInput);
        $item->setCount(max(1, $count));

        if(isset($entry["name"])){
            $item->setCustomName(C::colorize((string)$entry["name"]));
        }

        if(isset($entry["lore"]) && is_array($entry["lore"])){
            $extraLore = array_map(
                fn(string $line) => C::colorize($line),
                $entry["lore"]
            );
            $item->setLore([...$item->getLore(), ...$extraLore]);
        }

        if(isset($entry["enchantments"]) && is_array($entry["enchantments"])){
            self::applyEnchantments($item, $entry["enchantments"]);
        }

        if(isset($entry["color"])){
            self::applyColor($item, $entry["color"]);
        }

        if(self::shouldApplyTransmog($entry)){
            $item = GlaciaItemFactory::applyTransmog($item);
        }

        $item = self::applyConfiguredRune($item, $entry);
        $item = self::applyConfiguredHeroic($item, $entry);

        return $item;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private static function resolveBaseItem(array $entry, ?Player $player = null): ?Item
    {
        foreach (["id", "material"] as $key) {
            if (!isset($entry[$key])) {
                continue;
            }

            $identifier = (string) $entry[$key];
            $item = StringToItemParser::getInstance()->parse($identifier);
            if ($item !== null) {
                return $item;
            }

            $item = self::resolveFactoryItem($identifier, $entry, $player);
            if ($item !== null) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private static function resolveFactoryItem(string $id, array $entry, ?Player $player = null): ?Item {
        $id = strtolower($id);

        $lootbox = self::resolveLootboxItem([
            "id" => $id
        ] + $entry, $player);
        if($lootbox !== null){
            return $lootbox;
        }

        $etherealCrate = self::resolveEtherealCrateItem([
            "id" => $id
        ] + $entry, $player);
        if ($etherealCrate !== null) {
            return $etherealCrate;
        }

        $customArmor = self::resolveCustomArmorItem($id, $entry);
        if ($customArmor !== null) {
            return $customArmor;
        }

        $customWeapon = self::resolveCustomWeaponItem($id, $entry);
        if ($customWeapon !== null) {
            return $customWeapon;
        }

        if (in_array($id, ["enchant_book", "enchantment_book", "enchanted_book", "cebook"])) {
            return self::resolveDirectEnchantmentBook($entry);
        }

        if ($id === "title_voucher" || $id === "titlevoucher") {
            $titleId = strtolower(trim((string) ($entry["title"] ?? $entry["title_id"] ?? $entry["id"] ?? "")));
            if ($titleId === "" || $titleId === "title_voucher" || $titleId === "titlevoucher") {
                return null;
            }

            return GlaciaItemFactory::create("title_voucher_" . $titleId);
        }

        return match($id) {
            "money_note", "bank_note", "moneynote", "econote" => GlaciaItemFactory::bankNote($player, GeneralUtils::parseRandomNumber($entry["amount"] ?? 1)),
            "xpbottle", "xp_note", "xpnote" => GlaciaItemFactory::xpNote($player, GeneralUtils::parseRandomNumber($entry["amount"] ?? 1)),
            "admin_coin_note", "admin_coins", "admincoins", "admin_coin" => GlaciaItemFactory::adminCoinNote($player, GeneralUtils::parseRandomNumber($entry["amount"] ?? 1)),

            "simplebook", "simple_book" => GlaciaItemFactory::randomEnchantBook("simple"),
            "uniquebook", "unique_book" => GlaciaItemFactory::randomEnchantBook("unique"),
            "elitebook", "elite_book" => GlaciaItemFactory::randomEnchantBook("elite"),
            "ultimatebook", "ultimate_book", "exoticbook", "exotic_book" => GlaciaItemFactory::randomEnchantBook("exotic"),
            "legendarybook", "legendary_book" => GlaciaItemFactory::randomEnchantBook("legendary"),
            "masterybook", "mastery_book" => GlaciaItemFactory::randomEnchantBook("mastery"),

            "whitescroll", "white_scroll" => GlaciaItemFactory::whitescroll(),
            "holy_whitescroll", "holywhite_scroll", "holy_white_scroll", "holywhitescroll" => GlaciaItemFactory::create("holy_whitescroll"),
            "holy_water", "holywater" => GlaciaItemFactory::create("holy_water"),
            "blackscroll", "black_scroll" => GlaciaItemFactory::blackscroll(self::parsePercentValue($entry["success"] ?? "{90-100}")),
            "renametag", "itemnametag", "rename_tag", "item_name_tag" => GlaciaItemFactory::renametag(),
            "transmog", "transmog_scroll", "transmogscroll" => GlaciaItemFactory::create("transmog"),
            "heroic_upgrade", "heroicupgrade", "heroic_crystal", "heroic_crystal_upgrade" => GlaciaItemFactory::heroicUpgrade(
                isset($entry["success"]) ? self::parsePercentValue($entry["success"]) : null
            ),
            "charm_shard", "charmshard" => GlaciaItemFactory::charmShard(
                isset($entry["success"]) ? self::parsePercentValue($entry["success"]) : null,
                max(0, GeneralUtils::parseRandomNumber($entry["bonus_success"] ?? $entry["bonus"] ?? 0))
            ),
            "charm_dust", "charmdust" => GlaciaItemFactory::charmDust(
                isset($entry["bonus"]) ? max(1, GeneralUtils::parseRandomNumber($entry["bonus"])) : null
            ),
            "chunkbuster", "chunk_buster" => GlaciaItemFactory::create("chunk_buster"),
            "collectionchest", "collection_chest" => GlaciaItemFactory::collectionChest(),
            "soul_compass", "soulcompass" => GlaciaItemFactory::soulCompass(isset($entry["uses"]) ? GeneralUtils::parseRandomNumber($entry["uses"]) : null),
            "orb", "armor_orb", "weapon_orb", "tool_orb" => self::resolveOrbItem($id, $entry),

            "simpledp" => GlaciaItemFactory::dropPackage("simple_chest"),
            "uniquedp" => GlaciaItemFactory::dropPackage("unique_chest"),
            "elitedp" => GlaciaItemFactory::dropPackage("elite_chest"),
            "ultimatedp" => GlaciaItemFactory::dropPackage("exotic_chest"),
            "legendarydp" => GlaciaItemFactory::dropPackage("legendary_chest"),
            "godlydp", "goddp" => GlaciaItemFactory::dropPackage("godly_chest"),


            "simpledust" => self::resolveDustItem("simple", $entry),
            "uniquedust" => self::resolveDustItem("unique", $entry),
            "elitedust" => self::resolveDustItem("elite", $entry),
            "ultimatedust" => self::resolveDustItem("exotic", $entry),
            "legendarydust" => self::resolveDustItem("legendary", $entry),

            "simpleprimaldust", "simple_primal_dust" => self::resolveDustItem("simple_primal", $entry),
            "uniqueprimaldust", "unique_primal_dust" => self::resolveDustItem("unique_primal", $entry),
            "eliteprimaldust", "elite_primal_dust" => self::resolveDustItem("elite_primal", $entry),
            "ultimateprimaldust", "ultimate_primal_dust", "exoticprimaldust", "exotic_primal_dust" => self::resolveDustItem("exotic_primal", $entry),
            "legendaryprimaldust", "legendary_primal_dust" => self::resolveDustItem("legendary_primal", $entry),

            "mysterymobspawner" => GlaciaItemFactory::mysteryMobSpawner(),
            'heroicmysterymobspawner', 'heroic_mysterymobspawner', 'heroic_mystery_mob_spawner' => GlaciaItemFactory::heroicMysteryMobSpawner(),
            "slot_ticket" => GlaciaItemFactory::slotTicket(),
            "slotticket" => GlaciaItemFactory::slotTicket(),
            "heroic_slot_ticket" => GlaciaItemFactory::heroicSlotTicket(),
            "heroicslotticket" => GlaciaItemFactory::heroicSlotTicket(),
            "item_flip_credit_voucher", "itemflipcreditvoucher", "if_credit_voucher", "icf_voucher" => GlaciaItemFactory::itemFlipCreditVoucher(
                isset($entry["credits"]) ? max(1, GeneralUtils::parseRandomNumber((string) $entry["credits"])) : 10
            ),
            "envoy_beacon", "envoybeacon", "force_envoy", "envoy_starter" => GlaciaItemFactory::create("envoy_beacon"),
            "disguise_unlock", "disguiseunlock", "disguise" => GlaciaItemFactory::disguiseUnlock((string) ($entry["disguise"] ?? $entry["mob"] ?? $entry["type_id"] ?? "")),
            "faction_xp_booster", "factionxpbooster" => GlaciaItemFactory::factionXpBooster(
                (float) ($entry["multiplier"] ?? 2.0),
                (string) ($entry["duration"] ?? "15m")
            ),
            "slotkeeper_rune", "slot_saver_rune", "slotticket_rune" => GlaciaItemFactory::rune("slotkeeper"),
            "celerity_rune", "gears_rune", "gears5_rune" => GlaciaItemFactory::rune("celerity"),
            "colossus_rune", "overload_rune", "overload4_rune" => GlaciaItemFactory::rune("colossus"),
            "bloodfang_rune", "combat_rune", "lifesteal_negation_rune" => GlaciaItemFactory::rune("bloodfang"),
            "verdant_rune", "crop_rune", "farm_rune" => GlaciaItemFactory::rune("verdant"),
            "scholar_rune", "booksave_rune", "book_save_rune" => GlaciaItemFactory::rune("scholar"),
            "envoy_vacuum_rune", "envoy_rune", "envoy_autocollect_rune" => GlaciaItemFactory::rune("envoy_vacuum"),
            "raider_edge_rune", "zone_double_drop_rune" => GlaciaItemFactory::rune("raider_edge"),
            "null_field_rune", "potion_break_rune" => GlaciaItemFactory::rune("null_field"),
            "echo_cache_rune", "lootbox_save_rune" => GlaciaItemFactory::rune("echo_cache"),
            "hemoguard_rune", "lifesteal_immune_rune" => GlaciaItemFactory::rune("hemoguard"),
            "chronoshift_rune", "booster_time_rune" => GlaciaItemFactory::rune("chronoshift"),
            "merchant_sigil_rune", "sell_x2_rune" => GlaciaItemFactory::rune("merchant_sigil"),
            "tidehook_rune", "fishing_speed_rune" => GlaciaItemFactory::rune("tidehook"),
            "solitary_hunt_rune", "solo_zone_rune" => GlaciaItemFactory::rune("solitary_hunt"),
            "envoy_echo_rune", "envoy_extra_rune" => GlaciaItemFactory::rune("envoy_echo"),
            "swiftbane_rune", "slowness_immune_rune" => GlaciaItemFactory::rune("swiftbane"),
            "sigil_of_surge_rune", "ce_plus_rune" => GlaciaItemFactory::rune("sigil_of_surge"),
            "reaper_oath_rune", "boss_execute_rune" => GlaciaItemFactory::rune("reaper_oath"),
            "bottomless_gullet_rune", "hunger_tax_rune" => GlaciaItemFactory::rune("bottomless_gullet"),
            "stormward_rune", "stormcaller_immune_rune" => GlaciaItemFactory::rune("stormward"),
            "beastclock_rune", "pet_cooldown_rune" => GlaciaItemFactory::rune("beastclock"),
            "harvester_mark_rune", "inquisitive_style_rune" => GlaciaItemFactory::rune("harvester_mark"),
            "pet_duration_rune", "pet_rune", "attunement_rune" => GlaciaItemFactory::petDurationRune((string) ($entry["pet"] ?? $entry["pet_id"] ?? "gale_fox")),
            "rune_remover", "rune_scrubber", "rune_extractor" => GlaciaItemFactory::runeRemover(),
            "gkit_gem", "gkitgem" => self::resolveGKitGem($entry),
            "gkit_summoner", "gkitsummoner", "fallenhero", "fallen_hero", "warlord_summoner", "warlordsummoner", "warlord" => self::resolveGKitSummoner($entry),

            "blaze_spawner" => GlaciaItemFactory::mobSpawner("blaze"),
            "creeper_spawner" => GlaciaItemFactory::mobSpawner("creeper"),
            "enderman_spawner" => GlaciaItemFactory::mobSpawner("enderman"),
            "ghast_spawner" => GlaciaItemFactory::mobSpawner("ghast"),
            "magma_cube_spawner" => GlaciaItemFactory::mobSpawner("magma_cube"),
            "skeleton_spawner" => GlaciaItemFactory::mobSpawner("skeleton"),
            "slime_spawner" => GlaciaItemFactory::mobSpawner("slime"),
            "squid_spawner" => GlaciaItemFactory::mobSpawner("squid"),
            "zombie_spawner" => GlaciaItemFactory::mobSpawner("zombie"),
            "zombie_pigman_spawner" => GlaciaItemFactory::mobSpawner("zombie_pigman"),
            "iron_golem_spawner" => GlaciaItemFactory::mobSpawner("iron_golem"),
            "cow_spawner" => GlaciaItemFactory::mobSpawner("cow"),
            "mooshroom_spawner" => GlaciaItemFactory::mobSpawner("mooshroom"),
            "pig_spawner" => GlaciaItemFactory::mobSpawner("pig"), 
            "chicken_spawner" => GlaciaItemFactory::mobSpawner("chicken"),
            "sheep_spawner" => GlaciaItemFactory::mobSpawner("sheep"),
            'spider_spawner' => GlaciaItemFactory::mobSpawner("spider"),
            'snowman_spawner', 'snow_golem_spawner' => GlaciaItemFactory::mobSpawner("snowman"),

            'dormant_sigil' => GlaciaItemFactory::questSigil('dormant'),
            'awakened_sigil' => GlaciaItemFactory::questSigil('awakened'),
            'ascendent_sigil' => GlaciaItemFactory::questSigil('ascendent'),
            'eternal_sigil' => GlaciaItemFactory::questSigil('eternal'),

            'astral_devourer' => GlaciaItemFactory::boss("astral_devourer"),
            'titan_knight' => GlaciaItemFactory::boss("titan_knight"),

            default => self::resolveRegisteredGlaciaItem($id)
        };
    }

    private static function resolveRegisteredGlaciaItem(string $id): ?Item
    {
        try {
            return GlaciaItemFactory::create($id);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    private static function notifyInvalidConfigItem(?Item $item, ?Player $player): void
    {
        if ($item === null || $player === null) {
            return;
        }

        $glacia = $item->getNamedTag()->getCompoundTag("Glacia");
        if ($glacia?->getString("glaciaItem", "") !== "invalid_config_item") {
            return;
        }

        $player->sendMessage(C::colorize("&r&cReward config has an unknown Glacia item id: &f" . $glacia->getString("invalid_id", "unknown")));
    }

    /**
     * @param array<string, mixed> $entry
     */
    private static function describeEntry(array $entry, string $type): string
    {
        foreach (["id", "material", "item", "enchant", "lootbox", "box"] as $key) {
            if (isset($entry[$key]) && trim((string) $entry[$key]) !== "") {
                return "{$type}:{$key}=" . trim((string) $entry[$key]);
            }
        }

        return $type !== "" ? $type : "missing_reward_identifier";
    }

    /**
     * @param array<string, mixed> $entry
     */
    private static function resolveLootboxItem(array $entry, ?Player $player = null): ?Item{
        $lookup = (string) ($entry["id"] ?? $entry["box"] ?? $entry["lootbox"] ?? "");
        if($lookup === ""){
            return null;
        }

        if(LootboxRepository::getBoxByLookup($lookup) === null){
            return null;
        }

        return GlaciaItemFactory::lootbox($lookup, $player);
    }

    /**
     * @param array<string, mixed> $entry
     */
    private static function resolveEtherealCrateItem(array $entry, ?Player $player = null): ?Item
    {
        $lookup = (string) ($entry["id"] ?? $entry["crate"] ?? $entry["ethereal_crate"] ?? "");
        if ($lookup === "") {
            return null;
        }

        if (EtherealCrateRepository::getCrateByLookup($lookup) === null) {
            return null;
        }

        return GlaciaItemFactory::etherealCrate($lookup, $player);
    }

    private static function resolveDirectEnchantmentBook(array $entry): ?Item {
        $rawEnchantName = strtolower(trim((string) ($entry["enchant"] ?? "")));
        if ($rawEnchantName === "") {
            return null;
        }

        $level = max(1, GeneralUtils::parseRandomNumber($entry["level"] ?? 1));
        $success = isset($entry["success"]) ? self::parsePercentValue($entry["success"]) : 50;
        $destroy = isset($entry["destroy"]) ? self::parsePercentValue($entry["destroy"]) : 50;

        $customEnchant = CustomEnchantments::getEnchantmentByName(self::normalizeEnchantIdentifier($rawEnchantName))
            ?? CustomEnchantments::getEnchantmentByName($rawEnchantName);

        if ($customEnchant !== null) {
            return GlaciaItemFactory::enchantmentBook($customEnchant, $level, $success, $destroy);
        }

        return GlaciaItemFactory::vanillaEnchantmentBook($rawEnchantName, $level, $success, $destroy);
    }


    private static function resolveCustomArmorItem(string $id, array $entry): ?Item {
        if (!preg_match('/^([a-z0-9]+)_(helmet|chestplate|leggings|boots)$/', $id, $matches)) {
            return null;
        }

        $set = $matches[1];
        $piece = $matches[2];
        $enchanted = (bool)($entry["enchanted"] ?? true);

        return GlaciaItemFactory::customArmor($set, $piece, $enchanted);
    }

    private static function resolveCustomWeaponItem(string $id, array $entry): ?Item {
        if (!preg_match('/^([a-z0-9]+)_(sword|bow)$/', $id, $matches)) {
            return null;
        }

        $set = $matches[1];
        $type = $matches[2];

        return GlaciaItemFactory::customWeapon($set, $type);
    }

    /**
     * @param array<string, mixed> $entry
     */
    private static function resolveDustItem(string $group, array $entry): Item{
        if(isset($entry["percent"])){
            return GlaciaItemFactory::magicDust($group, self::parsePercentValue($entry["percent"]));
        }

        return GlaciaItemFactory::randomMagicDust($group);
    }

    /**
     * @param array<string, mixed> $entry
     */
    private static function resolveOrbItem(string $id, array $entry): Item
    {
        $type = match ($id) {
            "armor_orb" => "armor",
            "weapon_orb" => "weapon",
            "tool_orb" => "tool",
            default => strtolower((string) ($entry["orb_type"] ?? $entry["type"] ?? "armor"))
        };

        $max = max(1, GeneralUtils::parseRandomNumber($entry["max"] ?? $entry["slots"] ?? 12));
        $new = max(1, GeneralUtils::parseRandomNumber($entry["new"] ?? max(1, $max - 9)));
        $success = self::parsePercentValue($entry["success"] ?? 100);

        return GlaciaItemFactory::enchantOrb($type, $max, $new, $success);
    }

    /**
     * @param array<int, array<string, mixed>> $enchantments
     */
    private static function applyEnchantments(Item $item, array $enchantments): void{
        foreach($enchantments as $enchantmentData){
            $enchantName = self::normalizeEnchantIdentifier((string)($enchantmentData["enchant"] ?? ""));
            $level = GeneralUtils::parseRandomNumber($enchantmentData["level"] ?? 1);

            if($enchantName === ""){
                continue;
            }

            // Try vanilla enchant parser first
            $vanilla = StringToEnchantmentParser::getInstance()->parse($enchantName);
            if($vanilla !== null){
                $item->addEnchantment(new EnchantmentInstance($vanilla, $level));
                continue;
            }

            $custom = CustomEnchantments::getEnchantmentByName($enchantName);
            if($custom !== null){
                CustomEnchantmentManager::applyEnchantment($item, $custom, $level);
            }

            continue;
        }
    }

    private static function applyColor(Item $item, mixed $colorData): void{
        $color = self::parseColor($colorData);
        if($color === null){
            return;
        }

        if ($item instanceof Armor) {
            $item->setCustomColor($color);
        }
        
    }

    private static function parseColor(mixed $colorData): ?Color{
        if(is_string($colorData)){
            $colorKey = strtolower(trim($colorData));

            $named = match($colorKey){
                "white" => [255, 255, 255],
                "silver", "light_gray", "lightgrey" => [157, 157, 151],
                "gray", "grey" => [71, 79, 82],
                "black" => [29, 29, 33],
                "red" => [176, 46, 38],
                "orange" => [249, 128, 29],
                "yellow" => [254, 216, 61],
                "lime" => [128, 199, 31],
                "green" => [94, 124, 22],
                "light_blue" => [58, 179, 218],
                "cyan" => [22, 156, 156],
                "blue" => [60, 68, 170],
                "purple" => [137, 50, 184],
                "magenta" => [199, 78, 189],
                "pink" => [243, 139, 170],
                "brown" => [131, 84, 50],
                default => null
            };

            if($named !== null){
                return new Color($named[0], $named[1], $named[2]);
            }

            $hex = ltrim($colorKey, "#");
            if(preg_match('/^[0-9a-f]{6}$/i', $hex) === 1){
                return Color::fromRGB((int) hexdec($hex));
            }
        }

        if(is_array($colorData) && isset($colorData["r"], $colorData["g"], $colorData["b"])){
            return new Color(
                (int) $colorData["r"],
                (int) $colorData["g"],
                (int) $colorData["b"]
            );
        }

        return null;
    }

    private static function parsePercentValue(int|string $input): int{
        return max(1, min(100, GeneralUtils::parseRandomNumber($input)));
    }

    /**
     * @param array<string, mixed> $entry
     */
    private static function resolveGKitGem(array $entry): Item
    {
        $kitId = (string) ($entry["kit"] ?? $entry["gkit"] ?? $entry["warlord"] ?? "unknown");
        $kit = GKitRepository::getGKitById($kitId);
        if ($kit !== null) {
            return GKitRepository::createGem($kit);
        }

        return GlaciaItemFactory::gkitGem(
            $kitId,
            ucfirst($kitId),
            (string) ($entry["name"] ?? "")
        );
    }

    /**
     * @param array<string, mixed> $entry
     */
    private static function resolveGKitSummoner(array $entry): Item
    {
        $kitId = (string) ($entry["kit"] ?? $entry["gkit"] ?? $entry["warlord"] ?? "unknown");
        $kit = GKitRepository::getGKitById($kitId);
        if ($kit !== null) {
            return GKitRepository::createSummoner($kit);
        }

        return GlaciaItemFactory::gkitSummoner(
            $kitId,
            ucfirst($kitId),
            (string) ($entry["name"] ?? "")
        );
    }

    private static function normalizeEnchantIdentifier(string $identifier): string
    {
        return strtolower(str_replace(["_", "-", " "], "", trim($identifier)));
    }

    private static function usesAmountAsValue(string $identifier, string $type): bool
    {
        $identifier = strtolower(trim($identifier));
        $type = strtolower(trim($type));

        return in_array($identifier, [
            "money_note", "bank_note", "econote", "moneynote",
            "xp_note", "xpnote", "xpbottle",
        ], true) || in_array($type, [
            "money_note", "bank_note", "econote",
            "xp_note", "xpnote", "xpbottle",
        ], true);
    }

    /**
     * @param array<string, mixed> $entry
     */
    private static function getDefaultStackCount(array $entry): int|string
    {
        $id = strtolower((string) ($entry["id"] ?? $entry["material"] ?? $entry["type"] ?? ""));

        return match ($id) {
            "money_note", "bank_note", "econote", "xp_note", "xpnote", "moneynote", "xpbottle" => 1,
            default => $entry["amount"] ?? 1
        };
    }

    /**
     * @param array<string, mixed> $entry
     */
    private static function shouldApplyTransmog(array $entry): bool
    {
        foreach (["transmogged", "pretransmogged", "apply_transmog", "transmogged_key"] as $key) {
            if (!array_key_exists($key, $entry)) {
                continue;
            }

            $value = $entry[$key];
            if (is_bool($value)) {
                return $value;
            }

            return in_array(strtolower(trim((string) $value)), ["1", "true", "yes", "on"], true);
        }

        return false;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private static function applyConfiguredRune(Item $item, array $entry): Item
    {
        $runeId = self::getConfiguredRuneId($entry);
        if ($runeId === null) {
            return $item;
        }

        if (!RuneManager::hasDefinition($runeId) || !RuneManager::isApplied($runeId)) {
            return $item;
        }

        if (RuneManager::hasAppliedRune($item) || !RuneManager::canApplyTo($item, $runeId)) {
            return $item;
        }

        return RuneManager::applyRune($item, $runeId);
    }

    /**
     * @param array<string, mixed> $entry
     */
    private static function getConfiguredRuneId(array $entry): ?string
    {
        foreach (["rune", "applied_rune", "preapplied_rune", "bound_rune", "rune_id"] as $key) {
            if (!array_key_exists($key, $entry)) {
                continue;
            }

            $value = strtolower(trim((string) $entry[$key]));
            if ($value !== "") {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private static function applyConfiguredHeroic(Item $item, array $entry): Item
    {
        if (!$item instanceof Durable || !self::isHeroicEnabled($entry)) {
            return $item;
        }

        return HeroicArmorDurabilityManager::upgradeItemToHeroic($item);
    }

    /**
     * @param array<string, mixed> $entry
     */
    private static function isHeroicEnabled(array $entry): bool
    {
        foreach (["heroic", "is_heroic", "heroic_armor"] as $key) {
            if (!array_key_exists($key, $entry)) {
                continue;
            }

            $value = $entry[$key];
            if (is_bool($value)) {
                return $value;
            }

            return in_array(strtolower(trim((string) $value)), ["1", "true", "yes", "on"], true);
        }

        return false;
    }
}
