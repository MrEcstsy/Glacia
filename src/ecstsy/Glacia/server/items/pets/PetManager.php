<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\items\pets;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\items\GlaciaItemFactory;
use ecstsy\Glacia\server\items\runes\RuneManager;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

final class PetManager
{
    /**
     * Resolves a pet id to the key present under {@code pets} in {@code pets.yml}.
     * Prefers the canonical display-style id (e.g. {@code gildedmoth}); legacy snake_case and {@code *_pet}
     * aliases still resolve when present.
     * Legacy {@code *_pet} inputs still resolve when a matching short key exists.
     */
    public static function resolvePetKey(string $petKey) : string{
        $petKey = strtolower(trim($petKey));
        if($petKey === ""){
            return $petKey;
        }

        $petKey = strtolower(trim(GlaciaItemFactory::resolvePetAliasKey($petKey)));

        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "pets.yml");
        $pets = (array) ($config?->get("pets") ?? []);

        if(array_key_exists($petKey, $pets)){
            return $petKey;
        }

        if(str_ends_with($petKey, "_pet")){
            $base = substr($petKey, 0, -4);
            if($base !== "" && array_key_exists($base, $pets)){
                return $base;
            }

            return $petKey;
        }

        $legacy = $petKey . "_pet";
        if(array_key_exists($legacy, $pets)){
            return $legacy;
        }

