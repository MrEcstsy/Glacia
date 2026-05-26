<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\items\lore;

use ecstsy\Glacia\server\enchantments\CustomEnchantments;
use pocketmine\item\Armor;
use pocketmine\item\Axe;
use pocketmine\item\Bow;
use pocketmine\item\FishingRod;
use pocketmine\item\Hoe;
use pocketmine\item\Item;
use pocketmine\item\Pickaxe;
use pocketmine\item\Shovel;
use pocketmine\item\Sword;
use pocketmine\utils\TextFormat as C;

final class ItemLoreLayout
{
    /** @var string[]|null */
    private static ?array $customEnchantNames = null;

    /**
     * Applies a stable lore section order for armor/weapons/tools.
     */
    public static function normalizeForCombatGear(Item $item): Item
    {
        if (!self::shouldNormalize($item)) {
            return $item;
        }

        $lore = $item->getLore();
        if ($lore === []) {
            return $item;
        }

        $normalized = self::normalizeLines($lore, $item);
        if ($normalized !== $lore) {
            $item->setLore($normalized);
        }

        return $item;
    }

    private static function shouldNormalize(Item $item): bool
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

    /**
     * @param list<string> $lore
     * @return list<string>
     */
    private static function normalizeLines(array $lore, Item $item): array
    {
        $itemStats = [];
        $enchants = [];
        $description = [];
        $configLore = [];
        $setBonus = [];
        $rarityStats = [];
        $soulSystem = [];
        $extraSystems = [];
        $closers = [];
        $configuredLoreIndices = self::findConfiguredLoreIndices($lore, $item);

        $expectFlavorContinuation = false;

        foreach ($lore as $index => $line) {
            if (isset($configuredLoreIndices[$index])) {
                $configLore[] = $line;
                $expectFlavorContinuation = false;
                continue;
            }

            $clean = trim(C::clean($line));
            if ($clean === "") {
                continue;
            }

            $lower = strtolower($clean);

            if (str_contains($lower, "stronger than diamond")) {
                $closers[] = self::canonicalCloserLine($clean, $item);
                $expectFlavorContinuation = false;
                continue;
            }

            if (self::isHeroicDurabilityLine($clean)) {
                $rarityStats[] = self::normalizeHeroicDurabilityLine($clean);
                $expectFlavorContinuation = false;
                continue;
            }

            if (self::isRarityStatLine($clean)) {
                $rarityStats[] = $line;
                $expectFlavorContinuation = false;
                continue;
            }

            if (self::isSoulSystemLine($clean)) {
                $soulSystem[] = $line;
                $expectFlavorContinuation = false;
                continue;
            }

            if (self::isCustomEnchantLine($clean)) {
                $enchants[] = $line;
                $expectFlavorContinuation = false;
                continue;
            }

            if (self::isSetBonusLine($clean)) {
                $setBonus[] = $line;
                $expectFlavorContinuation = false;
                continue;
            }

            if (self::isItemStatLine($clean)) {
                $itemStats[] = $line;
                $expectFlavorContinuation = false;
                continue;
            }

            if (self::isExtraSystemLine($clean)) {
                $extraSystems[] = $line;
                $expectFlavorContinuation = false;
                continue;
            }

            if ($expectFlavorContinuation && self::isFlavorContinuationLine($clean)) {
                $description[] = $line;
                $expectFlavorContinuation = !str_ends_with($clean, ".");
                continue;
            }

            if (self::isDescriptionLine($clean)) {
                $description[] = $line;
                $expectFlavorContinuation = !str_ends_with($clean, ".");
                continue;
            }

            $expectFlavorContinuation = false;
            $extraSystems[] = $line;
        }

        $rarityStats = self::dedupeHeroicAndNumericDurabilityLines($rarityStats);

        $orderedSections = self::orderedSectionsForItem($item, $itemStats, $enchants, $description, $configLore, $setBonus, $rarityStats, $soulSystem, $extraSystems);

        $result = [];
        $previousKey = null;
        foreach ($orderedSections as $key => $section) {
            if ($section === []) {
                continue;
            }
            if (
                $result !== []
                && C::clean((string) end($result)) !== ""
                && self::shouldInsertBlankBetweenSections($item, $previousKey, $key)
            ) {
                $result[] = "";
            }
            foreach ($section as $line) {
                $result[] = $line;
            }
            $previousKey = $key;
        }

        if ($closers !== []) {
            $result[] = end($closers);
        }

        return $result;
    }

    /**
     * @return array<string, list<string>>
     */
    private static function orderedSectionsForItem(
        Item $item,
        array $itemStats,
        array $enchants,
        array $description,
        array $configLore,
        array $setBonus,
        array $rarityStats,
        array $soulSystem,
        array $extraSystems
    ): array {
        $commonTail = [
            "soulSystem" => $soulSystem,
            "extraSystems" => $extraSystems,
        ];

        if ($item instanceof Armor) {
            return [
                "itemStats" => $itemStats,
                "enchants" => $enchants,
                "description" => $description,
                "configLore" => $configLore,
                "setBonus" => $setBonus,
                "rarityStats" => $rarityStats,
            ] + $commonTail;
        }

        // Weapons / tools: durability & numeric stats sit directly under enchants (matches armor visual rhythm).
        return [
            "itemStats" => $itemStats,
            "enchants" => $enchants,
            "description" => $description,
            "configLore" => $configLore,
            "rarityStats" => $rarityStats,
            "setBonus" => $setBonus,
        ] + $commonTail;
    }

