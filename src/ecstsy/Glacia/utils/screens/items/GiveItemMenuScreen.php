<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\items;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\enchantments\CustomEnchantments;
use ecstsy\Glacia\server\enchantments\essence\EnchantEssenceManager;
use ecstsy\Glacia\server\enchantments\Groups;
use ecstsy\Glacia\server\etherealcrates\EtherealCrateRepository;
use ecstsy\Glacia\server\items\GlaciaItemFactory;
use ecstsy\Glacia\server\items\GlaciaItems;
use ecstsy\Glacia\server\lootboxes\LootboxRepository;
use ecstsy\Glacia\server\zones\ZoneManager;
use ecstsy\Glacia\utils\lang\MessageTemplates;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use Vecnavium\FormsUI\CustomForm;
use Vecnavium\FormsUI\SimpleForm;

final class GiveItemMenuScreen
{
    private const ITEMS_PER_PAGE = 36;
    private const DYNAMIC_LOOTBOX_CATEGORY = "lootboxes";
    private const DYNAMIC_ETHEREAL_CATEGORY = "ethereal_crates";
    private const RECENT_CATEGORY = "__recent__";
    /** @var array<string, list<string>> */
    private static array $recentItems = [];
    /** @var array<string, string> */
    private static array $lastCategory = [];
    /** @var array<string, int> */
    private static array $lastPage = [];

    public static function displayCategoryMenu(Player $player): void
    {
        $categories = self::getCategoryDisplays($player);
        if ($categories === []) {
            $player->sendMessage(C::colorize(MessageTemplates::render("giveitem.error.no_categories")));
            return;
        }

        $form = new SimpleForm(function (Player $player, $data) use ($categories): void {
            if (!is_int($data)) {
                return;
            }

            $keys = array_keys($categories);
            $category = $keys[$data] ?? null;
            if ($category === null) {
                return;
            }

            self::displayItemMenu($player, (string) $category);
        });

        $form->setTitle(C::colorize(MessageTemplates::render("giveitem.menu.category.title")));
        $lastCategory = self::$lastCategory[$player->getUniqueId()->toString()] ?? null;
        $content = MessageTemplates::render("giveitem.menu.category.content");
        if ($lastCategory !== null && isset($categories[$lastCategory])) {
            $content .= "\n&r&8Last: &f" . $categories[$lastCategory];
        }
        $form->setContent(C::colorize($content));
        foreach ($categories as $key => $display) {
            $form->addButton(C::colorize(MessageTemplates::render("giveitem.menu.category.button", [
                "display" => $display,
                "count" => count(self::getCategoryItems($key, $player)),
            ])));
        }
        $player->sendForm($form);
    }

    public static function displayItemMenu(Player $player, string $category, ?int $page = null): void
    {
        $items = self::getCategoryItems($category, $player);

        if ($items === []) {
            $player->sendMessage(C::colorize(MessageTemplates::render("giveitem.error.no_items")));
            return;
        }

        $playerKey = $player->getUniqueId()->toString();
        self::$lastCategory[$playerKey] = $category;
        $page = $page ?? (self::$lastPage[$playerKey] ?? 1);
        $maxPage = max(1, (int) ceil(count($items) / self::ITEMS_PER_PAGE));
        $page = max(1, min($maxPage, $page));
        self::$lastPage[$playerKey] = $page;

        $display = self::getCategoryDisplay($category);
        $pageItems = array_slice($items, ($page - 1) * self::ITEMS_PER_PAGE, self::ITEMS_PER_PAGE);
        $form = new SimpleForm(function (Player $player, $data) use ($pageItems, $category, $page, $maxPage): void {
            if ($data === null) {
                return;
            }

            if ($data === "back") {
                self::displayCategoryMenu($player);
                return;
            }

            if ($data === "prev") {
                self::displayItemMenu($player, $category, $page - 1);
                return;
            }

            if ($data === "next") {
                self::displayItemMenu($player, $category, $page + 1);
                return;
            }

            if ($data === "recent") {
                self::displayItemMenu($player, self::RECENT_CATEGORY, 1);
                return;
            }

            if (!is_int($data)) {
                return;
            }

            $itemId = $pageItems[$data] ?? null;
            if ($itemId === null) {
                return;
            }

            if (self::requiresParameters($itemId)) {
                self::displayParameterForm($player, $category, $itemId, $page);
                return;
            }

            self::giveSelectedItem($player, $category, $itemId, [], $page);
        });

        $form->setTitle(C::colorize(MessageTemplates::render("giveitem.menu.item.title", ["display" => $display]) . " &8({$page}/{$maxPage})"));
        $content = MessageTemplates::render("giveitem.menu.item.content");
        if ($category !== self::RECENT_CATEGORY && self::getRecentItems($player) !== []) {
            $content .= "\n&r&8Tip: recent items are pinned in their own category.";
        }
        $form->setContent(C::colorize($content));
        foreach ($pageItems as $id) {
            $form->addButton(C::colorize(MessageTemplates::render("giveitem.menu.item.button", [
                "label" => self::getMenuItemLabel($id),
                "id" => self::getMenuItemSubtitle($id),
            ])));
        }
        if ($page > 1) {
            $form->addButton(C::colorize("&r&ePrevious Page"), -1, "", "prev");
        }
        if ($page < $maxPage) {
            $form->addButton(C::colorize("&r&eNext Page"), -1, "", "next");
        }
        $form->addButton(C::colorize(MessageTemplates::render("giveitem.menu.item.back")), -1, "", "back");
        $player->sendForm($form);
    }

