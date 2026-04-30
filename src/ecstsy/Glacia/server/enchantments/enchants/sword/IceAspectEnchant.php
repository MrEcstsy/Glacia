<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\sword;

use ecstsy\Glacia\entity\gkits\ForsakenChampion;
use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\enchantments\manager\ProcLuckManager;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class IceAspectEnchant implements EntityDamageByEntityEnchantmentHandler
{
    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) return;

        $damager = $event->getDamager();
        $victim = $event->getEntity();
        if (!$damager instanceof Living || !$victim instanceof Living) return;

        $item = $damager instanceof Player ? $damager->getInventory()->getItemInHand() : ($damager instanceof ForsakenChampion ? $damager->getCombatItem() : null);
        if ($item === null || !CustomEnchantmentManager::hasEnchantment($item, "iceaspect")) return;

        $forcedProc = $damager instanceof Player && BrotherhoodEnchant::consumeForcedProc($damager, "iceaspect");
        if (!$forcedProc) {
            $chance = EnchantTuningManager::getLevelInt("iceaspect", "chance-by-level", $level, 8 + ($level * 2));
            if (!ProcLuckManager::roll($damager, "iceaspect", $chance, 2, 12)) return;
        }

        $session = $damager instanceof Player ? PlayerManager::getInstance()->getSession($damager) : null;
        $cooldown = max(0, EnchantTuningManager::getInt("iceaspect", "cooldown", 3));
        if (!$forcedProc && $session !== null && $cooldown > 0 && $session->isOnCooldown("iceaspect")) return;

        $seconds = max(1, EnchantTuningManager::getLevelInt("iceaspect", "freeze-duration-seconds-by-level", $level, min(3, $level)));
        $amplifier = max(0, EnchantTuningManager::getInt("iceaspect", "slowness-amplifier", 6));
        $victim->getEffects()->add(new EffectInstance(VanillaEffects::SLOWNESS(), $seconds * 20, $amplifier));

        if ($damager instanceof Player) {
            $brotherhoodLevel = CustomEnchantmentManager::getLevel($item, "brotherhood");
            BrotherhoodEnchant::tryDistributeFromMasteryProc($damager, $brotherhoodLevel, "iceaspect");
        }

        if (!$forcedProc && $session !== null && $cooldown > 0) {
            $session->addCooldown("iceaspect", $cooldown);
        }
    }
}

