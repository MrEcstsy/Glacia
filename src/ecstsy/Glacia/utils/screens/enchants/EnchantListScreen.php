<?php

declare(strict_types=1);

namespace ecstsy\Glacia\utils\screens\enchants;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantments;
use ecstsy\Glacia\server\enchantments\Groups;
use pocketmine\item\enchantment\ItemEnchantmentTags;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use Vecnavium\FormsUI\SimpleForm;
use function array_diff;
use function array_fill_keys;
use function array_keys;
use function array_slice;
use function ceil;
use function count;
use function implode;
use function max;
use function min;
use function preg_replace;
use function preg_split;
use function sort;
use function str_replace;
use function str_starts_with;
use function strcmp;
use function substr;
use function trim;
use function ucwords;
use function usort;

final class EnchantListScreen
{
    private const ENCHANTS_PER_PAGE = 36;

    /**
     * @var list<string>
     */
    private const CATEGORY_ORDER = [
        "Armor",
        "Helmets",
        "Chestplates",
        "Leggings",
        "Boots",
        "Weapons",
        "Swords",
        "Axes",
        "Bows",
        "Pickaxes",
        "Shovels",
        "Hoes",
        "Crossbows",
        "Tridents",
        "Shields",
        "Fishing rods",
        "Shears",
        "Elytra",
        "Masks",
        "Brushes",
        "Flint and steel",
        "Carrot on a stick",
        "Compasses",
        "All items",
    ];

    public static function displayCategoryMenu(Player $player): void
    {
        $categories = self::getGroupedEnchantments();
        if ($categories === []) {
            $player->sendMessage(C::colorize("&r&cNo enchantments are currently available."));
            return;
        }

        $form = new SimpleForm(function (Player $player, ?string $data) use ($categories): void {
            if ($data === null || !str_starts_with($data, "category:")) {
                return;
            }

            $category = substr($data, strlen("category:"));
            if (!isset($categories[$category])) {
                return;
            }

            self::displayEnchantMenu($player, $category, 1);
        });

        $form->setTitle(C::colorize("&r&6Custom Enchantments"));
        $form->setContent(C::colorize("&r&7Browse enchants by category. If you need help understanding the system itself, use &f/guide enchanting&r&7."));
        foreach ($categories as $category => $enchants) {
            $form->addButton(
                C::colorize("&r&f{$category}\n&r&8" . count($enchants) . " Enchantments"),
                -1,
                "",
                "category:" . $category
            );
        }
        $player->sendForm($form);
    }

    public static function displayEnchantMenu(Player $player, string $category, int $page = 1): void
    {
        $categories = self::getGroupedEnchantments();
        $enchants = $categories[$category] ?? [];
        if ($enchants === []) {
            self::displayCategoryMenu($player);
            return;
        }

        $maxPage = max(1, (int) ceil(count($enchants) / self::ENCHANTS_PER_PAGE));
        $page = max(1, min($maxPage, $page));
        $pageEnchants = array_slice($enchants, ($page - 1) * self::ENCHANTS_PER_PAGE, self::ENCHANTS_PER_PAGE);

        $form = new SimpleForm(function (Player $player, ?string $data) use ($pageEnchants, $category, $page): void {
            if ($data === null) {
                return;
            }

            if ($data === "back") {
                self::displayCategoryMenu($player);
                return;
            }

            if ($data === "prev") {
                self::displayEnchantMenu($player, $category, $page - 1);
                return;
            }

            if ($data === "next") {
                self::displayEnchantMenu($player, $category, $page + 1);
                return;
            }

            if (!str_starts_with($data, "enchant:")) {
                return;
            }

            $enchantName = substr($data, strlen("enchant:"));
            $enchant = null;
            foreach ($pageEnchants as $pageEnchant) {
                if ($pageEnchant->getName() === $enchantName) {
                    $enchant = $pageEnchant;
                    break;
                }
            }
            if (!$enchant instanceof CustomEnchantment) {
                return;
            }

            self::displayEnchantDetail($player, $category, $enchant, $page);
        });

        $form->setTitle(C::colorize("&r&6{$category} Enchants &8({$page}/{$maxPage})"));
        $form->setContent(C::colorize("&r&7Click an enchantment to view what it does. For dust, scrolls, souls, and upgrade flow, use &f/guide enchanting&r&7."));
        foreach ($pageEnchants as $enchant) {
            $groupColor = Groups::translateGroupToColor($enchant->getRarity());
            $displayName = CustomEnchantments::getEnchantmentDisplayName($enchant->getName(), $groupColor);
            $descriptionPreview = self::formatDescriptionPreview($enchant);
            $subtitle = "&r&8Max " . $enchant->getMaxLevel() . " &7| &8" . self::formatApplicableItems($enchant->getApplicableItems());
            if ($descriptionPreview !== "") {
                $subtitle .= "\n&r&7" . $descriptionPreview;
            }
            $form->addButton(
                C::colorize($displayName . "\n" . $subtitle),
                -1,
                "",
                "enchant:" . $enchant->getName()
            );
        }
        if ($page > 1) {
            $form->addButton(C::colorize("&r&ePrevious Page"), -1, "", "prev");
        }
        if ($page < $maxPage) {
            $form->addButton(C::colorize("&r&eNext Page"), -1, "", "next");
        }
        $form->addButton(C::colorize("&r&cBack"), -1, "", "back");
        $player->sendForm($form);
    }

