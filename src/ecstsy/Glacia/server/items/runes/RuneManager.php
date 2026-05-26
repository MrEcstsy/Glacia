<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\items\runes;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\items\lore\ItemLoreLayout;
use ecstsy\Glacia\server\items\pets\PetManager;
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
use pocketmine\item\FishingRod;
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
        $petKey = PetManager::resolvePetKey($petKey);
        foreach (self::getPassiveRuneStorageContents($player) as $item) {
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
        $petKey = PetManager::resolvePetKey($petKey);
        $displayKey = preg_replace('/_pet$/', '', $petKey) ?? $petKey;

        return (string) (self::getDefinition("pet_duration")["pets"][$displayKey] ?? ucwords(str_replace("_", " ", $displayKey)));
    }

    public static function hasPassiveRune(Player $player, string $runeId): bool
    {
        $runeId = strtolower($runeId);
        foreach (self::getPassiveRuneStorageContents($player) as $item) {
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

    /**
     * @return list<Item>
     */
    private static function getPassiveRuneStorageContents(Player $player): array
    {
        return [
            ...array_values($player->getInventory()->getContents()),
            ...array_values($player->getEnderInventory()->getContents()),
            ...Loader::getPassiveRunePouchManager()->getActiveContents($player),
        ];
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
        if (self::hasRuneOnArmor($player, "hemoguard")) {
            return 100.0;
        }

        return self::hasRuneOnArmor($player, "bloodfang") ? 10.0 : 0.0;
    }

    public static function hasRuneOnHeldItem(Player $player, string $runeId): bool
    {
        return self::getAppliedRuneId($player->getInventory()->getItemInHand()) === strtolower($runeId);
    }

    public static function getChance(string $runeId, int $fallback = 0): int
    {
        return self::getPassiveChance($runeId, $fallback);
    }

    public static function getValueFloat(string $runeId, string $key, float $fallback): float
    {
        return (float) (self::getDefinition($runeId)[$key] ?? $fallback);
    }

    public static function getValueInt(string $runeId, string $key, int $fallback): int
    {
        return (int) (self::getDefinition($runeId)[$key] ?? $fallback);
    }

    public static function shouldPreserveConsumable(Player $player, string $itemTag): bool
    {
        if (!self::hasPassiveRune($player, "echo_cache")) {
            return false;
        }

        $chance = self::getChance("echo_cache", 1);
        if ($chance <= 0 || mt_rand(1, 100) > $chance) {
            return false;
        }

        $allowed = array_map(
            static fn(string $v): string => strtolower(trim($v)),
            (array) (self::getDefinition("echo_cache")["items"] ?? ["lootbox"])
        );

        return in_array(strtolower($itemTag), $allowed, true);
    }

    public static function getSellMultiplier(Player $player): float
    {
        if (!self::hasPassiveRune($player, "merchant_sigil")) {
            return 1.0;
        }

        return max(1.0, self::getValueFloat("merchant_sigil", "multiplier", 2.0));
    }

    public static function getAuctionFeeMultiplier(Player $player): float
    {
        if (!self::hasPassiveRune($player, "bottomless_gullet")) {
            return 1.0;
        }

        return max(0.0, min(1.0, self::getValueFloat("bottomless_gullet", "auction-fee-multiplier", 0.8)));
    }

    public static function getFactionBoosterDurationMultiplier(Player $player): float
    {
        if (!self::hasPassiveRune($player, "chronoshift")) {
            return 1.0;
        }

        return max(1.0, self::getValueFloat("chronoshift", "booster-duration-multiplier", 1.2));
    }

    public static function getPetCooldownMultiplier(Player $player): float
    {
        if (!self::hasPassiveRune($player, "beastclock")) {
            return 1.0;
        }

        return max(0.1, min(1.0, self::getValueFloat("beastclock", "pet-cooldown-multiplier", 0.9)));
    }

    public static function hasNoHungerPassive(Player $player): bool
    {
        return self::hasPassiveRune($player, "bottomless_gullet");
    }

    public static function shouldDoubleZoneDrops(Player $player): bool
    {
        return self::hasRuneOnHeldItem($player, "raider_edge")
            && mt_rand(1, 100) <= self::getChance("raider_edge", 5);
    }

    public static function shouldInstantKillBoss(Player $player): bool
    {
        return self::hasRuneOnHeldItem($player, "reaper_oath")
            && mt_rand(1, 100) <= self::getChance("reaper_oath", 2);
    }

    public static function getMobRewardMultiplier(Player $player): float
    {
        if (!self::hasRuneOnHeldItem($player, "harvester_mark")) {
            return 1.0;
        }

        return max(1.0, self::getValueFloat("harvester_mark", "mob-reward-multiplier", 2.5));
    }

    public static function shouldTriggerPotionPurge(Player $player): bool
    {
        return self::hasRuneOnArmor($player, "null_field")
            && mt_rand(1, 100) <= self::getChance("null_field", 1);
    }

    public static function getPotionPurgeRadius(): float
    {
        return max(1.0, self::getValueFloat("null_field", "radius", 2.0));
    }

    public static function getNullFieldDamageMultiplier(Player $player): float
    {
        if (!self::hasRuneOnArmor($player, "null_field")) {
            return 1.0;
        }

        return max(1.0, self::getValueFloat("null_field", "damage-multiplier", 2.0));
    }

    public static function hasStormcallerWard(Player $player): bool
    {
        return self::hasRuneOnArmor($player, "stormward");
    }

    public static function getLegendaryDamageReduction(Player $player): float
    {
        if (!self::hasStormcallerWard($player)) {
            return 0.0;
        }

        return max(0.0, min(100.0, self::getValueFloat("stormward", "legendary-damage-reduction-percent", 10.0)));
    }

    public static function hasSlownessImmunity(Player $player): bool
    {
        return self::hasRuneOnArmor($player, "swiftbane");
    }

    public static function getGlobalProcChanceBonus(Player $player): int
    {
        $bonus = 0;
        if (self::hasRuneOnArmor($player, "swiftbane")) {
            $bonus += self::getValueInt("swiftbane", "proc-bonus-percent", 3);
        }
        if (self::hasRuneOnArmor($player, "sigil_of_surge")) {
            $bonus += self::getValueInt("sigil_of_surge", "proc-bonus-percent", 1);
        }

        return max(0, $bonus);
    }

    public static function getRodSpawnRateMultiplier(Player $player): float
    {
        if (!self::hasRuneOnHeldItem($player, "tidehook")) {
            return 1.0;
        }

        return max(1.0, self::getValueFloat("tidehook", "fishing-speed-multiplier", 1.5));
    }

    public static function shouldDropExtraEnvoyReward(Player $player): bool
    {
        return self::hasPassiveRune($player, "envoy_echo")
            && mt_rand(1, 100) <= self::getChance("envoy_echo", 2);
    }

    public static function canOverclockZoneSpawns(Player $player): bool
    {
        return self::hasRuneOnArmor($player, "solitary_hunt");
    }

    public static function getZoneSpawnSpeedMultiplier(Player $player): float
    {
        if (!self::canOverclockZoneSpawns($player)) {
            return 1.0;
        }

        return max(1.0, self::getValueFloat("solitary_hunt", "spawn-speed-multiplier", 2.0));
    }

    public static function getZoneXpMultiplier(Player $player): float
    {
        if (!self::canOverclockZoneSpawns($player)) {
            return 1.0;
        }

        return max(1.0, self::getValueFloat("solitary_hunt", "zone-xp-multiplier", 1.5));
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
            "rod" => $item instanceof FishingRod,
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
        ItemLoreLayout::normalizeForCombatGear($item);

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
        ItemLoreLayout::normalizeForCombatGear($item);

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
        $display = self::getDisplayName($runeId);
        $lore[] = C::colorize(
            str_contains($display, "&")
                ? "&r&7Rune: {$display}"
                : "&r&7Rune: &f{$display}"
        );
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

    public static function shouldPreventFarmlandTrample(Player $player): bool
    {
        if (!self::hasPassiveRune($player, "verdant")) {
            return false;
        }

        if (!(bool) (self::getDefinition("verdant")["anti-trample-farmland"] ?? true)) {
            return false;
        }

        $chance = max(0, min(100, (int) (self::getDefinition("verdant")["farmland-trample-chance"] ?? 100)));
        if ($chance <= 0) {
            return false;
        }

        return mt_rand(1, 100) <= $chance;
    }
}
