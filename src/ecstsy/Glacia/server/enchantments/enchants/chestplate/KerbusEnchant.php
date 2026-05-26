<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\chestplate;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class KerbusEnchant implements EntityDamageByEntityEnchantmentHandler
{
    /** @var array<string, int> */
    private static array $guardUntil = [];

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) {
            return;
        }

        $victim = $event->getEntity();
        if (!$victim instanceof Player) {
            return;
        }

        $session = Loader::getPlayerManager()->getSession($victim);
        $cooldownSeconds = EnchantTuningManager::cooldownSeconds("kerbus", $level, 6);
        $chance = EnchantTuningManager::chance("kerbus", $level, 45.0);
        $result = EnchantProcManager::attempt(
            $victim,
            "kerbus",
            $level,
            $chance,
            $cooldownSeconds,
            static fn() => max(0, (int) ($session?->getCooldown("kerbus") ?? 0))
        );
        if (!$result->success) {
            return;
        }

        $windowSeconds = max(1, EnchantTuningManager::valueInt("kerbus", "protection-window-seconds", $level, 2));
        self::$guardUntil[$victim->getUniqueId()->toString()] = time() + $windowSeconds;
        EnchantProcManager::applyCooldownIfNeeded($result, static function (int $seconds) use ($session): void {
            $session?->addCooldown("kerbus", $seconds);
        });
        EnchantProcManager::debugAppliedValues($victim, $result, [
            "protection_window_seconds" => $windowSeconds,
        ]);
    }

    public static function consumeProtection(Player $player): bool
    {
        $key = $player->getUniqueId()->toString();
        $until = self::$guardUntil[$key] ?? 0;
        if ($until <= time()) {
            unset(self::$guardUntil[$key]);
            return false;
        }

        unset(self::$guardUntil[$key]);
        return true;
    }
}
