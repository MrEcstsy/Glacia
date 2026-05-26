<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\essence;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantments;
use ecstsy\Glacia\server\items\GlaciaItemFactory;
use ecstsy\Glacia\server\items\GlaciaItems;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\item\Armor;
use pocketmine\item\Axe;
use pocketmine\item\Bow;
use pocketmine\item\Hoe;
use pocketmine\item\Item;
use pocketmine\item\Pickaxe;
use pocketmine\item\Shovel;
use pocketmine\item\StringToItemParser;
use pocketmine\item\Sword;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class EnchantEssenceManager
{
    /** @var array<string, array<string, mixed>> */
    private static array $essences = [];

    public static function loadFromConfig() : void
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "essences.yml");
        self::$essences = [];

        foreach ((array) ($config?->get("essences", []) ?? []) as $id => $entry) {
            if (!is_array($entry) || !((bool) ($entry["enabled"] ?? true))) {
                continue;
            }

            $key = strtolower(trim((string) $id));
            if ($key === "") {
                continue;
            }

            self::$essences[$key] = $entry;
        }
    }

    public static function registerItemDefinitions() : void
    {
        $definitionIds = [];
        foreach (self::$essences as $id => $entry) {
            $material = StringToItemParser::getInstance()->parse((string) ($entry["material"] ?? "prismarine_shard"))
                ?? StringToItemParser::getInstance()->parse("prismarine_shard");

            GlaciaItemFactory::$definitions["essence_" . $id] = [
                "material" => $material,
                "name" => (string) ($entry["name"] ?? "&r&l&d" . ucfirst($id) . " Essence"),
                "lore" => is_array($entry["lore"] ?? null) ? $entry["lore"] : [
                    "&r&7Apply this essence to the matching item.",
                ],
                "nbt" => [
                    "glaciaItem" => "enchant_essence",
                    "essence_id" => $id,
                    "success" => (string) max(1, min(100, (int) ($entry["success-rate"] ?? 100))),
                ],
            ];
            $definitionIds[] = "essence_" . $id;
        }

        if ($definitionIds !== []) {
            GlaciaItems::registerCategory("essences", "Essences", $definitionIds);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function getEssence(string $essenceId) : ?array
    {
        return self::$essences[strtolower(trim($essenceId))] ?? null;
    }

    public static function canApply(Item $item, string $essenceId, ?string &$reason = null) : bool
    {
        if (!$item instanceof Armor && !$item instanceof Sword && !$item instanceof Axe && !$item instanceof Pickaxe && !$item instanceof Shovel && !$item instanceof Hoe && !$item instanceof Bow) {
            $reason = "You can only apply essences to gear.";
            return false;
        }

        $essence = self::getEssence($essenceId);
        if ($essence === null) {
            $reason = "That essence is invalid.";
            return false;
        }

        if (self::hasEssence($item, $essenceId)) {
            $reason = "This item already has that essence.";
            return false;
        }

        $enchantId = strtolower(trim((string) ($essence["enchant"] ?? "")));
        if ($enchantId === "" || !CustomEnchantmentManager::hasEnchantment($item, $enchantId)) {
            $reason = "This item does not have the required enchant.";
            return false;
        }

        $level = CustomEnchantmentManager::getLevel($item, $enchantId);
        if ((bool) ($essence["require-max-level"] ?? true)) {
            $enchant = CustomEnchantments::getEnchantmentByName($enchantId);
            if ($enchant !== null && $level < $enchant->getMaxLevel()) {
                $reason = "You need max level " . ucfirst($enchantId) . " to apply this essence.";
                return false;
            }
        }

        return true;
    }

    public static function apply(Item $item, string $essenceId) : Item
    {
        $root = $item->getNamedTag();
        $essenceTag = $root->getCompoundTag("GlaciaEssences") ?? new \pocketmine\nbt\tag\CompoundTag();
        $essenceTag->setByte(strtolower($essenceId), 1);
        $root->setTag("GlaciaEssences", $essenceTag);
        $item->setNamedTag($root);

        self::refreshLore($item);
        return $item;
    }

    public static function hasEssence(Item $item, string $essenceId) : bool
    {
        $tag = $item->getNamedTag()->getCompoundTag("GlaciaEssences");
        return $tag !== null && $tag->getByte(strtolower($essenceId), 0) === 1;
    }

    /**
     * Default apply success percent from essences.yml (1–100).
     */
    public static function getEssenceDefaultSuccessPercent(string $essenceId) : int
    {
        $entry = self::getEssence(strtolower(trim($essenceId)));
        $rate = (int) ($entry["success-rate"] ?? 100);

        return max(1, min(100, $rate));
    }

    /**
     * Success chance when dragging an essence onto gear: per-stack NBT "success" overrides config.
     */
    public static function getApplySuccessPercent(Item $essenceItem, string $essenceId) : int
    {
        $tag = $essenceItem->getNamedTag()->getCompoundTag("Glacia");
        if ($tag !== null) {
            $successTag = $tag->getTag("success");
            if ($successTag !== null) {
                $nbt = match (true) {
                    $successTag instanceof \pocketmine\nbt\tag\IntTag => $successTag->getValue(),
                    $successTag instanceof \pocketmine\nbt\tag\StringTag => (int) $successTag->getValue(),
                    default => -1,
                };
                if ($nbt > 0) {
                    return max(1, min(100, $nbt));
                }
            }
        }

        return self::getEssenceDefaultSuccessPercent($essenceId);
    }

    /**
     * Reads an essence "effects" value from a single weapon or armor piece when the essence is applied and requirements pass.
     */
    public static function resolveGearEssenceFloat(Item $item, string $essenceId, string $effectKey, float $default) : float
    {
        if ($item->isNull()) {
            return $default;
        }

        $essenceId = strtolower(trim($essenceId));
        if (!self::hasEssence($item, $essenceId)) {
            return $default;
        }

        $essence = self::getEssence($essenceId);
        if ($essence === null) {
            return $default;
        }

        $enchantId = strtolower(trim((string) ($essence["enchant"] ?? $essenceId)));
        $level = CustomEnchantmentManager::getLevel($item, $enchantId);
        if ($level <= 0) {
            return $default;
        }

        if ((bool) ($essence["require-max-level"] ?? true)) {
            $enchant = CustomEnchantments::getEnchantmentByName($enchantId);
            if ($enchant !== null && $level < $enchant->getMaxLevel()) {
                return $default;
            }
        }

        return (float) ($essence["effects"][$effectKey] ?? $default);
    }

    public static function resolveArmorValue(Player $player, string $essenceId, string $effectKey, float $default) : float
    {
        $essence = self::getEssence($essenceId);
        if ($essence === null) {
            return $default;
        }

        $configured = (float) (($essence["effects"][$effectKey] ?? $default));
        $requiredEnchant = strtolower(trim((string) ($essence["enchant"] ?? $essenceId)));
        foreach ($player->getArmorInventory()->getContents() as $piece) {
            if (!self::hasEssence($piece, $essenceId)) {
                continue;
            }

            $level = CustomEnchantmentManager::getLevel($piece, $requiredEnchant);
            if ($level <= 0) {
                continue;
            }

            if ((bool) ($essence["require-max-level"] ?? true)) {
                $enchant = CustomEnchantments::getEnchantmentByName($requiredEnchant);
                if ($enchant !== null && $level < $enchant->getMaxLevel()) {
                    continue;
                }
            }

            return $configured;
        }

        return $default;
    }

    private static function refreshLore(Item $item) : void
    {
        $lore = array_values(array_filter(
            $item->getLore(),
            static fn(string $line) : bool => !str_starts_with(C::clean($line), "Essence:")
        ));
        $applied = self::getAppliedEssenceLabels($item);
        if ($applied !== []) {
            $lore[] = "";
            $lore[] = C::colorize("&r&7Essence: &f" . implode(", ", $applied));
        }
        $item->setLore($lore);
    }

    /**
     * @return string[]
     */
    private static function getAppliedEssenceLabels(Item $item) : array
    {
        $tag = $item->getNamedTag()->getCompoundTag("GlaciaEssences");
        if ($tag === null) {
            return [];
        }

        $labels = [];
        foreach (array_keys(self::$essences) as $essenceId) {
            if ($tag->getByte($essenceId, 0) === 1) {
                $labels[] = ucfirst(str_replace("_", " ", $essenceId));
            }
        }

        return $labels;
    }
}
