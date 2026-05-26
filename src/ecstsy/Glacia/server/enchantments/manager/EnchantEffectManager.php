<?php 

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\manager;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\essence\EnchantEssenceManager;
use ecstsy\Glacia\server\items\runes\RuneManager;
use pocketmine\entity\effect\Effect;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\StringToEffectParser;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\Inventory;
use pocketmine\inventory\PlayerInventory;
use pocketmine\item\Item;
use pocketmine\player\Player;

final class EnchantEffectManager {
    private const ARMOR_EFFECT_DURATION = 2147483647;
    private const MANAGED_EFFECT_DURATION_THRESHOLD = 1000000;


    /** @var array<string, array<string, true>> */
    private static array $suppressedArmorDebuffs = [];

    /** @var array<string, array<string, array<string, true>>> */
    private static array $externalSuppressedArmorDebuffs = [];

    /** @var array<string, true> */
    private static array $refreshingArmorEffects = [];

    /** @var array<string, true> */
    private static array $pendingArmorRefreshes = [];

    /** @var array<string, string> */
    private static array $armorEffectSignatures = [];

    private const ARMOR_ONLY_ENCHANTS = [
        'glowing',
        'aquatic',
        'gears',
        'bounce',
        'lavabound',
        'overload',
        'drunk'
    ];

    public const EFFECT_ENCHANTS = [
        "haste" => [["haste", 3]],
        "aquatic" => [["water_breathing", 1]],
        "gears" => [["speed", 3]],
        "glowing" => [["night_vision", 1]],
        "bounce" => [["jump_boost", 3]],
        "lavabound" => [["fire_resistance", 1]],
        "overload" => [["health_boost", 3]],
        "drunk" => [
            ["strength", 4],
            ["slowness", 4],
            ["mining_fatigue", 4]
        ]
    ];

    public static function onArmorSlotChange(ArmorInventory $inventory, int $slot, Item $oldItem): void {
        $player = $inventory->getHolder();
        if (!$player instanceof Player) return;

        $newItem = $inventory->getItem($slot);
        if ($newItem->equals($oldItem, false)) return;
        self::clearSuppressedDebuffsForArmorChange($player, $oldItem, $newItem);
        self::scheduleArmorRefresh($player);
    }

    /**
     * @param list<string> $effectIds
     */
    public static function suppressArmorDebuffs(Player $player, array $effectIds): void
    {
        $key = $player->getUniqueId()->toString();

        foreach ($effectIds as $effectId) {
            $normalized = self::normalizeEffectId($effectId);
            if ($normalized === null) {
                continue;
            }

            self::$suppressedArmorDebuffs[$key][$normalized] = true;
        }
    }

    public static function clearSuppressedArmorDebuffs(Player $player): void
    {
        unset(self::$suppressedArmorDebuffs[$player->getUniqueId()->toString()]);
    }

    /**
     * @param list<string> $effectIds
     */
    public static function setExternalArmorDebuffSuppression(Player $player, string $source, array $effectIds): void
    {
        $source = strtolower(trim($source));
        if ($source === "") {
            return;
        }

        $normalizedEffects = [];
        foreach ($effectIds as $effectId) {
            $normalized = self::normalizeEffectId($effectId);
            if ($normalized !== null) {
                $normalizedEffects[$normalized] = true;
            }
        }

        $key = $player->getUniqueId()->toString();
        $current = self::$externalSuppressedArmorDebuffs[$key][$source] ?? [];
        if ($current === $normalizedEffects) {
            return;
        }

        if ($normalizedEffects === []) {
            unset(self::$externalSuppressedArmorDebuffs[$key][$source]);
            if ((self::$externalSuppressedArmorDebuffs[$key] ?? []) === []) {
                unset(self::$externalSuppressedArmorDebuffs[$key]);
            }
        } else {
            self::$externalSuppressedArmorDebuffs[$key][$source] = $normalizedEffects;
        }

        self::scheduleArmorRefresh($player);
    }

    public static function clearExternalArmorDebuffSuppression(Player $player, string $source): void
    {
        $source = strtolower(trim($source));
        if ($source === "") {
            return;
        }

        $key = $player->getUniqueId()->toString();
        if (!isset(self::$externalSuppressedArmorDebuffs[$key][$source])) {
            return;
        }

        unset(self::$externalSuppressedArmorDebuffs[$key][$source]);
        if (self::$externalSuppressedArmorDebuffs[$key] === []) {
            unset(self::$externalSuppressedArmorDebuffs[$key]);
        }

        self::scheduleArmorRefresh($player);
    }

