<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\vanilla;

use pocketmine\data\bedrock\EnchantmentIdMap;
use pocketmine\data\bedrock\EnchantmentIds;
use pocketmine\entity\Attribute;
use pocketmine\item\Item;
use pocketmine\item\enchantment\AvailableEnchantmentRegistry;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\IncompatibleEnchantmentRegistry;
use pocketmine\item\enchantment\ItemEnchantmentTags;
use pocketmine\item\enchantment\Rarity;
use pocketmine\item\enchantment\StringToEnchantmentParser;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\player\Player;

final class ExtendedVanillaEnchantmentRegistry
{
    /** @var array<string, array{save_id:int, enchantment:Enchantment, aliases:list<string>, primary:list<string>, secondary:list<string>, sync:?callable(Player, Enchantment): void}> */
    private static array $definitions = [];

    /** @var array<string, Enchantment> */
    private static array $aliasMap = [];

    private static bool $bootstrapped = false;

    public static function registerDefaults(): void
    {
        if (self::$bootstrapped) {
            return;
        }

        self::$bootstrapped = true;

        self::register(
            "depth_strider",
            EnchantmentIds::DEPTH_STRIDER,
            new Enchantment(
                KnownTranslationFactory::enchantment_waterWalker(),
                Rarity::RARE,
                0,
                0,
                3,
                fn(int $level): int => 10 * $level,
                15
            ),
            ["depth_strider", "depthstrider"],
            [ItemEnchantmentTags::BOOTS],
            [],
            static function(Player $player, Enchantment $enchantment): void {
                $attribute = $player->getAttributeMap()->get(Attribute::UNDERWATER_MOVEMENT);
                if ($attribute === null) {
                    return;
                }

                $base = $attribute->getDefaultValue();
                $movement = $player->getAttributeMap()->get(Attribute::MOVEMENT_SPEED)?->getValue() ?? 0.1;
                $level = self::getEquippedLevel($player, $enchantment);
                $target = $level > 0
                    ? $base + (($movement - $base) * min(3, $level) / 3)
                    : $base;

                $attribute->setValue($target, true, true);
            }
        );

        self::register(
            "smite",
            EnchantmentIds::SMITE,
            new Enchantment(
                KnownTranslationFactory::enchantment_damage_undead(),
                Rarity::UNCOMMON,
                0,
                0,
                5,
                fn(int $level): int => 11 * ($level - 1) + 5,
                20
            ),
            ["smite"],
            [ItemEnchantmentTags::SWORD],
            []
        );

        self::register(
            "bane_of_arthropods",
            EnchantmentIds::BANE_OF_ARTHROPODS,
            new Enchantment(
                KnownTranslationFactory::enchantment_damage_arthropods(),
                Rarity::UNCOMMON,
                0,
                0,
                5,
                fn(int $level): int => 11 * ($level - 1) + 5,
                20
            ),
            ["bane_of_arthropods", "baneofarthropods"],
            [ItemEnchantmentTags::SWORD],
            []
        );

        self::register(
            "looting",
            EnchantmentIds::LOOTING,
            new Enchantment(
                KnownTranslationFactory::enchantment_lootBonus(),
                Rarity::RARE,
                0,
                0,
                3,
                fn(int $level): int => 9 * ($level - 1) + 15,
                50
            ),
            ["looting"],
            [ItemEnchantmentTags::SWORD],
            []
        );

        self::register(
            "luck_of_the_sea",
            EnchantmentIds::LUCK_OF_THE_SEA,
            new Enchantment(
                KnownTranslationFactory::enchantment_lootBonusFishing(),
                Rarity::RARE,
                0,
                0,
                3,
                fn(int $level): int => 9 * ($level - 1) + 15,
                50
            ),
            ["luck_of_the_sea", "luckofthesea"],
            [ItemEnchantmentTags::FISHING_ROD],
            []
        );

        self::register(
            "lure",
            EnchantmentIds::LURE,
            new Enchantment(
                KnownTranslationFactory::enchantment_fishingSpeed(),
                Rarity::RARE,
                0,
                0,
                3,
                fn(int $level): int => 9 * ($level - 1) + 15,
                50
            ),
            ["lure"],
            [ItemEnchantmentTags::FISHING_ROD],
            []
        );

        self::register(
            "binding",
            EnchantmentIds::BINDING,
            new Enchantment(
                KnownTranslationFactory::enchantment_curse_binding(),
                Rarity::MYTHIC,
                0,
                0,
                1,
                fn(int $level): int => 25,
                25
            ),
            ["binding", "curse_of_binding", "curseofbinding"],
            [ItemEnchantmentTags::ARMOR, ItemEnchantmentTags::ELYTRA],
            []
        );

        self::register(
            "impaling",
            EnchantmentIds::IMPALING,
            new Enchantment(
                KnownTranslationFactory::enchantment_tridentImpaling(),
                Rarity::RARE,
                0,
                0,
                5,
                fn(int $level): int => 8 * ($level - 1) + 1,
                20
            ),
            ["impaling"],
            [ItemEnchantmentTags::TRIDENT],
            []
        );

        self::register(
            "riptide",
            EnchantmentIds::RIPTIDE,
            new Enchantment(
                KnownTranslationFactory::enchantment_tridentRiptide(),
                Rarity::RARE,
                0,
                0,
                3,
                fn(int $level): int => 10 * $level,
                15
            ),
            ["riptide"],
            [ItemEnchantmentTags::TRIDENT],
            []
        );

        self::register(
            "loyalty",
            EnchantmentIds::LOYALTY,
            new Enchantment(
                KnownTranslationFactory::enchantment_tridentLoyalty(),
                Rarity::UNCOMMON,
                0,
                0,
                3,
                fn(int $level): int => 12 * ($level - 1) + 5,
                50
            ),
            ["loyalty"],
            [ItemEnchantmentTags::TRIDENT],
            []
        );

        self::register(
            "channeling",
            EnchantmentIds::CHANNELING,
            new Enchantment(
                KnownTranslationFactory::enchantment_tridentChanneling(),
                Rarity::RARE,
                0,
                0,
                1,
                fn(int $level): int => 25,
                25
            ),
            ["channeling"],
            [ItemEnchantmentTags::TRIDENT],
            []
        );

        self::register(
            "multishot",
            EnchantmentIds::MULTISHOT,
            new Enchantment(
                KnownTranslationFactory::enchantment_crossbowMultishot(),
                Rarity::RARE,
                0,
                0,
                1,
                fn(int $level): int => 20,
                30
            ),
            ["multishot"],
            [ItemEnchantmentTags::CROSSBOW],
            []
        );

        self::register(
            "piercing",
            EnchantmentIds::PIERCING,
            new Enchantment(
                KnownTranslationFactory::enchantment_crossbowPiercing(),
                Rarity::COMMON,
                0,
                0,
                4,
                fn(int $level): int => 10 * ($level - 1) + 1,
                50
            ),
            ["piercing"],
            [ItemEnchantmentTags::CROSSBOW],
            []
        );

        self::register(
            "quick_charge",
            EnchantmentIds::QUICK_CHARGE,
            new Enchantment(
                KnownTranslationFactory::enchantment_crossbowQuickCharge(),
                Rarity::UNCOMMON,
                0,
                0,
                3,
                fn(int $level): int => 12 * ($level - 1) + 10,
                50
            ),
            ["quick_charge", "quickcharge"],
            [ItemEnchantmentTags::CROSSBOW],
            []
        );

        self::register(
            "soul_speed",
            EnchantmentIds::SOUL_SPEED,
            new Enchantment(
                KnownTranslationFactory::enchantment_soul_speed(),
                Rarity::MYTHIC,
                0,
                0,
                3,
                fn(int $level): int => 10 * $level,
                5
            ),
            ["soul_speed", "soulspeed"],
            [ItemEnchantmentTags::BOOTS],
            []
        );

        IncompatibleEnchantmentRegistry::getInstance()->register("glacia_boots_movement", [
            self::mustGet("depth_strider"),
            VanillaEnchantments::FROST_WALKER()
        ]);

        IncompatibleEnchantmentRegistry::getInstance()->register("glacia_sword_damage_vanilla", [
            VanillaEnchantments::SHARPNESS(),
            self::mustGet("smite"),
            self::mustGet("bane_of_arthropods"),
        ]);

        IncompatibleEnchantmentRegistry::getInstance()->register("glacia_crossbow_modes", [
            self::mustGet("multishot"),
            self::mustGet("piercing"),
        ]);

        IncompatibleEnchantmentRegistry::getInstance()->register("glacia_trident_weather", [
            self::mustGet("riptide"),
            self::mustGet("channeling"),
        ]);
    }