    /**
     * @return array<string, string>
     */
    public static function getCategoryDisplays(?Player $player = null): array
    {
        $categories = GlaciaItems::getCategoryDisplays();
        $lootboxes = LootboxRepository::getBoxIds();
        $etherealCrates = EtherealCrateRepository::getCrateIds();
        if ($lootboxes !== []) {
            $categories[self::DYNAMIC_LOOTBOX_CATEGORY] = "Lootboxes";
        }
        if ($etherealCrates !== []) {
            $categories[self::DYNAMIC_ETHEREAL_CATEGORY] = MessageTemplates::render("giveitem.category.ethereal_crates");
        }

        if ($player !== null && self::getRecentItems($player) !== []) {
            $categories = [self::RECENT_CATEGORY => "Recent Items"] + $categories;
        }

        $pinned = [
            self::RECENT_CATEGORY,
            "tools",
            "economy",
            "enchants",
            "crates",
            "lootboxes",
            "progression",
            self::DYNAMIC_ETHEREAL_CATEGORY,
            "pets",
            "quests",
            "runes",
            "rings",
        ];

        uksort($categories, static function(string $left, string $right) use ($pinned, $categories): int {
            $leftPinned = array_search($left, $pinned, true);
            $rightPinned = array_search($right, $pinned, true);
            if ($leftPinned !== false || $rightPinned !== false) {
                return ($leftPinned === false ? 999 : $leftPinned) <=> ($rightPinned === false ? 999 : $rightPinned);
            }

            return strnatcasecmp((string) ($categories[$left] ?? $left), (string) ($categories[$right] ?? $right));
        });

        return $categories;
    }

    /**
     * @return string[]
     */
    public static function getCategoryItems(string $category, ?Player $player = null): array
    {
        if ($category === self::RECENT_CATEGORY) {
            return $player !== null ? self::getRecentItems($player) : [];
        }

        if ($category === self::DYNAMIC_LOOTBOX_CATEGORY) {
            $items = array_map(
                static fn(string $id): string => "lootbox:" . $id,
                LootboxRepository::getBoxIds()
            );
            usort($items, static fn(string $a, string $b): int => strnatcasecmp(self::getMenuItemLabel($a), self::getMenuItemLabel($b)));
            return $items;
        }

        if ($category === self::DYNAMIC_ETHEREAL_CATEGORY) {
            $items = array_map(
                static fn(string $id): string => "ethereal_crate:" . $id,
                EtherealCrateRepository::getCrateIds()
            );
            usort($items, static fn(string $a, string $b): int => strnatcasecmp(self::getMenuItemLabel($a), self::getMenuItemLabel($b)));
            return $items;
        }

        $items = array_values(array_filter(
            GlaciaItems::getCategoryItems($category),
            static fn(string $id): bool => isset(GlaciaItemFactory::$definitions[$id])
        ));
        usort($items, static fn(string $a, string $b): int => strnatcasecmp(self::getMenuItemLabel($a), self::getMenuItemLabel($b)));
        return $items;
    }

    public static function getCategoryDisplay(string $category): string
    {
        if ($category === self::RECENT_CATEGORY) {
            return "Recent Items";
        }
        return self::getCategoryDisplays()[$category] ?? (GlaciaItems::getCategoryDisplay($category) ?? ucfirst($category));
    }

    /**
     * @return list<string>
     */
    public static function getAllCatalogueItems(?Player $player = null): array
    {
        $items = [];
        foreach (array_keys(self::getCategoryDisplays($player)) as $category) {
            if ($category === self::RECENT_CATEGORY) {
                continue;
            }

            foreach (self::getCategoryItems($category, $player) as $itemId) {
                $items[$itemId] = $itemId;
            }
        }

        $items = array_values($items);
        usort($items, static fn(string $a, string $b): int => strnatcasecmp(self::getMenuItemLabel($a), self::getMenuItemLabel($b)));
        return $items;
    }

    /**
     * @return list<string>
     */
    public static function searchItems(string $query, ?Player $player = null): array
    {
        $terms = array_values(array_filter(
            preg_split('/\s+/', strtolower(trim($query))) ?: [],
            static fn(string $term): bool => $term !== ""
        ));
        if ($terms === []) {
            return [];
        }

        $matches = [];
        foreach (self::getAllCatalogueItems($player) as $itemId) {
            $haystack = self::buildSearchHaystack($itemId);
            $score = 0;
            $matched = true;
            foreach ($terms as $term) {
                if (!str_contains($haystack, $term)) {
                    $matched = false;
                    break;
                }

                if (str_contains(strtolower($itemId), $term)) {
                    $score += 4;
                }
                if (str_contains(strtolower(self::getMenuItemLabel($itemId)), $term)) {
                    $score += 3;
                }
                if (str_starts_with(strtolower($itemId), $term)) {
                    $score += 2;
                }
                if (str_starts_with(strtolower(self::getMenuItemLabel($itemId)), $term)) {
                    $score += 2;
                }
            }

            if ($matched) {
                $matches[] = [
                    "id" => $itemId,
                    "score" => $score,
                ];
            }
        }

        usort($matches, static function (array $left, array $right): int {
            $scoreCompare = ((int) $right["score"]) <=> ((int) $left["score"]);
            if ($scoreCompare !== 0) {
                return $scoreCompare;
            }

            return strnatcasecmp(self::getMenuItemLabel((string) $left["id"]), self::getMenuItemLabel((string) $right["id"]));
        });

        return array_values(array_map(static fn(array $row): string => (string) $row["id"], $matches));
    }