    /**
     * @return list<string>
     */
    public static function getActiveArmorDebuffIds(Player $player): array
    {
        $activeArmorEnchants = [];
        foreach ($player->getArmorInventory()->getContents() as $item) {
            foreach (CustomEnchantmentManager::getEnchantments($item) as $enchant => $level) {
                $enchant = strtolower((string) $enchant);
                if (isset(ArmorEffectState::mappings()[$enchant])) {
                    $activeArmorEnchants[$enchant] = max($activeArmorEnchants[$enchant] ?? 0, (int) $level);
                }
            }
            foreach (RuneManager::getArmorEffectRuneEnchants($item) as $enchant => $level) {
                if (isset(ArmorEffectState::mappings()[$enchant])) {
                    $activeArmorEnchants[$enchant] = max($activeArmorEnchants[$enchant] ?? 0, (int) $level);
                }
            }
        }

        $effectIds = [];
        foreach (ArmorEffectState::mappings() as $enchant => $effects) {
            if (($activeArmorEnchants[$enchant] ?? 0) <= 0) {
                continue;
            }

            foreach ($effects as [$effectId, $_]) {
                $effect = self::resolveEffect($effectId);
                if ($effect !== null && $effect->isBad()) {
                    $effectIds[] = $effectId;
                }
            }
        }

        return array_values(array_unique(array_filter(array_map(
            static fn(string $effectId): ?string => self::normalizeEffectId($effectId),
            $effectIds
        ))));
    }

    public static function onInventorySlotChange(Inventory $inventory, int $slot, Item $oldItem): void {
        if (!$inventory instanceof PlayerInventory && !$inventory instanceof ArmorInventory) {
            return;
        }

        $player = $inventory->getHolder();
        if (!$player instanceof Player) return;

        $heldSlot = $player->getInventory()->getHeldItemIndex();
        $newItem = $inventory->getItem($slot);

        if ($inventory instanceof PlayerInventory && $slot === $heldSlot) {

            foreach (self::EFFECT_ENCHANTS as $enchant => $effects) {

                if (in_array($enchant, self::ARMOR_ONLY_ENCHANTS, true)) continue;

                if (!$oldItem->isNull() && CustomEnchantmentManager::hasEnchantment($oldItem, $enchant)) {
                    foreach ($effects as [$effectId, $maxLevel]) {
                        $effect = self::resolveEffect($effectId);
                        if ($effect !== null) {
                            $current = $player->getEffects()->get($effect);
                            if ($current !== null && $current->getAmplifier() < $maxLevel) {
                                if (!self::shouldPreserveManagedEffect($player, $effect)) {
                                    $player->getEffects()->remove($effect);
                                }
                            }
                        }
                    }
                }

                if (!$newItem->isNull() && CustomEnchantmentManager::hasEnchantment($newItem, $enchant)) {
                    $level = CustomEnchantmentManager::getLevel($newItem, $enchant);

                    foreach ($effects as [$effectId, $maxLevel]) {
                        $effect = self::resolveEffect($effectId);
                        if ($effect === null) continue;

                        $current = $player->getEffects()->get($effect);
                        if ($current === null || $current->getAmplifier() + 1 < $level) {
                            $player->getEffects()->add(new EffectInstance(
                                $effect,
                                self::ARMOR_EFFECT_DURATION,
                                $level - 1,
                                false
                            ));
                        }
                    }
                }
            }
        }
    }

    public static function updateHeldItemEffects(Player $player, ?Item $oldItem, ?Item $newItem): void {
        foreach (self::EFFECT_ENCHANTS as $enchant => $effects) {

            if (in_array($enchant, self::ARMOR_ONLY_ENCHANTS, true)) continue;

            if ($oldItem !== null && CustomEnchantmentManager::hasEnchantment($oldItem, $enchant)) {
                foreach ($effects as [$effectId, $maxLevel]) {
                    $effect = self::resolveEffect($effectId);

                    if ($effect !== null && ($current = $player->getEffects()->get($effect))) {
                        if ($current->getAmplifier() < $maxLevel) {
                            if (!self::shouldPreserveManagedEffect($player, $effect)) {
                                $player->getEffects()->remove($effect);
                            }
                        }
                    }
                }
            }

            if ($newItem !== null && CustomEnchantmentManager::hasEnchantment($newItem, $enchant)) {
                $level = CustomEnchantmentManager::getLevel($newItem, $enchant);

                foreach ($effects as [$effectId, $maxLevel]) {
                    $effect = self::resolveEffect($effectId);
                    if ($effect === null) continue;

                    $current = $player->getEffects()->get($effect);
                    if ($current === null || $current->getAmplifier() + 1 < $level) {
                        $player->getEffects()->add(new EffectInstance(
                            $effect,
                            self::ARMOR_EFFECT_DURATION,
                            $level - 1,
                            false
                        ));
                    }
                }
            }
        }
    }

