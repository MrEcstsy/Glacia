<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\bow;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\enchantments\manager\ProcLuckManager;
use pocketmine\entity\projectile\Projectile;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class SlingshotEnchant implements EntityDamageByEntityEnchantmentHandler
{
    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) return;
        if ($event->getCause() !== EntityDamageEvent::CAUSE_PROJECTILE) return;
        $damager = $event->getDamager();
        $victim = $event->getEntity();
        $projectile = $damager instanceof Projectile
            ? $damager
            : (($event instanceof EntityDamageByChildEntityEvent && $event->getChild() instanceof Projectile) ? $event->getChild() : null);
        if (!$projectile instanceof Projectile || !$victim instanceof Player) return;
        $owner = $projectile->getOwningEntity();
        if (!$owner instanceof Player) return;

        $chance = EnchantTuningManager::getLevelInt("slingshot", "chance-by-level", $level, $level);
        if (!ProcLuckManager::roll($owner, "slingshot", $chance, 1, 6)) return;

        $inv = $victim->getInventory();
        $pearl = VanillaItems::ENDER_PEARL()->setCount(1);
        if (!$inv->contains($pearl)) {
            return;
        }

        $inv->removeItem($pearl);
        $victim->sendMessage(C::colorize("&r&dSlingshot &7ripped an ender pearl out of your inventory."));
    }
}