    public static function getMenuItemLabel(string $itemId): string
    {
        if (str_starts_with($itemId, "lootbox:")) {
            $lookup = substr($itemId, strlen("lootbox:"));
            $box = LootboxRepository::getBoxByLookup($lookup);
            return $box !== null
                ? C::clean(C::colorize((string) ($box["item-name"] ?? $box["name"] ?? $lookup)))
                : ucfirst(str_replace("_", " ", $lookup));
        }

        if (str_starts_with($itemId, "ethereal_crate:")) {
            $lookup = substr($itemId, strlen("ethereal_crate:"));
            $crate = EtherealCrateRepository::getCrateByLookup($lookup);
            return $crate !== null
                ? C::clean(C::colorize((string) ($crate["item-name"] ?? $crate["name"] ?? $lookup)))
                : ucfirst(str_replace("_", " ", $lookup));
        }

        $definition = GlaciaItemFactory::$definitions[$itemId] ?? null;
        $rawName = (string) ($definition["name"] ?? $itemId);
        return C::clean(C::colorize($rawName));
    }

    public static function getMenuItemSubtitle(string $itemId): string
    {
        $suffix = self::requiresParameters($itemId) ? " &8| &eConfigure" : "";
        if (str_starts_with($itemId, "lootbox:")) {
            return "lootbox:" . substr($itemId, strlen("lootbox:")) . $suffix;
        }
        if (str_starts_with($itemId, "ethereal_crate:")) {
            return MessageTemplates::render("giveitem.menu.item.dynamic_id", [
                "id" => substr($itemId, strlen("ethereal_crate:")),
            ]) . $suffix;
        }

        return $itemId . $suffix;
    }

    public static function requiresParameters(string $itemId): bool
    {
        if (str_starts_with($itemId, "essence_")) {
            return true;
        }

        return in_array($itemId, [
            "heroic_upgrade",
            "charm_shard",
            "charm_dust",
            "blackscroll_dust",
            "blackscroll",
            "holy_blackscroll",
            "armor_orb_script",
            "weapon_orb_script",
            "disarmor_ring",
            "lifesteal_ring",
            "guardian_ring",
            "orb",
            "magicdust",
            "primal_dust",
            "random_magicdust",
            "random_book",
            "enchantment_book",
            "bank_note",
            "xpnote",
            "admin_coin_note",
            "soul_conduit",
            "efficiency_conduit",
            "overlord_conduit",
            "soul_gem",
            "efficiency_gem",
            "overlord_gem",
            "faction_xp_booster",
            "zone_xp_booster",
            "zone_fishing_booster",
            "item_flip_credit_voucher",
            "soul_compass",
            "exclusive_amplifier",
            "pet_duration_rune",
        ], true);
    }

    private static function giveSelectedItem(Player $player, string $category, string $itemId, array $params = [], ?int $page = null): void
    {
        $item = self::buildMenuItem($itemId, $player, $params);
        if ($item === null) {
            $player->sendMessage(C::colorize(MessageTemplates::render("giveitem.error.manual_item", ["id" => $itemId])));
            return;
        }

        $item->setCount(1);
        if ($player->getInventory()->canAddItem($item)) {
            $player->getInventory()->addItem($item);
        } else {
            $player->getWorld()->dropItem($player->getPosition(), $item);
        }

        $name = $item->getCustomName() !== "" ? $item->getCustomName() : $item->getName();
        $player->sendMessage(C::colorize(MessageTemplates::render("giveitem.success.given", ["name" => $name])));
        self::rememberRecentItem($player, $itemId);
        self::displayItemMenu($player, $category, $page);
    }

    private static function displayParameterForm(Player $player, string $category, string $itemId, ?int $page = null): void
    {
        $definition = self::getParameterFormDefinition($itemId);
        if ($definition === null) {
            $player->sendMessage(C::colorize(MessageTemplates::render("giveitem.error.manual_item", ["id" => $itemId])));
            return;
        }

        $form = new CustomForm(function (Player $player, ?array $data) use ($category, $itemId, $definition, $page): void {
            if ($data === null) {
                self::displayItemMenu($player, $category, $page);
                return;
            }

            $values = [];
            foreach ($definition["fields"] as $index => $field) {
                $values[$field["key"]] = $data[$index + 1] ?? null;
            }

            $item = self::buildMenuItem($itemId, $player, $values);
            if ($item === null) {
                self::displayParameterForm($player, $category, $itemId, $page);
                return;
            }

            $item->setCount(1);
            if ($player->getInventory()->canAddItem($item)) {
                $player->getInventory()->addItem($item);
            } else {
                $player->getWorld()->dropItem($player->getPosition(), $item);
            }

            $name = $item->getCustomName() !== "" ? $item->getCustomName() : $item->getName();
            $player->sendMessage(C::colorize(MessageTemplates::render("giveitem.success.given", ["name" => $name])));
            self::rememberRecentItem($player, $itemId);
            self::displayItemMenu($player, $category, $page);
        });

        $form->setTitle(C::colorize(MessageTemplates::render("giveitem.form.title", ["item" => self::getMenuItemLabel($itemId)])));
        $form->addLabel(C::colorize($definition["content"]));
        foreach ($definition["fields"] as $field) {
            if (($field["type"] ?? "input") === "dropdown") {
                $form->addDropdown((string) $field["label"], array_values((array) $field["options"]), (int) ($field["default"] ?? 0));
                continue;
            }

            $form->addInput(
                (string) $field["label"],
                (string) ($field["placeholder"] ?? ""),
                (string) ($field["default"] ?? "")
            );
        }
        $player->sendForm($form);
    }

