<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\items;

use ecstsy\Glacia\server\enchantments\Groups;
use ecstsy\Glacia\server\etherealcrates\EtherealCrateRepository;
use ecstsy\Glacia\server\items\GlaciaItemFactory;
use ecstsy\Glacia\server\items\GlaciaItems;
use ecstsy\Glacia\utils\lang\MessageTemplates;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use Vecnavium\FormsUI\CustomForm;
use Vecnavium\FormsUI\SimpleForm;

final class GiveItemMenuScreen
{
    private const DYNAMIC_ETHEREAL_CATEGORY = "ethereal_crates";

    public static function displayCategoryMenu(Player $player): void
    {
        $categories = self::getCategoryDisplays();
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
        $form->setContent(C::colorize(MessageTemplates::render("giveitem.menu.category.content")));
        foreach ($categories as $key => $display) {
            $form->addButton(C::colorize(MessageTemplates::render("giveitem.menu.category.button", [
                "display" => $display,
                "count" => count(self::getCategoryItems($key)),
            ])));
        }
        $player->sendForm($form);
    }

    public static function displayItemMenu(Player $player, string $category): void
    {
        $items = self::getCategoryItems($category);

        if ($items === []) {
            $player->sendMessage(C::colorize(MessageTemplates::render("giveitem.error.no_items")));
            return;
        }

        $display = self::getCategoryDisplay($category);
        $form = new SimpleForm(function (Player $player, $data) use ($items, $category): void {
            if ($data === null) {
                return;
            }

            if ($data === "back") {
                self::displayCategoryMenu($player);
                return;
            }

            if (!is_int($data)) {
                return;
            }

            $itemId = $items[$data] ?? null;
            if ($itemId === null) {
                return;
            }

            if (self::requiresParameters($itemId)) {
                self::displayParameterForm($player, $category, $itemId);
                return;
            }

            self::giveSelectedItem($player, $category, $itemId);
        });

        $form->setTitle(C::colorize(MessageTemplates::render("giveitem.menu.item.title", ["display" => $display])));
        $form->setContent(C::colorize(MessageTemplates::render("giveitem.menu.item.content")));
        foreach ($items as $id) {
            $form->addButton(C::colorize(MessageTemplates::render("giveitem.menu.item.button", [
                "label" => self::getMenuItemLabel($id),
                "id" => self::getMenuItemSubtitle($id),
            ])));
        }
        $form->addButton(C::colorize(MessageTemplates::render("giveitem.menu.item.back")), -1, "", "back");
        $player->sendForm($form);
    }

    /**
     * @return array<string, string>
     */
    private static function getCategoryDisplays(): array
    {
        $categories = GlaciaItems::getCategoryDisplays();
        $etherealCrates = EtherealCrateRepository::getCrateIds();
        if ($etherealCrates !== []) {
            $categories[self::DYNAMIC_ETHEREAL_CATEGORY] = MessageTemplates::render("giveitem.category.ethereal_crates");
        }

        return $categories;
    }

    /**
     * @return string[]
     */
    private static function getCategoryItems(string $category): array
    {
        if ($category === self::DYNAMIC_ETHEREAL_CATEGORY) {
            return array_map(
                static fn(string $id): string => "ethereal_crate:" . $id,
                EtherealCrateRepository::getCrateIds()
            );
        }

        return array_values(array_filter(
            GlaciaItems::getCategoryItems($category),
            static fn(string $id): bool => isset(GlaciaItemFactory::$definitions[$id])
        ));
    }

    private static function getCategoryDisplay(string $category): string
    {
        return self::getCategoryDisplays()[$category] ?? (GlaciaItems::getCategoryDisplay($category) ?? ucfirst($category));
    }

    private static function getMenuItemLabel(string $itemId): string
    {
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

    private static function getMenuItemSubtitle(string $itemId): string
    {
        if (str_starts_with($itemId, "ethereal_crate:")) {
            return MessageTemplates::render("giveitem.menu.item.dynamic_id", [
                "id" => substr($itemId, strlen("ethereal_crate:")),
            ]);
        }

        return $itemId;
    }

    private static function requiresParameters(string $itemId): bool
    {
        return in_array($itemId, [
            "heroic_upgrade",
            "charm_shard",
            "charm_dust",
            "blackscroll",
            "orb",
            "magicdust",
            "primal_dust",
            "random_magicdust",
            "random_book",
            "bank_note",
            "xpnote",
            "admin_coin_note",
            "faction_xp_booster",
            "item_flip_credit_voucher",
            "soul_compass",
            "pet_duration_rune",
        ], true);
    }

    private static function giveSelectedItem(Player $player, string $category, string $itemId, array $params = []): void
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
        self::displayItemMenu($player, $category);
    }