    public static function refreshArmorEffects(Player $player): void
    {
        $key = $player->getUniqueId()->toString();
        if (isset(self::$refreshingArmorEffects[$key])) {
            return;
        }

        self::$refreshingArmorEffects[$key] = true;

        $armorInventory = $player->getArmorInventory();
        $activeArmorEnchants = [];

        try {
            $activeArmorEnchants = self::collectActiveArmorEnchants($player);
            self::$armorEffectSignatures[$key] = self::buildArmorEffectSignature($activeArmorEnchants);

            foreach (ArmorEffectState::mappings() as $enchant => $effects) {
                $activeLevel = $activeArmorEnchants[$enchant] ?? 0;

                foreach ($effects as [$effectId, $amplifierResolver]) {
                    $effect = self::resolveEffect($effectId);
                    if ($effect === null) {
                        continue;
                    }

                    $current = $player->getEffects()->get($effect);
                    if ($activeLevel <= 0) {
                        if ($current !== null && self::isManagedArmorEffect($current) && !self::shouldPreserveManagedEffect($player, $effect)) {
                            $player->getEffects()->remove($effect);
                        }
                        continue;
                    }

                    $amplifier = $amplifierResolver($activeLevel);
                    if ($enchant === "bounce") {
                        $amplifier = self::applyBounceEssenceJumpAmplifier($player, $amplifier);
                    }
                    if ($effect->isBad() && self::isArmorDebuffSuppressed($player, $effectId)) {
                        if ($current !== null && self::isManagedArmorEffect($current) && !self::shouldPreserveManagedEffect($player, $effect)) {
                            $player->getEffects()->remove($effect);
                        }
                        continue;
                    }

                    if (
                        $current === null ||
                        $current->getAmplifier() < $amplifier ||
                        (self::isManagedArmorEffect($current) && $current->getAmplifier() !== $amplifier)
                    ) {
                        $player->getEffects()->add(new EffectInstance(
                            $effect,
                            self::ARMOR_EFFECT_DURATION,
                            $amplifier,
                            self::shouldArmorEffectBeVisible($effectId)
                        ));
                    }
                }
            }
        } finally {
            unset(self::$refreshingArmorEffects[$key]);
        }
    }

    public static function syncArmorEffects(Player $player): void
    {
        $key = $player->getUniqueId()->toString();
        $activeArmorEnchants = self::collectActiveArmorEnchants($player);
        $signature = self::buildArmorEffectSignature($activeArmorEnchants);

        if ((self::$armorEffectSignatures[$key] ?? null) !== $signature || self::hasArmorEffectMismatch($player, $activeArmorEnchants)) {
            self::scheduleArmorRefresh($player);
        }
    }

    public static function isRefreshingArmorEffects(Player $player): bool
    {
        return isset(self::$refreshingArmorEffects[$player->getUniqueId()->toString()]);
    }

    private static function applyBounceEssenceJumpAmplifier(Player $player, int $baseAmplifier): int
    {
        $best = $baseAmplifier;
        foreach ($player->getArmorInventory()->getContents() as $piece) {
            if ($piece->isNull()) {
                continue;
            }
            if (!CustomEnchantmentManager::hasEnchantment($piece, "bounce")) {
                continue;
            }
            $essenceAmp = (int) round(EnchantEssenceManager::resolveGearEssenceFloat(
                $piece,
                "bounce",
                "jump-boost-amplifier-with-essence",
                0.0
            ));
            if ($essenceAmp <= 0) {
                continue;
            }
            $best = max($best, $essenceAmp);
        }

        // Jump Boost IV = amplifier 3; cap so Bounce + essence never reaches V.
        return max(0, min(3, min(10, $best)));
    }