    /**
     * @return array{content: string, fields: array<int, array<string, mixed>>}|null
     */
    private static function getParameterFormDefinition(string $itemId): ?array
    {
        if (str_starts_with($itemId, "essence_")) {
            $plain = substr($itemId, strlen("essence_"));
            $defaultSuccess = (string) EnchantEssenceManager::getEssenceDefaultSuccessPercent($plain);

            return [
                "content" => "&r&7Apply success chance (1–100) stored on this essence. Dragging onto gear rolls this first; on failure the essence is consumed without bonding.",
                "fields" => [[
                    "key" => "success",
                    "label" => MessageTemplates::render("giveitem.form.field.success"),
                    "placeholder" => "1-100",
                    "default" => $defaultSuccess,
                ]],
            ];
        }

        $groupOptions = self::getEnchantGroupOptions();
        $petOptions = self::getPetOptions();

        return match ($itemId) {
            "heroic_upgrade" => [
                "content" => MessageTemplates::render("giveitem.form.content.heroic_upgrade"),
                "fields" => [[
                    "key" => "success",
                    "label" => MessageTemplates::render("giveitem.form.field.success"),
                    "placeholder" => "1-100",
                    "default" => "65",
                ]],
            ],
            "charm_shard" => [
                "content" => MessageTemplates::render("giveitem.form.content.charm_shard"),
                "fields" => [
                    [
                        "key" => "success",
                        "label" => MessageTemplates::render("giveitem.form.field.base_success"),
                        "placeholder" => "1-100",
                        "default" => "70",
                    ],
                    [
                        "key" => "bonus_success",
                        "label" => MessageTemplates::render("giveitem.form.field.bonus_success"),
                        "placeholder" => "0+",
                        "default" => "0",
                    ],
                ],
            ],
            "charm_dust" => [
                "content" => MessageTemplates::render("giveitem.form.content.charm_dust"),
                "fields" => [[
                    "key" => "bonus",
                    "label" => MessageTemplates::render("giveitem.form.field.bonus"),
                    "placeholder" => "1+",
                    "default" => "5",
                ]],
            ],
            "blackscroll", "holy_blackscroll", "disarmor_ring", "lifesteal_ring", "guardian_ring", "blackscroll_dust" => [
                "content" => MessageTemplates::render("giveitem.form.content.blackscroll"),
                "fields" => [[
                    "key" => $itemId === "blackscroll_dust" ? "bonus" : "success",
                    "label" => MessageTemplates::render("giveitem.form.field.success"),
                    "placeholder" => "1-100",
                    "default" => "100",
                ]],
            ],
            "orb" => [
                "content" => MessageTemplates::render("giveitem.form.content.orb"),
                "fields" => [
                    [
                        "key" => "type",
                        "type" => "dropdown",
                        "label" => MessageTemplates::render("giveitem.form.field.orb_type"),
                        "options" => ["weapon", "armor", "tool"],
                        "default" => 1,
                    ],
                    [
                        "key" => "max",
                        "label" => MessageTemplates::render("giveitem.form.field.max_slots"),
                        "placeholder" => "1-100",
                        "default" => "100",
                    ],
                    [
                        "key" => "success",
                        "label" => MessageTemplates::render("giveitem.form.field.success"),
                        "placeholder" => "1-100",
                        "default" => "100",
                    ],
                ],
            ],
            "magicdust", "primal_dust", "random_magicdust", "random_book" => [
                "content" => MessageTemplates::render("giveitem.form.content.grouped_item"),
                "fields" => array_values(array_filter([
                    [
                        "key" => "group",
                        "type" => "dropdown",
                        "label" => MessageTemplates::render("giveitem.form.field.group"),
                        "options" => $groupOptions,
                        "default" => 0,
                    ],
                    in_array($itemId, ["magicdust", "primal_dust"], true) ? [
                        "key" => "percent",
                        "label" => MessageTemplates::render("giveitem.form.field.percent"),
                        "placeholder" => "1-100",
                        "default" => "100",
                    ] : null,
                ])),
            ],
            "enchantment_book" => [
                "content" => MessageTemplates::render("giveitem.form.content.enchantment_book"),
                "fields" => [
                    [
                        "key" => "enchant",
                        "label" => MessageTemplates::render("giveitem.form.field.enchant"),
                        "placeholder" => "e.g. looting, silk_touch, obliterate",
                        "default" => "",
                    ],
                    [
                        "key" => "level",
                        "label" => MessageTemplates::render("giveitem.form.field.level"),
                        "placeholder" => "1+",
                        "default" => "1",
                    ],
                    [
                        "key" => "success",
                        "label" => MessageTemplates::render("giveitem.form.field.success"),
                        "placeholder" => "1-100",
                        "default" => "100",
                    ],
                    [
                        "key" => "destroy",
                        "label" => MessageTemplates::render("giveitem.form.field.destroy"),
                        "placeholder" => "0-100",
                        "default" => "0",
                    ],
                ],
            ],
            "bank_note", "xpnote", "admin_coin_note", "item_flip_credit_voucher", "soul_conduit", "efficiency_conduit", "overlord_conduit", "soul_gem", "efficiency_gem", "overlord_gem" => [
                "content" => MessageTemplates::render("giveitem.form.content.numeric_item"),
                "fields" => [[
                    "key" => "amount",
                    "label" => MessageTemplates::render("giveitem.form.field.amount"),
                    "placeholder" => "1+",
                    "default" => "1000",
                ]],
            ],
            "soul_compass" => [
                "content" => MessageTemplates::render("giveitem.form.content.soul_compass"),
                "fields" => [[
                    "key" => "uses",
                    "label" => MessageTemplates::render("giveitem.form.field.uses"),
                    "placeholder" => "1+",
                    "default" => "8",
                ]],
            ],
            "exclusive_amplifier" => [
                "content" => "&r&7Set how many uses this Exclusive Amplifier should add.",
                "fields" => [[
                    "key" => "uses",
                    "label" => MessageTemplates::render("giveitem.form.field.uses"),
                    "placeholder" => "1+",
                    "default" => "8",
                ]],
            ],
            "faction_xp_booster" => [
                "content" => MessageTemplates::render("giveitem.form.content.faction_xp_booster"),
                "fields" => [
                    [
                        "key" => "multiplier",
                        "label" => MessageTemplates::render("giveitem.form.field.multiplier"),
                        "placeholder" => "e.g. 2 or 2.5",
                        "default" => "2",
                    ],
                    [
                        "key" => "duration",
                        "label" => MessageTemplates::render("giveitem.form.field.duration"),
                        "placeholder" => "e.g. 1h or 30m",
                        "default" => "1h",
                    ],
                ],
            ],
            "zone_xp_booster" => [
                "content" => MessageTemplates::render("giveitem.form.content.zone_xp_booster"),
                "fields" => [
                    [
                        "key" => "multiplier",
                        "label" => MessageTemplates::render("giveitem.form.field.multiplier"),
                        "placeholder" => "e.g. 2 or 2.5",
                        "default" => "2",
                    ],
                    [
                        "key" => "duration",
                        "label" => MessageTemplates::render("giveitem.form.field.duration"),
                        "placeholder" => "e.g. 1h or 30m",
                        "default" => "1h",
                    ],
                ],
            ],
            "zone_fishing_booster" => [
                "content" => MessageTemplates::render("giveitem.form.content.zone_fishing_booster"),
                "fields" => [
                    [
                        "key" => "zone",
                        "label" => MessageTemplates::render("giveitem.form.field.zone"),
                        "placeholder" => "e.g. frostreach",
                        "default" => "",
                    ],
                    [
                        "key" => "multiplier",
                        "label" => MessageTemplates::render("giveitem.form.field.multiplier"),
                        "placeholder" => "e.g. 2 or 2.5",
                        "default" => "2",
                    ],
                    [
                        "key" => "duration",
                        "label" => MessageTemplates::render("giveitem.form.field.duration"),
                        "placeholder" => "e.g. 1h or 30m",
                        "default" => "1h",
                    ],
                ],
            ],
            "pet_duration_rune" => [
                "content" => MessageTemplates::render("giveitem.form.content.pet_duration_rune"),
                "fields" => [[
                    "key" => "pet",
                    "type" => "dropdown",
                    "label" => MessageTemplates::render("giveitem.form.field.pet"),
                    "options" => array_column($petOptions, "label"),
                    "default" => 0,
                ]],
            ],
            default => null,
        };
    }