    /**
     * When heroic durability is present, drop a redundant trailing "{max} Durability" line (same max).
     *
     * @param list<string> $lines
     * @return list<string>
     */
    private static function dedupeHeroicAndNumericDurabilityLines(array $lines): array
    {
        $heroicMax = null;
        foreach ($lines as $line) {
            if (preg_match('/Heroic Durability:\s*[\d,]+\s*\/\s*([\d,]+)/i', C::clean((string) $line), $m) === 1) {
                $heroicMax = str_replace(",", "", $m[1]);
                break;
            }
        }

        if ($heroicMax === null) {
            return $lines;
        }

        $out = [];
        foreach ($lines as $line) {
            $clean = C::clean((string) $line);
            if (preg_match('/^([\d,]+)\s+Durability$/', $clean, $m) === 1) {
                $n = str_replace(",", "", $m[1]);
                if ($n === $heroicMax) {
                    continue;
                }
            }
            $out[] = $line;
        }

        return $out;
    }

    private static function shouldInsertBlankBetweenSections(Item $item, ?string $previousKey, string $nextKey): bool
    {
        if ($previousKey === null) {
            return false;
        }

        // Tight: custom enchants flow straight into the flavor / description line (armor).
        if ($item instanceof Armor && $previousKey === "enchants" && $nextKey === "description") {
            return false;
        }

        // Tight: enchants straight into durability / attack damage / armor value block (weapons & tools).
        if (!$item instanceof Armor && $previousKey === "enchants" && $nextKey === "rarityStats") {
            return false;
        }

        return true;
    }

    private static function isFlavorContinuationLine(string $clean): bool
    {
        if (str_starts_with($clean, "*") || str_starts_with($clean, "(") || str_starts_with($clean, "+") || str_starts_with($clean, ">")) {
            return false;
        }
        if (str_contains($clean, ":")) {
            return false;
        }
        if (preg_match('/^\d/', $clean) === 1) {
            return false;
        }

        return str_contains($clean, " ") && strlen($clean) >= 8;
    }

    private static function canonicalCloserLine(string $clean, Item $item): string
    {
        if ($item instanceof Armor) {
            return C::colorize("&r&cThis armor is stronger than diamond.");
        }

        return C::colorize("&r&c" . rtrim($clean, "!.") . ".");
    }

    private static function isHeroicDurabilityLine(string $clean): bool
    {
        return str_starts_with(strtolower($clean), "heroic durability:");
    }

    private static function normalizeHeroicDurabilityLine(string $clean): string
    {
        if (preg_match('/Heroic Durability:\s*([\d,]+)\s*\/\s*([\d,]+)/i', $clean, $match) === 1) {
            return C::colorize("&r&7Heroic Durability: {$match[1]}/{$match[2]}");
        }

        return C::colorize("&r&7" . $clean);
    }

    private static function isRarityStatLine(string $clean): bool
    {
        return str_contains($clean, "Armor Value")
            || preg_match('/^\d[\d,]*\s+Durability$/', $clean) === 1
            || str_contains($clean, "Enchantment Slots")
            || preg_match('/^\+\d[\d,]*\s+Attack Damage$/', $clean) === 1
            || preg_match('/^\+\d[\d,]*\s+Attack Speed$/', $clean) === 1;
    }

    private static function isSoulSystemLine(string $clean): bool
    {
        return $clean === "Soul Gem"
            || $clean === "Soul Conduit"
            || $clean === "Efficiency Soul Conduit"
            || $clean === "Efficiency Conduit"
            || $clean === "Overload Soul Conduit"
            || $clean === "Overlord Soul Conduit"
            || $clean === "Overload Conduit"
            || $clean === "Overlord Conduit"
            || str_starts_with($clean, "Soul Mode:")
            || str_starts_with($clean, "> Soul Mode:")
            || str_starts_with($clean, "Stored Souls:")
            || str_starts_with($clean, "> Stored Souls:")
            || str_starts_with($clean, "Stored Group:")
            || str_starts_with($clean, "> Stored Group:")
            || str_starts_with($clean, "Next Tier In:")
            || str_starts_with($clean, "> Next Tier In:")
            || str_starts_with($clean, "Next Tier:")
            || str_starts_with($clean, "> Next Tier:")
            || str_starts_with($clean, "Soul Conduit:")
            || str_starts_with($clean, "> Soul Conduit:");
    }

    private static function isSetBonusLine(string $clean): bool
    {
        $lower = strtolower($clean);

        return str_contains($lower, "set bonus")
            || str_contains($lower, "weapon bonus")
            || str_starts_with($clean, "* ")
            || str_starts_with($clean, "(Requires all ");
    }