    public static function displayEnchantDetail(Player $player, string $category, CustomEnchantment $enchantment, int $page = 1): void
    {
        $groupId = $enchantment->getRarity();
        $groupName = Groups::getGroupName($groupId) ?? "Unknown";
        $groupColor = Groups::translateGroupToColor($groupId);
        $displayName = CustomEnchantments::getEnchantmentDisplayName($enchantment->getName(), $groupColor);
        $description = trim($enchantment->getDescription());
        $lines = preg_split('/\r\n|\r|\n/', $description) ?: [$description];

        $content = [
            "&r&7Rarity: " . $groupColor . $groupName,
            "&r&7Internal ID: &f" . $enchantment->getName(),
            "&r&7Max Level: &f" . $enchantment->getMaxLevel(),
            "&r&7Applies To: &f" . self::formatApplicableItems($enchantment->getApplicableItems()),
            "",
            "&r&7Description:",
        ];
        foreach ($lines as $line) {
            $content[] = "&r&f" . $line;
        }
        $content[] = "";
        $content[] = "&r&8Need system help? Use &f/guide enchanting";

        $form = new SimpleForm(function (Player $player, mixed $data) use ($category, $page): void {
            if ($data === "back") {
                self::displayEnchantMenu($player, $category, $page);
                return;
            }

            if ($data === "categories") {
                self::displayCategoryMenu($player);
            }
        });

        $form->setTitle(C::colorize($displayName));
        $form->setContent(C::colorize(implode("\n", $content)));
        $form->addButton(C::colorize("&r&aBack to {$category}"), -1, "", "back");
        $form->addButton(C::colorize("&r&6Categories"), -1, "", "categories");
        $player->sendForm($form);
    }

    /**
     * @return array<string, list<CustomEnchantment>>
     */
    private static function getGroupedEnchantments(): array
    {
        /** @var array<string, list<CustomEnchantment>> $raw */
        $raw = [];

        foreach (CustomEnchantments::getAll() as $enchantment) {
            foreach (self::resolveCategories($enchantment) as $category) {
                $raw[$category][] = $enchantment;
            }
        }

        $orderedNames = self::orderedCategoryNames(array_keys($raw));
        $grouped = [];
        foreach ($orderedNames as $category) {
            $enchants = $raw[$category] ?? [];
            if ($enchants === []) {
                continue;
            }

            usort($enchants, static function (CustomEnchantment $left, CustomEnchantment $right): int {
                $leftName = C::clean(CustomEnchantments::getEnchantmentDisplayName($left->getName(), ""));
                $rightName = C::clean(CustomEnchantments::getEnchantmentDisplayName($right->getName(), ""));
                return strcmp($leftName, $rightName);
            });
            $grouped[$category] = $enchants;
        }

        return $grouped;
    }

    private static function formatDescriptionPreview(CustomEnchantment $enchantment): string
    {
        $description = trim($enchantment->getDescription());
        if ($description === "") {
            return "";
        }

        $lines = preg_split('/\r\n|\r|\n/', $description) ?: [$description];
        $line = trim((string) ($lines[0] ?? ""));
        $line = preg_replace('/\s+/', ' ', $line) ?? $line;
        if ($line === "") {
            return "";
        }

        return mb_strlen($line) > 72 ? mb_substr($line, 0, 69) . "..." : $line;
    }