    private static function isManagedArmorEffect(EffectInstance $effect): bool
    {
        return $effect->getDuration() > self::MANAGED_EFFECT_DURATION_THRESHOLD;
    }

    private static function isArmorDebuffSuppressed(Player $player, string $effectId): bool
    {
        $normalized = self::normalizeEffectId($effectId);
        if ($normalized === null) {
            return false;
        }

        $key = $player->getUniqueId()->toString();
        if (isset(self::$suppressedArmorDebuffs[$key][$normalized])) {
            return true;
        }

        foreach (self::$externalSuppressedArmorDebuffs[$key] ?? [] as $effects) {
            if (isset($effects[$normalized])) {
                return true;
            }
        }

        return false;
    }

    public static function normalizeEffectId(string $effectId): ?string
    {
        $normalized = strtolower(str_replace([" ", "-"], "_", trim($effectId)));
        return $normalized !== "" ? $normalized : null;
    }

    private static function clearSuppressedDebuffsForArmorChange(Player $player, Item $oldItem, Item $newItem): void
    {
        $key = $player->getUniqueId()->toString();
        if (!isset(self::$suppressedArmorDebuffs[$key])) {
            return;
        }

        // Durability updates while getting hit should not clear bless suppression.
        // Only clear if relevant armor-effect enchant composition actually changed.
        if (self::getArmorEffectEnchantSignature($oldItem) === self::getArmorEffectEnchantSignature($newItem)) {
            return;
        }

        $changedEffectIds = self::getArmorDebuffIdsForItem($newItem);

        if ($changedEffectIds === []) {
            return;
        }

        foreach ($changedEffectIds as $effectId) {
            unset(self::$suppressedArmorDebuffs[$key][$effectId]);
        }

        if (self::$suppressedArmorDebuffs[$key] === []) {
            unset(self::$suppressedArmorDebuffs[$key]);
        }
    }

    /**
     * @return array<string, int>
     */
    private static function getArmorEffectEnchantSignature(Item $item): array
    {
        $signature = [];
        foreach (CustomEnchantmentManager::getEnchantments($item) as $enchant => $level) {
            $enchant = strtolower((string) $enchant);
            if (!isset(ArmorEffectState::mappings()[$enchant])) {
                continue;
            }

            $signature[$enchant] = (int) $level;
        }

        foreach (RuneManager::getArmorEffectRuneEnchants($item) as $enchant => $level) {
            if (!isset(ArmorEffectState::mappings()[$enchant])) {
                continue;
            }

            $signature[$enchant] = max($signature[$enchant] ?? 0, (int) $level);
        }

        ksort($signature);
        return $signature;
    }

    /**
     * @return list<string>
     */
    private static function getArmorDebuffIdsForItem(Item $item): array
    {
        $effectIds = [];

        foreach (CustomEnchantmentManager::getEnchantments($item) as $enchant => $level) {
            $enchant = strtolower((string) $enchant);
            if (($level ?? 0) <= 0 || !isset(ArmorEffectState::mappings()[$enchant])) {
                continue;
            }

            foreach (ArmorEffectState::mappings()[$enchant] as [$effectId, $_]) {
                $effect = self::resolveEffect($effectId);
                if ($effect !== null && $effect->isBad()) {
                    $normalized = self::normalizeEffectId($effectId);
                    if ($normalized !== null) {
                        $effectIds[] = $normalized;
                    }
                }
            }
        }

        return array_values(array_unique($effectIds));
    }

    /**
     * @return array<string, int>
     */
    private static function collectActiveArmorEnchants(Player $player): array
    {
        $activeArmorEnchants = [];
        foreach ($player->getArmorInventory()->getContents() as $item) {
            foreach (CustomEnchantmentManager::getEnchantments($item) as $enchant => $level) {
                $enchant = strtolower((string) $enchant);
                if (isset(ArmorEffectState::mappings()[$enchant])) {
                    $activeArmorEnchants[$enchant] = max($activeArmorEnchants[$enchant] ?? 0, (int) $level);
                }
            }

            foreach (RuneManager::getArmorEffectRuneEnchants($item) as $enchant => $level) {
                if (isset(ArmorEffectState::mappings()[$enchant])) {
                    $activeArmorEnchants[$enchant] = max($activeArmorEnchants[$enchant] ?? 0, (int) $level);
                }
            }
        }

        ksort($activeArmorEnchants);
        return $activeArmorEnchants;
    }

