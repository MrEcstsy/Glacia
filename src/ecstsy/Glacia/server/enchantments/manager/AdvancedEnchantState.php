<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\manager;

use pocketmine\player\Player;
use pocketmine\world\Position;

/**
 * Centralised transient state for the Advanced / “new set” enchants.
 */
final class AdvancedEnchantState
{
    /** @var array<string, int> uuid -> expiry unix */
    private static array $glimmerSuppressUntil = [];
    /** @var array<string, int> */
    private static array $malicewardUntil = [];
    /** @var array<string, int> */
    private static array $coupAttackerSilenceUntil = [];
    /** @var array<string, int> */
    private static array $coupInvulnUntil = [];
    /** @var array<string, int> */
    private static array $muzzleUntil = [];
    /** @var array<string, int> str -> str slot index string -> expiry */
    private static array $fetterLockedSlots = [];
    /** @var array<string, int> */
    private static array $forewarnedHalveNext = [];
    /** @var array<string, int> */
    private static array $netherPigmen = [];
    /** @var array<string, int> */
    private static array $primalPactUntil = [];
    /** @var array<string, int> uuid -> free pet bypass charges left in window */
    private static array $primalPactFreePetByUuid = [];
    /** @var array<string, int> */
    private static array $runeswellSoulDiscountUntil = [];
    /** @var array<string, int> v→a (last hit) */
    private static array $trifoldCounter = [];
    /** @var array<string, int> */
    private static array $inventoryLockUntil = [];
    /** @var array<string, int> */
    private static array $nametagBlindUntil = [];
    /** @var array<string, int> */
    private static array $divineFireballUntil = [];
    /** @var array<string, int> */
    private static array $triggerhappyUntil = [];
    /** @var array<string, int> */
    private static array $anatomyUntil = [];
    /** @var array<string, int> */
    private static array $soulCrusherHitsRemaining = [];
    /** @var array<string, int> */
    private static array $soulCrusherCurrentHit = [];
    /** @var array<string, array{set: string, until: int, multiplier: float}> */
    private static array $natureParadoxBoost = [];
    // --- Glimmerfade (attacker cannot proc weapon CEs) ---
    public static function setGlimmerSuppressed(Player $attacker, int $seconds, int $level): void
    {
        $uuid = $attacker->getUniqueId()->toString();
        self::$glimmerSuppressUntil[$uuid] = time() + max(1, $seconds * $level);
    }

    public static function isGlimmerSuppressed(Player $attacker): bool
    {
        $uuid = $attacker->getUniqueId()->toString();
        $t = self::$glimmerSuppressUntil[$uuid] ?? 0;
        if ($t <= time()) {
            unset(self::$glimmerSuppressUntil[$uuid]);
            return false;
        }
        return true;
    }

    // --- Maliceward ---
    public static function setMalicewardImmune(Player $p, int $secondsPerLevel, int $level): void
    {
        $uuid = $p->getUniqueId()->toString();
        self::$malicewardUntil[$uuid] = time() + max(1, $secondsPerLevel * $level);
    }

    public static function isMalicewardImmune(Player $p): bool
    {
        $uuid = $p->getUniqueId()->toString();
        $t = self::$malicewardUntil[$uuid] ?? 0;
        if ($t <= time()) {
            unset(self::$malicewardUntil[$uuid]);
            return false;
        }
        return true;
    }

    // --- Coup de Grâce ---
    public static function setCoupAttackerSilence(Player $attacker, int $seconds): void
    {
        self::$coupAttackerSilenceUntil[$attacker->getUniqueId()->toString()] = time() + max(1, $seconds);
    }

    public static function isCoupAttackerSilenced(Player $attacker): bool
    {
        $t = self::$coupAttackerSilenceUntil[$attacker->getUniqueId()->toString()] ?? 0;
        if ($t <= time()) {
            unset(self::$coupAttackerSilenceUntil[$attacker->getUniqueId()->toString()]);
            return false;
        }
        return true;
    }

    public static function setCoupInvuln(Player $victim, int $seconds): void
    {
        self::$coupInvulnUntil[$victim->getUniqueId()->toString()] = time() + max(1, $seconds);
    }

    public static function isCoupInvuln(Player $p): bool
    {
        $t = self::$coupInvulnUntil[$p->getUniqueId()->toString()] ?? 0;
        if ($t <= time()) {
            unset(self::$coupInvulnUntil[$p->getUniqueId()->toString()]);
            return false;
        }
        return true;
    }

    // --- Iron muzzle ---
    public static function muzzle(Player $p, int $seconds): void
    {
        self::$muzzleUntil[$p->getUniqueId()->toString()] = time() + max(1, $seconds);
    }

    public static function isMuzzled(Player $p): bool
    {
        $t = self::$muzzleUntil[$p->getUniqueId()->toString()] ?? 0;
        if ($t <= time()) {
            unset(self::$muzzleUntil[$p->getUniqueId()->toString()]);
            return false;
        }
        return true;
    }

