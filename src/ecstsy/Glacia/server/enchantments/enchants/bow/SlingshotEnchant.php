<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\bow;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\enchantments\manager\ProcLuckManager;
use pocketmine\entity\projectile\Projectile;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;

final class SlingshotEnchant implements EntityDamageByEntityEnchantmentHandler
{
    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) return;
        $damager = $event->getDamager();
        $victim = $event->getEntity();
        if (!$damager instanceof Projectile || !$victim instanceof Player) return;
        $owner = $damager->getOwningEntity();
        if (!$owner instanceof Player) return;

        $chance = EnchantTuningManager::getLevelInt("slingshot", "chance-by-level", $level, $level);
        if (!ProcLuckManager::roll($owner, "slingshot", $chance, 1, 6)) return;

        $inv = $victim->getInventory();
        foreach ($inv->getContents() as $slot => $item) {
            if ($item->getTypeId() === ItemTypeIds::ENDER_PEARL) {
                $inv->setItem($slot, VanillaItems::AIR());
            }
        }
    }
}
