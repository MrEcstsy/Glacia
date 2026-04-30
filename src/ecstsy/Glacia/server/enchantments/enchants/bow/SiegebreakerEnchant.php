<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\bow;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantCombatStateManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\enchantments\manager\ProcLuckManager;
use pocketmine\entity\projectile\Projectile;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\item\Durable;
use pocketmine\player\Player;

final class SiegebreakerEnchant implements EntityDamageByEntityEnchantmentHandler
{
    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) return;
        $damager = $event->getDamager();
        $victim = $event->getEntity();
        if (!$damager instanceof Projectile || !$victim instanceof Player) return;
        $owner = $damager->getOwningEntity();
        if (!$owner instanceof Player) return;

        if (EnchantCombatStateManager::isSiegebreakerActive($owner)) {
            $event->setBaseDamage($event->getBaseDamage() * 4.0);
            return;
        }

        $forcedProc = \ecstsy\Glacia\server\enchantments\enchants\sword\BrotherhoodEnchant::consumeForcedProc($owner, "siegebreaker");
        if (!$forcedProc) {
            $chance = EnchantTuningManager::getInt("siegebreaker", "activation-chance", 6);
            if (!ProcLuckManager::roll($owner, "siegebreaker", $chance, 1, 6)) return;
        }

        $duration = max(1, EnchantTuningManager::getInt("siegebreaker", "duration-seconds", 7));
        EnchantCombatStateManager::activateSiegebreaker($owner, $duration);
        $brotherhoodLevel = CustomEnchantmentManager::getLevel($owner->getInventory()->getItemInHand(), "brotherhood");
        \ecstsy\Glacia\server\enchantments\enchants\sword\BrotherhoodEnchant::tryDistributeFromMasteryProc($owner, $brotherhoodLevel, "siegebreaker");

        $armor = $victim->getArmorInventory()->getContents();
        $slots = [];
        foreach ($armor as $slot => $item) {
            if ($item instanceof Durable && !$item->isNull()) {
                $slots[] = $slot;
            }
        }
        if ($slots !== []) {
            $slot = $slots[array_rand($slots)];
            $piece = $victim->getArmorInventory()->getItem($slot);
            if ($piece instanceof Durable) {
                $extra = max(1, EnchantTuningManager::getInt("siegebreaker", "extra-durability-damage", 25));
                $piece->applyDamage($extra);
                $victim->getArmorInventory()->setItem($slot, $piece);
            }
        }
    }
}
