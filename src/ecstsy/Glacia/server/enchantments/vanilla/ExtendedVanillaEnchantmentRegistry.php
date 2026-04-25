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

        IncompatibleEnchantmentRegistry::getInstance()->register("glacia_boots_movement", [
            self::mustGet("depth_strider"),
            VanillaEnchantments::FROST_WALKER()
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