    /**
     * @param list<string> $aliases
     * @param list<string> $primaryTags
     * @param list<string> $secondaryTags
     * @param null|callable(Player, Enchantment): void $sync
     */
    public static function register(
        string $id,
        int $saveId,
        Enchantment $enchantment,
        array $aliases,
        array $primaryTags = [],
        array $secondaryTags = [],
        ?callable $sync = null
    ): void {
        $normalizedId = self::normalize($id);
        self::$definitions[$normalizedId] = [
            "save_id" => $saveId,
            "enchantment" => $enchantment,
            "aliases" => $aliases,
            "primary" => $primaryTags,
            "secondary" => $secondaryTags,
            "sync" => $sync,
        ];

        EnchantmentIdMap::getInstance()->register($saveId, $enchantment);

        $parser = StringToEnchantmentParser::getInstance();
        foreach ($aliases as $alias) {
            $normalizedAlias = self::normalize($alias);
            self::$aliasMap[$normalizedAlias] = $enchantment;
            if ($parser->parse($alias) === null) {
                $parser->register($alias, fn(): Enchantment => $enchantment);
            }
        }

        $availability = AvailableEnchantmentRegistry::getInstance();
        if (!$availability->isRegistered($enchantment)) {
            $availability->register($enchantment, $primaryTags, $secondaryTags);
        } else {
            $availability->setPrimaryItemTags($enchantment, $primaryTags);
            $availability->setSecondaryItemTags($enchantment, $secondaryTags);
        }
    }

    public static function resolve(string $name): ?Enchantment
    {
        return self::$aliasMap[self::normalize($name)] ?? null;
    }

    public static function mustGet(string $id): Enchantment
    {
        $definition = self::$definitions[self::normalize($id)] ?? null;
        if ($definition === null) {
            throw new \InvalidArgumentException("Unknown extended vanilla enchantment: {$id}");
        }

        return $definition["enchantment"];
    }

    public static function syncPlayer(Player $player): void
    {
        foreach (self::$definitions as $definition) {
            if ($definition["sync"] !== null) {
                ($definition["sync"])($player, $definition["enchantment"]);
            }
        }
    }

    private static function getEquippedLevel(Player $player, Enchantment $enchantment): int
    {
        foreach ($player->getArmorInventory()->getContents() as $item) {
            if (!$item instanceof Item || $item->isNull()) {
                continue;
            }

            foreach ($item->getEnchantments() as $instance) {
                if ($instance->getType() === $enchantment) {
                    return $instance->getLevel();
                }
            }
        }

        return 0;
    }

    private static function normalize(string $id): string
    {
        return strtolower(str_replace(["_", "-", " "], "", trim($id)));
    }
}