    public static function buildMenuItem(string $itemId, Player $player, array $params = []): ?Item
    {
        try {
        if (str_starts_with($itemId, "lootbox:")) {
            $lookup = substr($itemId, strlen("lootbox:"));
            try {
                return GlaciaItemFactory::lootbox($lookup, $player);
            } catch (\Throwable) {
                $player->sendMessage(C::colorize(MessageTemplates::render("giveitem.error.manual_item", ["id" => $lookup])));
                return null;
            }
        }

        if (str_starts_with($itemId, "ethereal_crate:")) {
            $lookup = substr($itemId, strlen("ethereal_crate:"));
            try {
                return GlaciaItemFactory::etherealCrate($lookup, $player);
            } catch (\Throwable) {
                $player->sendMessage(C::colorize(MessageTemplates::render("giveitem.error.invalid_crate", ["id" => $lookup])));
                return null;
            }
        }

        if (str_starts_with($itemId, "essence_")) {
            $plain = substr($itemId, strlen("essence_"));
            $defSucc = EnchantEssenceManager::getEssenceDefaultSuccessPercent($plain);
            $success = self::readBoundedInt($player, $params["success"] ?? null, $defSucc, 1, 100, "giveitem.error.invalid_success");

            return GlaciaItemFactory::enchantEssence($plain, $success);
        }

        return match ($itemId) {
            "heroic_upgrade" => GlaciaItemFactory::heroicUpgrade(self::readBoundedInt($player, $params["success"] ?? null, 65, 1, 100, "giveitem.error.invalid_success")),
            "charm_shard" => GlaciaItemFactory::charmShard(
                self::readBoundedInt($player, $params["success"] ?? null, 70, 1, 100, "giveitem.error.invalid_success"),
                self::readBoundedInt($player, $params["bonus_success"] ?? null, 0, 0, 100, "giveitem.error.invalid_bonus")
            ),
            "charm_dust" => GlaciaItemFactory::charmDust(self::readBoundedInt($player, $params["bonus"] ?? null, 5, 1, 1000000, "giveitem.error.invalid_bonus")),
            "blackscroll" => GlaciaItemFactory::blackscroll(self::readBoundedInt($player, $params["success"] ?? null, 100, 1, 100, "giveitem.error.invalid_success")),
            "holy_blackscroll" => GlaciaItemFactory::holyBlackscroll(self::readBoundedInt($player, $params["success"] ?? null, 100, 1, 100, "giveitem.error.invalid_success")),
            "blackscroll_dust" => GlaciaItemFactory::blackscrollDust(self::readBoundedInt($player, $params["bonus"] ?? null, 100, 1, 1000000, "giveitem.error.invalid_bonus")),
            "disarmor_ring" => GlaciaItemFactory::ring("disarmor", self::readBoundedInt($player, $params["success"] ?? null, 100, 1, 100, "giveitem.error.invalid_success")),
            "lifesteal_ring" => GlaciaItemFactory::ring("lifesteal", self::readBoundedInt($player, $params["success"] ?? null, 100, 1, 100, "giveitem.error.invalid_success")),
            "guardian_ring" => GlaciaItemFactory::ring("guardian", self::readBoundedInt($player, $params["success"] ?? null, 100, 1, 100, "giveitem.error.invalid_success")),
            "orb" => self::buildOrbItem($player, $params),
            "magicdust" => self::buildMagicDustItem($player, $params, false),
            "primal_dust" => self::buildMagicDustItem($player, $params, true),
            "random_magicdust" => self::buildRandomDustItem($player, $params),
            "random_book" => self::buildRandomBookItem($player, $params),
            "enchantment_book" => self::buildEnchantmentBookItem($player, $params),
            "bank_note" => GlaciaItemFactory::bankNoteSigned($player->getName(), self::readPositiveInt($player, $params["amount"] ?? null, 1000, "giveitem.error.invalid_amount")),
            "xpnote" => GlaciaItemFactory::xpNoteSigned($player->getName(), self::readPositiveInt($player, $params["amount"] ?? null, 1000, "giveitem.error.invalid_amount")),
            "admin_coin_note" => GlaciaItemFactory::adminCoinNoteSigned($player->getName(), self::readPositiveInt($player, $params["amount"] ?? null, 1000, "giveitem.error.invalid_amount")),
            "soul_conduit" => GlaciaItemFactory::soulConduit(self::readPositiveInt($player, $params["amount"] ?? null, 1, "giveitem.error.invalid_amount")),
            "efficiency_conduit" => GlaciaItemFactory::soulConduitEfficiency(self::readPositiveInt($player, $params["amount"] ?? null, 1, "giveitem.error.invalid_amount")),
            "overlord_conduit" => GlaciaItemFactory::soulConduitOverload(self::readPositiveInt($player, $params["amount"] ?? null, 1, "giveitem.error.invalid_amount")),
            "soul_gem" => GlaciaItemFactory::soulGem(self::readPositiveInt($player, $params["amount"] ?? null, 1, "giveitem.error.invalid_amount")),
            "efficiency_gem" => GlaciaItemFactory::efficiencyConduitGem(self::readPositiveInt($player, $params["amount"] ?? null, 1, "giveitem.error.invalid_amount")),
            "overlord_gem" => GlaciaItemFactory::overlordConduitGem(self::readPositiveInt($player, $params["amount"] ?? null, 1, "giveitem.error.invalid_amount")),
            "item_flip_credit_voucher" => GlaciaItemFactory::itemFlipCreditVoucher(self::readPositiveInt($player, $params["amount"] ?? null, 10, "giveitem.error.invalid_amount")),
            "soul_compass" => GlaciaItemFactory::soulCompass(self::readPositiveInt($player, $params["uses"] ?? null, 8, "giveitem.error.invalid_uses")),
            "exclusive_amplifier" => GlaciaItemFactory::exclusiveAmplifier(self::readPositiveInt($player, $params["uses"] ?? null, 8, "giveitem.error.invalid_uses")),
            "faction_xp_booster" => self::buildFactionBoosterItem($player, $params),
            "zone_xp_booster" => self::buildZoneXpBoosterItem($player, $params),
            "zone_fishing_booster" => self::buildZoneFishingBoosterItem($player, $params),
            "pet_duration_rune" => self::buildPetDurationRune($player, $params),
            default => self::buildDefaultItem($itemId),
        };
        } catch (\InvalidArgumentException) {
            return null;
        } catch (\Throwable $e) {
            try {
                Loader::getInstance()->getLogger()->logException($e);
            } catch (\Throwable) {
                // ignore
            }
            return null;
        }
    }