    private static function isItemStatLine(string $clean): bool
    {
        return str_starts_with($clean, "Holy Lives:")
            || $clean === "PROTECTED"
            || $clean === "CORRUPTED"
            || $clean === "SEMI-CORRUPT"
            || $clean === "HOLY WHITE ACTIVE";
    }

    private static function isExtraSystemLine(string $clean): bool
    {
        return str_starts_with($clean, "Rune:")
            || str_starts_with($clean, "Exclusive Use:")
            || str_starts_with($clean, "> Blocks Broken:")
            || str_starts_with($clean, "> Player Kills:")
            || str_starts_with($clean, "> Mob Kills:")
            || str_starts_with($clean, "> XP Gained:")
            || str_starts_with($clean, "> Souls:");
    }

    private static function isDescriptionLine(string $clean): bool
    {
        if (preg_match('/^(The|An|A)\b.*\.$/i', $clean) === 1) {
            return true;
        }

        // Opening line of multi-line flavor (second line often ends the sentence).
        return preg_match('/^(The|An|A)\b/i', $clean) === 1;
    }

    private static function isCustomEnchantLine(string $clean): bool
    {
        if (preg_match('/\s+[IVXLCDM0-9]+$/', $clean) !== 1) {
            return false;
        }

        $lower = strtolower($clean);
        foreach (self::getCustomEnchantNames() as $name) {
            if ($name !== "" && preg_match('/^' . preg_quote($name, '/') . '\s+[IVXLCDM0-9]+$/', $lower) === 1) {
                return true;
            }
        }

        // Fallback: some display names differ from internal enchant IDs
        // (e.g. spacing/punctuation), but still look like "<Enchant Name> <Roman>".
        return !self::isKnownNonEnchantRomanLine($clean);
    }

    private static function isKnownNonEnchantRomanLine(string $clean): bool
    {
        return str_starts_with($clean, "Heroic Durability:")
            || str_contains($clean, "Armor Value")
            || str_contains($clean, "Durability")
            || str_contains($clean, "Enchantment Slots")
            || str_starts_with($clean, "Rune:")
            || str_starts_with($clean, "Exclusive Use:")
            || str_starts_with($clean, "Soul Mode:")
            || str_starts_with($clean, "Stored Souls:")
            || str_starts_with($clean, "Stored Group:")
            || str_starts_with($clean, "Next Tier")
            || str_contains(strtolower($clean), "set bonus")
            || str_contains(strtolower($clean), "weapon bonus")
            || str_starts_with($clean, "(Requires all ")
            || preg_match('/^\+\d[\d,]*\s+Attack Damage$/', $clean) === 1
            || preg_match('/^\+\d[\d,]*\s+Attack Speed$/', $clean) === 1;
    }

    /**
     * @return string[]
     */
    private static function getCustomEnchantNames(): array
    {
        $all = CustomEnchantments::getAll();
        if (self::$customEnchantNames !== null && (self::$customEnchantNames !== [] || $all === [])) {
            return self::$customEnchantNames;
        }

        $names = [];
        foreach ($all as $enchant) {
            $name = strtolower(C::clean($enchant->getName()));
            if ($name !== "") {
                $names[] = $name;
            }
        }

        self::$customEnchantNames = array_values(array_unique($names));
        return self::$customEnchantNames;
    }

    /**
     * @param list<string> $lore
     * @return array<int, true>
     */
    private static function findConfiguredLoreIndices(array $lore, Item $item): array
    {
        $configuredLore = self::getConfiguredLoreSequence($item);
        if ($configuredLore === []) {
            return [];
        }

        $configuredLength = count($configuredLore);
        $loreLength = count($lore);
        if ($configuredLength <= $loreLength) {
            for ($offset = 0; $offset <= $loreLength - $configuredLength; $offset++) {
                if (array_slice($lore, $offset, $configuredLength) === $configuredLore) {
                    $indices = [];
                    for ($index = $offset; $index < $offset + $configuredLength; $index++) {
                        $indices[$index] = true;
                    }

                    return $indices;
                }
            }
        }

        $configuredVisibleLines = [];
        foreach ($configuredLore as $line) {
            $clean = trim(C::clean($line));
            if ($clean === "") {
                continue;
            }

            $configuredVisibleLines[$clean] = true;
        }

        if ($configuredVisibleLines === []) {
            return [];
        }

        $indices = [];
        foreach ($lore as $index => $line) {
            $clean = trim(C::clean($line));
            if ($clean !== "" && isset($configuredVisibleLines[$clean])) {
                $indices[$index] = true;
            }
        }

        return $indices;
    }

    /**
     * @return list<string>
     */
    private static function getConfiguredLoreSequence(Item $item): array
    {
        $raw = $item->getNamedTag()->getCompoundTag("Glacia")?->getString("config_lore_json", "") ?? "";
        if ($raw === "") {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $lines = [];
        foreach ($decoded as $line) {
            if (is_string($line)) {
                $lines[] = $line;
            }
        }

        return $lines;
    }
}

