<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\items;

use customiesdevs\customies\item\CustomiesItemFactory;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantments;
use ecstsy\Glacia\server\enchantments\Groups;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\enchantments\manager\SoulGemSessionManager;
use ecstsy\Glacia\server\disguises\DisguiseManager;
use ecstsy\Glacia\server\etherealcrates\EtherealCrateRepository;
use ecstsy\Glacia\server\enchantments\essence\EnchantEssenceManager;
use ecstsy\Glacia\server\enchantments\vanilla\ExtendedVanillaEnchantmentRegistry;
use ecstsy\Glacia\server\items\armor\ArmorSetEnchants;
use ecstsy\Glacia\server\items\armor\HeroicArmorDurabilityManager;
use ecstsy\Glacia\server\items\lore\ItemLoreLayout;
use ecstsy\Glacia\server\items\pets\PetManager;
use ecstsy\Glacia\server\items\rings\RingManager;
use ecstsy\Glacia\server\items\runes\RuneManager;
use ecstsy\Glacia\server\lootboxes\LootboxRepository;
use ecstsy\Glacia\utils\GlaciaUtils;
use ecstsy\Glacia\utils\RewardResolver;
use ecstsy\Glacia\utils\StorageUtils;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\color\Color;
use pocketmine\item\Armor;
use pocketmine\item\Durable;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\StringToEnchantmentParser;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\Item;
use pocketmine\item\PotionType;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use pocketmine\utils\TextFormat;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class GlaciaItemFactory {

    public static array $definitions = [];

    public static function hasDefinition(string $key): bool {
        $definitionKey = self::resolveDefinitionKey($key);
        return isset(self::$definitions[$definitionKey]);
    }

    public static function resolvePetAliasKey(string $key): string
    {
        $trimmedKey = trim($key);
        if (preg_match('/_pet$/i', $trimmedKey) === 1) {
            return self::resolvePetAliasKey(substr($trimmedKey, 0, -4));
        }

        $normalizedInput = self::normalizeDefinitionKey($key);
        if ($normalizedInput === "") {
            return $trimmedKey;
        }

        foreach (self::petLegacyAliases() as $legacyKey => $canonicalKey) {
            if (self::normalizeDefinitionKey($legacyKey) === $normalizedInput) {
                return $canonicalKey;
            }

            if (self::normalizeDefinitionKey($canonicalKey) === $normalizedInput) {
                return $canonicalKey;
            }
        }

        foreach (self::$definitions as $definitionKey => $definition) {
            if (!self::isPetDisplayDefinition($definitionKey, $definition)) {
                continue;
            }

            $displayName = trim(TextFormat::clean((string) ($definition["name"] ?? "")));
            if ($displayName === "") {
                continue;
            }

            $displayNormalized = self::normalizeDefinitionKey($displayName);
            if ($displayNormalized === $normalizedInput) {
                return $definitionKey;
            }

            $displayWithoutPet = preg_replace('/\s+pet$/i', '', $displayName) ?? $displayName;
            if (self::normalizeDefinitionKey($displayWithoutPet) === $normalizedInput) {
                return $definitionKey;
            }
        }

        return $trimmedKey;
    }

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
            $lineTemplate = (string) $line;
            $resolved = self::template($lineTemplate, $args);
            $splitLines = preg_split("/\r\n|\n|\r/", $resolved) ?: [$resolved];
            $continuationPrefix = self::extractLeadingFormatPrefix($lineTemplate);

            foreach ($splitLines as $index => $splitLine) {
                $finalLine = ($index > 0 && $continuationPrefix !== "")
                    ? $continuationPrefix . $splitLine
                    : $splitLine;
                $loreLines[] = C::colorize($finalLine);
            }
        }
        if ($loreLines) {
            $item->setLore(self::applyDynamicPetLore($definitionKey, $loreLines));
            $item = self::markConfiguredLore($item, $item->getLore());
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

        if (str_starts_with($definitionKey, "essence_")) {
            $item = self::appendEssenceBondSuccessLore($item);
        }

        $item = self::applyConfiguredEnhancements($item, $def);
        $item = self::decorateRuneLore($item, $definitionKey);
        if ($definitionKey === "passive_rune_pouch") {
            $item = Loader::getPassiveRunePouchManager()->refreshPouchMeta(
                Loader::getPassiveRunePouchManager()->initializePouchItem($item)
            );
        }

        return $item;
    }

    /**
     * @param array<string, mixed> $definition
     */
    private static function applyConfiguredEnhancements(Item $item, array $definition): Item
    {
        foreach ((array) ($definition["vanilla-enchants"] ?? []) as $id => $levelValue) {
            $enchantment = self::resolveVanillaEnchantment((string) $id);
            $level = max(1, (int) $levelValue);
            if ($enchantment !== null) {
                $item->addEnchantment(new EnchantmentInstance($enchantment, $level));
            }
        }

        foreach ((array) ($definition["custom-enchants"] ?? []) as $id => $levelValue) {
            $enchantId = strtolower(str_replace(["_", "-", " "], "", trim((string) $id)));
            $enchantment = CustomEnchantments::getEnchantmentByName($enchantId);
            $level = max(1, (int) $levelValue);
            if ($enchantment !== null) {
                CustomEnchantmentManager::applyEnchantment($item, $enchantment, $level);
            }
        }

        if (($definition["unbreakable"] ?? false) && $item instanceof Durable) {
            $item->setUnbreakable();
        }

        if (($definition["keep-on-death"] ?? false) === true) {
            $item->setKeepOnDeath(true);
        }

        $rewardConfig = (array) ($definition["reward-config"] ?? []);
        if ($rewardConfig !== []) {
            $item = RewardResolver::decorateExistingItem($item, $rewardConfig);
        }

        return $item;
    }

    /**
     * Creates an enchant essence item; optional success overrides NBT apply chance (1–100) for drag-to-apply.
     */
    public static function enchantEssence(string $plainEssenceId, ?int $successPercent = null): Item
    {
        $key = "essence_" . strtolower(trim($plainEssenceId));
        $item = self::create($key);
        if ($successPercent !== null) {
            $n = max(1, min(100, $successPercent));
            $root = $item->getNamedTag();
            $g = $root->getCompoundTag("Glacia") ?? new CompoundTag();
            $g->setString("success", (string) $n);
            $root->setTag("Glacia", $g);
            $item->setNamedTag($root);
            $item = self::appendEssenceBondSuccessLore($item);
        }

        return $item;
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

    public static function adminCoinNote(?Player $player, int $amount): Item
    {
        $signer = $player?->getName() ?? 'Admin Bazaar';
        return self::adminCoinNoteSigned($signer, $amount);
    }

    public static function adminCoinNoteSigned(string $signer, int $amount): Item
    {
        return self::create('admin_coin_note', [
            'amount' => number_format($amount),
            'rawAmount' => $amount,
            'signer' => $signer,
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
            'description' => self::formatBookDescription($enchantment->getDescription()),
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
            'description' => self::formatBookDescription(self::getVanillaEnchantmentDescription($normalized)),
            'group-color' => '&7',
            'applies-to' => self::getVanillaAppliesToText($enchantment, $normalized),
            'book-type' => 'vanilla',
            'vanilla-enchant' => $normalized,
        ]);
    }

    public static function enchantOrb(string $type, int $max, int $new, int $success): ?Item {
        if (!in_array($type, ['weapon', 'armor', 'tool', 'fishing'], true)) {
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

    public static function holyBlackscroll(?int $success): Item
    {
        if ($success === null) {
            $success = mt_rand(1, 100);
        }

        return self::create('holy_blackscroll', [
            'success' => $success
        ]);
    }

    public static function renametag(): Item {
        return self::create('renametag');
    }

    public static function randomEnchantBook(string $group): Item {
        $key = strtolower(trim($group));
        if (Groups::getGroupId($key) === null) {
            $key = strtolower(Groups::getFallbackGroup());
        }

        return self::create('random_book', [
            'group-name' => ucfirst($key),
            'group-color' => C::colorize(Groups::translateGroupToColor(Groups::getGroupId($key))),
            'group' => $key,
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

    public static function heroicUpgrade(?int $success = null): Item
    {
        $success = $success ?? EnchantTuningManager::getInt("heroicarmor", "upgrade-success-chance", 65);
        $success = max(1, min(100, $success));
        return self::create('heroic_upgrade', [
            'success' => $success
        ]);
    }

    public static function charmShard(?int $success = null, int $bonusSuccess = 0): Item
    {
        $settings = EnchantTuningManager::getSettingsArray("charm-upgrades");
        $success = $success ?? (int) ($settings["shard-base-success"] ?? 70);
        $success = max(1, min(100, $success));
        $bonusSuccess = max(0, $bonusSuccess);
        $effectiveSuccess = max(1, min(100, $success + $bonusSuccess));

        return self::create('charm_shard', [
            'success' => $success,
            'base_success' => $success,
            'bonus_success' => $bonusSuccess,
            'effective_success' => $effectiveSuccess
        ]);
    }

    public static function charmDust(?int $bonus = null): Item
    {
        $settings = EnchantTuningManager::getSettingsArray("charm-upgrades");
        $bonus = $bonus ?? (int) ($settings["dust-bonus-default"] ?? 5);
        $bonus = max(1, $bonus);

        return self::create('charm_dust', [
            'bonus' => $bonus
        ]);
    }

    public static function blackscrollDust(?int $bonus = null): Item
    {
        $bonus = max(1, (int) ($bonus ?? 5));

        return self::create('blackscroll_dust', [
            'bonus' => $bonus
        ]);
    }

    public static function exclusiveAmplifier(int $uses = 8): Item
    {
        return self::create('exclusive_amplifier', [
            'uses' => max(1, $uses)
        ]);
    }

    public static function lootbox(string $lookup, ?Player $player = null): Item {
        $box = LootboxRepository::getBoxByLookup($lookup);
        if ($box === null) {
            throw new \InvalidArgumentException("Unknown lootbox: {$lookup}");
        }

        $useCcIcon = (bool) ($box["cc-icon"] ?? false)
            || strtolower((string) ($box["special-icon"] ?? "")) === "cc";
        $icon = $useCcIcon
            ? self::resolvePreferredIcon(
                (string) ($box["icon"] ?? "glacia:ethereal_crate_icon"),
                "glacia:ethereal_crate_icon",
                "ender_chest",
                "chest"
            )
            : (StringToItemParser::getInstance()->parse((string) ($box["icon"] ?? "chest")) ?? StringToItemParser::getInstance()->parse("chest"));
        $item = clone $icon;
        $item->setCustomName(C::colorize((string) ($box["item-name"] ?? $box["name"] ?? "&r&l&dLootbox")));

        $lore = $box["item-lore"] ?? [
            "&r&7Right-click to open this lootbox.",
            "&r&7Sneak + right-click: preview loot tables.",
            "&r&7Contains randomized rewards."
        ];
        if (is_array($lore)) {
            $item->setLore(self::resolveRecipientLore($lore, $player));
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
        if ($useCcIcon) {
            $tag->setByte("cc_icon", 1);
        }
        $root->setTag("Glacia", $tag);
        $item->setNamedTag($root);

        return $item;
    }

    public static function etherealCrate(string $lookup, ?Player $player = null): Item
    {
        $crate = EtherealCrateRepository::getCrateByLookup($lookup);
        if ($crate === null) {
            throw new \InvalidArgumentException("Unknown ethereal crate: {$lookup}");
        }

        $icon = self::resolvePreferredIcon(
            (string) ($crate["icon"] ?? "glacia:ethereal_crate_icon"),
            "glacia:ethereal_crate_icon",
            "ender_chest",
            "chest"
        );
        $item = clone $icon;
        $item->setCustomName(C::colorize((string) ($crate["item-name"] ?? $crate["name"] ?? "&r&l&dEthereal Crate")));

        $lore = $crate["item-lore"] ?? [
            "&r&7Right-click to open this ethereal crate.",
            "&r&7Sneak + right-click: preview loot tables.",
            "&r&7Contains randomized themed rewards."
        ];
        if (is_array($lore)) {
            $item->setLore(self::resolveRecipientLore($lore, $player));
        }

        if ((bool) ($crate["glow"] ?? true)) {
            GlaciaUtils::applyDisplayEnchant($item);
        } else {
            GlaciaUtils::removeDisplayEnchant($item);
        }

        $root = $item->getNamedTag();
        $tag = new CompoundTag();
        $tag->setString("glaciaItem", "ethereal_crate");
        $tag->setString("ethereal_crate", (string) ($crate["id"] ?? $lookup));
        $root->setTag("Glacia", $tag);
        $item->setNamedTag($root);

        return $item;
    }

    public static function etherealCrateIcon(): Item
    {
        return clone self::resolvePreferredIcon(
            "glacia:ethereal_crate_icon",
            "glacia:ethereal_crate_icon",
            "ender_chest",
            "chest"
        );
    }

    private static function resolvePreferredIcon(string $requestedId, string $defaultCustomId, string ...$fallbackIds): Item
    {
        foreach ([$requestedId, $defaultCustomId] as $customId) {
            $customItem = self::resolveCustomiesItem($customId);
            if ($customItem !== null) {
                return $customItem;
            }
        }

        foreach ([$requestedId, $defaultCustomId, ...$fallbackIds] as $identifier) {
            $parsed = StringToItemParser::getInstance()->parse($identifier);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        throw new \RuntimeException("Unable to resolve icon item for identifier {$requestedId}");
    }

    private static function resolveCustomiesItem(string $identifier): ?Item
    {
        if (!class_exists(CustomiesItemFactory::class)) {
            return null;
        }

        try {
            return CustomiesItemFactory::getInstance()->get($identifier);
        } catch (\Throwable) {
            return null;
        }
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

    public static function ring(string $ringId, ?int $success = null): Item
    {
        $ringId = strtolower(trim(str_replace("_ring", "", $ringId)));
        $success = $success ?? RingManager::getSuccessChance($ringId);
        return self::create($ringId . "_ring", [
            "success" => max(1, min(100, $success)),
        ]);
    }

    public static function runeRemover(): Item
    {
        return self::create("rune_remover");
    }

    public static function petDurationRune(string $petKey): Item
    {
        $petKey = PetManager::resolvePetKey($petKey);
        $petKey = strtolower(trim($petKey));
        return self::create("pet_duration_rune", [
            "pet" => $petKey,
            "pet-display" => RuneManager::getPetDisplay($petKey),
        ]);
    }

    private static function resolveDefinitionKey(string $key): string
    {
        $petAliasKey = self::resolvePetAliasKey($key);
        if ($petAliasKey !== trim($key) && isset(self::$definitions[$petAliasKey])) {
            return $petAliasKey;
        }

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

    /**
     * @param array<string, mixed> $definition
     */
    private static function isPetDisplayDefinition(string $definitionKey, array $definition): bool
    {
        if (!self::isPetDefinition($definitionKey)) {
            return false;
        }

        $displayName = trim(TextFormat::clean((string) ($definition["name"] ?? "")));
        return $displayName !== "" && str_ends_with(strtolower($displayName), " pet");
    }

    /**
     * @param string[] $lore
     * @return string[]
     */
    private static function applyDynamicPetLore(string $definitionKey, array $lore): array
    {
        if (!self::isPetDefinition($definitionKey)) {
            return $lore;
        }

        $fallback = "10s";
        foreach ($lore as $line) {
            $clean = C::clean($line);
            if (str_contains($clean, "Cooldown:")) {
                $parts = explode("Cooldown:", $clean, 2);
                $candidate = trim($parts[1] ?? "");
                if ($candidate !== "") {
                    $fallback = $candidate;
                }
                break;
            }
        }

        $cooldown = PetManager::getCooldownLabel($definitionKey, $fallback);
        foreach ($lore as $idx => $line) {
            if (str_contains(C::clean($line), "Cooldown:")) {
                $lore[$idx] = C::colorize("&r&7Cooldown: &f" . $cooldown);
            }
        }

        return $lore;
    }

    private static function isPetDefinition(string $definitionKey): bool
    {
        return in_array($definitionKey, [
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
        ], true);
    }

    /**
     * @return array<string, string>
     */
    private static function petLegacyAliases(): array
    {
        return [
            "purity_wisp" => "puritywisp",
            "gale_fox" => "galefox",
            "ironclad_wyrm" => "ironcladwyrm",
            "aegis_ram" => "aegisram",
            "rift_viper" => "riftviper",
            "sunscorch_jackal" => "sunscorchjackal",
            "runesage_owl" => "runesageowl",
            "stormcore_behemoth" => "stormcorebehemoth",
            "blastforge_hound" => "blastforgehound",
            "jackpot" => "gildedmoth",
            "ticket" => "rafflefinch",
            "guaranteed_success" => "oathbondbeetle",
            "aoe_silence" => "murmurloft",
            "utility" => "patchworksprite",
            "armor_bypass" => "renderscalewyrm",
            "envoy_upgrade" => "skycourierdrake",
            "enchanter_discount" => "inkwellfinch",
            "zone_xp" => "terracrestjackal",
            "free_enchanter" => "soulsurge",
            "ruinspire_moth" => "ruinspiremoth",
            "bellforge_custodian" => "bellforgecustodian",
            "arc_codex" => "arcbindercodex",
            "crownspire_kestrel" => "crownspirekestrel",
            "shadebroker" => "shadebrokercrow",
        ];
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

    private static function getVanillaAppliesToText(Enchantment $enchantment, string $enchantName = ""): string
    {
        $class = strtolower((new \ReflectionClass($enchantment))->getShortName());
        $compact = str_replace(['_', '-', ' '], '', strtolower(trim($enchantName)));

        return match (true) {
            in_array($compact, ['respiration', 'aquaaffinity'], true),
            str_contains($class, 'helmet'),
            str_contains($class, 'aquaaffinity'),
            str_contains($class, 'respiration') => 'Helmet',
            in_array($compact, ['depthstrider', 'featherfalling', 'frostwalker', 'soulspeed'], true),
            str_contains($class, 'boots'),
            str_contains($class, 'depthstrider'),
            str_contains($class, 'featherfalling'),
            str_contains($class, 'frostwalker'),
            str_contains($class, 'soulspeed'),
            str_contains($class, 'swiftsneak') => 'Boots',
            in_array($compact, ['sharpness', 'smite', 'baneofarthropods', 'knockback', 'fireaspect', 'looting'], true),
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
            in_array($compact, ['multishot', 'piercing', 'quickcharge'], true),
            str_contains($class, 'crossbow'),
            str_contains($class, 'multishot'),
            str_contains($class, 'piercing'),
            str_contains($class, 'quickcharge') => 'Crossbow',
            in_array($compact, ['channeling', 'impaling', 'loyalty', 'riptide'], true),
            str_contains($class, 'trident'),
            str_contains($class, 'channeling'),
            str_contains($class, 'impaling'),
            str_contains($class, 'loyalty'),
            str_contains($class, 'riptide') => 'Trident',
            in_array($compact, ['efficiency', 'fortune', 'silktouch'], true),
            str_contains($class, 'efficiency'),
            str_contains($class, 'fortune'),
            str_contains($class, 'silktouch') => 'Tools',
            in_array($compact, ['luckofthesea', 'lure'], true) => 'Fishing Rod',
            $compact === 'swiftsneak' => 'Leggings',
            $compact === 'binding' => 'Armor',
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
        $set = self::resolveCustomArmorSetAlias($set);
        $piece = $piece !== null ? strtolower($piece) : "full";

        return match ($piece) {
            "helmet", "chestplate", "leggings", "boots" => self::buildCustomArmorPiece($set, $piece, $enchanted),
            "full", "set" => self::customArmorSet($set, $enchanted),
            default => self::unknownDefinitionItem("{$set}_{$piece}")
        };
    }

    public static function customArmorSet(string $set, bool $enchanted = false): array {
        $set = self::resolveCustomArmorSetAlias($set);

        return [
            self::buildCustomArmorPiece($set, "helmet", $enchanted),
            self::buildCustomArmorPiece($set, "chestplate", $enchanted),
            self::buildCustomArmorPiece($set, "leggings", $enchanted),
            self::buildCustomArmorPiece($set, "boots", $enchanted),
        ];
    }

    private static function buildCustomArmorPiece(string $set, string $piece, bool $enchanted = false): Item {
        $set = self::resolveCustomArmorSetAlias($set);
        $identifier = "{$set}_{$piece}";

        if (!isset(self::$definitions[$identifier])) {
            return self::unknownDefinitionItem($identifier);
        }

        $item = self::create($identifier, [
            'type' => $identifier
        ]);

        self::applyCustomArmorColor($item, $set);
        self::addArmorBaseEnchantments($item);
        if ($enchanted) {
            ArmorSetEnchants::apply($item, $set, $piece);
        }
        self::appendCustomArmorStrengthLore($item);
        if ($item instanceof Durable) {
            HeroicArmorDurabilityManager::initializeItem($item);
        }

        return $item;
    }

    private static function applyCustomArmorColor(Item $item, string $set): void
    {
        if (!$item instanceof Armor) {
            return;
        }

        $color = match (strtolower($set)) {
            // &5-like purple tint for Dreadveil/Phantom set.
            "phantom" => new Color(170, 0, 170),
            // Frostbound/Yeti: cool blue, slightly muted (not neon bright).
            "yeti" => new Color(60, 125, 195),
            // Stormcaller/Yijki: deeper &3-like cyan/blue.
            "yijki" => new Color(0, 105, 120),
            default => null,
        };

        if ($color !== null) {
            $item->setCustomColor($color);
        }
    }

    private static function addArmorBaseEnchantments(Item $item): void {
        // Leather base for dyeing, then boosted to outperform diamond durability.
        $item->addEnchantment(new EnchantmentInstance(VanillaEnchantments::PROTECTION(), 5));
        $item->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 6));
    }

    private static function appendCustomArmorStrengthLore(Item $item): void
    {
        $line = C::colorize("&r&cThis item is stronger than diamond!");
        $lore = $item->getLore();

        foreach ($lore as $existing) {
            if (TextFormat::clean((string) $existing) === TextFormat::clean($line)) {
                return;
            }
        }

        $lore[] = "";
        $lore[] = $line;
        $item->setLore($lore);
    }

    public static function customWeapon(string $set, string $type = "sword"): Item {
        $set = self::resolveCustomArmorSetAlias($set);
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

    public static function resolveCustomArmorSetAlias(string $set): string
    {
        return match (strtolower(trim($set))) {
            "dreadveil", "phantom" => "phantom",
            "frostbound", "yeti" => "yeti",
            "stormcaller", "yijki" => "yijki",
            default => strtolower(trim($set)),
        };
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
        unset($questIndex);

        $key = match (strtolower($tier)) {
            "dormant" => "dormant_sigil",
            "awakened" => "awakened_sigil",
            "ascendent" => "ascendent_sigil",
            "eternal" => "eternal_sigil",
            default => null,
        };

        if ($key !== null) {
            $item = self::create($key);
            $nbt = $item->getNamedTag();
            $glacia = $nbt->getCompoundTag("Glacia") ?? new CompoundTag();
            $resolvedTier = strtolower($glacia->getString("questTier", strtolower($tier)));
            $glacia->setString("glaciaItem", "quest_sigil");
            $glacia->setString("questTier", $resolvedTier);
            $nbt->setTag("Glacia", $glacia);
            $nbt->setString("glacia_quest_tier", $resolvedTier);
            $item->setNamedTag($nbt);

            return $item;
        }

        throw new \InvalidArgumentException("Unknown quest sigil tier: {$tier}");

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

        $tierColor = (string) ($data["color"] ?? $tierColor);
        $symbol = (string) ($data["symbol"] ?? "*");

        $quest = $data["quests"][$questIndex] ?? null;
        if ($quest === null) {
            throw new \InvalidArgumentException("Invalid quest index {$questIndex} for sigil tier {$tier}");
        }

        $item = $material->setCustomName(C::colorize("&r&7{$symbol} {$tierColor}{$data['display']} Sigil &7{$symbol}"));

        $nbt = $item->getNamedTag();
        $glacia = $nbt->getCompoundTag("Glacia") ?? new CompoundTag();
        $glacia->setString("glaciaItem", "quest_sigil");
        $glacia->setString("questTier", $tier);
        $nbt->setTag("Glacia", $glacia);
        $nbt->setString("glacia_quest_tier", $tier);
        $nbt->setInt("glacia_quest_index", $questIndex);
        $nbt->setString("glacia_quest_type", $quest["type"]);
        $nbt->setString(
            "glacia_quest_progress",
            StorageUtils::encodeJsonString(
                array_fill(0, count($quest["objectives"]), 0),
                "quest item progress seed"
            ) ?? "[]"
        );
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

    public static function itemFlipCreditVoucher(int $credits = 1): Item
    {
        return self::create("item_flip_credit_voucher", [
            "credits" => max(1, $credits)
        ]);
    }

    public static function passiveRunePouch(): Item
    {
        return self::create("passive_rune_pouch");
    }

    public static function collectionChest(): Item
    {
        return self::create("collection_chest");
    }

    public static function soulGem(int $souls, string $group = "", ?string $gemId = null): Item {
        return self::buildSoulFuelGemItem("soul_gem", "stable", $souls, $group, $gemId);
    }

    public static function soulConduit(int $souls, string $group = "", ?string $gemId = null): Item {
        return self::buildSoulConduitItem("soul_conduit", "stable", $souls, $group, $gemId);
    }

    public static function soulConduitEfficiency(int $souls, string $group = "", ?string $gemId = null): Item {
        return self::buildSoulConduitItem("efficiency_conduit", "efficiency", $souls, $group, $gemId);
    }

    public static function soulConduitOverload(int $souls, string $group = "", ?string $gemId = null): Item {
        return self::buildSoulConduitItem("overlord_conduit", "overload", $souls, $group, $gemId);
    }

    public static function soulConduitGem(int $souls, string $group = "", ?string $gemId = null): Item
    {
        return self::buildSoulFuelGemItem("soul_gem", "stable", $souls, $group, $gemId);
    }

    public static function efficiencyConduitGem(int $souls, string $group = "", ?string $gemId = null): Item
    {
        return self::buildSoulFuelGemItem("efficiency_gem", "efficiency", $souls, $group, $gemId);
    }

    public static function overlordConduitGem(int $souls, string $group = "", ?string $gemId = null): Item
    {
        return self::buildSoulFuelGemItem("overlord_gem", "overload", $souls, $group, $gemId);
    }

    /**
     * @param "stable"|"efficiency"|"overload" $conduitType
     */
    private static function buildSoulConduitItem(string $glaciaKey, string $conduitType, int $souls, string $group, ?string $gemId): Item {
        $souls = max(0, $souls);
        $group = strtoupper(trim($group));
        $formattedSouls = SoulGemSessionManager::formatSoulCount($souls);
        $tierDisp = SoulGemSessionManager::getConduitTierDisplayData($conduitType);
        $tierRules = SoulGemSessionManager::getTierDataForConduitType($conduitType);
        $mult = $tierRules["cost_multiplier"];

        $item = self::create($glaciaKey);
        $header = match ($conduitType) {
            "efficiency" => "&r&8Efficiency Conduit",
            "overload" => "&r&8Overlord Conduit",
            default => "&r&8Soul Conduit",
        };
        $lore = [
            C::colorize($header),
            "",
            C::colorize("&r&7Soul Mode: {$tierDisp["color"]}{$tierDisp["mode"]}"),
            C::colorize("&r&7Proc soul cost scales by &f×" . rtrim(rtrim((string) $mult, "0"), ".") . " &7of each enchant’s base cost."),
        ];
        if (($tierRules["siphon_chance"] ?? 0) > 0) {
            $lore[] = C::colorize(
                "&r&7Siphon: &f" . ($tierRules["siphon_chance"] ?? 0) . "% &7chance to refund &f" .
                ($tierRules["siphon_amount"] ?? 1) . " &7soul after paying."
            );
        }
        $lore[] = C::colorize("&r&7Stored Souls: " . $formattedSouls);
        if ($group !== "") {
            $groupData = Groups::getGroupData($group);
            $label = is_array($groupData) ? (string) ($groupData["group_name"] ?? ucfirst(strtolower($group))) : ucfirst(strtolower($group));
            $lore[] = C::colorize("&r&7Stored Group: &f" . $label);
        }
        $lore[] = "";
        $lore[] = C::colorize("&r&8> &r&7Drag onto weapons, tools, bows, or armor to merge");
        $lore[] = C::colorize("&r&8> &r&7Merged conduit powers soul-cost custom enchants");
        $item->setLore($lore);

        $root = $item->getNamedTag();
        $tag = $root->getCompoundTag("Glacia") ?? new CompoundTag();
        $tag->setInt("souls", $souls);
        $tag->setString("group", $group);
        $tag->setString("soul_gem_id", $gemId ?? bin2hex(random_bytes(8)));
        $root->setTag("Glacia", $tag);
        $item->setNamedTag($root);

        $customName = match ($conduitType) {
            "efficiency" => "&r&6&lEfficiency Conduit &r&7[" . $formattedSouls . "&7]",
            "overload" => "&r&4&lOverlord Conduit &r&7[" . $formattedSouls . "&7]",
            default => "&r&c&lSoul Conduit &r&7[" . $formattedSouls . "&7]",
        };
        $item->setCustomName(C::colorize($customName));

        return $item;
    }

    /**
     * @param "stable"|"efficiency"|"overload" $conduitType
     */
    private static function buildSoulFuelGemItem(string $glaciaKey, string $conduitType, int $souls, string $group, ?string $gemId): Item
    {
        $souls = max(1, $souls);
        $group = strtoupper(trim($group));
        $formattedSouls = SoulGemSessionManager::formatSoulCount($souls);

        $item = self::create($glaciaKey);
        $header = match ($conduitType) {
            "efficiency" => "&r&8Efficiency Gem",
            "overload" => "&r&8Overlord Gem",
            default => "&r&8Soul Gem",
        };
        $target = match ($conduitType) {
            "efficiency" => "&6Efficiency Conduit",
            "overload" => "&4Overlord Conduit",
            default => "&cSoul Conduit",
        };
        $blockedTargets = match ($conduitType) {
            "efficiency" => "&cSoul &7or &4Overlord &7conduits",
            "overload" => "&cSoul &7or &6Efficiency &7conduits",
            default => "&6Efficiency &7or &4Overlord &7conduits",
        };
        $lore = [
            C::colorize($header),
            "",
            C::colorize("&r&7Stored Souls: " . $formattedSouls),
        ];
        if ($group !== "") {
            $groupData = Groups::getGroupData($group);
            $label = is_array($groupData) ? (string) ($groupData["group_name"] ?? ucfirst(strtolower($group))) : ucfirst(strtolower($group));
            $lore[] = C::colorize("&r&7Stored Group: &f" . $label);
        }
        $lore[] = "";
        $lore[] = C::colorize("&r&8> &r&7Drag onto gear with a merged {$target}");
        $lore[] = C::colorize("&r&8> &r&7Cannot be applied to {$blockedTargets}");
        $item->setLore($lore);

        $root = $item->getNamedTag();
        $tag = $root->getCompoundTag("Glacia") ?? new CompoundTag();
        $tag->setInt("souls", $souls);
        $tag->setString("group", $group);
        $tag->setString("soul_gem_id", $gemId ?? bin2hex(random_bytes(8)));
        $root->setTag("Glacia", $tag);
        $item->setNamedTag($root);

        $customName = match ($conduitType) {
            "efficiency" => "&r&6&lEfficiency Gem &r&7[" . $formattedSouls . "&7]",
            "overload" => "&r&4&lOverlord Gem &r&7[" . $formattedSouls . "&7]",
            default => "&r&c&lSoul Gem &r&7[" . $formattedSouls . "&7]",
        };
        $item->setCustomName(C::colorize($customName));

        return $item;
    }

    public static function harvesterChest(): Item
    {
        return self::create("harvester_chest");
    }

    public static function watchdogSupplement(int $days = 1): Item
    {
        return self::create("watchman_supplement", [
            "days" => max(1, $days),
        ]);
    }

    public static function watchmanSupplement(int $days = 1): Item
    {
        return self::watchdogSupplement($days);
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

    public static function wraithPearl(?int $uses = null): Item
    {
        $uses = max(1, $uses ?? 40);

        $item = self::create("wraith_pearl");
        $item->setLore([
            C::colorize("&r&7Right-click to throw a stored"),
            C::colorize("&r&7ender pearl charge."),
            "",
            C::colorize("&r&7Uses: &f" . $uses),
            C::colorize("&r&8Drag ender pearls onto this item to charge it."),
        ]);

        $root = $item->getNamedTag();
        $tag = $root->getCompoundTag("Glacia") ?? new CompoundTag();
        $tag->setInt("uses", $uses);
        $root->setTag("Glacia", $tag);
        $item->setNamedTag($root);

        return $item;
    }

    public static function angelPotion(?int $uses = null): Item
    {
        $uses = max(1, $uses ?? 40);

        $item = self::create("angel_potion");
        if ($item instanceof \pocketmine\item\SplashPotion) {
            $item->setType(PotionType::STRONG_HEALING());
        }
        $item->setLore([
            C::colorize("&r&7Right-click to throw a stored"),
            C::colorize("&r&7strong healing splash potion."),
            "",
            C::colorize("&r&7Uses: &f" . $uses),
            C::colorize("&r&8Drag strong heals onto this item to charge it."),
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

    public static function zoneXpBooster(string $zoneId, float $multiplier, string $duration): Item
    {
        $formattedMultiplier = rtrim(rtrim(number_format($multiplier, 2), "0"), ".");
        return self::create("zone_xp_booster", [
            "multiplier" => $formattedMultiplier,
            "duration" => $duration,
        ]);
    }

    public static function zoneFishingBooster(string $zoneId, float $multiplier, string $duration): Item
    {
        $formattedMultiplier = rtrim(rtrim(number_format($multiplier, 2), "0"), ".");
        $zoneId = strtolower(trim($zoneId));
        return self::create("zone_fishing_booster", [
            "zone" => $zoneId !== "" ? $zoneId : "Current Zone",
            "zone_id" => $zoneId,
            "multiplier" => $formattedMultiplier,
            "duration" => $duration,
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

    /**
     * @param string[] $configuredLore
     */
    public static function markConfiguredLore(Item $item, array $configuredLore): Item
    {
        $hasVisibleLine = false;
        foreach ($configuredLore as $line) {
            $clean = trim(C::clean((string) $line));
            if ($clean !== "") {
                $hasVisibleLine = true;
                break;
            }
        }

        if (!$hasVisibleLine) {
            return $item;
        }

        $root = $item->getNamedTag();
        $tag = $root->getCompoundTag("Glacia") ?? new CompoundTag();
        $tag->setString("config_lore_json", StorageUtils::encodeJsonString(array_values($configuredLore), "configured item lore") ?? "[]");
        $root->setTag("Glacia", $tag);
        $item->setNamedTag($root);

        return $item;
    }

    public static function applyWhiteScrollProtection(Item $item): Item
    {
        $root = $item->getNamedTag();
        $tag = $root->getCompoundTag("Glacia") ?? new CompoundTag();
        $tag->setByte("protected", 1);
        $root->setTag("Glacia", $tag);
        $item->setNamedTag($root);

        $protectedLine = C::colorize("&r&l&fPROTECTED");
        $lore = $item->getLore();
        if (!in_array($protectedLine, $lore, true)) {
            $lore[] = $protectedLine;
            $item->setLore($lore);
        }

        ItemLoreLayout::normalizeForCombatGear($item);
        return $item;
    }

    public static function applyHolyWhiteState(Item $item, int $used = 0, int $max = 3, bool $active = true): Item
    {
        $item = self::applyWhiteScrollProtection($item);

        $used = max(0, $used);
        $max = max(1, $max);

        $root = $item->getNamedTag();
        $tag = $root->getCompoundTag("Glacia") ?? new CompoundTag();
        $tag->setInt("holy_white_used", min($used, $max));
        $tag->setInt("holy_white_max", $max);
        $tag->setByte("holy_white_active", $active ? 1 : 0);
        $tag->setByte("holy_white_corrupted", $used >= $max ? 1 : 0);
        $root->setTag("Glacia", $tag);
        $item->setNamedTag($root);

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

        $remaining = max(0, $max - min($used, $max));
        $lore[] = C::colorize("&r&bHoly Lives: &f{$remaining}/{$max}");
        if ($used >= $max) {
            $lore[] = C::colorize("&r&c&lCORRUPTED");
        } elseif ($used > 0) {
            $lore[] = C::colorize("&r&6&lSEMI-CORRUPT");
        }
        if ($active) {
            $lore[] = C::colorize("&r&f&lHOLY WHITE ACTIVE");
        }

        $item->setLore($lore);
        ItemLoreLayout::normalizeForCombatGear($item);
        return $item;
    }

    /**
     * @param string[] $essenceIds
     */
    public static function applyConfiguredEssences(Item $item, array $essenceIds): Item
    {
        foreach ($essenceIds as $essenceId) {
            $essenceId = strtolower(trim($essenceId));
            if ($essenceId === "") {
                continue;
            }

            $reason = null;
            if (!EnchantEssenceManager::canApply($item, $essenceId, $reason)) {
                continue;
            }

            $item = EnchantEssenceManager::apply($item, $essenceId);
        }

        ItemLoreLayout::normalizeForCombatGear($item);
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
     * Lore line for drag-to-apply bond chance (reads {@code Glacia.success}).
     */
    private static function appendEssenceBondSuccessLore(Item $item): Item
    {
        $g = $item->getNamedTag()->getCompoundTag("Glacia");
        $pct = 100;
        if ($g !== null) {
            $t = $g->getTag("success");
            if ($t instanceof \pocketmine\nbt\tag\IntTag) {
                $pct = max(1, min(100, $t->getValue()));
            } elseif ($t instanceof \pocketmine\nbt\tag\StringTag) {
                $pct = max(1, min(100, (int) $t->getValue()));
            }
        }

        $lore = array_values(array_filter(
            $item->getLore(),
            static fn(string $line): bool => !str_starts_with(C::clean($line), "Bond success:")
        ));
        $lore[] = C::colorize("&r&7Bond success: &a{$pct}%&7 (drag onto gear)");
        $item->setLore($lore);

        return $item;
    }

    private static function formatBookDescription(string $description): string
    {
        return str_replace(["\r\n", "\r"], "\n", $description);
    }

    private static function extractLeadingFormatPrefix(string $line): string
    {
        if (preg_match('/^((?:&[0-9A-FK-OR])+)/i', $line, $matches) === 1) {
            return $matches[1];
        }

        return "";
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

    /**
     * @param mixed[] $lore
     * @return string[]
     */
    private static function resolveRecipientLore(array $lore, ?Player $player): array
    {
        $resolved = [];
        $recipient = $player?->getName();
        foreach ($lore as $line) {
            if (!is_string($line)) {
                continue;
            }

            $text = $line;
            if (str_contains($text, "{player}")) {
                if ($recipient === null || trim($recipient) === "") {
                    continue;
                }
                $text = str_replace("{player}", $recipient, $text);
            }

            $resolved[] = C::colorize($text);
        }

        return $resolved;
    }
}