    // --- Fetter: block re-equip into a slot (victim, armor slot) ---
    public static function lockFetterSlot(Player $victim, int $armorSlot, int $seconds): void
    {
        $k = $victim->getUniqueId()->toString() . ":" . $armorSlot;
        self::$fetterLockedSlots[$k] = time() + max(1, $seconds);
    }

    public static function isFetterSlotLocked(Player $victim, int $armorSlot): bool
    {
        $k = $victim->getUniqueId()->toString() . ":" . $armorSlot;
        $t = self::$fetterLockedSlots[$k] ?? 0;
        if ($t <= time()) {
            unset(self::$fetterLockedSlots[$k]);
            return false;
        }
        return true;
    }

    // --- Forewarned (next single hit 50% on victim) ---
    public static function setForewarnedNextHitHalved(Player $victim): void
    {
        self::$forewarnedHalveNext[$victim->getUniqueId()->toString()] = 1;
    }

    public static function consumeForewarnedHalve(Player $victim): bool
    {
        $u = $victim->getUniqueId()->toString();
        if (($self = self::$forewarnedHalveNext[$u] ?? 0) !== 1) {
            return false;
        }
        unset(self::$forewarnedHalveNext[$u]);
        return true;
    }

    // --- Nether Warden: pigman entity id + position for pearl check ---
    public static function addNetherPigman(int $eid, Position $at): void
    {
        self::$netherPigmen[(string) $eid] = 1; // set marker; we scan entities by id in isNear
    }

    public static function removeNetherPigman(int $eid): void
    {
        unset(self::$netherPigmen[(string) $eid]);
    }

    /** @return array<string, int> */
    public static function getActiveNetherPigmanIds(): array
    {
        return self::$netherPigmen;
    }

    // --- Primal Pact ---
    public static function setPrimalPactWindow(Player $p, int $seconds, int $level): void
    {
        $u = $p->getUniqueId()->toString();
        self::$primalPactUntil[$u] = time() + max(1, $seconds);
        // The pet use itself is the first activation; Primal Pact should only grant one extra free follow-up.
        self::$primalPactFreePetByUuid[$u] = 1;
    }

    public static function isPrimalPactWindow(Player $p): bool
    {
        $u = $p->getUniqueId()->toString();
        $t = self::$primalPactUntil[$u] ?? 0;
        if ($t <= time()) {
            unset(self::$primalPactUntil[$u], self::$primalPactFreePetByUuid[$u]);
            return false;
        }
        return true;
    }

    public static function consumePrimalPactFreePet(Player $p): bool
    {
        $u = $p->getUniqueId()->toString();
        if (!self::isPrimalPactWindow($p)) {
            return false;
        }
        if ((self::$primalPactFreePetByUuid[$u] ?? 0) <= 0) {
            return false;
        }
        self::$primalPactFreePetByUuid[$u]--;
        return true;
    }

    // --- Runeswell soul discount (placeholder hook for future soul economy) ---
    public static function setRuneswellSoulDiscount(Player $p, int $seconds): void
    {
        self::$runeswellSoulDiscountUntil[$p->getUniqueId()->toString()] = time() + max(1, $seconds);
    }

    public static function isRuneswellSoulDiscountActive(Player $p): bool
    {
        $t = self::$runeswellSoulDiscountUntil[$p->getUniqueId()->toString()] ?? 0;
        if ($t <= time()) {
            unset(self::$runeswellSoulDiscountUntil[$p->getUniqueId()->toString()]);
            return false;
        }
        return true;
    }

    // --- Soul Crusher inventory/hotbar lock ---
    public static function lockInventory(Player $p, int $seconds): void
    {
        self::$inventoryLockUntil[$p->getUniqueId()->toString()] = time() + max(1, $seconds);
    }

    public static function isInventoryLocked(Player $p): bool
    {
        $t = self::$inventoryLockUntil[$p->getUniqueId()->toString()] ?? 0;
        if ($t <= time()) {
            unset(self::$inventoryLockUntil[$p->getUniqueId()->toString()]);
            return false;
        }
        return true;
    }

    // --- Divine Immorality nametag blindness ---
    public static function blindNametags(Player $viewer, int $seconds): void
    {
        self::$nametagBlindUntil[$viewer->getUniqueId()->toString()] = time() + max(1, $seconds);
    }

    public static function isNametagBlind(Player $viewer): bool
    {
        $t = self::$nametagBlindUntil[$viewer->getUniqueId()->toString()] ?? 0;
        if ($t <= time()) {
            unset(self::$nametagBlindUntil[$viewer->getUniqueId()->toString()]);
            return false;
        }
        return true;
    }

    // --- Divine Immorality fireball window ---
    public static function setDivineFireball(Player $p, int $seconds): void
    {
        self::$divineFireballUntil[$p->getUniqueId()->toString()] = time() + max(1, $seconds);
    }

    public static function hasDivineFireball(Player $p): bool
    {
        $t = self::$divineFireballUntil[$p->getUniqueId()->toString()] ?? 0;
        if ($t <= time()) {
            unset(self::$divineFireballUntil[$p->getUniqueId()->toString()]);
            return false;
        }
        return true;
    }

