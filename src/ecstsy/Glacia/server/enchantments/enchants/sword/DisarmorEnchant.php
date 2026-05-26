<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\sword;

use ecstsy\Glacia\entity\gkits\ForsakenChampion;
use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\enchants\chestplate\KerbusEnchant;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\AdvancedEnchantState;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\enchantments\manager\MirrorbrandProcManager;
use ecstsy\Glacia\server\items\rings\RingManager;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class DisarmorEnchant implements EntityDamageByEntityEnchantmentHandler
{
    /** @var array<string, float> */
    private static array $ringNoticeCooldowns = [];

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) {
            return;
        }

        $damager = $event->getDamager();
        $victim = $event->getEntity();
        if (!$damager instanceof Living || !$victim instanceof Player) {
            return;
        }

        $item = $damager instanceof Player ? $damager->getInventory()->getItemInHand() : ($damager instanceof ForsakenChampion ? $damager->getCombatItem() : null);
        if ($item === null || !CustomEnchantmentManager::hasEnchantment($item, "disarmor")) {
            return;
        }

        $forcedProc = $damager instanceof Player && BrotherhoodEnchant::consumeForcedProc($damager, "disarmor");
        $session = $damager instanceof Player ? PlayerManager::getInstance()->getSession($damager) : null;
        $cooldownSeconds = EnchantTuningManager::cooldownSeconds("disarmor", $level, 12);

        $result = null;
        if (!$forcedProc) {
            $result = EnchantProcManager::attempt(
                $damager,
                "disarmor",
                $level,
                EnchantTuningManager::chance("disarmor", $level, 4.0),
                $cooldownSeconds,
                static fn() => max(0, (int) ($session?->getCooldown("disarmor") ?? 0))
            );
            if (!$result->success) {
                return;
            }
        }

        if (!self::forceDisarmor($damager instanceof Player ? $damager : null, $victim)) {
            return;
        }

        if ($damager instanceof Player) {
            MirrorbrandProcManager::record($damager, "disarmor", static function (Player $reflector, Player $attacker): bool {
                return self::forceDisarmor($reflector, $attacker);
            });
            $damager->sendMessage(C::colorize("&r&4Disarmor &r&7ripped a piece of armor loose."));
            $brotherhoodLevel = CustomEnchantmentManager::getLevel($item, "brotherhood");
            BrotherhoodEnchant::tryDistributeFromMasteryProc($damager, $brotherhoodLevel, "disarmor");
        }

        if (!$forcedProc && $result !== null) {
            EnchantProcManager::applyCooldownIfNeeded($result, static function (int $seconds) use ($session): void {
                $session?->addCooldown("disarmor", $seconds);
            });
            EnchantProcManager::debugAppliedValues($damager, $result, [
                "removed_armor" => true,
            ]);
        }
    }

    public static function forceDisarmor(?Player $attacker, Player $victim): bool
    {
        if (RingManager::playerBlocksEnchant($victim, "disarmor")) {
            self::sendRingBlockedMessage($victim);
            return false;
        }

        $inv = $victim->getArmorInventory();
        if (KerbusEnchant::consumeProtection($victim)) {
            return false;
        }

        $slots = [];
        foreach ($inv->getContents() as $slot => $armor) {
            if ($armor->isNull()) {
                continue;
            }
            if ($attacker !== null && AdvancedEnchantState::isFetterSlotLocked($victim, $slot)) {
                continue;
            }
            $slots[] = $slot;
        }
        if ($slots === []) {
            return false;
        }

        $slot = $slots[array_rand($slots)];
        $removed = $inv->getItem($slot);
        $inv->setItem($slot, VanillaItems::AIR());
        foreach ($victim->getInventory()->addItem($removed) as $leftover) {
            $victim->getWorld()->dropItem($victim->getPosition(), $leftover);
        }

        if ($attacker !== null) {
            $fetterLevel = CustomEnchantmentManager::getLevel($attacker->getArmorInventory()->getBoots(), "soulshackle");
            if ($fetterLevel > 0) {
                $secondsPerLevel = max(1, EnchantTuningManager::getInt("soulshackle", "lock-seconds-per-level", 3));
                AdvancedEnchantState::lockFetterSlot($victim, $slot, $secondsPerLevel * $fetterLevel);
            }
        }

        return true;
    }

    private static function sendRingBlockedMessage(Player $victim): void
    {
        $key = strtolower($victim->getName());
        $now = microtime(true);
        $nextAllowed = self::$ringNoticeCooldowns[$key] ?? 0.0;
        if ($nextAllowed > $now) {
            return;
        }

        self::$ringNoticeCooldowns[$key] = $now + 2.5;
        $victim->sendMessage(C::colorize("&r&dDisarmor Ring &7protected your items from being disarmored."));
    }
}
