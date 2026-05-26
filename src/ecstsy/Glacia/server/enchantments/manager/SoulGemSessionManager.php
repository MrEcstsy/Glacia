<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\manager;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantments;
use ecstsy\Glacia\server\enchantments\Groups;
use ecstsy\Glacia\server\items\GlaciaItemFactory;
use ecstsy\Glacia\server\items\lore\ItemLoreLayout;
use ecstsy\Glacia\server\items\pets\PetManager;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\item\Armor;
use pocketmine\item\Axe;
use pocketmine\item\Bow;
use pocketmine\item\FishingRod;
use pocketmine\item\Hoe;
use pocketmine\item\Item;
use pocketmine\item\Pickaxe;
use pocketmine\item\Shovel;
use pocketmine\item\Sword;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class SoulGemSessionManager
{
    public const MAX_SOUL_DISPLAY_TIER = 500000;

    private const BIND_ID_KEY = "soul_gem_bind_id";
    private const BIND_MODE_KEY = "soul_gem_bind_mode";
    private const BOUND_SOULS_KEY = "soul_gem_souls";
    private const BOUND_GROUP_KEY = "soul_gem_group";
    private const SOUL_GEM_ID_KEY = "soul_gem_id";
    /** stable | efficiency | overload — set when merging a typed conduit item */
    private const BOUND_CONDUIT_TYPE_KEY = "soul_conduit_type";

    /** @var array<string, int> */
    private static array $lastFailureNoticeAt = [];

    public static function isEnabled(): bool
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "config.yml");
        return (bool) ($config?->getNested("souls.enabled", true) ?? true);
    }

    public static function getTrackerLoreTemplate(): string
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "config.yml");
        return (string) ($config?->getNested("souls.tracker-lore", "&r&7Souls: {souls}") ?? "&r&7Souls: {souls}");
    }

    public static function requiresMatchingGroup(): bool
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "config.yml");
        return (bool) ($config?->getNested("souls.soulgem.require-matching-book-group", false) ?? false);
    }

    /**
     * @return string[]
     */
    public static function getSoulConduitGlaciaIds(): array {
        return ["soul_conduit", "efficiency_conduit", "overlord_conduit", "soul_conduit_efficiency", "soul_conduit_overload", "soul_conduit_overlord"];
    }

    public static function isSoulConduitGemId(string $glaciaItemId): bool {
        return in_array($glaciaItemId, self::getSoulConduitGlaciaIds(), true);
    }

    /**
     * @return string[]
     */
    public static function getSoulFuelGemGlaciaIds(): array
    {
        return ["soul_gem", "efficiency_gem", "overlord_gem", "soul_conduit_gem", "efficiency_conduit_gem", "overlord_conduit_gem"];
    }

    public static function isSoulFuelGemId(string $glaciaItemId): bool
    {
        return in_array($glaciaItemId, self::getSoulFuelGemGlaciaIds(), true);
    }

    public static function isLegacyStableConduitItem(Item $item): bool
    {
        if (self::getGlaciaItemId($item) !== "soul_gem") {
            return false;
        }

        foreach ($item->getLore() as $line) {
            $clean = trim(C::clean($line));
            if ($clean === "Soul Conduit") {
                return true;
            }
            if ($clean === "Soul Gem") {
                return false;
            }
        }

        return str_contains(C::clean($item->getCustomName()), "Soul Conduit");
    }

    public static function formatSoulCount(int $souls): string
    {
        $souls = max(0, $souls);
        $color = match (true) {
            $souls >= self::MAX_SOUL_DISPLAY_TIER => "&4",
            $souls >= 25000 => "&c",
            $souls >= 10000 => "&6",
            default => "&a",
        };

        return $color . number_format($souls);
    }

    public static function canBindToItem(Item $item): bool
    {
        return $item instanceof Armor
            || $item instanceof Sword
            || $item instanceof Axe
            || $item instanceof Pickaxe
            || $item instanceof Shovel
            || $item instanceof Hoe
            || $item instanceof Bow
            || $item instanceof FishingRod;
    }

    public static function hasBoundSoulGem(Item $item): bool
    {
        return self::getBoundGemId($item) !== "";
    }

    public static function getBoundSoulCount(Item $item): int
    {
        $glacia = $item->getNamedTag()->getCompoundTag("Glacia");
        return max(0, $glacia?->getInt(self::BOUND_SOULS_KEY, 0) ?? 0);
    }

    public static function getBoundGroup(Item $item): string
    {
        $glacia = $item->getNamedTag()->getCompoundTag("Glacia");
        return strtoupper(trim((string) ($glacia?->getString(self::BOUND_GROUP_KEY, "") ?? "")));
    }

    public static function setBoundSoulCount(Item $item, int $souls): Item
    {
        if (!self::hasBoundSoulGem($item)) {
            return $item;
        }

        $clone = clone $item;
        $root = $clone->getNamedTag();
        $glacia = $root->getCompoundTag("Glacia") ?? new CompoundTag();
        $glacia->setInt(self::BOUND_SOULS_KEY, max(0, $souls));
        $tier = self::getEffectiveTierDataForBoundItem($clone, max(0, $souls));
        $glacia->setString(self::BIND_MODE_KEY, (string) $tier["mode"]);
        $root->setTag("Glacia", $glacia);
        $clone->setNamedTag($root);

        return self::refreshBoundItemLore($clone, max(0, $souls));
    }

    /**
     * @return array{mode:string,color:string,next_threshold:?int}
     */
    public static function getTierDisplayData(int $souls): array
    {
        $tier = self::getTierDataBySoulThresholds($souls);
        return [
            "mode" => (string) $tier["mode"],
            "color" => (string) $tier["color"],
            "next_threshold" => $tier["next_threshold"],
        ];
    }

    /**
     * Lore/display for an unbound conduit item (Stable / Efficiency / Overload).
     *
     * @return array{mode:string,color:string,next_threshold:?int}
     */
    public static function getConduitTierDisplayData(string $conduitType): array {
        $tier = self::getTierDataForConduitType($conduitType);
        return [
            "mode" => (string) $tier["mode"],
            "color" => (string) $tier["color"],
            "next_threshold" => null,
        ];
    }

    /**
     * @return array{item:Item,gem:Item}
     */
    public static function bindGemToItem(Item $item, Item $gem): array
    {
        $gem = self::rebuildSoulGem($gem);
        $souls = self::getStoredSouls($gem);
        $group = self::getStoredGroup($gem);
        $gemId = self::ensureGemId($gem);
        $conduitType = self::conduitTypeFromGemGlaciaId(self::getGlaciaItemId($gem));
        $tier = self::getTierDataForConduitType($conduitType);

        $updatedItem = clone $item;
        $root = $updatedItem->getNamedTag();
        $glacia = $root->getCompoundTag("Glacia") ?? new CompoundTag();
        $glacia->setString(self::BIND_ID_KEY, $gemId);
        $glacia->setString(self::BIND_MODE_KEY, (string) $tier["mode"]);
        $glacia->setString(self::BOUND_CONDUIT_TYPE_KEY, $conduitType);
        $glacia->setInt(self::BOUND_SOULS_KEY, $souls);
        $glacia->setString(self::BOUND_GROUP_KEY, $group);
        $root->setTag("Glacia", $glacia);
        $updatedItem->setNamedTag($root);

        return [
            "item" => self::refreshBoundItemLore($updatedItem, $souls),
            "gem" => VanillaItems::AIR(),
        ];
    }

    /**
     * @return array{item:Item,gem:Item}|null
     */
    public static function applyFuelGemToItem(Item $item, Item $gem): ?array
    {
        $gem = self::rebuildSoulFuelGem($gem);
        $boundType = self::getBoundConduitType($item) ?? "stable";
        $gemType = self::fuelGemTypeFromGlaciaId(self::getGlaciaItemId($gem));
        if ($gemType === "" || $boundType !== $gemType) {
            return null;
        }

        $addedSouls = self::getStoredSouls($gem);
        if ($addedSouls <= 0) {
            return null;
        }

        $gemGroup = self::getStoredGroup($gem);
        $updatedItem = clone $item;
        $root = $updatedItem->getNamedTag();
        $glacia = $root->getCompoundTag("Glacia") ?? new CompoundTag();
        $currentGroup = strtoupper(trim($glacia->getString(self::BOUND_GROUP_KEY, "")));
        if ($currentGroup !== "" && $gemGroup !== "" && $currentGroup !== $gemGroup) {
            return null;
        }
        if ($currentGroup === "" && $gemGroup !== "") {
            $glacia->setString(self::BOUND_GROUP_KEY, $gemGroup);
        }
        $root->setTag("Glacia", $glacia);
        $updatedItem->setNamedTag($root);
        $updatedItem = self::setBoundSoulCount($updatedItem, self::getBoundSoulCount($updatedItem) + $addedSouls);

        return [
            "item" => $updatedItem,
            "gem" => VanillaItems::AIR(),
        ];
    }

    /**
     * @return array{item:Item,conduit:Item,gem:Item|null}|null
     */
    public static function removeConduitFromItem(Item $item): ?array
    {
        if (!self::hasBoundSoulGem($item)) {
            return null;
        }

        $souls = self::getBoundSoulCount($item);
        $group = self::getBoundGroup($item);
        $type = self::getBoundConduitType($item) ?? "stable";

        $updatedItem = clone $item;
        $root = $updatedItem->getNamedTag();
        $glacia = $root->getCompoundTag("Glacia");
        if ($glacia === null) {
            return null;
        }

        foreach ([
            self::BIND_ID_KEY,
            self::BIND_MODE_KEY,
            self::BOUND_SOULS_KEY,
            self::BOUND_GROUP_KEY,
            self::BOUND_CONDUIT_TYPE_KEY,
        ] as $tagName) {
            $glacia->removeTag($tagName);
        }
        $root->setTag("Glacia", $glacia);
        $updatedItem->setNamedTag($root);
        $updatedItem = self::stripBoundSoulLore($updatedItem);

        return [
            "item" => $updatedItem,
            "conduit" => self::createConduitItem($type, 0, ""),
            "gem" => $souls > 0 ? self::createFuelGemForConduitType($type, $souls, $group) : null,
        ];
    }

    public static function isItemBoundToGem(Item $item, Item $gem): bool
    {
        $bound = self::getBoundGemId($item);
        $gemId = self::getGemId($gem);
        return $bound !== "" && $gemId !== "" && $bound === $gemId;
    }

    public static function tryToggleChannel(Player $player): bool
    {
        if (!self::isEnabled()) {
            $player->sendMessage(C::colorize("&r&cSouls are currently disabled."));
            PlayerUtils::playSound($player, "note.bass");
            return false;
        }

        $player->sendMessage(C::colorize("&r&7Conduits activate by merge — &fdrag one onto your gear&7."));
        PlayerUtils::playSound($player, "random.click");
        return false;
    }

    public static function deactivate(Player $player, bool $silent = true): void
    {
        self::clearFailureNotices($player);
    }

    public static function clearOnQuit(Player $player): void
    {
        self::clearFailureNotices($player);
    }

    public static function tryConsumeForEnchant(Player $player, string $enchantId): bool
    {
        if (!self::isEnabled()) {
            return true;
        }

        $cost = self::getCostForEnchant($player, $enchantId);
        if ($cost <= 0 || PetManager::hasFreeSoulCost($player)) {
            return true;
        }

        $carrier = self::findBoundCarrierForEnchant($player, $enchantId);
        if ($carrier === null) {
            return false;
        }

        $held = $carrier["item"];

        $boundGroup = self::getBoundGroup($held);
        $enchantGroup = self::resolveEnchantGroup($enchantId);
        if (
            self::requiresMatchingGroup()
            && $boundGroup !== ""
            && $enchantGroup !== ""
            && strtoupper($boundGroup) !== strtoupper($enchantGroup)
        ) {
            self::notifyFailure($player, $player->getUniqueId()->toString() . ":group", "&r&cThis item's conduit does not match that enchant's rarity group.");
            return false;
        }

        $pool = self::getBoundSoulCount($held);
        $tier = self::getEffectiveTierDataForBoundItem($held, $pool);
        $adjustedCost = max(1, (int) ceil($cost * (float) ($tier["cost_multiplier"] ?? 1.0)));
        if ($pool < $adjustedCost) {
            self::notifyFailure($player, $player->getUniqueId()->toString() . ":souls", "&r&cYou need " . self::formatSoulCount($adjustedCost) . " &csouls for that proc.");
            return false;
        }

        $remaining = $pool - $adjustedCost;
        if (($tier["siphon_chance"] ?? 0) > 0 && mt_rand(1, 100) <= (int) $tier["siphon_chance"]) {
            $remaining += max(1, (int) ($tier["siphon_amount"] ?? 1));
        }

        ($carrier["apply"])(self::setBoundSoulCount($held, $remaining));
        return true;
    }

    /**
     * @return array{item:Item, apply:callable(Item):void}|null
     */
    private static function findBoundCarrierForEnchant(Player $player, string $enchantId): ?array
    {
        $hand = $player->getInventory()->getItemInHand();
        if (self::hasBoundSoulGem($hand) && CustomEnchantmentManager::hasEnchantment($hand, $enchantId)) {
            return [
                "item" => $hand,
                "apply" => static function (Item $updated) use ($player): void {
                    $player->getInventory()->setItemInHand($updated);
                },
            ];
        }

        $armorInventory = $player->getArmorInventory();
        foreach ($armorInventory->getContents() as $slot => $item) {
            if (!self::hasBoundSoulGem($item) || !CustomEnchantmentManager::hasEnchantment($item, $enchantId)) {
                continue;
            }

            return [
                "item" => $item,
                "apply" => static function (Item $updated) use ($armorInventory, $slot): void {
                    $armorInventory->setItem($slot, $updated);
                },
            ];
        }

        if (self::hasBoundSoulGem($hand)) {
            return [
                "item" => $hand,
                "apply" => static function (Item $updated) use ($player): void {
                    $player->getInventory()->setItemInHand($updated);
                },
            ];
        }

        return null;
    }

    public static function determinePrimaryGroup(Item $item): string
    {
        $highest = 0;
        foreach (CustomEnchantmentManager::getEnchantments($item) as $name => $_level) {
            $enchant = CustomEnchantments::getEnchantmentByName((string) $name);
            if ($enchant === null) {
                continue;
            }

            $highest = max($highest, $enchant->getRarity());
        }

        if ($highest <= 0) {
            return "";
        }

        return strtoupper((string) (Groups::getGroupNameById($highest) ?? ""));
    }

    public static function rebuildSoulGem(Item $item): Item
    {
        $gid = self::getGlaciaItemId($item);
        if (!self::isSoulConduitGemId($gid) && !self::isLegacyStableConduitItem($item)) {
            return $item;
        }

        $souls = self::getStoredSouls($item);
        if ($souls <= 0) {
            return $item;
        }

        $group = self::getStoredGroup($item);
        $id = self::ensureGemId($item);
        return match ($gid) {
            "efficiency_conduit", "soul_conduit_efficiency" => GlaciaItemFactory::soulConduitEfficiency($souls, $group, $id),
            "overlord_conduit", "soul_conduit_overload", "soul_conduit_overlord" => GlaciaItemFactory::soulConduitOverload($souls, $group, $id),
            default => GlaciaItemFactory::soulConduit($souls, $group, $id),
        };
    }

    public static function rebuildSoulFuelGem(Item $item): Item
    {
        $gid = self::getGlaciaItemId($item);
        if ((!self::isSoulFuelGemId($gid)) || self::isLegacyStableConduitItem($item)) {
            return $item;
        }

        $souls = self::getStoredSouls($item);
        if ($souls <= 0) {
            return $item;
        }

        $group = self::getStoredGroup($item);
        $id = self::ensureGemId($item);
        return self::createFuelGemForConduitType(self::fuelGemTypeFromGlaciaId($gid), $souls, $group, $id);
    }

    public static function refreshBoundItemLore(Item $item, ?int $souls = null): Item
    {
        $boundId = self::getBoundGemId($item);
        if ($boundId === "") {
            return $item;
        }

        $souls ??= self::getBoundSoulCount($item);
        $group = self::getBoundGroup($item);
        $tier = self::getEffectiveTierDataForBoundItem($item, $souls);
        $boundType = self::getBoundConduitType($item);
        $nextLine = null;
        if ($boundType === null) {
            $nextLine = $tier["next_threshold"] === null
                ? "&r&8> &r&7Next Tier: &4MAXED"
                : "&r&8> &r&7Next Tier In: &f" . number_format(max(0, (int) $tier["next_threshold"] - $souls)) . " &7souls";
        }

        $clone = clone $item;
        $lore = array_values(array_filter(
            $clone->getLore(),
            static fn(string $line): bool => !self::isBoundSoulLoreLine(C::clean($line))
        ));

        if ($lore !== [] && end($lore) !== "") {
            $lore[] = "";
        }

        $header = match ($boundType) {
            "efficiency" => "&r&8Efficiency Conduit",
            "overload" => "&r&8Overlord Conduit",
            default => "&r&8Soul Conduit",
        };
        $lore[] = C::colorize($header);
        $lore[] = C::colorize("&r&8> &r&7Soul Mode: {$tier["color"]}{$tier["mode"]}");
        $lore[] = C::colorize("&r&8> &r&7Stored Souls: " . self::formatSoulCount($souls));
        if ($group !== "") {
            $groupData = Groups::getGroupData($group);
            $label = is_array($groupData) ? (string) ($groupData["group_name"] ?? ucfirst(strtolower($group))) : ucfirst(strtolower($group));
            $lore[] = C::colorize("&r&8> &r&7Stored Group: &f" . $label);
        }
        if ($nextLine !== null) {
            $lore[] = C::colorize($nextLine);
        }
        $clone->setLore($lore);
        return ItemLoreLayout::normalizeForCombatGear($clone);
    }

    private static function isBoundSoulLoreLine(string $cleanLine): bool
    {
        $clean = trim($cleanLine);
        $normalized = ltrim($clean, " \t\n\r\0\x0B>*-");

        return $normalized === "Soul Gem"
            || $normalized === "Soul Conduit"
            || $normalized === "Efficiency Soul Conduit"
            || $normalized === "Efficiency Conduit"
            || $normalized === "Overload Soul Conduit"
            || $normalized === "Overlord Soul Conduit"
            || $normalized === "Overload Conduit"
            || $normalized === "Overlord Conduit"
            || str_starts_with($normalized, "Soul Mode:")
            || str_starts_with($normalized, "Stored Souls:")
            || str_starts_with($normalized, "Stored Group:")
            || str_starts_with($normalized, "Next Tier In:")
            || str_starts_with($normalized, "Next Tier:")
            || str_starts_with($normalized, "Soul Conduit:")
            || str_starts_with($normalized, "Bound Souls:")
            || str_starts_with($normalized, "Active conduit required");
    }

    private static function getCostForEnchant(Player $player, string $enchantId): int
    {
        $normalized = strtolower(trim($enchantId));
        if ($normalized === "") {
            return 0;
        }

        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "config.yml");
        $override = $config?->getNested("souls.costs.{$normalized}", null);
        if (is_numeric($override)) {
            $cost = max(0, (int) $override);
        } else {
            $group = strtolower(self::resolveEnchantGroup($normalized));
            if (!in_array($group, ["mastery", "exclusive"], true)) {
                $cost = 0;
            } else {
                $cost = max(0, (int) ($config?->getNested("souls.default-costs-by-group.{$group}", 0) ?? 0));
            }
        }

        if ($cost <= 0) {
            return 0;
        }

        if ($player->getInventory()->getItemInHand() instanceof Axe && AdvancedEnchantState::isRuneswellSoulDiscountActive($player)) {
            $cost = max(1, (int) ceil($cost / 2));
        }

        return $cost;
    }

    public static function conduitTypeFromGemGlaciaId(string $glaciaId): string {
        return match ($glaciaId) {
            "efficiency_conduit", "soul_conduit_efficiency" => "efficiency",
            "overlord_conduit", "soul_conduit_overload", "soul_conduit_overlord" => "overload",
            default => "stable",
        };
    }

    public static function getBoundConduitTypeName(Item $item): string
    {
        return self::getBoundConduitType($item) ?? "stable";
    }

    public static function createFuelGemForConduitType(string $conduitType, int $souls, string $group = "", ?string $gemId = null): Item
    {
        return match ($conduitType) {
            "efficiency" => GlaciaItemFactory::efficiencyConduitGem($souls, $group, $gemId),
            "overload" => GlaciaItemFactory::overlordConduitGem($souls, $group, $gemId),
            default => GlaciaItemFactory::soulGem($souls, $group, $gemId),
        };
    }

    public static function createConduitItem(string $conduitType, int $souls = 0, string $group = "", ?string $gemId = null): Item
    {
        return match ($conduitType) {
            "efficiency" => GlaciaItemFactory::soulConduitEfficiency($souls, $group, $gemId),
            "overload" => GlaciaItemFactory::soulConduitOverload($souls, $group, $gemId),
            default => GlaciaItemFactory::soulConduit($souls, $group, $gemId),
        };
    }

    private static function getBoundConduitType(Item $item): ?string {
        $glacia = $item->getNamedTag()->getCompoundTag("Glacia");
        $v = trim((string) ($glacia?->getString(self::BOUND_CONDUIT_TYPE_KEY, "") ?? ""));
        return $v !== "" ? $v : null;
    }

    /**
     * @return array{mode:string,color:string,cost_multiplier:float,siphon_chance:int,siphon_amount:int,next_threshold:?int}
     */
    private static function getEffectiveTierDataForBoundItem(Item $item, ?int $poolOverride = null): array {
        $pool = $poolOverride ?? self::getBoundSoulCount($item);
        $ct = self::getBoundConduitType($item);
        if ($ct !== null) {
            return self::getTierDataForConduitType($ct);
        }

        return self::getTierDataBySoulThresholds($pool);
    }

    /**
     * @return array{mode:string,color:string,cost_multiplier:float,siphon_chance:int,siphon_amount:int,next_threshold:?int}
     */
    public static function getTierDataForConduitType(string $conduitType): array {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "config.yml");
        return match ($conduitType) {
            "efficiency" => [
                "mode" => (string) ($config?->getNested("souls.tiers.tier2.mode", "Efficiency") ?? "Efficiency"),
                "color" => (string) ($config?->getNested("souls.tiers.tier2.color", "&6") ?? "&6"),
                "cost_multiplier" => max(0.1, (float) ($config?->getNested("souls.tiers.tier2.cost-multiplier", 0.75) ?? 0.75)),
                "siphon_chance" => 0,
                "siphon_amount" => 1,
                "next_threshold" => null,
            ],
            "overload" => [
                "mode" => (string) ($config?->getNested("souls.tiers.tier3.mode", "Overlord") ?? "Overlord"),
                "color" => (string) ($config?->getNested("souls.tiers.tier3.color", "&4") ?? "&4"),
                "cost_multiplier" => max(0.1, (float) ($config?->getNested("souls.tiers.tier3.cost-multiplier", 0.5) ?? 0.5)),
                "siphon_chance" => max(0, min(100, (int) ($config?->getNested("souls.tiers.tier3.siphon-chance", 20) ?? 20))),
                "siphon_amount" => max(1, (int) ($config?->getNested("souls.tiers.tier3.siphon-amount", 1) ?? 1)),
                "next_threshold" => null,
            ],
            default => [
                "mode" => (string) ($config?->getNested("souls.tiers.tier1.mode", "Stable") ?? "Stable"),
                "color" => (string) ($config?->getNested("souls.tiers.tier1.color", "&a") ?? "&a"),
                "cost_multiplier" => max(0.1, (float) ($config?->getNested("souls.tiers.tier1.cost-multiplier", 1.0) ?? 1.0)),
                "siphon_chance" => 0,
                "siphon_amount" => 1,
                "next_threshold" => null,
            ],
        };
    }

    /**
     * Legacy: soul-count thresholds for gear merged before `soul_conduit_type` existed.
     *
     * @return array{mode:string,color:string,cost_multiplier:float,siphon_chance:int,siphon_amount:int,next_threshold:?int}
     */
    private static function getTierDataBySoulThresholds(int $souls): array
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "config.yml");
        $tier3Min = max(25000, (int) ($config?->getNested("souls.tiers.tier3.min", 25000) ?? 25000));
        $tier2Min = max(10000, (int) ($config?->getNested("souls.tiers.tier2.min", 10000) ?? 10000));

        if ($souls >= $tier3Min) {
            return [
                "mode" => (string) ($config?->getNested("souls.tiers.tier3.mode", "Overlord") ?? "Overlord"),
                "color" => (string) ($config?->getNested("souls.tiers.tier3.color", "&4") ?? "&4"),
                "cost_multiplier" => max(0.1, (float) ($config?->getNested("souls.tiers.tier3.cost-multiplier", 0.5) ?? 0.5)),
                "siphon_chance" => max(0, min(100, (int) ($config?->getNested("souls.tiers.tier3.siphon-chance", 20) ?? 20))),
                "siphon_amount" => max(1, (int) ($config?->getNested("souls.tiers.tier3.siphon-amount", 1) ?? 1)),
                "next_threshold" => null,
            ];
        }

        if ($souls >= $tier2Min) {
            return [
                "mode" => (string) ($config?->getNested("souls.tiers.tier2.mode", "Efficiency") ?? "Efficiency"),
                "color" => (string) ($config?->getNested("souls.tiers.tier2.color", "&6") ?? "&6"),
                "cost_multiplier" => max(0.1, (float) ($config?->getNested("souls.tiers.tier2.cost-multiplier", 0.75) ?? 0.75)),
                "siphon_chance" => 0,
                "siphon_amount" => 1,
                "next_threshold" => $tier3Min,
            ];
        }

        return [
            "mode" => (string) ($config?->getNested("souls.tiers.tier1.mode", "Stable") ?? "Stable"),
            "color" => (string) ($config?->getNested("souls.tiers.tier1.color", "&a") ?? "&a"),
            "cost_multiplier" => max(0.1, (float) ($config?->getNested("souls.tiers.tier1.cost-multiplier", 1.0) ?? 1.0)),
            "siphon_chance" => 0,
            "siphon_amount" => 1,
            "next_threshold" => $tier2Min,
        ];
    }

    private static function resolveEnchantGroup(string $enchantId): string
    {
        $enchant = CustomEnchantments::getEnchantmentByName($enchantId);
        if ($enchant === null) {
            return "";
        }

        return strtoupper((string) (Groups::getGroupNameById($enchant->getRarity()) ?? ""));
    }

    private static function ensureGemId(Item &$item): string
    {
        $glacia = $item->getNamedTag()->getCompoundTag("Glacia") ?? new CompoundTag();
        $existing = trim($glacia->getString(self::SOUL_GEM_ID_KEY, ""));
        if ($existing !== "") {
            return $existing;
        }

        $generated = bin2hex(random_bytes(8));
        $glacia->setString(self::SOUL_GEM_ID_KEY, $generated);
        $root = $item->getNamedTag();
        $root->setTag("Glacia", $glacia);
        $item->setNamedTag($root);
        return $generated;
    }

    private static function getGemId(Item $item): string
    {
        $glacia = $item->getNamedTag()->getCompoundTag("Glacia");
        return trim((string) ($glacia?->getString(self::SOUL_GEM_ID_KEY, "") ?? ""));
    }

    private static function getBoundGemId(Item $item): string
    {
        $glacia = $item->getNamedTag()->getCompoundTag("Glacia");
        return trim((string) ($glacia?->getString(self::BIND_ID_KEY, "") ?? ""));
    }

    private static function getStoredSouls(Item $item): int
    {
        $glacia = $item->getNamedTag()->getCompoundTag("Glacia");
        return max(0, $glacia?->getInt("souls", 0) ?? 0);
    }

    private static function getStoredGroup(Item $item): string
    {
        $glacia = $item->getNamedTag()->getCompoundTag("Glacia");
        return strtoupper(trim((string) ($glacia?->getString("group", "") ?? "")));
    }

    private static function fuelGemTypeFromGlaciaId(string $glaciaId): string
    {
        return match ($glaciaId) {
            "efficiency_gem", "efficiency_conduit_gem" => "efficiency",
            "overlord_gem", "overlord_conduit_gem" => "overload",
            "soul_gem", "soul_conduit_gem" => "stable",
            default => "",
        };
    }

    private static function getGlaciaItemId(Item $item): string
    {
        return $item->getNamedTag()->getCompoundTag("Glacia")?->getString("glaciaItem", "") ?? "";
    }

    private static function stripBoundSoulLore(Item $item): Item
    {
        $clone = clone $item;
        $clone->setLore(array_values(array_filter(
            $clone->getLore(),
            static fn(string $line): bool => !self::isBoundSoulLoreLine(C::clean($line))
        )));
        return ItemLoreLayout::normalizeForCombatGear($clone);
    }

    private static function notifyFailure(Player $player, string $key, string $message): void
    {
        $now = time();
        $last = self::$lastFailureNoticeAt[$key] ?? 0;
        if (($now - $last) < 2) {
            return;
        }

        self::$lastFailureNoticeAt[$key] = $now;
        $player->sendMessage(C::colorize($message));
        PlayerUtils::playSound($player, "note.bass");
    }

    private static function clearFailureNotices(Player $player): void
    {
        $prefix = $player->getUniqueId()->toString();
        foreach (array_keys(self::$lastFailureNoticeAt) as $key) {
            if (str_starts_with($key, $prefix)) {
                unset(self::$lastFailureNoticeAt[$key]);
            }
        }
    }
}