    // --- Triggerhappy active window ---
    public static function setTriggerhappy(Player $p, int $seconds): void
    {
        self::$triggerhappyUntil[$p->getUniqueId()->toString()] = time() + max(1, $seconds);
    }

    public static function isTriggerhappyActive(Player $p): bool
    {
        $t = self::$triggerhappyUntil[$p->getUniqueId()->toString()] ?? 0;
        if ($t <= time()) {
            unset(self::$triggerhappyUntil[$p->getUniqueId()->toString()]);
            return false;
        }
        return true;
    }

    public static function setAnatomyBlocked(Player $p, int $seconds): void
    {
        self::$anatomyUntil[$p->getUniqueId()->toString()] = time() + max(1, $seconds);
    }

    public static function isAnatomyBlocked(Player $p): bool
    {
        $key = $p->getUniqueId()->toString();
        $t = self::$anatomyUntil[$key] ?? 0;
        if ($t <= time()) {
            unset(self::$anatomyUntil[$key]);
            return false;
        }
        return true;
    }

    // --- Soul Crusher forced rage hits ---
    public static function setSoulCrusherHits(Player $p, int $hits): void
    {
        self::$soulCrusherHitsRemaining[$p->getUniqueId()->toString()] = max(0, $hits);
    }

    public static function hasSoulCrusherHits(Player $p): bool
    {
        return (self::$soulCrusherHitsRemaining[$p->getUniqueId()->toString()] ?? 0) > 0;
    }

    public static function consumeSoulCrusherHit(Player $p): bool
    {
        $key = $p->getUniqueId()->toString();
        $remaining = self::$soulCrusherHitsRemaining[$key] ?? 0;
        if ($remaining <= 0) {
            unset(self::$soulCrusherHitsRemaining[$key]);
            return false;
        }

        $remaining--;
        if ($remaining <= 0) {
            unset(self::$soulCrusherHitsRemaining[$key]);
        } else {
            self::$soulCrusherHitsRemaining[$key] = $remaining;
        }

        return true;
    }

    public static function markSoulCrusherCurrentHit(Player $p): void
    {
        self::$soulCrusherCurrentHit[$p->getUniqueId()->toString()] = 1;
    }

    public static function consumeSoulCrusherCurrentHit(Player $p): bool
    {
        $key = $p->getUniqueId()->toString();
        if ((self::$soulCrusherCurrentHit[$key] ?? 0) !== 1) {
            return false;
        }
        unset(self::$soulCrusherCurrentHit[$key]);
        return true;
    }

    // --- Nature's Paradox set amplification ---
    public static function setNatureParadoxBoost(Player $p, string $setId, int $seconds, float $multiplier = 2.0): void
    {
        self::$natureParadoxBoost[$p->getUniqueId()->toString()] = [
            "set" => strtolower($setId),
            "until" => time() + max(1, $seconds),
            "multiplier" => max(1.0, $multiplier),
        ];
    }

    public static function getNatureParadoxBoostMultiplier(Player $p, string $setId): float
    {
        $key = $p->getUniqueId()->toString();
        $entry = self::$natureParadoxBoost[$key] ?? null;
        if ($entry === null) {
            return 1.0;
        }
        if (($entry["until"] ?? 0) <= time()) {
            unset(self::$natureParadoxBoost[$key]);
            return 1.0;
        }
        if (($entry["set"] ?? "") !== strtolower($setId)) {
            return 1.0;
        }

        return max(1.0, (float) ($entry["multiplier"] ?? 1.0));
    }

    // --- Trifold Rend (3rd hit) ---
    public static function trifoldKey(Player $damager, Player $victim): string
    {
        return $damager->getUniqueId()->toString() . ">" . $victim->getUniqueId()->toString();
    }

    public static function bumpTrifoldAndIsThird(Player $damager, Player $victim): bool
    {
        $k = self::trifoldKey($damager, $victim);
        $c = (self::$trifoldCounter[$k] ?? 0) + 1;
        if ($c >= 3) {
            self::$trifoldCounter[$k] = 0;
            return true;
        }
        self::$trifoldCounter[$k] = $c;
        return false;
    }

    public static function clearPlayer(Player $p): void
    {
        $u = $p->getUniqueId()->toString();
        unset(
            self::$glimmerSuppressUntil[$u],
            self::$malicewardUntil[$u],
            self::$coupAttackerSilenceUntil[$u],
            self::$coupInvulnUntil[$u],
            self::$muzzleUntil[$u],
            self::$forewarnedHalveNext[$u],
            self::$primalPactUntil[$u],
            self::$primalPactFreePetByUuid[$u],
            self::$runeswellSoulDiscountUntil[$u],
            self::$inventoryLockUntil[$u],
            self::$nametagBlindUntil[$u],
            self::$divineFireballUntil[$u],
            self::$triggerhappyUntil[$u],
            self::$soulCrusherHitsRemaining[$u],
            self::$soulCrusherCurrentHit[$u],
            self::$natureParadoxBoost[$u],
        );
    }
}
