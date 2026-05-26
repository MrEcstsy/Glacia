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
use pocketmine\player\Player;

final class PelletEnchant implements EntityDamageByEntityEnchantmentHandler
{
    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) return;
        $damager = $event->getDamager();
        $victim = $event->getEntity();
        $projectile = $damager instanceof Projectile
            ? $damager
            : (($event instanceof EntityDamageByChildEntityEvent && $event->getChild() instanceof Projectile) ? $event->getChild() : null);
        if (!$projectile instanceof Projectile || !$victim instanceof Player) return;
        $owner = $projectile->getOwningEntity();
        if (!$owner instanceof Player) return;

        $chance = EnchantTuningManager::getLevelInt("pellet", "chance-by-level", $level, $level);
        if (!ProcLuckManager::roll($owner, "pellet", $chance, 1, 4)) return;

        $extraDamage = max(0.0, EnchantTuningManager::getFloat("pellet", "bonus-damage", 4.0));
        $victim->attack(new EntityDamageByEntityEvent($owner, $victim, EntityDamageEvent::CAUSE_PROJECTILE, $extraDamage));

        $knockback = max(0.3, EnchantTuningManager::getFloat("pellet", "knockback-strength", 1.1));
        $dir = $victim->getPosition()->subtractVector($owner->getPosition())->normalize();
        $motion = $victim->getMotion();
        $motion->x += $dir->x * $knockback;
        $motion->z += $dir->z * $knockback;
        $motion->y = max($motion->y, 0.2);
        $victim->setMotion($motion);
    }
}
