<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\gkits;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\player\GlaciaPlayer;
use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\items\GlaciaItemFactory;
use ecstsy\Glacia\server\items\pets\PetManager;
use ecstsy\Glacia\server\kits\KitRepository;
use ecstsy\Glacia\utils\RewardResolver;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\item\Armor;
use pocketmine\item\Axe;
use pocketmine\item\Bow;
use pocketmine\item\Item;
use pocketmine\item\Sword;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class GKitRepository
{
    private const LEVELS_SETTING = "gkit_levels";
    private const DEFAULT_MAX_LEVEL = 10;

    public static function getGKits(): array
    {
        return KitRepository::getKits("gkits");
    }

    public static function getGKitById(string $kitId): ?array
    {
        return KitRepository::getKitById($kitId, "gkits");
    }

    public static function createIcon(array $kit, ?Player $player = null): Item
    {
        $iconData = $kit["icon"] ?? null;
        $icon = is_array($iconData) ? RewardResolver::resolveItem($iconData) : null;
        if ($icon === null) {
            $icon = VanillaItems::NETHER_STAR();
        }

        $icon->setCustomName((string) $kit["display"]);
        $lore = [];

        $description = (string) ($kit["description"] ?? "");
        if ($description !== "") {
            foreach (explode("\n", $description) as $line) {
                $lore[] = $line;
            }
            $lore[] = "";
        }

        $maxLevel = self::getMaxLevel($kit);
        $lore[] = "&r&7Max Level: &f" . $maxLevel;

        if ($player !== null) {
            $level = self::getLevel(PlayerManager::getInstance()->getSession($player), (string) $kit["id"]);
            $lore[] = "&r&7Your Level: &f" . $level . "&7/&f" . $maxLevel;
            $lore[] = "";

            if ($level <= 0) {
                $lore[] = "&r&l&cLOCKED";
                $lore[] = "&r&7Redeem a matching gem to unlock.";
            } else {
                $remaining = self::getRemainingCooldown($player, $kit);
                if ($remaining > 0) {
                    $lore[] = "&r&l&eCOOLING DOWN";
                    $lore[] = "&r&7Ready in &f" . GeneralUtils::translateTime($remaining);
                } else {
                    $lore[] = "&r&l&aREADY";
                    $lore[] = "&r&7Click to claim.";
                }
            }
        }

        $icon->setLore(array_map(static fn(string $line): string => C::colorize($line), $lore));
        return $icon;
    }

    public static function claim(Player $player, array $kit): bool
    {
        $session = Loader::getPlayerManager()->getSession($player);
        if ($session === null) {
            $player->sendMessage(C::colorize("&r&cCould not load your profile."));
            return false;
        }

        $level = self::getLevel($session, (string) $kit["id"]);
        if ($level <= 0) {
            $player->sendMessage(C::colorize("&r&cYou have not unlocked this GKit yet."));
            return false;
        }

        $remaining = self::getRemainingCooldown($player, $kit);
        if ($remaining > 0) {
            $player->sendMessage(C::colorize("&r&cThis GKit is on cooldown for &f" . GeneralUtils::translateTime($remaining) . "&c."));
            return false;
        }

        $gkitCharmBonus = PetManager::getGKitUpgradeCharmBonus($player);
        $items = self::resolveItemsForLevel($kit, $level, $gkitCharmBonus);
        if ($items === []) {
            $player->sendMessage(C::colorize("&r&cThis GKit has no valid items configured."));
            return false;
        }

        $overflow = self::deliverItems($player, $session, $items);

        $cooldown = GeneralUtils::parseTimeString((string) ($kit["cooldown"] ?? "0"));
        if ($cooldown > 0) {
            $session->addCooldown(self::getCooldownKey((string) $kit["id"]), $cooldown);
        }

        PlayerUtils::playSound($player, "random.levelup");
        $player->sendMessage(C::colorize("&r&aClaimed &f" . ((string) $kit["display"]) . "&r&a at level &f" . $level . "&a."));
        if ($overflow !== []) {
            $player->sendMessage(C::colorize("&r&eSome items were sent to your collection because your inventory was full."));
        }

        if ($gkitCharmBonus > 0) {
            PetManager::consumeGKitUpgradeCharm($player);
            $player->sendMessage(C::colorize("&r&eCrownspire Kestrel &r&7consumed its GKit empowerment on this pull."));
        }

        return true;
    }

    public static function give(Player $player, array $kit, int $levelOverride = 1): bool
    {
        $items = self::resolveItemsForLevel($kit, max(1, $levelOverride));
        if ($items === []) {
            return false;
        }

        $session = Loader::getPlayerManager()->getSession($player);
        if ($session === null) {
            return false;
        }

        self::deliverItems($player, $session, $items);

        return true;
    }

    public static function resolveItemsForLevel(array $kit, int $level, int $petGkitEnchantUpgradeBonus = 0): array
    {
        $resolved = [];
        foreach ((array) ($kit["items"] ?? []) as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $item = RewardResolver::resolveItem(self::applyKitItemDefaults(self::applyLevelScaling($entry, $kit, $level, $petGkitEnchantUpgradeBonus), $kit));
            if ($item !== null) {
                $resolved[] = $item;
            }
        }

        $randomPool = (array) ($kit["random-items"] ?? []);
        $rolls = max(0, (int) ($kit["random-rolls"] ?? 0));
        for ($i = 0; $i < $rolls; $i++) {
            $entry = RewardResolver::rollEntry($randomPool);
            if (!is_array($entry)) {
                continue;
            }

            $item = RewardResolver::resolveItem(self::applyKitItemDefaults(self::applyLevelScaling($entry, $kit, $level, $petGkitEnchantUpgradeBonus), $kit));
            if ($item !== null) {
                $resolved[] = $item;
            }
        }

        return $resolved;
    }

    public static function getLevel(?GlaciaPlayer $session, string $kitId): int
    {
        if ($session === null) {
            return 0;
        }

        $levels = $session->getSetting(self::LEVELS_SETTING);
        if (!is_array($levels)) {
            return 0;
        }

        return max(0, (int) ($levels[strtolower($kitId)] ?? 0));
    }

    public static function setLevel(GlaciaPlayer $session, string $kitId, int $level): void
    {
        $levels = $session->getSetting(self::LEVELS_SETTING);
        $levels = is_array($levels) ? $levels : [];
        $kitId = strtolower($kitId);

        if ($level <= 0) {
            unset($levels[$kitId]);
        } else {
            $levels[$kitId] = $level;
        }

        $session->setSetting(self::LEVELS_SETTING, $levels);
    }

    public static function redeemGem(Player $player, string $kitId, int $amount = 1): bool
    {
        $session = Loader::getPlayerManager()->getSession($player);
        $kit = self::getGKitById($kitId);
        if ($session === null || $kit === null) {
            return false;
        }

        $maxLevel = self::getMaxLevel($kit);
        $current = self::getLevel($session, $kitId);
        if ($current >= $maxLevel) {
            $warlord = WarlordRepository::getForKit($kit);
            $display = (string) ($warlord["display"] ?? $kit["display"] ?? ucfirst($kitId));
            $player->sendMessage(C::colorize(str_replace(
                ["{display}", "{gkit}", "{level}"],
                [$display, $display, (string) $maxLevel],
                (string) ($warlord["gem-max-level-message"] ?? "&r&c{display} is already at max level (&f{level}&c).")
            )));
            PlayerUtils::playSound($player, "note.bass");
            return false;
        }

        $newLevel = min($maxLevel, $current + $amount);
        self::setLevel($session, $kitId, $newLevel);

        $verb = $current <= 0 ? "unlocked" : "upgraded";
        $warlord = WarlordRepository::getForKit($kit);
        $display = (string) ($warlord["display"] ?? $kit["display"] ?? ucfirst($kitId));
        $player->sendMessage(C::colorize(str_replace(
            ["{verb}", "{level}", "{display}", "{gkit}"],
            [$verb, (string) $newLevel, $display, $display],
            (string) ($warlord["gem-redeem-message"] ?? "&r&aYou have {$verb} &f{display}&r&a to level &f{level}&a.")
        )));
        $player->sendTitle(
            C::colorize((string) ($warlord["gem-redeem-title"] ?? "&r&bGKit Empowered")),
            C::colorize(str_replace(
                ["{verb}", "{level}", "{display}", "{gkit}"],
                [$verb, (string) $newLevel, $display, $display],
                (string) ($warlord["gem-redeem-subtitle"] ?? "&r&7Level &f{level}")
            ))
        );
        PlayerUtils::playSound($player, "random.levelup");
        return true;
    }

    public static function createGem(array $kit): Item
    {
        return GlaciaItemFactory::gkitGem(
            (string) $kit["id"],
            C::clean((string) ($kit["display"] ?? ucfirst((string) $kit["id"]))),
            (string) ($kit["gem-name"] ?? "")
        );
    }

    public static function createSummoner(array $kit): Item
    {
        $warlord = WarlordRepository::getForKit($kit);
        return GlaciaItemFactory::gkitSummoner(
            (string) $kit["id"],
            C::clean((string) ($kit["display"] ?? ucfirst((string) $kit["id"]))),
            (string) ($warlord["summoner-name"] ?? $kit["summoner-name"] ?? ""),
            (array) ($warlord["summoner-lore"] ?? [])
        );
    }

    public static function createChampionReward(array $kit): ?Item
    {
        $warlord = WarlordRepository::getForKit($kit);
        $gemChance = max(0, min(100, (int) ($warlord["gem-drop-chance"] ?? $kit["gem-drop-chance"] ?? 60)));
        $gearChance = max(0, min(100, (int) ($warlord["gear-drop-chance"] ?? $kit["gear-drop-chance"] ?? 40)));

        $roll = mt_rand(1, max(1, $gemChance + $gearChance));
        if ($roll <= $gemChance) {
            return self::createGem($kit);
        }

        $gearPool = [];
        foreach ((array) ($kit["items"] ?? []) as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $item = RewardResolver::resolveItem($entry);
            if ($item instanceof Armor || $item instanceof Sword || $item instanceof Axe || $item instanceof Bow) {
                $gearPool[] = $item;
            }
        }

        if ($gearPool === []) {
            return self::createGem($kit);
        }

        $reward = clone $gearPool[array_rand($gearPool)];
        $reward->setCount(1);
        return $reward;
    }

    public static function clearCooldown(Player $player, array $kit): bool
    {
        $session = Loader::getPlayerManager()->getSession($player);
        if ($session === null) {
            return false;
        }

        $session->clearCooldown(self::getCooldownKey((string) $kit["id"]));
        return true;
    }

    public static function getRemainingCooldown(Player $player, array $kit): int
    {
        $session = Loader::getPlayerManager()->getSession($player);
        return $session?->getCooldown(self::getCooldownKey((string) $kit["id"])) ?? 0;
    }

    public static function getMaxLevel(array $kit): int
    {
        return max(1, (int) ($kit["max-level"] ?? self::DEFAULT_MAX_LEVEL));
    }

    /**
     * @param array<string, mixed> $entry
     * @param array<string, mixed> $kit
     * @return array<string, mixed>
     */
    private static function applyLevelScaling(array $entry, array $kit, int $gkitLevel, int $petUpgradeRollBonus = 0): array
    {
        if (!isset($entry["enchantments"]) || !is_array($entry["enchantments"])) {
            return $entry;
        }

        $levelConfig = self::resolveLevelConfig($kit, $gkitLevel);
        $chancePerLevel = max(0, (int) ($levelConfig["upgrade-chance-per-enchantment"] ?? $entry["upgrade-chance-per-level"] ?? $kit["upgrade-chance-per-level"] ?? 8));
        $scaledEnchantments = [];

        foreach ($entry["enchantments"] as $enchantment) {
            if (!is_array($enchantment)) {
                continue;
            }

            $enchantKey = strtolower((string) ($enchantment["enchant"] ?? ""));
            $mappedLevelData = is_array($levelConfig["enchantments"] ?? null) && isset($levelConfig["enchantments"][$enchantKey]) && is_array($levelConfig["enchantments"][$enchantKey])
                ? $levelConfig["enchantments"][$enchantKey]
                : [];

            $baseLevel = max(1, (int) GeneralUtils::parseRandomNumber(
                $mappedLevelData["minimum-level"]
                ?? $mappedLevelData["min-level"]
                ?? $mappedLevelData["minimum"]
                ?? $mappedLevelData["min"]
                ?? $enchantment["base-level"]
                ?? $enchantment["minimum-level"]
                ?? $enchantment["min-level"]
                ?? $enchantment["level"]
                ?? 1
            ));
            $maxLevel = max($baseLevel, (int) GeneralUtils::parseRandomNumber(
                $mappedLevelData["maximum-level"]
                ?? $mappedLevelData["max-level"]
                ?? $mappedLevelData["maximum"]
                ?? $mappedLevelData["max"]
                ?? $enchantment["maximum-level"]
                ?? $enchantment["max-level"]
                ?? $enchantment["max"]
                ?? $baseLevel
            ));

            // Respect configured min/max as an actual roll range instead of pinning at level 1.
            $finalLevel = $maxLevel > $baseLevel ? mt_rand($baseLevel, $maxLevel) : $baseLevel;

            // Optional "upgrade chance per level" plus Crownspire Kestrel (+N% per successful roll tick).
            if ($finalLevel < $maxLevel) {
                $extraChance = 0;
                if ($gkitLevel > 1 && $chancePerLevel > 0) {
                    $extraChance = min(90, ($gkitLevel - 1) * $chancePerLevel);
                }
                $extraChance = min(100, $extraChance + max(0, $petUpgradeRollBonus));
                while ($finalLevel < $maxLevel && $extraChance > 0 && mt_rand(1, 100) <= $extraChance) {
                    $finalLevel++;
                    $extraChance = max(5, $extraChance - 12);
                }
            }

            $enchantment["level"] = $finalLevel;
            unset($enchantment["base-level"], $enchantment["max-level"], $enchantment["upgrade-chance-per-level"]);
            $scaledEnchantments[] = $enchantment;
        }

        $entry["enchantments"] = $scaledEnchantments;
        return $entry;
    }

    private static function resolveLevelConfig(array $kit, int $gkitLevel): array
    {
        $levels = (array) ($kit["levels"] ?? []);
        if ($levels === []) {
            return [];
        }

        ksort($levels, SORT_NUMERIC);
        $resolved = [];
        foreach ($levels as $level => $data) {
            if ((int) $level > $gkitLevel || !is_array($data)) {
                continue;
            }

            $resolved = array_replace_recursive($resolved, $data);
        }

        return $resolved;
    }

    private static function getCooldownKey(string $kitId): string
    {
        return "gkit_" . strtolower($kitId);
    }

    /**
     * Allow root-level gkit flags like "heroic: true" to apply to entries
     * unless the entry explicitly overrides it.
     *
     * @param array<string, mixed> $entry
     * @param array<string, mixed> $kit
     * @return array<string, mixed>
     */
    private static function applyKitItemDefaults(array $entry, array $kit): array
    {
        foreach (["heroic", "is_heroic", "heroic_armor"] as $heroicKey) {
            if (array_key_exists($heroicKey, $entry)) {
                return $entry;
            }
        }

        if (array_key_exists("heroic", $kit)) {
            $entry["heroic"] = $kit["heroic"];
        } elseif (array_key_exists("is_heroic", $kit)) {
            $entry["heroic"] = $kit["is_heroic"];
        } elseif (array_key_exists("heroic_armor", $kit)) {
            $entry["heroic"] = $kit["heroic_armor"];
        }

        return $entry;
    }

    /**
     * @param list<Item> $items
     * @return list<Item>
     */
    private static function deliverItems(Player $player, GlaciaPlayer $session, array $items): array
    {
        $overflow = [];
        foreach ($items as $item) {
            foreach ($player->getInventory()->addItem(clone $item) as $leftover) {
                $overflow[] = $leftover;
                $session->addItemToCollection($leftover);
            }
        }

        return $overflow;
    }
}
