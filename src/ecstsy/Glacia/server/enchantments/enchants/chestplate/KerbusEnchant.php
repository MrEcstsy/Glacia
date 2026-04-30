<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\chestplate;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\enchantments\manager\ProcLuckManager;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class KerbusEnchant implements EntityDamageByEntityEnchantmentHandler
{
    /** @var array<string, int> */
    private static array $guardUntil = [];

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) return;
        $victim = $event->getEntity();
        if (!$victim instanceof Player) return;

        $chance = EnchantTuningManager::getLevelInt("kerbus", "chance-by-level", $level, min(100, $level * 45));
        if (!ProcLuckManager::roll($victim, "kerbus", $chance, 1, 6)) return;

        self::$guardUntil[$victim->getUniqueId()->toString()] = time() + 2;
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
