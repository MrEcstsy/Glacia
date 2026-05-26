<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\items\rings;

use ecstsy\Glacia\server\enchantments\CustomEnchantments;
use pocketmine\item\Armor;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\Sword;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\item\enchantment\ItemEnchantmentTags;

final class RingManager
{
    private const GLACIA_TAG = "Glacia";
    private const MAX_RINGS = 2;

    /** @var array<string, array{display:string, blocks:string, success:int}> */
    private static array $definitions = [
        "disarmor" => [
            "display" => "Disarmor",
            "blocks" => "disarmor",
            "success" => 100,
        ],
        "lifesteal" => [
            "display" => "Lifesteal",
            "blocks" => "lifesteal",
            "success" => 100,
        ],
        "guardian" => [
            "display" => "Guardian",
            "blocks" => "watchmen",
            "success" => 100,
        ],
        "dodge" => [
            "display" => "Dodge",
            "blocks" => "dodge",
            "success" => 100,
        ],
        "ascended" => [
            "display" => "Ascended",
            "blocks" => "ascended",
            "success" => 100,
        ],
        "premonition" => [
            "display" => "Premonition",
            "blocks" => "premonition",
            "success" => 100,
        ],
        "nullglimmer" => [
            "display" => "Nullglimmer",
            "blocks" => "nullglimmer",
            "success" => 100,
        ],
    ];

    /**
     * @return array<string, array{display:string, blocks:string, success:int}>
     */
    public static function getDefinitions(): array
    {
        return self::$definitions;
    }

    /**
     * @return array{display:string, blocks:string, success:int}|null
     */
    public static function getDefinition(string $ringId): ?array
    {
        return self::$definitions[self::normalizeId($ringId)] ?? null;
    }

    public static function hasDefinition(string $ringId): bool
    {
        return self::getDefinition($ringId) !== null;
    }

    public static function getDisplayName(string $ringId): string
    {
        return self::getDefinition($ringId)["display"] ?? ucfirst(str_replace("_", " ", self::normalizeId($ringId)));
    }

    public static function getBlockedEnchantId(string $ringId): string
    {
        return (string) (self::getDefinition($ringId)["blocks"] ?? "");
    }

    public static function getSuccessChance(string $ringId): int
    {
        return max(1, min(100, (int) (self::getDefinition($ringId)["success"] ?? 100)));
    }

    public static function canApplyTo(Item $item, string $ringId): bool
    {
        $ringId = self::normalizeId($ringId);
        if (!self::hasDefinition($ringId) || self::hasAppliedRing($item, $ringId)) {
            return false;
        }

        if (count(self::getAppliedRingIds($item)) >= self::MAX_RINGS) {
            return false;
        }

        $enchant = CustomEnchantments::getEnchantmentByName(self::getBlockedEnchantId($ringId));
        return $enchant !== null && self::matchesRingTarget($item, $enchant);
    }

    /**
     * @return list<string>
     */
    public static function getAppliedRingIds(Item $item): array
    {
        $glacia = $item->getNamedTag()->getCompoundTag(self::GLACIA_TAG);
        if ($glacia === null) {
            return [];
        }

        $rings = [];
        for ($slot = 1; $slot <= self::MAX_RINGS; $slot++) {
            $ringId = self::normalizeId($glacia->getString("ring_slot_" . $slot, ""));
            if ($ringId !== "") {
                $rings[] = $ringId;
            }
        }

        return array_values(array_unique($rings));
    }

    public static function hasAppliedRing(Item $item, ?string $ringId = null): bool
    {
        $applied = self::getAppliedRingIds($item);
        if ($ringId === null) {
            return $applied !== [];
        }

        return in_array(self::normalizeId($ringId), $applied, true);
    }

    public static function applyRing(Item $item, string $ringId): Item
    {
        $ringId = self::normalizeId($ringId);
        $root = $item->getNamedTag();
        $glacia = $root->getCompoundTag(self::GLACIA_TAG) ?? new CompoundTag();
        $rings = self::getAppliedRingIds($item);
        if (!in_array($ringId, $rings, true)) {
            $rings[] = $ringId;
        }

        $rings = array_slice(array_values(array_unique($rings)), 0, self::MAX_RINGS);
        for ($slot = 1; $slot <= self::MAX_RINGS; $slot++) {
            $ringForSlot = $rings[$slot - 1] ?? "";
            if ($ringForSlot === "") {
                $glacia->removeTag("ring_slot_" . $slot);
            } else {
                $glacia->setString("ring_slot_" . $slot, $ringForSlot);
            }
        }

        $root->setTag(self::GLACIA_TAG, $glacia);
        $item->setNamedTag($root);
        $item->setLore(self::withRingLore($item->getLore(), $rings));
        return $item;
    }