    private static function buildOrbItem(Player $player, array $params): ?Item
    {
        $type = strtolower((string) ($params["type"] ?? "armor"));
        if (!in_array($type, ["weapon", "armor", "tool"], true)) {
            $player->sendMessage(C::colorize(MessageTemplates::render("giveitem.error.invalid_orb_type")));
            return null;
        }

        $max = self::readBoundedInt($player, $params["max"] ?? null, 100, 1, 100, "giveitem.error.invalid_max_slots");
        $success = self::readBoundedInt($player, $params["success"] ?? null, 100, 1, 100, "giveitem.error.invalid_success");
        return GlaciaItemFactory::enchantOrb($type, $max, 1, $success);
    }

    private static function buildMagicDustItem(Player $player, array $params, bool $primal): ?Item
    {
        $group = self::resolveGroupSelection($player, $params["group"] ?? null);
        if ($group === null) {
            return null;
        }

        $percent = self::readBoundedInt($player, $params["percent"] ?? null, 100, 1, 100, "giveitem.error.invalid_percent");
        return $primal
            ? GlaciaItemFactory::primalDust($group, $percent)
            : GlaciaItemFactory::magicDust($group, $percent);
    }

    private static function buildRandomDustItem(Player $player, array $params): ?Item
    {
        $group = self::resolveGroupSelection($player, $params["group"] ?? null);
        return $group !== null ? GlaciaItemFactory::randomMagicDust($group) : null;
    }