    /**
     * @return list<string>
     */
    private static function orderedCategoryNames(array $present): array
    {
        $presentSet = array_fill_keys($present, true);
        $ordered = [];
        foreach (self::CATEGORY_ORDER as $name) {
            if (isset($presentSet[$name])) {
                $ordered[] = $name;
            }
        }
        $extra = array_diff($present, $ordered);
        sort($extra);
        foreach ($extra as $name) {
            $ordered[] = $name;
        }

        return $ordered;
    }

    /**
     * Shared multi-slot enchants get a broad category, while slot-specific
     * enchants stay in their own item bucket.
     *
     * @return list<string>
     */
    private static function resolveCategories(CustomEnchantment $enchantment): array
    {
        $tags = $enchantment->getApplicableItems();
        if ($tags === []) {
            return ["All items"];
        }

        $tagSet = array_fill_keys($tags, true);
        $hasAllArmorSlots =
            isset($tagSet[ItemEnchantmentTags::HELMET]) &&
            isset($tagSet[ItemEnchantmentTags::CHESTPLATE]) &&
            isset($tagSet[ItemEnchantmentTags::LEGGINGS]) &&
            isset($tagSet[ItemEnchantmentTags::BOOTS]);
        $hasSwordAndAxe =
            isset($tagSet[ItemEnchantmentTags::SWORD]) &&
            isset($tagSet[ItemEnchantmentTags::AXE]);

        if (count($tags) === 1 && isset($tagSet[ItemEnchantmentTags::ARMOR])) {
            return ["Armor"];
        }
        if ($hasAllArmorSlots) {
            return ["Armor"];
        }
        if (count($tags) === 1 && isset($tagSet[ItemEnchantmentTags::WEAPONS])) {
            return ["Weapons"];
        }
        if ($hasSwordAndAxe) {
            return ["Weapons"];
        }

        /** @var array<string, bool> $bucket */
        $bucket = [];
        foreach ($tags as $tag) {
            foreach (self::expandTagToCategories((string) $tag) as $category) {
                $bucket[$category] = true;
            }
        }

        return array_keys($bucket);
    }

    /**
     * Maps PocketMine enchantment tags (and umbrella tags like armor, weapons) to menu buckets.
     *
     * @return list<string>
     */
    private static function expandTagToCategories(string $tag): array
    {
        return match ($tag) {
            ItemEnchantmentTags::ALL => ["All items"],
            ItemEnchantmentTags::ARMOR => ["Armor"],
            ItemEnchantmentTags::BLOCK_TOOLS => ["Pickaxes", "Shovels", "Hoes"],
            ItemEnchantmentTags::WEAPONS => ["Weapons"],
            ItemEnchantmentTags::HELMET => ["Helmets"],
            ItemEnchantmentTags::CHESTPLATE => ["Chestplates"],
            ItemEnchantmentTags::LEGGINGS => ["Leggings"],
            ItemEnchantmentTags::BOOTS => ["Boots"],
            ItemEnchantmentTags::SWORD => ["Swords"],
            ItemEnchantmentTags::AXE => ["Axes"],
            ItemEnchantmentTags::PICKAXE => ["Pickaxes"],
            ItemEnchantmentTags::SHOVEL => ["Shovels"],
            ItemEnchantmentTags::HOE => ["Hoes"],
            ItemEnchantmentTags::BOW => ["Bows"],
            ItemEnchantmentTags::CROSSBOW => ["Crossbows"],
            ItemEnchantmentTags::TRIDENT => ["Tridents"],
            ItemEnchantmentTags::SHIELD => ["Shields"],
            ItemEnchantmentTags::FISHING_ROD => ["Fishing rods"],
            ItemEnchantmentTags::SHEARS => ["Shears"],
            ItemEnchantmentTags::FLINT_AND_STEEL => ["Flint and steel"],
            ItemEnchantmentTags::ELYTRA => ["Elytra"],
            ItemEnchantmentTags::MASK => ["Masks"],
            ItemEnchantmentTags::BRUSH => ["Brushes"],
            ItemEnchantmentTags::CARROT_ON_STICK => ["Carrot on a stick"],
            ItemEnchantmentTags::COMPASS => ["Compasses"],
            default => [ucwords(str_replace("_", " ", $tag))],
        };
    }

    /**
     * @param string[] $tags
     */
    private static function formatApplicableItems(array $tags): string
    {
        if ($tags === []) {
            return "Any";
        }

        $pretty = [];
        foreach ($tags as $tag) {
            $pretty[] = ucwords(str_replace('_', ' ', $tag));
        }

        return implode(", ", $pretty);
    }
}
