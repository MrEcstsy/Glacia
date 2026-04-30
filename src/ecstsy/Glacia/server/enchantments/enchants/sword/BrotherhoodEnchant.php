<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\sword;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\enchantments\manager\ProcLuckManager;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class BrotherhoodEnchant implements EntityDamageByEntityEnchantmentHandler
{
    /** @var array<string, array<string, int>> */
    private static array $forcedProcCharges = [];
    /** @var string[] */
    private const SUPPORTED_MASTERY_WEAPON_ENCHANTS = [
        "disarmor",
        "iceaspect",
        "vampiricdevour",
        "demonicfinisher",
        "hexlock",
        "siegebreaker",
        "thunderfamine",
    ];

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        // Triggered by other mastery enchant procs.
    }

    public static function grantForcedProc(Player $player, string $enchantId, int $charges = 1): void
    {
        $enchantId = strtolower(trim($enchantId));
        if ($charges <= 0 || !in_array($enchantId, self::SUPPORTED_MASTERY_WEAPON_ENCHANTS, true)) {
            return;
        }

        $key = strtolower($player->getName());
        self::$forcedProcCharges[$key][$enchantId] = (self::$forcedProcCharges[$key][$enchantId] ?? 0) + $charges;
    }

    public static function consumeForcedProc(Player $player, string $enchantId): bool
    {
        $enchantId = strtolower(trim($enchantId));
        $key = strtolower($player->getName());
        $charges = self::$forcedProcCharges[$key][$enchantId] ?? 0;
        if ($charges <= 0) {
            return false;
        }

        $charges--;
        if ($charges <= 0) {
            unset(self::$forcedProcCharges[$key][$enchantId]);
            if ((self::$forcedProcCharges[$key] ?? []) === []) {
                unset(self::$forcedProcCharges[$key]);
            }
        } else {
            self::$forcedProcCharges[$key][$enchantId] = $charges;
        }

        return true;
    }

    public static function tryDistributeFromMasteryProc(Player $source, int $level, string $sourceEnchantId): void
    {
        $sourceEnchantId = strtolower(trim($sourceEnchantId));
        if ($level <= 0) {
            return;
        }
        if (!in_array($sourceEnchantId, self::SUPPORTED_MASTERY_WEAPON_ENCHANTS, true)) {
            return;
        }

        $chance = EnchantTuningManager::getLevelInt("brotherhood", "chance-by-level", $level, 5 + ($level * 3));
        if (!ProcLuckManager::roll($source, "brotherhood", $chance, 1, 10)) {
            return;
        }

        $radius = max(1.0, EnchantTuningManager::getFloat("brotherhood", "ally-radius", 3.0));
        $radiusSquared = $radius * $radius;
        $origin = $source->getPosition();

        foreach ($source->getWorld()->getPlayers() as $nearby) {
            if ($nearby->getId() === $source->getId()) {
                continue;
            }

            if ($nearby->getPosition()->distanceSquared($origin) > $radiusSquared) {
                continue;
            }

            if (!GlaciaUtils::isAlly($source, $nearby)) {
                continue;
            }

            $held = $nearby->getInventory()->getItemInHand();
            if (
                !CustomEnchantmentManager::hasEnchantment($held, "brotherhood") ||
                !CustomEnchantmentManager::hasEnchantment($held, $sourceEnchantId)
            ) {
                continue;
            }

            self::grantForcedProc($nearby, $sourceEnchantId, 1);
        }
    }
}