        return $petKey;
    }

    /** @var array<string, int> */
    private static array $gargoyleExpiries = [];
    /** @var array<string, int> */
    private static array $ironcladDisarmCooldowns = [];
    /** @var array<string, int> */
    private static array $aegisExpiries = [];
    /** @var array<string, int> */
    private static array $pearlBlockExpiries = [];
    /** @var array<string, int> */
    private static array $gappleBlockExpiries = [];
    /** @var array<string, int> */
    private static array $enchantSurgeExpiries = [];
    /** @var array<string, int> */
    private static array $nearHideExpiries = [];
    /** @var array<string, int> */
    private static array $blastShieldExpiries = [];
    /** @var array<string, int> */
    private static array $jackpotSlotGuaranteeExpiries = [];
    /** @var array<string, int> */
    private static array $guaranteedSuccessExpiries = [];
    /** @var array<string, int> */
    private static array $silenceBurstExpiries = [];
    /** @var array<string, int> */
    private static array $defenseBreakExpiries = [];
    /** @var array<string, int> */
    private static array $envoyUpgradeExpiries = [];
    /** @var array<string, int> */
    private static array $zoneXpExpiries = [];
    /** @var array<string, int> */
    private static array $freeSoulCostExpiries = [];
    /** @var array<string, array{expires_at:int, percent:int}> */
    private static array $enchanterDiscountStates = [];
    /** @var array<string, \pocketmine\item\Item[]> */
    private static array $pendingTamedPets = [];

    /** @var array<string, int> epoch seconds knockback immunity (Watchman Anchor / misc) */
    private static array $briefKnockbackImmuneUntil = [];

    /** @var array<string, int> epoch — +N% enchant book apply success bonus from Arcbinder Codex */
    private static array $bookApplyCharmExpiries = [];

    /**
     * @var array<string, array{bonus:int, until:int}>
     *         Next /gkits claim enchant-upgrade roll modifier; must claim before {@code until}.
     */
    private static array $gkitUpgradeCharms = [];

    public static function getCooldown(string $petKey, int $fallback): int
    {
        $petKey = self::resolvePetKey($petKey);
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "pets.yml");
        $raw = $config?->getNested("pets.{$petKey}.cooldown", "{$fallback}s");
        return max(1, GeneralUtils::parseTimeString((string) $raw));
    }

    public static function getCooldownLabel(string $petKey, string $fallback): string
    {
        $petKey = self::resolvePetKey($petKey);
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "pets.yml");
        return (string) ($config?->getNested("pets.{$petKey}.cooldown", $fallback) ?? $fallback);
    }

    public static function getDuration(string $petKey, string $path, int $fallback): int
    {
        $petKey = self::resolvePetKey($petKey);
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "pets.yml");
        $raw = $config?->getNested("pets.{$petKey}.{$path}", "{$fallback}s");
        return max(1, GeneralUtils::parseTimeString((string) $raw));
    }

    public static function getInt(string $petKey, string $path, int $fallback): int
    {
        $petKey = self::resolvePetKey($petKey);
        $config = GeneralUtils::getConfiguration(Loader::getInstance(), "pets.yml");
        return max(0, (int) ($config?->getNested("pets.{$petKey}.{$path}", $fallback) ?? $fallback));
    }

    public static function activateShatterfang(Player $player, int $durationSeconds): void
    {
        self::$gargoyleExpiries[$player->getUniqueId()->toString()] = time() + $durationSeconds;
    }

    public static function isShatterfangActive(Player $player): bool
    {
        $expiry = self::$gargoyleExpiries[$player->getUniqueId()->toString()] ?? 0;
        if ($expiry <= time()) {
            unset(self::$gargoyleExpiries[$player->getUniqueId()->toString()]);
            return false;
        }

        return true;
    }

    public static function triggerIroncladDisarm(Player $player): void
    {
        self::$ironcladDisarmCooldowns[$player->getUniqueId()->toString()] = time() + 3;
    }

    public static function consumeIroncladDisarm(Player $player): bool
    {
        $key = $player->getUniqueId()->toString();
        $expiry = self::$ironcladDisarmCooldowns[$key] ?? 0;
        if ($expiry <= time()) {
            unset(self::$ironcladDisarmCooldowns[$key]);
            return false;
        }

        unset(self::$ironcladDisarmCooldowns[$key]);
        return true;
    }

    public static function activateAegis(Player $player, int $durationSeconds): void
    {
        self::$aegisExpiries[$player->getUniqueId()->toString()] = time() + $durationSeconds;
    }

    public static function isAegisActive(Player $player): bool
    {
        return self::isTimedStateActive(self::$aegisExpiries, $player);
    }

    public static function scaleBossMotion(Player $player, Vector3 $motion): Vector3
    {
        if (self::isAegisActive($player) || self::isBriefKnockbackImmune($player)) {
            return new Vector3(0.0, 0.0, 0.0);
        }

        if (!self::isAegisActive($player)) {
            return $motion;
        }

        return $motion;
    }

    public static function blockPearls(Player $player, int $durationSeconds): void
    {
        self::$pearlBlockExpiries[$player->getUniqueId()->toString()] = time() + $durationSeconds;
    }

    public static function isPearlBlocked(Player $player): bool
    {
        return self::isTimedStateActive(self::$pearlBlockExpiries, $player);
    }

    public static function blockGapples(Player $player, int $durationSeconds): void
    {
        self::$gappleBlockExpiries[$player->getUniqueId()->toString()] = time() + $durationSeconds;
    }

    public static function isGappleBlocked(Player $player): bool
    {
        return self::isTimedStateActive(self::$gappleBlockExpiries, $player);
    }

    public static function activateEnchantSurge(Player $player, int $durationSeconds): void
    {
        self::$enchantSurgeExpiries[$player->getUniqueId()->toString()] = time() + $durationSeconds;
    }

    public static function getEnchantBonusPercent(Player $player): int
    {
        $total = 0;
        if (self::isTimedStateActive(self::$enchantSurgeExpiries, $player)) {
            $total += max(1, min(100, self::getInt("runesageowl", "surge-bonus-percent", 5)));
        }
        $key = $player->getUniqueId()->toString();
        $bce = self::$bookApplyCharmExpiries[$key] ?? 0;
        if ($bce > time()) {
            $total += max(1, min(100, self::getInt("arcbindercodex", "apply-bonus-percent", 5)));
        } else {
            unset(self::$bookApplyCharmExpiries[$key]);
        }

        return min(100, $total);
    }

    public static function activateArcCodexCharm(Player $player, int $durationSeconds): void
    {
        self::$bookApplyCharmExpiries[$player->getUniqueId()->toString()] = time() + $durationSeconds;
    }

    public static function activateGKitUpgradeCharm(Player $player): void
    {
        $bonus = max(1, min(90, self::getInt("crownspirekestrel", "upgrade-roll-bonus-percent", 20)));
        $baseSecs = max(60, self::getDuration("crownspirekestrel", "claim-window", 3600));
        $until = time() + (int) ceil($baseSecs * RuneManager::getPetMultiplier($player, "crownspirekestrel"));
        self::$gkitUpgradeCharms[$player->getUniqueId()->toString()] = [
            "bonus" => $bonus,
            "until" => $until,
        ];
    }

    /** Bonus percent added to GKit armor enchant upgrade ticks while claiming; zero if expired. */
    public static function getGKitUpgradeCharmBonus(Player $player): int
    {
        $key = $player->getUniqueId()->toString();
        $state = self::$gkitUpgradeCharms[$key] ?? null;
        if (!is_array($state) || ($state["until"] ?? 0) <= time()) {
            unset(self::$gkitUpgradeCharms[$key]);
            return 0;
        }

        return max(0, min(90, (int) ($state["bonus"] ?? 0)));
    }

    public static function consumeGKitUpgradeCharm(Player $player): void
    {
        unset(self::$gkitUpgradeCharms[$player->getUniqueId()->toString()]);
    }

    public static function activateBriefKnockbackImmunity(Player $player, int $durationSeconds): void
    {
        self::$briefKnockbackImmuneUntil[$player->getUniqueId()->toString()] = time() + max(1, $durationSeconds);
    }

    public static function isBriefKnockbackImmune(Player $player): bool
    {
        $key = $player->getUniqueId()->toString();
        $until = self::$briefKnockbackImmuneUntil[$key] ?? 0;
        if ($until <= time()) {
            unset(self::$briefKnockbackImmuneUntil[$key]);

            return false;
        }

        return true;
    }

    public static function hideFromNear(Player $player, int $durationSeconds): void
    {
        self::$nearHideExpiries[$player->getUniqueId()->toString()] = time() + $durationSeconds;
    }

    public static function isHiddenFromNear(Player $player): bool
    {
        return self::isTimedStateActive(self::$nearHideExpiries, $player);
    }

    public static function activateBlastShield(Player $player, int $durationSeconds): void
    {
        self::$blastShieldExpiries[$player->getUniqueId()->toString()] = time() + $durationSeconds;
    }

    public static function hasBlastShield(Player $player): bool
    {
        return self::isTimedStateActive(self::$blastShieldExpiries, $player);
    }

    public static function activateJackpotSlotGuarantee(Player $player, int $durationSeconds): void
    {
        self::$jackpotSlotGuaranteeExpiries[$player->getUniqueId()->toString()] = time() + $durationSeconds;
    }

    public static function consumeJackpotSlotGuarantee(Player $player): bool
    {
        return self::consumeTimedState(self::$jackpotSlotGuaranteeExpiries, $player);
    }

    public static function hasJackpotSlotGuarantee(Player $player): bool
    {
        return self::isTimedStateActive(self::$jackpotSlotGuaranteeExpiries, $player);
    }

    public static function activateGuaranteedSuccess(Player $player, int $durationSeconds): void
    {
        self::$guaranteedSuccessExpiries[$player->getUniqueId()->toString()] = time() + $durationSeconds;
    }

    public static function consumeGuaranteedSuccess(Player $player): bool
    {
        return self::consumeTimedState(self::$guaranteedSuccessExpiries, $player);
    }

    public static function hasGuaranteedSuccess(Player $player): bool
    {
        return self::isTimedStateActive(self::$guaranteedSuccessExpiries, $player);
    }

    public static function activateSilenceBurst(Player $player, int $durationSeconds): void
    {
        self::$silenceBurstExpiries[$player->getUniqueId()->toString()] = time() + $durationSeconds;
    }

    public static function isSilenceBurstActive(Player $player): bool
    {
        return self::isTimedStateActive(self::$silenceBurstExpiries, $player);
    }

    public static function getSilenceBurstRadius(): float
    {
        return max(1.0, (float) self::getInt("murmurloft", "radius", 3));
    }

    public static function shouldNegateHolyWhite(Player $player): bool
    {
        if (!self::isSilenceBurstActive($player)) {
            return false;
        }

        $chance = max(0, min(100, self::getInt("murmurloft", "holy-white-negate-chance", 50)));
        return $chance > 0 && mt_rand(1, 100) <= $chance;
    }

    public static function activateDefenseBreak(Player $player, int $durationSeconds): void
    {
        self::$defenseBreakExpiries[$player->getUniqueId()->toString()] = time() + $durationSeconds;
    }

    public static function isDefenseBreakActive(Player $player): bool
    {
        return self::isTimedStateActive(self::$defenseBreakExpiries, $player);
    }

    public static function getDefenseBreakDamageMultiplier(Player $player): float
    {
        if (!self::isDefenseBreakActive($player)) {
            return 1.0;
        }

        $bonus = max(0, self::getInt("renderscalewyrm", "damage-bonus-percent", 5));
        return 1.0 + ($bonus / 100);
    }

    public static function activateEnvoyUpgrade(Player $player, int $durationSeconds): void
    {
        self::$envoyUpgradeExpiries[$player->getUniqueId()->toString()] = time() + $durationSeconds;
    }

    public static function isEnvoyUpgradeActive(Player $player): bool
    {
        return self::isTimedStateActive(self::$envoyUpgradeExpiries, $player);
    }

    public static function getEnvoyUpgradeChance(): int
    {
        return max(0, min(100, self::getInt("skycourierdrake", "upgrade-chance", 10)));
    }

    public static function activateEnchanterDiscount(Player $player, int $durationSeconds, int $percent): void
    {
        self::$enchanterDiscountStates[$player->getUniqueId()->toString()] = [
            "expires_at" => time() + $durationSeconds,
            "percent" => max(0, min(100, $percent)),
        ];
    }

    public static function getEnchanterDiscountPercent(Player $player): int
    {
        $key = $player->getUniqueId()->toString();
        $state = self::$enchanterDiscountStates[$key] ?? null;
        if (!is_array($state) || ($state["expires_at"] ?? 0) <= time()) {
            unset(self::$enchanterDiscountStates[$key]);
            return 0;
        }

        return max(0, min(100, (int) ($state["percent"] ?? 0)));
    }

    public static function activateZoneXp(Player $player, int $durationSeconds): void
    {
        self::$zoneXpExpiries[$player->getUniqueId()->toString()] = time() + $durationSeconds;
    }

    public static function getZoneXpMultiplier(Player $player): float
    {
        if (!self::isTimedStateActive(self::$zoneXpExpiries, $player)) {
            return 1.0;
        }

        $multiplier = (float) self::getInt("terracrestjackal", "multiplier-tenths", 25) / 10;
        return max(1.0, $multiplier);
    }

    public static function activateFreeSoulCost(Player $player, int $durationSeconds): void
    {
        self::$freeSoulCostExpiries[$player->getUniqueId()->toString()] = time() + $durationSeconds;
    }

    public static function hasFreeSoulCost(Player $player): bool
    {
        return self::isTimedStateActive(self::$freeSoulCostExpiries, $player);
    }

    public static function activateFreeEnchanter(Player $player, int $durationSeconds): void
    {
        self::activateFreeSoulCost($player, $durationSeconds);
    }

    public static function hasFreeEnchanter(Player $player): bool
    {
        return self::hasFreeSoulCost($player);
    }

    /**
     * @param \pocketmine\item\Item[] $items
     */
    public static function queueTamedPets(Player $player, array $items): void
    {
        if ($items === []) {
            return;
        }

        $key = $player->getUniqueId()->toString();
        self::$pendingTamedPets[$key] = array_merge(self::$pendingTamedPets[$key] ?? [], $items);
    }

    /**
     * @return \pocketmine\item\Item[]
     */
    public static function claimQueuedTamedPets(Player $player): array
    {
        $key = $player->getUniqueId()->toString();
        $items = self::$pendingTamedPets[$key] ?? [];
        unset(self::$pendingTamedPets[$key]);
        return $items;
    }

    private static function isTimedStateActive(array &$store, Player $player): bool
    {
        $key = $player->getUniqueId()->toString();
        $expiry = $store[$key] ?? 0;
        if ($expiry <= time()) {
            unset($store[$key]);
            return false;
        }

        return true;
    }

    private static function consumeTimedState(array &$store, Player $player): bool
    {
        $key = $player->getUniqueId()->toString();
        $expiry = $store[$key] ?? 0;
        if ($expiry <= time()) {
            unset($store[$key]);
            return false;
        }

        unset($store[$key]);
        return true;
    }
}
