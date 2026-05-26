<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\items;

use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\item\VanillaItems;

final class AdminConfiguredItemManager
{
    public static function loadFromConfig(): void
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "admin_items.yml");
        $items = (array) ($config?->get("items", []) ?? []);
        /** @var array<string, array{display: string, items: string[]}> $categories */
        $categories = [];

        foreach ($items as $identifier => $data) {
            if (!is_array($data)) {
                Loader::getInstance()->getLogger()->warning("Admin item '{$identifier}' must be a map.");
                continue;
            }

            $id = self::normalizeIdentifier((string) $identifier);
            if ($id === "") {
                Loader::getInstance()->getLogger()->warning("Admin item identifier '{$identifier}' is invalid.");
                continue;
            }

            $materialId = self::resolveMaterialIdentifier($data, $id);
            $material = ItemMaterialParser::parse($materialId) ?? VanillaItems::PAPER();
            if ($material->getTypeId() === VanillaItems::PAPER()->getTypeId() && strtolower($materialId) !== "paper") {
                Loader::getInstance()->getLogger()->warning(
                    "Admin item '{$id}' could not parse material '{$materialId}'; using paper. Use material/item: diamond_axe (not the Glacia item id)."
                );
            }
            $lore = self::normalizeLore($data["lore"] ?? []);
            $nbt = is_array($data["nbt"] ?? null) ? (array) $data["nbt"] : [];
            $rewardConfig = self::extractRewardConfig($data);

            GlaciaItemFactory::$definitions[$id] = [
                "material" => $material,
                "name" => (string) ($data["name"] ?? "&r&f" . ucwords(str_replace("_", " ", $id))),
                "lore" => $lore,
                "nbt" => ["glaciaItem" => $id] + $nbt,
                "vanilla-enchants" => is_array($data["vanilla-enchants"] ?? null) ? (array) $data["vanilla-enchants"] : [],
                "custom-enchants" => is_array($data["custom-enchants"] ?? null) ? (array) $data["custom-enchants"] : [],
                "unbreakable" => (bool) ($data["unbreakable"] ?? false),
                "keep-on-death" => (bool) ($data["keep-on-death"] ?? false),
                "reward-config" => $rewardConfig,
            ];
            self::registerAliases($id, $data);

            $category = self::normalizeIdentifier((string) ($data["category"] ?? "admin_items"));
            if ($category === "") {
                $category = "admin_items";
            }

            $categories[$category] ??= [
                "display" => (string) ($data["category-display"] ?? self::humanizeCategory($category)),
                "items" => [],
            ];

            if (is_string($data["category-display"] ?? null) && trim((string) $data["category-display"]) !== "") {
                $categories[$category]["display"] = (string) $data["category-display"];
            }

            $categories[$category]["items"][] = $id;
        }

        foreach ($categories as $category => $entry) {
            GlaciaItems::appendCategoryItems($category, $entry["display"], $entry["items"]);
        }
    }

    private static function normalizeIdentifier(string $identifier): string
    {
        $identifier = strtolower(trim($identifier));
        return preg_match('/^[a-z0-9_]{3,48}$/', $identifier) === 1 ? $identifier : "";
    }

    private static function humanizeCategory(string $category): string
    {
        return ucwords(str_replace("_", " ", $category));
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function registerAliases(string $canonicalId, array $data): void
    {
        $definition = GlaciaItemFactory::$definitions[$canonicalId] ?? null;
        if (!is_array($definition)) {
            return;
        }

        foreach (self::normalizeAliases($data["aliases"] ?? []) as $alias) {
            if ($alias === $canonicalId) {
                continue;
            }

            if (isset(GlaciaItemFactory::$definitions[$alias])) {
                Loader::getInstance()->getLogger()->warning(
                    "Admin item alias '{$alias}' for '{$canonicalId}' is already in use; skipping alias."
                );
                continue;
            }

            GlaciaItemFactory::$definitions[$alias] = $definition;
        }
    }

    /**
     * @return list<string>
     */
    private static function normalizeAliases(mixed $aliases): array
    {
        $normalized = [];
        foreach ((array) $aliases as $alias) {
            if (!is_string($alias) && !is_int($alias)) {
                continue;
            }

            $id = self::normalizeIdentifier((string) $alias);
            if ($id !== "") {
                $normalized[] = $id;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function resolveMaterialIdentifier(array $data, string $definitionId): string
    {
        foreach (["material", "item"] as $key) {
            if (!isset($data[$key])) {
                continue;
            }

            $value = trim((string) $data[$key]);
            if ($value !== "") {
                return $value;
            }
        }

        if (!isset($data["id"])) {
            return "paper";
        }

        $value = trim((string) $data["id"]);
        if ($value === "") {
            return "paper";
        }

        // Avoid treating the Glacia definition id as a Minecraft item id.
        if (strcasecmp($value, $definitionId) === 0 && ItemMaterialParser::parse($value) === null) {
            return "paper";
        }

        return $value;
    }

    /**
     * @return string[]
     */
    private static function normalizeLore(mixed $lore): array
    {
        if (is_string($lore)) {
            $split = preg_split('/\R/', $lore);
            return $split === false ? [$lore] : array_values(array_map(static fn(string $line): string => $line, $split));
        }

        return array_values(array_map(static fn(mixed $line): string => (string) $line, (array) $lore));
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function extractRewardConfig(array $data): array
    {
        $keys = [
            "enchantments",
            "color",
            "transmogged",
            "pretransmogged",
            "apply_transmog",
            "transmogged_key",
            "announce",
            "rune",
            "applied_rune",
            "preapplied_rune",
            "bound_rune",
            "rune_id",
            "essence",
            "essence_id",
            "essences",
            "protected",
            "whitescrolled",
            "white_scrolled",
            "white_scroll",
            "holy_white",
            "holy_whitescroll",
            "holy_white_scroll",
            "holywhitescroll",
            "holy_white_max",
            "holy_max",
            "holy_white_used",
            "holy_used",
            "holy_white_active",
            "holy_water",
            "holywater",
            "heroic",
            "is_heroic",
            "heroic_armor",
            "soul_conduit",
            "souls",
            "soul_count",
            "stored_souls",
            "soul_group",
            "soul_identifier",
        ];

        $rewardConfig = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                $rewardConfig[$key] = $data[$key];
            }
        }

        if (isset($rewardConfig["enchantments"]) && is_array($rewardConfig["enchantments"])) {
            $rewardConfig["enchantments"] = self::normalizeEnchantmentsConfig($rewardConfig["enchantments"]);
        }

        return $rewardConfig;
    }

    /**
     * Accepts loot-table list entries or a shorthand map (sharpness: 6).
     *
     * @return list<array{enchant: string, level: int|string}>
     */
    private static function normalizeEnchantmentsConfig(array $enchantments): array
    {
        if ($enchantments === []) {
            return [];
        }

        $isList = array_is_list($enchantments);
        if (!$isList) {
            $first = reset($enchantments);
            $isList = is_array($first) && array_key_exists("enchant", $first);
        }

        if ($isList) {
            $normalized = [];
            foreach ($enchantments as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $enchant = trim((string) ($entry["enchant"] ?? $entry["id"] ?? $entry["name"] ?? ""));
                if ($enchant === "") {
                    continue;
                }

                $normalized[] = [
                    "enchant" => $enchant,
                    "level" => $entry["level"] ?? $entry["lvl"] ?? 1,
                ];
            }

            return $normalized;
        }

        $normalized = [];
        foreach ($enchantments as $enchant => $level) {
            if (!is_string($enchant) && !is_int($enchant)) {
                continue;
            }

            $enchantName = trim((string) $enchant);
            if ($enchantName === "") {
                continue;
            }

            $normalized[] = [
                "enchant" => $enchantName,
                "level" => $level,
            ];
        }

        return $normalized;
    }
}
