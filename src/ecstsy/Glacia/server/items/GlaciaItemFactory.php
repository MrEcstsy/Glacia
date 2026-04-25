<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\items;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantments;
use ecstsy\Glacia\server\enchantments\Groups;
use ecstsy\Glacia\server\disguises\DisguiseManager;
use ecstsy\Glacia\server\enchantments\vanilla\ExtendedVanillaEnchantmentRegistry;
use ecstsy\Glacia\server\items\runes\RuneManager;
use ecstsy\Glacia\server\lootboxes\LootboxRepository;
use ecstsy\Glacia\server\quests\QuestInstance;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\StringToEnchantmentParser;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class GlaciaItemFactory {

    public static array $definitions = [];

    public static function create(string $key, array $args = []): Item {
        $definitionKey = self::resolveDefinitionKey($key);
        if (!isset(self::$definitions[$definitionKey])) {
            return self::unknownDefinitionItem($key);
        }

        $def = self::$definitions[$definitionKey];

        /** @var Item $item */
        $item = clone $def['material'];

        $item->setCustomName(C::colorize(self::template($def['name'] ?? '', $args)));
        $loreLines = [];
        foreach ($def['lore'] ?? [] as $line) {
            $resolved = self::template((string) $line, $args);
            foreach (preg_split("/\r\n|\n|\r/", $resolved) ?: [$resolved] as $splitLine) {
                $loreLines[] = C::colorize($splitLine);
            }
        }
        if ($loreLines) {
            $item->setLore($loreLines);
        }

        $root = $item->getNamedTag();
        $aTag = new CompoundTag();
        foreach ($def['nbt'] ?? [] as $tag => $valueTpl) {
            $resolved = self::template((string) $valueTpl, $args);
            if (is_numeric($resolved) && (string)(int)$resolved === $resolved) {
                $aTag->setInt($tag, (int)$resolved);
            } else {
                $aTag->setString($tag, $resolved);
            }
        }
        $root->setTag("Glacia", $aTag);

        return self::decorateRuneLore($item, $definitionKey);
    }

    public static function invalidConfigItem(string $key): Item
    {
        try {
            Loader::getInstance()->getLogger()->warning("Unknown Glacia item definition in config: '{$key}'");
        } catch (\Throwable) {
            // Loader may not be available during very early bootstrap.
        }

        $item = StringToItemParser::getInstance()->parse("barrier") ?? VanillaItems::PAPER();
        $item->setCustomName(C::colorize("&r&l&cInvalid Glacia Item"));
        $item->setLore([
            C::colorize("&r&7A config tried to create an unknown"),
            C::colorize("&r&7Glacia item id: &f{$key}"),
            "",
            C::colorize("&r&cFix the id in the reward config."),
        ]);

        $root = $item->getNamedTag();
        $tag = new CompoundTag();
        $tag->setString("glaciaItem", "invalid_config_item");
        $tag->setString("invalid_id", $key);
        $root->setTag("Glacia", $tag);
        $item->setNamedTag($root);

        return $item;
    }

    private static function unknownDefinitionItem(string $key): Item
    {
        return self::invalidConfigItem($key);
    }

    public static function bankNote(?Player $player, int $amount): Item {
        $signer = $player?->getName() ?? 'Ethereal Hub';
        return self::bankNoteSigned($signer, $amount);
    }

    public static function bankNoteSigned(string $signer, int $amount): Item {
        return self::create('bank_note', [
            'amount' => number_format($amount),
            'rawAmount' => $amount,
            'signer' => $signer
        ]);
    }

    public static function xpNote(?Player $player, int $amount): Item {
        $signer = $player?->getName() ?? 'Ethereal Hub';
        return self::xpNoteSigned($signer, $amount);
    }

    public static function xpNoteSigned(string $signer, int $amount): Item {
        return self::create('xpnote', [
            'amount' => number_format($amount),
            'rawAmount' => $amount,
            'signer' => $signer
        ]);
    }

    public static function boss(string $boss): Item {
        return self::create("boss_$boss", ['boss' => strtolower($boss)]);
    }

    public static function enchantmentBook(CustomEnchantment $enchantment, int $level = 1, ?int $forcedSuccessChance = null, ?int $forcedDestroyChance = null): ?Item {
        $success = $forcedSuccessChance !== null ? $forcedSuccessChance : mt_rand(1, 100);
        $destroy = $forcedDestroyChance !== null ? $forcedDestroyChance : mt_rand(1, 100);

        $appliesTo = array_map('ucfirst', $enchantment->getApplicableItems());

        return self::create('enchantment_book', [
            'enchantment' => CustomEnchantments::getEnchantmentDisplayName($enchantment->getName(), C::colorize(Groups::translateGroupToColor($enchantment->getRarity()))),
            'enchant-no-color' => $enchantment->getName(),
            'level' => $level,
            'roman-level' => GeneralUtils::getRomanNumeral($level),
            'success' => $success,
            'destroy' => $destroy,
            'description' => $enchantment->getDescription(),
            'group-color' => C::colorize(Groups::translateGroupToColor($enchantment->getRarity())),
            'applies-to' => implode(", ", $appliesTo),
            'book-type' => 'custom',
            'vanilla-enchant' => '',
        ]);
    }

    public static function vanillaEnchantmentBook(string $enchantName, int $level = 1, ?int $forcedSuccessChance = null, ?int $forcedDestroyChance = null): ?Item
    {
        $normalized = strtolower(trim($enchantName));
        $enchantment = self::resolveVanillaEnchantment($normalized);
        if ($enchantment === null) {
            return null;
        }

        $success = $forcedSuccessChance ?? 50;
        $destroy = $forcedDestroyChance ?? 50;

        return self::create('enchantment_book', [
            'enchantment' => self::formatVanillaEnchantmentName($normalized),
            'enchant-no-color' => $normalized,
            'level' => $level,
            'roman-level' => GeneralUtils::getRomanNumeral($level),
            'success' => $success,
            'destroy' => $destroy,
            'description' => self::getVanillaEnchantmentDescription($normalized),
            'group-color' => '&7',
            'applies-to' => self::getVanillaAppliesToText($enchantment),
            'book-type' => 'vanilla',
            'vanilla-enchant' => $normalized,
        ]);
    }

    public static function enchantOrb(string $type, int $max, int $new, int $success): ?Item {
        if (!in_array($type, ['weapon', 'armor', 'tool'])) {
            throw new \InvalidArgumentException("Invalid orb type: $type");
        }

        return self::create('orb', [
            'type' => ucfirst($type),
            'upper-type' => strtoupper($type),
            'low_type' => strtolower($type),
            'max' => $max,
            'new' => $new,
            'success' => $success,
        ]);
    }

    public static function whitescroll(): Item{ 
        return self::create('whitescroll');
    }

    public static function blackscroll(?int $success): Item {
        if ($success === null) {
            $success = mt_rand(1, 100);
        }

        return self::create('blackscroll', [
            'success' => $success
        ]);
    }

    public static function renametag(): Item {
        return self::create('renametag');
    }

    public static function randomEnchantBook(string $group): Item {
        return self::create('random_book', [
            'group-name' => ucfirst($group),
            'group-color' => C::colorize(Groups::translateGroupToColor(Groups::getGroupId($group))),
            'group' => $group,
        ]);
    }

    /**
     * Available packages: simple_chest, unique_chest, elite_chest, ultimate_chest, legendary_chest, godly_chest
     */
    public static function dropPackage(string $package): Item {
        return self::create('ethereal_' . $package);
    }

    public static function mysteryMobSpawner(): Item {
        return self::create('mysterymobspawner');
    }

    public static function heroicMysteryMobSpawner(): Item {
        return self::create('heroicmysterymobspawner');
    }

    public static function randomMagicDust(string $group): Item {
        return self::create('random_magicdust', [
            'group-name' => ucfirst($group),
            'group-color' => C::colorize(Groups::translateGroupToColor(Groups::getGroupId($group))),
            'group' => $group,
        ]);
    }


    public static function primalDust(string $group, int $percentage = 100): Item {
        return self::create('primal_dust', [
            'group-name' => ucfirst($group),
            'group-color' => C::colorize(Groups::translateGroupToColor(Groups::getGroupId($group))),
            'group' => $group,
            'dust-percent' => $percentage
        ]);
    }

    public static function magicDust(string $group, int $percentage = 100): Item {
        return self::create('magicdust', [
            'group-name' => ucfirst($group),
            'group-color' => C::colorize(Groups::translateGroupToColor(Groups::getGroupId($group))),
            'group' => $group,
            'dust-percent' => $percentage
        ]);
    }

    public static function mysteryDust(): Item {
        return self::create('mysterydust');
    } 

    public static function lootbox(string $lookup): Item {
        $box = LootboxRepository::getBoxByLookup($lookup);
        if ($box === null) {
            throw new \InvalidArgumentException("Unknown lootbox: {$lookup}");
        }

        $icon = StringToItemParser::getInstance()->parse((string) ($box["icon"] ?? "chest")) ?? StringToItemParser::getInstance()->parse("chest");
        $item = clone $icon;
        $item->setCustomName(C::colorize((string) ($box["item-name"] ?? $box["name"] ?? "&r&l&dLootbox")));

        $lore = $box["item-lore"] ?? [
            "&r&7Right-click to open this lootbox.",
            "&r&7Contains randomized rewards."
        ];
        if (is_array($lore)) {
            $item->setLore(array_map(static fn(string $line): string => C::colorize($line), $lore));
        }

        if ((bool) ($box["glow"] ?? false)) {
            GlaciaUtils::applyDisplayEnchant($item);
        } else {
            GlaciaUtils::removeDisplayEnchant($item);
        }

        $root = $item->getNamedTag();
        $tag = new CompoundTag();
        $tag->setString("glaciaItem", "lootbox");
        $tag->setString("lootbox", (string) $box["id"]);
        $root->setTag("Glacia", $tag);
        $item->setNamedTag($root);

        return $item;
    }

    public static function disguiseUnlock(string $id): Item
    {
        $id = DisguiseManager::normalizeId($id);
        if (isset(self::$definitions["disguise_" . $id])) {
            return self::create("disguise_" . $id);
        }

        return self::create("disguise_unlock", [
            "disguise" => $id,
            "display" => DisguiseManager::getDisplayName($id),
            "description" => DisguiseManager::getDescription($id),
        ]);
    }

    public static function rune(string $runeId): Item
    {
        return self::create($runeId . "_rune");
    }

    public static function runeRemover(): Item
    {
        return self::create("rune_remover");
    }

    public static function petDurationRune(string $petKey): Item
    {
        $petKey = strtolower(trim($petKey));
        return self::create("pet_duration_rune", [
            "pet" => $petKey,
            "pet-display" => RuneManager::getPetDisplay($petKey),
        ]);
    }

    private static function resolveDefinitionKey(string $key): string
    {
        if (isset(self::$definitions[$key])) {
            return $key;
        }

        $normalized = self::normalizeDefinitionKey($key);
        foreach (array_keys(self::$definitions) as $existingKey) {
            if (self::normalizeDefinitionKey($existingKey) === $normalized) {
                return $existingKey;
            }
        }

        return $key;
    }

    private static function normalizeDefinitionKey(string $key): string
    {
        return strtolower(str_replace(["_", "-", " "], "", $key));
    }

    private static function formatVanillaEnchantmentName(string $enchantName): string
    {
        return implode(' ', array_map(
            static fn(string $part): string => ucfirst($part),
            preg_split('/[_\-\s]+/', $enchantName) ?: [$enchantName]
        ));
    }

    public static function getVanillaEnchantmentDescription(string $enchantName): string
    {
        return match ($enchantName) {
            'aqua_affinity' => 'Mine underwater blocks at normal speed.',
            'bane_of_arthropods' => 'Deals extra damage to spiders and arthropods.',
            'blast_protection' => 'Reduces explosion damage taken.',
            'channeling' => 'Calls lightning during storms with thrown tridents.',
            'depth_strider' => 'Move faster while underwater.',
            'efficiency' => 'Breaks blocks faster.',
            'feather_falling' => 'Reduces fall damage taken.',
            'fire_aspect' => 'Sets targets on fire.',
            'fire_protection' => 'Reduces fire and lava damage taken.',
            'flame' => 'Sets arrows on fire.',
            'fortune' => 'Increases block drop yields.',
            'frost_walker' => 'Freezes water beneath your feet.',
            'impaling' => 'Deals extra trident damage to aquatic mobs.',
            'infinity' => 'Fires arrows without consuming them.',
            'knockback' => 'Knocks enemies farther away.',
            'looting' => 'Increases mob drop amounts.',
            'loyalty' => 'Returns a thrown trident to its owner.',
            'luck_of_the_sea' => 'Improves fishing luck.',
            'lure' => 'Makes fish bite faster.',
            'mending' => 'Repairs gear using experience.',
            'multishot' => 'Fires multiple projectiles at once.',
            'piercing' => 'Crossbow bolts pierce through targets.',
            'power' => 'Increases arrow damage.',
            'projectile_protection' => 'Reduces damage from projectiles.',
            'protection' => 'Reduces incoming damage.',
            'punch' => 'Increases bow knockback.',
            'quick_charge' => 'Loads a crossbow faster.',
            'respiration' => 'Extends underwater breathing time.',
            'riptide' => 'Launches you with a trident through water or rain.',
            'sharpness' => 'Increases melee damage dealt.',
            'silk_touch' => 'Mined blocks drop themselves.',
            'smite' => 'Deals extra damage to undead mobs.',
            'soul_speed' => 'Move faster on soul sand and soul soil.',
            'swift_sneak' => 'Sneak faster while crouching.',
            'thorns' => 'Damages attackers when hit.',
            'unbreaking' => 'Reduces durability loss.',
            default => 'Applies the ' . self::formatVanillaEnchantmentName($enchantName) . ' enchantment.',
        };
    }

    public static function resolveVanillaEnchantment(string $enchantName): ?Enchantment
    {
        $normalized = strtolower(trim($enchantName));
        $parsed = StringToEnchantmentParser::getInstance()->parse($normalized);
        if ($parsed !== null) {
            return $parsed;
        }

        $extended = ExtendedVanillaEnchantmentRegistry::resolve($normalized);
        if ($extended !== null) {
            return $extended;
        }

        $compact = str_replace(['_', '-', ' '], '', $normalized);

        return match ($compact) {
            'protection' => VanillaEnchantments::PROTECTION(),
            'fireprotection' => VanillaEnchantments::FIRE_PROTECTION(),
            'featherfalling' => VanillaEnchantments::FEATHER_FALLING(),
            'blastprotection' => VanillaEnchantments::BLAST_PROTECTION(),
            'projectileprotection' => VanillaEnchantments::PROJECTILE_PROTECTION(),
            'thorns' => VanillaEnchantments::THORNS(),
            'respiration' => VanillaEnchantments::RESPIRATION(),
            'aquaaffinity' => VanillaEnchantments::AQUA_AFFINITY(),
            'sharpness' => VanillaEnchantments::SHARPNESS(),
            'smite' => VanillaEnchantments::SMITE(),
            'baneofarthropods' => VanillaEnchantments::BANE_OF_ARTHROPODS(),
            'knockback' => VanillaEnchantments::KNOCKBACK(),
            'fireaspect' => VanillaEnchantments::FIRE_ASPECT(),
            'efficiency' => VanillaEnchantments::EFFICIENCY(),
            'silktouch' => VanillaEnchantments::SILK_TOUCH(),
            'unbreaking' => VanillaEnchantments::UNBREAKING(),
            'fortune' => VanillaEnchantments::FORTUNE(),
            'power' => VanillaEnchantments::POWER(),
            'punch' => VanillaEnchantments::PUNCH(),
            'flame' => VanillaEnchantments::FLAME(),
            'infinity' => VanillaEnchantments::INFINITY(),
            'mending' => VanillaEnchantments::MENDING(),
            'vanishing', 'curseofvanishing' => VanillaEnchantments::VANISHING(),
            'swiftsneak' => VanillaEnchantments::SWIFT_SNEAK(),
            'frostwalker' => VanillaEnchantments::FROST_WALKER(),
            default => null,
        };
    }

    private static function getVanillaAppliesToText(Enchantment $enchantment): string
    {
        $class = strtolower((new \ReflectionClass($enchantment))->getShortName());

        return match (true) {
            str_contains($class, 'helmet'),
            str_contains($class, 'aquaaffinity'),
            str_contains($class, 'respiration') => 'Helmet',
            str_contains($class, 'boots'),
            str_contains($class, 'depthstrider'),
            str_contains($class, 'featherfalling'),
            str_contains($class, 'frostwalker'),
            str_contains($class, 'soulspeed'),
            str_contains($class, 'swiftsneak') => 'Boots',
            str_contains($class, 'sword'),
            str_contains($class, 'sharpness'),
            str_contains($class, 'smite'),
            str_contains($class, 'baneofarthropods'),
            str_contains($class, 'fireaspect'),
            str_contains($class, 'knockback'),
            str_contains($class, 'looting') => 'Sword',
            str_contains($class, 'bow'),
            str_contains($class, 'power'),
            str_contains($class, 'punch'),
            str_contains($class, 'flame'),
            str_contains($class, 'infinity') => 'Bow',
            str_contains($class, 'crossbow'),
            str_contains($class, 'multishot'),
            str_contains($class, 'piercing'),
            str_contains($class, 'quickcharge') => 'Crossbow',
            str_contains($class, 'trident'),
            str_contains($class, 'channeling'),
            str_contains($class, 'impaling'),
            str_contains($class, 'loyalty'),
            str_contains($class, 'riptide') => 'Trident',
            str_contains($class, 'efficiency'),
            str_contains($class, 'fortune'),
            str_contains($class, 'silktouch') => 'Tools',
            str_contains($class, 'protection'),
            str_contains($class, 'thorns') => 'Armor',
            default => 'Vanilla',
        };
    }

    public static function tnt(string $type): Item {
        return self::create($type, [
            'type' => $type
        ]);
    }

    public static function cegg(string $type): Item {
        return self::create($type, [
            'type' => $type
        ]);
    }

    public static function customArmor(string $set, ?string $piece = null, bool $enchanted = false): Item|array {
        $set = strtolower($set);
        $piece = $piece !== null ? strtolower($piece) : "full";

        return match ($piece) {
            "helmet", "chestplate", "leggings", "boots" => self::buildCustomArmorPiece($set, $piece, $enchanted),
            "full", "set" => self::customArmorSet($set, $enchanted),
            default => self::unknownDefinitionItem("{$set}_{$piece}")
        };
    }

    public static function customArmorSet(string $set, bool $enchanted = false): array {
        $set = strtolower($set);

        return [
            self::buildCustomArmorPiece($set, "helmet", $enchanted),
            self::buildCustomArmorPiece($set, "chestplate", $enchanted),
            self::buildCustomArmorPiece($set, "leggings", $enchanted),
            self::buildCustomArmorPiece($set, "boots", $enchanted),
        ];
    }

    private static function buildCustomArmorPiece(string $set, string $piece, bool $enchanted = false): Item {
        $identifier = "{$set}_{$piece}";

        if (!isset(self::$definitions[$identifier])) {
            return self::unknownDefinitionItem($identifier);
        }

        $item = self::create($identifier, [
            'type' => $identifier
        ]);

        self::addArmorBaseEnchantments($item);

        return $item;
    }

    private static function addArmorBaseEnchantments(Item $item): void {
        $item->addEnchantment(new EnchantmentInstance(VanillaEnchantments::PROTECTION(), 4));
        $item->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 3));
    }

    public static function customWeapon(string $set, string $type = "sword"): Item {
        $set = strtolower($set);
        $type = strtolower($type);

        $identifier = "{$set}_{$type}";

        if (!isset(self::$definitions[$identifier])) {
            return self::unknownDefinitionItem($identifier);
        }

        $item = self::create($identifier, [
            'type' => $identifier
        ]);

        self::addWeaponBaseEnchantments($item, $type);

        return $item;
    }

    private static function addWeaponBaseEnchantments(Item $item, string $type): void {
        switch (strtolower($type)) {
            case 'bow':
                $item->addEnchantment(new EnchantmentInstance(VanillaEnchantments::POWER(), 5));
                $item->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 3));
                $item->addEnchantment(new EnchantmentInstance(VanillaEnchantments::FLAME(), 2));
                $item->addEnchantment(new EnchantmentInstance(VanillaEnchantments::INFINITY(), 1));
                break;

            default:
                $item->addEnchantment(new EnchantmentInstance(VanillaEnchantments::SHARPNESS(), 5));
                $item->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 3));
                break;
        }
    }

    public static function mobSpawner(string $type): Item {
        return StringToItemParser::getInstance()->parse($type . "_spawner");
    }

    public static function questSigil(string $tier, int $questIndex = 0): Item {
        $player = null; 
        $data = QuestInstance::getTierData($tier);

        if ($data === null) {
            throw new \InvalidArgumentException("Unknown quest sigil tier: {$tier}");
        }

        $material = \pocketmine\item\StringToItemParser::getInstance()->parse($data["material"]) ?? \pocketmine\item\VanillaItems::AMETHYST_SHARD();

        $tierColor = match($tier) {
            "dormant" => "&b",
            "awakened" => "&5",
            "ascendent" => "&e",
            "eternal" => "&c",
            default => "&7",
        };

        $symbol = match($tier) {
            "dormant" => "❄",
            "awakened" => "✦",
            "ascendent" => "✪",
            "eternal" => "☯",
            default => "•",
        };

        $quest = $data["quests"][$questIndex] ?? null;
        if ($quest === null) {
            throw new \InvalidArgumentException("Invalid quest index {$questIndex} for sigil tier {$tier}");
        }

        $item = $material->setCustomName(C::colorize("&r&7{$symbol} {$tierColor}{$data['display']} Sigil &7{$symbol}"));

        $nbt = $item->getNamedTag();
        $nbt->setString("glacia_quest_tier", $tier);
        $nbt->setInt("glacia_quest_index", $questIndex);
        $nbt->setString("glacia_quest_type", $quest["type"]);
        $nbt->setString("glacia_quest_progress", json_encode(array_fill(0, count($quest["objectives"]), 0)));
        $item->setNamedTag($nbt);

        QuestInstance::updateQuestLore($item);

        return $item;
    }

    public static function slotTicket(): Item {
        return self::create("slot_ticket");
    }

    public static function heroicSlotTicket(): Item {
        return self::create("heroic_slot_ticket");
    }

    public static function collectionChest(): Item
    {
        return self::create("collection_chest");
    }

    public static function soulCompass(?int $uses = null): Item
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "envoy.yml");
        $defaultUses = max(1, (int) ($config?->getNested("soul-compass.default-uses", 8) ?? 8));
        $uses = max(1, $uses ?? $defaultUses);

        $item = self::create("soul_compass");
        $item->setLore([
            C::colorize("&r&7Right-click to trace the nearest"),
            C::colorize("&r&7active &bEnvoy crate &7through the warzone."),
            "",
            C::colorize("&r&7Uses: &f" . $uses),
            C::colorize("&r&8The signal weakens with each pulse."),
        ]);

        $root = $item->getNamedTag();
        $tag = $root->getCompoundTag("Glacia") ?? new CompoundTag();
        $tag->setInt("uses", $uses);
        $root->setTag("Glacia", $tag);
        $item->setNamedTag($root);

        return $item;
    }

    public static function factionXpBooster(float $multiplier, string $duration): Item
    {
        $formattedMultiplier = rtrim(rtrim(number_format($multiplier, 2), "0"), ".");
        return self::create("faction_xp_booster", [
            "multiplier" => $formattedMultiplier,
            "duration" => $duration
        ]);
    }

    public static function gkitGem(string $kitId, string $display, string $overrideName = ""): Item
    {
        $cleanDisplay = trim($display) !== "" ? $display : ucfirst($kitId);
        $name = $overrideName !== ""
            ? $overrideName
            : "&r&l&b" . $cleanDisplay . " Gem";

        $item = StringToItemParser::getInstance()->parse("amethyst_shard") ?? StringToItemParser::getInstance()->parse("diamond");
        $item->setCustomName(C::colorize($name));
        $item->setLore([
            C::colorize("&r&7Redeem this shard to unlock or"),
            C::colorize("&r&7upgrade the &f" . $cleanDisplay . " &7GKit."),
            "",
            C::colorize("&r&bHigher levels improve your odds"),
            C::colorize("&r&bof rolling stronger enchantments."),
        ]);

        $root = $item->getNamedTag();
        $tag = $root->getCompoundTag("Glacia") ?? new CompoundTag();
        $tag->setString("glaciaItem", "gkit_gem");
        $tag->setString("kit", strtolower($kitId));
        $root->setTag("Glacia", $tag);
        $item->setNamedTag($root);

        return $item;
    }

    public static function gkitSummoner(string $kitId, string $display, string $overrideName = "", array $overrideLore = []): Item
    {
        $cleanDisplay = trim($display) !== "" ? $display : ucfirst($kitId);
        $name = $overrideName !== ""
            ? $overrideName
            : "&r&8&lWarlord&7: &r&f" . $cleanDisplay;

        $item = StringToItemParser::getInstance()->parse("bone") ?? StringToItemParser::getInstance()->parse("stick");
        $item->setCustomName(C::colorize($name));
        $lore = $overrideLore !== [] ? $overrideLore : [
            "&r&7Call forth the shattered champion of",
            "&r&f" . $cleanDisplay . " &r&7and challenge it in Warzone.",
            "",
            "&r&cWarzone Only",
            "&r&7Defeat it for a chance at a",
            "&r&bGKit Gem &7or a piece of its gear.",
        ];
        $item->setLore(array_map(static fn(string $line): string => C::colorize($line), $lore));

        $root = $item->getNamedTag();
        $tag = $root->getCompoundTag("Glacia") ?? new CompoundTag();
        $tag->setString("glaciaItem", "gkit_summoner");
        $tag->setString("kit", strtolower($kitId));
        $root->setTag("Glacia", $tag);
        $item->setNamedTag($root);

        return $item;
    }

    public static function getGlaciaIdentifier(Item $item): string
    {
        return $item->getNamedTag()->getCompoundTag("Glacia")?->getString("glaciaItem", "") ?? "";
    }

    public static function applyTransmog(Item $item): Item
    {
        $root = $item->getNamedTag();
        $tag = $root->getCompoundTag("Glacia") ?? new CompoundTag();
        $tag->setByte("transmog", 1);
        $root->setTag("Glacia", $tag);
        $item->setNamedTag($root);

        return $item;
    }

    public static function isGlaciaItem(Item $item, string $identifier): bool
    {
        return self::getGlaciaIdentifier($item) === $identifier;
    }

    private static function template(string $template, array $args): string {
        foreach ($args as $k => $v) {
            $template = str_replace("{" . $k . "}", (string)$v, $template);
            $template = str_replace("{{" . $k . "}}", (string)$v, $template);
        }

        return str_replace('\n', "\n", $template);
    }

    /**
     * @param list<string> $lore
     * @return list<string>
     */
    private static function trimTrailingBlankLoreLines(array $lore): array
    {
        while ($lore !== [] && C::clean((string) end($lore)) === "") {
            array_pop($lore);
        }

        return array_values($lore);
    }

    private static function decorateRuneLore(Item $item, string $definitionKey): Item
    {
        if ($definitionKey === "rune_remover") {
            return self::appendRuneDetailLore(
                $item,
                "&r&8- &7Success Rate: &a" . RuneManager::getRemovalChance() . "%"
            );
        }

        if (str_ends_with($definitionKey, "_rune")) {
            $runeId = strtolower(substr($definitionKey, 0, -5));
            if (RuneManager::isApplied($runeId)) {
                return self::appendRuneDetailLore(
                    $item,
                    "&r&8- &7Success Rate: &a" . RuneManager::getSuccessChance($runeId) . "%"
                );
            }
        }

        return $item;
    }

    private static function appendRuneDetailLore(Item $item, string $detailLine): Item
    {
        $lore = self::trimTrailingBlankLoreLines($item->getLore());
        if ($lore !== []) {
            $lore[] = "";
        }

        $lore[] = C::colorize($detailLine);
        $item->setLore($lore);
        return $item;
    }
}