    private static function displayParameterForm(Player $player, string $category, string $itemId): void
    {
        $definition = self::getParameterFormDefinition($itemId);
        if ($definition === null) {
            $player->sendMessage(C::colorize(MessageTemplates::render("giveitem.error.manual_item", ["id" => $itemId])));
            return;
        }

        $form = new CustomForm(function (Player $player, ?array $data) use ($category, $itemId, $definition): void {
            if ($data === null) {
                self::displayItemMenu($player, $category);
                return;
            }

            $values = [];
            foreach ($definition["fields"] as $index => $field) {
                $values[$field["key"]] = $data[$index + 1] ?? null;
            }

            $item = self::buildMenuItem($itemId, $player, $values);
            if ($item === null) {
                self::displayParameterForm($player, $category, $itemId);
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
            self::displayItemMenu($player, $category);
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
            "blackscroll" => [
                "content" => MessageTemplates::render("giveitem.form.content.blackscroll"),
                "fields" => [[
                    "key" => "success",
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
            "bank_note", "xpnote", "admin_coin_note", "item_flip_credit_voucher" => [
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

    private static function buildMenuItem(string $itemId, Player $player, array $params = []): ?Item
    {
        try {
        if (str_starts_with($itemId, "ethereal_crate:")) {
            $lookup = substr($itemId, strlen("ethereal_crate:"));
            try {
                return GlaciaItemFactory::etherealCrate($lookup, $player);
            } catch (\Throwable) {
                $player->sendMessage(C::colorize(MessageTemplates::render("giveitem.error.invalid_crate", ["id" => $lookup])));
                return null;
            }
        }

        return match ($itemId) {
            "heroic_upgrade" => GlaciaItemFactory::heroicUpgrade(self::readBoundedInt($player, $params["success"] ?? null, 65, 1, 100, "giveitem.error.invalid_success")),
            "charm_shard" => GlaciaItemFactory::charmShard(
                self::readBoundedInt($player, $params["success"] ?? null, 70, 1, 100, "giveitem.error.invalid_success"),
                self::readBoundedInt($player, $params["bonus_success"] ?? null, 0, 0, 100, "giveitem.error.invalid_bonus")
            ),
            "charm_dust" => GlaciaItemFactory::charmDust(self::readBoundedInt($player, $params["bonus"] ?? null, 5, 1, 1000000, "giveitem.error.invalid_bonus")),
            "blackscroll" => GlaciaItemFactory::blackscroll(self::readBoundedInt($player, $params["success"] ?? null, 100, 1, 100, "giveitem.error.invalid_success")),
            "orb" => self::buildOrbItem($player, $params),
            "magicdust" => self::buildMagicDustItem($player, $params, false),
            "primal_dust" => self::buildMagicDustItem($player, $params, true),
            "random_magicdust" => self::buildRandomDustItem($player, $params),
            "random_book" => self::buildRandomBookItem($player, $params),
            "bank_note" => GlaciaItemFactory::bankNoteSigned($player->getName(), self::readPositiveInt($player, $params["amount"] ?? null, 1000, "giveitem.error.invalid_amount")),
            "xpnote" => GlaciaItemFactory::xpNoteSigned($player->getName(), self::readPositiveInt($player, $params["amount"] ?? null, 1000, "giveitem.error.invalid_amount")),
            "admin_coin_note" => GlaciaItemFactory::adminCoinNoteSigned($player->getName(), self::readPositiveInt($player, $params["amount"] ?? null, 1000, "giveitem.error.invalid_amount")),
            "item_flip_credit_voucher" => GlaciaItemFactory::itemFlipCreditVoucher(self::readPositiveInt($player, $params["amount"] ?? null, 10, "giveitem.error.invalid_amount")),
            "soul_compass" => GlaciaItemFactory::soulCompass(self::readPositiveInt($player, $params["uses"] ?? null, 8, "giveitem.error.invalid_uses")),
            "faction_xp_booster" => self::buildFactionBoosterItem($player, $params),
            "pet_duration_rune" => self::buildPetDurationRune($player, $params),
            default => self::buildDefaultItem($itemId),
        };
        } catch (\InvalidArgumentException) {
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
            return GlaciaItemFactory::create($itemId);
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

            $pets[] = [
                "id" => $id,
                "label" => ucfirst(str_replace("_", " ", $id)),
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
}
