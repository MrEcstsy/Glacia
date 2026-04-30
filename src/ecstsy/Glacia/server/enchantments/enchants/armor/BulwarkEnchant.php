<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\armor;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\enchantments\manager\ProcLuckManager;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\item\Durable;
use pocketmine\player\Player;

final class BulwarkEnchant implements EntityDamageByEntityEnchantmentHandler
{
    /** @var array<string, int> */
    private static array $immuneUntil = [];

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        $victim = $event->getEntity();
        if (!$victim instanceof Player) return;

        $key = $victim->getUniqueId()->toString();
        if ((self::$immuneUntil[$key] ?? 0) > time()) {
            $event->cancel();
            return;
        }

        $chance = EnchantTuningManager::getLevelInt("bulwark", "chance-by-level", $level, $level * 5);
        if (!ProcLuckManager::roll($victim, "bulwark", $chance, 1, 8)) return;

        $seconds = max(1, EnchantTuningManager::getInt("bulwark", "immunity-seconds", 3));
        self::$immuneUntil[$key] = time() + $seconds;
        $event->cancel();

        $armorInv = $victim->getArmorInventory();
        foreach ($armorInv->getContents() as $slot => $item) {
            if ($item instanceof Durable) {
                $item->setDamage(0);
                $armorInv->setItem($slot, $item);
            }
        }
    }
}