    /**
     * @param array<string, int> $activeArmorEnchants
     */
    private static function buildArmorEffectSignature(array $activeArmorEnchants): string
    {
        $encoded = json_encode($activeArmorEnchants, JSON_THROW_ON_ERROR);
        return is_string($encoded) ? $encoded : "";
    }

    /**
     * @param array<string, int> $activeArmorEnchants
     */
    private static function hasArmorEffectMismatch(Player $player, array $activeArmorEnchants): bool
    {
        foreach (ArmorEffectState::mappings() as $enchant => $effects) {
            $activeLevel = $activeArmorEnchants[$enchant] ?? 0;

            foreach ($effects as [$effectId, $amplifierResolver]) {
                $effect = self::resolveEffect($effectId);
                if ($effect === null) {
                    continue;
                }

                $current = $player->getEffects()->get($effect);
                if ($activeLevel <= 0) {
                    if ($current !== null && self::isManagedArmorEffect($current) && !self::shouldPreserveManagedEffect($player, $effect)) {
                        return true;
                    }
                    continue;
                }

                if ($effect->isBad() && self::isArmorDebuffSuppressed($player, $effectId)) {
                    if ($current !== null && self::isManagedArmorEffect($current) && !self::shouldPreserveManagedEffect($player, $effect)) {
                        return true;
                    }
                    continue;
                }

                $amplifier = $amplifierResolver($activeLevel);
                if (
                    $current === null ||
                    $current->getAmplifier() !== $amplifier ||
                    !self::isManagedArmorEffect($current)
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function resolveEffect(string $effectId): ?Effect
    {
        return match (self::normalizeEffectId($effectId)) {
            "speed" => VanillaEffects::SPEED(),
            "jump_boost" => VanillaEffects::JUMP_BOOST(),
            "water_breathing" => VanillaEffects::WATER_BREATHING(),
            "night_vision" => VanillaEffects::NIGHT_VISION(),
            "fire_resistance" => VanillaEffects::FIRE_RESISTANCE(),
            "health_boost" => VanillaEffects::HEALTH_BOOST(),
            "strength" => VanillaEffects::STRENGTH(),
            "slowness" => VanillaEffects::SLOWNESS(),
            "mining_fatigue" => VanillaEffects::MINING_FATIGUE(),
            default => StringToEffectParser::getInstance()->parse($effectId),
        };
    }

    private static function shouldArmorEffectBeVisible(string $effectId): bool
    {
        return match (self::normalizeEffectId($effectId)) {
            "speed", "jump_boost" => true,
            default => false,
        };
    }

    private static function shouldPreserveManagedEffect(Player $player, Effect $effect): bool
    {
        return $effect === VanillaEffects::NIGHT_VISION() && self::hasPersistentNightVisionEnabled($player);
    }

    private static function hasPersistentNightVisionEnabled(Player $player): bool
    {
        $session = Loader::getPlayerManager()->getSession($player);
        return $session !== null && (bool) $session->getSetting("night_vision");
    }

    // NEEDED?
    public static function onArmorContentsChange(ArmorInventory $inventory, array $oldContents): void {
        foreach ($oldContents as $slot => $oldItem) {
            $newItem = $inventory->getItem($slot);
            if (!$newItem->equals($oldItem, false)) {
                self::onArmorSlotChange($inventory, $slot, $oldItem);
            }
        }
    }

    public static function scheduleArmorRefresh(Player $player, int $delayTicks = 1): void
    {
        $key = $player->getUniqueId()->toString();
        if (isset(self::$pendingArmorRefreshes[$key])) {
            return;
        }

        self::$pendingArmorRefreshes[$key] = true;
        Loader::getInstance()->getScheduler()->scheduleDelayedTask(new \pocketmine\scheduler\ClosureTask(
            static function() use ($player, $key): void {
                unset(self::$pendingArmorRefreshes[$key]);

                if (!$player->isConnected()) {
                    return;
                }

                self::refreshArmorEffects($player);
            }
        ), max(1, $delayTicks));
    }

    public static function clearPlayerState(Player $player): void
    {
        $key = $player->getUniqueId()->toString();
        unset(
            self::$suppressedArmorDebuffs[$key],
            self::$externalSuppressedArmorDebuffs[$key],
            self::$refreshingArmorEffects[$key],
            self::$pendingArmorRefreshes[$key],
            self::$armorEffectSignatures[$key]
        );
    }
}