    private static function buildRandomBookItem(Player $player, array $params): ?Item
    {
        $group = self::resolveGroupSelection($player, $params["group"] ?? null);
        return $group !== null ? GlaciaItemFactory::randomEnchantBook($group) : null;
    }

    private static function buildEnchantmentBookItem(Player $player, array $params): ?Item
    {
        $enchantName = trim((string) ($params["enchant"] ?? ""));
        if ($enchantName === "") {
            $player->sendMessage(C::colorize(MessageTemplates::render("giveitem.error.invalid_enchant")));
            return null;
        }

        $level = self::readPositiveInt($player, $params["level"] ?? null, 1, "giveitem.error.invalid_level");
        $success = self::readBoundedInt($player, $params["success"] ?? null, 100, 1, 100, "giveitem.error.invalid_success");
        $destroy = self::readBoundedInt($player, $params["destroy"] ?? null, 0, 0, 100, "giveitem.error.invalid_destroy");

        $customEnchant = CustomEnchantments::getEnchantmentByName($enchantName)
            ?? CustomEnchantments::getEnchantmentByName(self::normalizeEnchantIdentifier($enchantName));
        if ($customEnchant !== null) {
            return GlaciaItemFactory::enchantmentBook($customEnchant, $level, $success, $destroy);
        }

        $book = GlaciaItemFactory::vanillaEnchantmentBook($enchantName, $level, $success, $destroy);
        if ($book === null) {
            $player->sendMessage(C::colorize(MessageTemplates::render("giveitem.error.invalid_enchant")));
            return null;
        }

        return $book;
    }

    private static function buildFactionBoosterItem(Player $player, array $params): ?Item
    {
        $multiplierRaw = trim((string) ($params["multiplier"] ?? "2"));
        if (!is_numeric($multiplierRaw)) {
            $player->sendMessage(C::colorize(MessageTemplates::render("giveitem.error.invalid_multiplier")));
            return null;
        }

        $multiplier = max(0.1, (float) $multiplierRaw);
        $duration = trim((string) ($params["duration"] ?? "1h"));
        if ($duration === "") {
            $player->sendMessage(C::colorize(MessageTemplates::render("giveitem.error.invalid_duration")));
            return null;
        }

        return GlaciaItemFactory::factionXpBooster($multiplier, $duration);
    }

    private static function buildZoneXpBoosterItem(Player $player, array $params): ?Item
    {
        $multiplierRaw = trim((string) ($params["multiplier"] ?? "2"));
        if (!is_numeric($multiplierRaw)) {
            $player->sendMessage(C::colorize(MessageTemplates::render("giveitem.error.invalid_multiplier")));
            return null;
        }

        $multiplier = max(0.1, (float) $multiplierRaw);
        $duration = trim((string) ($params["duration"] ?? "1h"));
        if ($duration === "") {
            $player->sendMessage(C::colorize(MessageTemplates::render("giveitem.error.invalid_duration")));
            return null;
        }

        return GlaciaItemFactory::zoneXpBooster("", $multiplier, $duration);
    }

    private static function buildZoneFishingBoosterItem(Player $player, array $params): ?Item
    {
        $zoneId = strtolower(trim((string) ($params["zone"] ?? "")));
        if ($zoneId !== "" && !in_array($zoneId, ["any", "any zone", "current", "*"], true) && ZoneManager::getZone($zoneId) === null) {
            $player->sendMessage(C::colorize("&r&cEnter a valid zone id from zones.yml."));
            return null;
        }

        $multiplierRaw = trim((string) ($params["multiplier"] ?? "2"));
        if (!is_numeric($multiplierRaw)) {
            $player->sendMessage(C::colorize(MessageTemplates::render("giveitem.error.invalid_multiplier")));
            return null;
        }

        $multiplier = max(0.1, (float) $multiplierRaw);
        $duration = trim((string) ($params["duration"] ?? "1h"));
        if ($duration === "") {
            $player->sendMessage(C::colorize(MessageTemplates::render("giveitem.error.invalid_duration")));
            return null;
        }

        if ($zoneId === "" || in_array($zoneId, ["any", "any zone", "current", "*"], true)) {
            $zoneId = "";
        }

        return GlaciaItemFactory::zoneFishingBooster($zoneId, $multiplier, $duration);
    }