    public static function playerBlocksEnchant(Player $player, string $enchantId): bool
    {
        $enchantId = self::normalizeBlockedEnchantId($enchantId);
        foreach (self::getPlayerRingCarrierItems($player) as $item) {
            foreach (self::getAppliedRingIds($item) as $ringId) {
                if (self::normalizeBlockedEnchantId(self::getBlockedEnchantId($ringId)) === $enchantId) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return list<Item>
     */
    private static function getPlayerRingCarrierItems(Player $player): array
    {
        return [
            ...array_values($player->getInventory()->getContents()),
            ...array_values($player->getArmorInventory()->getContents()),
            $player->getOffHandInventory()->getItem(0),
        ];
    }

    private static function matchesRingTarget(Item $item, object $enchant): bool
    {
        if (method_exists($enchant, 'matches') && $enchant->matches($item)) {
            return true;
        }

        if (!method_exists($enchant, 'getApplicableItems')) {
            return false;
        }

        foreach ((array) $enchant->getApplicableItems() as $tag) {
            if (self::matchesApplicableTag($item, (string) $tag)) {
                return true;
            }
        }

        return false;
    }

    private static function matchesApplicableTag(Item $item, string $tag): bool
    {
        return match ($tag) {
            ItemEnchantmentTags::ALL => true,
            ItemEnchantmentTags::SWORD => $item instanceof Sword,
            ItemEnchantmentTags::HELMET => in_array($item->getTypeId(), [
                ItemTypeIds::NETHERITE_HELMET,
                ItemTypeIds::DIAMOND_HELMET,
                ItemTypeIds::GOLDEN_HELMET,
                ItemTypeIds::IRON_HELMET,
                ItemTypeIds::CHAINMAIL_HELMET,
                ItemTypeIds::LEATHER_CAP,
            ], true),
            ItemEnchantmentTags::CHESTPLATE => in_array($item->getTypeId(), [
                ItemTypeIds::NETHERITE_CHESTPLATE,
                ItemTypeIds::DIAMOND_CHESTPLATE,
                ItemTypeIds::GOLDEN_CHESTPLATE,
                ItemTypeIds::IRON_CHESTPLATE,
                ItemTypeIds::CHAINMAIL_CHESTPLATE,
                ItemTypeIds::LEATHER_TUNIC,
            ], true),
            ItemEnchantmentTags::LEGGINGS => in_array($item->getTypeId(), [
                ItemTypeIds::NETHERITE_LEGGINGS,
                ItemTypeIds::DIAMOND_LEGGINGS,
                ItemTypeIds::GOLDEN_LEGGINGS,
                ItemTypeIds::IRON_LEGGINGS,
                ItemTypeIds::CHAINMAIL_LEGGINGS,
                ItemTypeIds::LEATHER_PANTS,
            ], true),
            ItemEnchantmentTags::BOOTS => in_array($item->getTypeId(), [
                ItemTypeIds::NETHERITE_BOOTS,
                ItemTypeIds::DIAMOND_BOOTS,
                ItemTypeIds::GOLDEN_BOOTS,
                ItemTypeIds::IRON_BOOTS,
                ItemTypeIds::CHAINMAIL_BOOTS,
                ItemTypeIds::LEATHER_BOOTS,
            ], true),
            default => $item instanceof Armor && in_array($tag, [
                ItemEnchantmentTags::HELMET,
                ItemEnchantmentTags::CHESTPLATE,
                ItemEnchantmentTags::LEGGINGS,
                ItemEnchantmentTags::BOOTS,
            ], true),
        };
    }

    /**
     * @param list<string> $lore
     * @param list<string> $ringIds
     * @return list<string>
     */
    private static function withRingLore(array $lore, array $ringIds): array
    {
        $lore = self::withoutRingLore($lore);
        if ($lore !== []) {
            $lore[] = "";
        }

        foreach ($ringIds as $ringId) {
            $lore[] = C::colorize("&r&dRing: &f" . self::getDisplayName($ringId) . " &7(blocked)");
        }

        return $lore;
    }

    /**
     * @param list<string> $lore
     * @return list<string>
     */
    private static function withoutRingLore(array $lore): array
    {
        $filtered = array_values(array_filter(
            $lore,
            static fn(string $line): bool => !str_contains(C::clean($line), "Ring:")
        ));

        while ($filtered !== [] && C::clean((string) end($filtered)) === "") {
            array_pop($filtered);
        }

        return $filtered;
    }

    private static function normalizeId(string $ringId): string
    {
        $normalized = strtolower(trim(str_replace("_ring", "", $ringId)));
        return match ($normalized) {
            "glimmerfade" => "nullglimmer",
            "forewarned" => "premonition",
            default => $normalized,
        };
    }

    private static function normalizeBlockedEnchantId(string $enchantId): string
    {
        $normalized = strtolower(trim($enchantId));
        return match ($normalized) {
            "forewarned" => "premonition",
            "glimmerfade" => "nullglimmer",
            default => $normalized,
        };
    }
}
