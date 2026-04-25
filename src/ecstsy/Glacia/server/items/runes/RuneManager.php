<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\items\runes;

use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\block\Beetroot;
use pocketmine\block\Carrot;
use pocketmine\block\NetherWartPlant;
use pocketmine\block\Potato;
use pocketmine\block\Wheat;
use pocketmine\inventory\ArmorInventory;
use pocketmine\item\Armor;
use pocketmine\item\Axe;
use pocketmine\item\Bow;
use pocketmine\item\Hoe;
use pocketmine\item\Item;
use pocketmine\item\Pickaxe;
use pocketmine\item\Shovel;
use pocketmine\item\Sword;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use pocketmine\world\Position;

final class RuneManager
{
    private const GLACIA_TAG = "Glacia";
    private const NBT_RUNE_ID = "rune_id";
    private const NBT_RUNE_MODE = "rune_mode";
    private const VERDANT_TICK_INTERVAL = 3;

    /** @var array<string, int> */
    private static array $lastVerdantTick = [];

    /** @var array<string, mixed> */
    private static array $config = [];

    public static function loadFromConfig(): void
    {
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "runes.yml");
        self::$config = $config?->getAll() ?? [];
    }

    public static function clearPlayerCaches(Player $player): void
    {
        unset(self::$lastVerdantTick[$player->getUniqueId()->toString()]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function getDefinitions(): array
    {
        return (array) (self::$config["runes"] ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    public static function getDefinition(string $runeId): array
    {
        return (array) (self::getDefinitions()[strtolower($runeId)] ?? []);
    }

    public static function hasDefinition(string $runeId): bool
    {
        return self::getDefinition($runeId) !== [];
    }

    public static function isPassive(string $runeId): bool
    {
        return strtolower((string) (self::getDefinition($runeId)["mode"] ?? "applied")) === "passive";
    }

    public static function isApplied(string $runeId): bool
    {
        return strtolower((string) (self::getDefinition($runeId)["mode"] ?? "applied")) === "applied";
    }

    public static function getSuccessChance(string $runeId): int
    {
        return max(1, min(100, (int) (self::getDefinition($runeId)["success"] ?? 100)));
    }

    public static function getRemovalChance(): int
    {
        return max(1, min(100, (int) (self::$config["remover"]["success"] ?? 80)));
    }

    public static function getDisplayName(string $runeId): string
    {
        return (string) (self::getDefinition($runeId)["display"] ?? ucfirst(str_replace("_", " ", $runeId)));
    }

    public static function getPassiveChance(string $runeId, int $fallback = 0): int
    {
        return max(0, min(100, (int) (self::getDefinition($runeId)["chance"] ?? $fallback)));
    }

    public static function getPetMultiplier(Player $player, string $petKey): float
    {
        $petKey = strtolower($petKey);
        foreach ($player->getInventory()->getContents() as $item) {
            $glacia = $item->getNamedTag()->getCompoundTag(self::GLACIA_TAG);
            if ($glacia === null || $glacia->getString("glaciaItem", "") !== "pet_duration_rune") {
                continue;
            }

            if (strtolower($glacia->getString("pet", "")) !== $petKey) {
                continue;
            }

            return max(1.0, (float) (self::getDefinition("pet_duration")["multiplier"] ?? 1.5));
        }

        return 1.0;
    }

    public static function getPetDisplay(string $petKey): string
    {
        return (string) (self::getDefinition("pet_duration")["pets"][strtolower($petKey)] ?? ucwords(str_replace("_", " ", $petKey)));
    }

    public static function hasPassiveRune(Player $player, string $runeId): bool
    {
        $runeId = strtolower($runeId);
        foreach ($player->getInventory()->getContents() as $item) {
            $glacia = $item->getNamedTag()->getCompoundTag(self::GLACIA_TAG);
            if ($glacia === null) {
                continue;
            }

            if ($glacia->getString("glaciaItem", "") !== "rune") {
                continue;
            }

            if (strtolower($glacia->getString("rune_id", "")) === $runeId) {
                return true;
            }
        }

        return false;
    }

    public static function hasRuneOnArmor(Player $player, string $runeId): bool
    {
        foreach ($player->getArmorInventory()->getContents() as $item) {
            if (self::getAppliedRuneId($item) === strtolower($runeId)) {
                return true;
            }
        }

        return false;
    }

    public static function getCombatTagExtensionSeconds(Player $player): int
    {
        return self::hasRuneOnArmor($player, "bloodfang") ? 3 : 0;
    }

    public static function getLifestealMitigationPercent(Player $player): float
    {
        return self::hasRuneOnArmor($player, "bloodfang") ? 10.0 : 0.0;
    }

    /**
     * @return array<string, int>
     */
    public static function getArmorEffectRuneEnchants(Item $item): array
    {
        return match (self::getAppliedRuneId($item)) {
            "celerity" => ["gears" => 5],
            "colossus" => ["overload" => 4],
            default => [],
        };
    }

    public static function canApplyTo(Item $item, string $runeId): bool
    {
        $runeId = strtolower($runeId);
        if (!self::isApplied($runeId)) {
            return false;
        }

        if (self::hasAppliedRune($item)) {
            return false;
        }

        $appliesTo = array_map("strtolower", (array) (self::getDefinition($runeId)["applies-to"] ?? []));
        if ($appliesTo === []) {
            return false;
        }

        foreach ($appliesTo as $type) {
            if (self::matchesType($item, $type)) {
                return true;
            }
        }

        return false;
    }

    private static function matchesType(Item $item, string $type): bool
    {
        $glacia = $item->getNamedTag()->getCompoundTag(self::GLACIA_TAG);
        $armorPiece = strtolower((string) $glacia?->getString("armorPiece", ""));

        return match ($type) {
            "armor" => $item instanceof Armor,
            "boots" => $item instanceof Armor && (
                $armorPiece === "boots" ||
                in_array($item->getTypeId(), [
                    \pocketmine\item\VanillaItems::LEATHER_BOOTS()->getTypeId(),
                    \pocketmine\item\VanillaItems::CHAINMAIL_BOOTS()->getTypeId(),
                    \pocketmine\item\VanillaItems::IRON_BOOTS()->getTypeId(),
                    \pocketmine\item\VanillaItems::GOLDEN_BOOTS()->getTypeId(),
                    \pocketmine\item\VanillaItems::DIAMOND_BOOTS()->getTypeId(),
                    \pocketmine\item\VanillaItems::NETHERITE_BOOTS()->getTypeId(),
                ], true)
            ),
            "weapon" => $item instanceof Sword || $item instanceof Axe || $item instanceof Bow,
            "tool" => $item instanceof Pickaxe || $item instanceof Shovel || $item instanceof Hoe || $item instanceof Axe,
            default => false,
        };
    }

    public static function hasAppliedRune(Item $item): bool
    {
        return self::getAppliedRuneId($item) !== null;
    }

    public static function getAppliedRuneId(Item $item): ?string
    {
        $glacia = $item->getNamedTag()->getCompoundTag(self::GLACIA_TAG);
        if ($glacia === null) {
            return null;
        }

        $id = strtolower($glacia->getString(self::NBT_RUNE_ID, ""));
        return $id !== "" ? $id : null;
    }

    public static function applyRune(Item $item, string $runeId): Item
    {
        $runeId = strtolower($runeId);
        $root = $item->getNamedTag();
        $glacia = $root->getCompoundTag(self::GLACIA_TAG) ?? new CompoundTag();
        $glacia->setString(self::NBT_RUNE_ID, $runeId);
        $glacia->setString(self::NBT_RUNE_MODE, "applied");
        $root->setTag(self::GLACIA_TAG, $glacia);
        $item->setNamedTag($root);
        $item->setLore(self::withRuneLore($item->getLore(), $runeId));

        return $item;
    }

    public static function removeRune(Item $item): Item
    {
        $root = $item->getNamedTag();
        $glacia = $root->getCompoundTag(self::GLACIA_TAG);
        if ($glacia !== null) {
            $glacia->removeTag(self::NBT_RUNE_ID);
            $glacia->removeTag(self::NBT_RUNE_MODE);
            $root->setTag(self::GLACIA_TAG, $glacia);
            $item->setNamedTag($root);
        }
        $item->setLore(self::withoutRuneLore($item->getLore()));

        return $item;
    }

    /**
     * @param list<string> $lore
     * @return list<string>
     */
    private static function withRuneLore(array $lore, string $runeId): array
    {
        $lore = self::withoutRuneLore($lore);
        if ($lore !== []) {
            $lore[] = "";
        }
        $lore[] = C::colorize("&r&dRune: &f" . self::getDisplayName($runeId));
        return $lore;
    }

    /**
     * @param list<string> $lore
     * @return list<string>
     */
    private static function withoutRuneLore(array $lore): array
    {
        $filtered = array_values(array_filter(
            $lore,
            static fn(string $line): bool => !str_contains(C::clean($line), "Rune:")
        ));

        while ($filtered !== [] && C::clean((string) end($filtered)) === "") {
            array_pop($filtered);
        }

        return $filtered;
    }

    public static function tryBoostNearbyCrops(Player $player): void
    {
        if (!self::hasPassiveRune($player, "verdant")) {
            return;
        }

        $uuid = $player->getUniqueId()->toString();
        $now = time();
        if ((self::$lastVerdantTick[$uuid] ?? 0) > $now) {
            return;
        }
        self::$lastVerdantTick[$uuid] = $now + self::VERDANT_TICK_INTERVAL;

        $center = $player->getPosition();
        $world = $center->getWorld();

        for ($x = -4; $x <= 4; $x++) {
            for ($y = -1; $y <= 2; $y++) {
                for ($z = -4; $z <= 4; $z++) {
                    if (mt_rand(1, 100) > 18) {
                        continue;
                    }

                    $position = new Position($center->getFloorX() + $x, $center->getFloorY() + $y, $center->getFloorZ() + $z, $world);
                    $block = $world->getBlock($position);

                    if ($block instanceof Wheat && $block->getAge() < Wheat::MAX_AGE) {
                        $world->setBlock($position, $block->setAge(min(Wheat::MAX_AGE, $block->getAge() + 1)));
                        continue;
                    }

                    if ($block instanceof Carrot && $block->getAge() < Carrot::MAX_AGE) {
                        $world->setBlock($position, $block->setAge(min(Carrot::MAX_AGE, $block->getAge() + 1)));
                        continue;
                    }

                    if ($block instanceof Potato && $block->getAge() < Potato::MAX_AGE) {
                        $world->setBlock($position, $block->setAge(min(Potato::MAX_AGE, $block->getAge() + 1)));
                        continue;
                    }

                    if ($block instanceof Beetroot && $block->getAge() < Beetroot::MAX_AGE) {
                        $world->setBlock($position, $block->setAge(min(Beetroot::MAX_AGE, $block->getAge() + 1)));
                        continue;
                    }

                    if ($block instanceof NetherWartPlant && $block->getAge() < NetherWartPlant::MAX_AGE) {
                        $world->setBlock($position, $block->setAge(min(NetherWartPlant::MAX_AGE, $block->getAge() + 1)));
                    }
                }
            }
        }
    }
}