    private static function buildPetDurationRune(Player $player, array $params): ?Item
    {
        $petOptions = self::getPetOptions();
        $selected = $params["pet"] ?? 0;
        $selectedIndex = is_numeric($selected) ? (int) $selected : 0;
        $pet = $petOptions[$selectedIndex] ?? null;
        if ($pet === null) {
            $player->sendMessage(C::colorize(MessageTemplates::render("giveitem.error.invalid_pet")));
            return null;
        }

        return GlaciaItemFactory::create("pet_duration_rune", [
            "pet" => $pet["id"],
            "pet-display" => $pet["label"],
        ]);
    }

    private static function buildDefaultItem(string $itemId): ?Item
    {
        try {
            return match ($itemId) {
                "dormant_sigil" => GlaciaItemFactory::questSigil("dormant"),
                "awakened_sigil" => GlaciaItemFactory::questSigil("awakened"),
                "ascendent_sigil" => GlaciaItemFactory::questSigil("ascendent"),
                "eternal_sigil" => GlaciaItemFactory::questSigil("eternal"),
                default => GlaciaItemFactory::create($itemId),
            };
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return string[]
     */
    private static function getEnchantGroupOptions(): array
    {
        return ["simple", "unique", "elite", "exotic", "legendary", "mastery"];
    }

    /**
     * @return array<int, array{id: string, label: string}>
     */
    private static function getPetOptions(): array
    {
        $pets = [];
        foreach (GlaciaItems::getCategoryItems("pets") as $id) {
            if ($id === "pet_tamer") {
                continue;
            }

            // `/gi` dropdown: show a readable label from the item id (underscores → spaces).
            $labelId = preg_replace('/_pet$/', '', (string) $id) ?? (string) $id;

            $pets[] = [
                "id" => $id,
                "label" => ucfirst(str_replace("_", " ", $labelId)),
            ];
        }

        return $pets;
    }

    private static function resolveGroupSelection(Player $player, mixed $selection): ?string
    {
        $options = self::getEnchantGroupOptions();
        $index = is_numeric($selection) ? (int) $selection : 0;
        $group = $options[$index] ?? null;
        if ($group === null || Groups::getGroupId($group) === null) {
            $player->sendMessage(C::colorize(MessageTemplates::render("giveitem.error.invalid_group")));
            return null;
        }

        return $group;
    }

    private static function readPositiveInt(Player $player, mixed $value, int $default, string $messageKey): int
    {
        return self::readBoundedInt($player, $value, $default, 1, PHP_INT_MAX, $messageKey);
    }

    private static function readBoundedInt(Player $player, mixed $value, int $default, int $min, int $max, string $messageKey): int
    {
        $raw = trim((string) ($value ?? ""));
        if ($raw === "") {
            return $default;
        }
        if (!is_numeric($raw)) {
            $player->sendMessage(C::colorize(MessageTemplates::render($messageKey)));
            throw new \InvalidArgumentException($messageKey);
        }

        $parsed = (int) round((float) $raw);
        if ($parsed < $min || $parsed > $max) {
            $player->sendMessage(C::colorize(MessageTemplates::render($messageKey)));
            throw new \InvalidArgumentException($messageKey);
        }

        return $parsed;
    }

    private static function normalizeEnchantIdentifier(string $identifier): string
    {
        return strtolower(str_replace(["_", "-", " "], "", trim($identifier)));
    }

    /**
     * @return list<string>
     */
    private static function getRecentItems(Player $player): array
    {
        return self::$recentItems[$player->getUniqueId()->toString()] ?? [];
    }

    private static function rememberRecentItem(Player $player, string $itemId): void
    {
        $key = $player->getUniqueId()->toString();
        $recent = self::$recentItems[$key] ?? [];
        $recent = array_values(array_filter($recent, static fn(string $existing): bool => $existing !== $itemId));
        array_unshift($recent, $itemId);
        self::$recentItems[$key] = array_slice($recent, 0, 8);
    }

    private static function buildSearchHaystack(string $itemId): string
    {
        $parts = [
            strtolower($itemId),
            strtolower(self::getMenuItemLabel($itemId)),
            strtolower(self::getMenuItemSubtitle($itemId)),
        ];

        if (str_starts_with($itemId, "lootbox:")) {
            $lookup = substr($itemId, strlen("lootbox:"));
            $box = LootboxRepository::getBoxByLookup($lookup);
            if ($box !== null) {
                $parts[] = strtolower((string) ($box["name"] ?? ""));
                $parts[] = strtolower(implode(" ", array_map("strval", (array) ($box["aliases"] ?? []))));
            }
        }

        if (str_starts_with($itemId, "ethereal_crate:")) {
            $lookup = substr($itemId, strlen("ethereal_crate:"));
            $crate = EtherealCrateRepository::getCrateByLookup($lookup);
            if ($crate !== null) {
                $parts[] = strtolower((string) ($crate["name"] ?? ""));
                $parts[] = strtolower(implode(" ", array_map("strval", (array) ($crate["aliases"] ?? []))));
            }
        }

        return implode(" ", $parts);
    }
}
