<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\sword;

use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantCombatStateManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\enchantments\manager\ExclusiveEnchantManager;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;
use pocketmine\world\particle\PortalParticle;

final class LeviathanEnchant implements EntityDamageByEntityEnchantmentHandler
{
    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) {
            return;
        }

        $damager = $event->getDamager();
        $victim = $event->getEntity();
        if (!$damager instanceof Player || !$victim instanceof Player) {
            return;
        }

        $session = PlayerManager::getInstance()->getSession($damager);
        $cooldownSeconds = EnchantTuningManager::cooldownSeconds("leviathan", $level, 24);
        $result = EnchantProcManager::attempt(
            $damager,
            "leviathan",
            $level,
            EnchantTuningManager::chance("leviathan", $level, 3.0),
            $cooldownSeconds,
            static fn() => max(0, (int) ($session?->getCooldown("leviathan") ?? 0))
        );
        if (!$result->success) {
            return;
        }

        if (!ExclusiveEnchantManager::consumeHeldUse($damager, "leviathan")) {
            return;
        }

        $duration = max(1, EnchantTuningManager::valueInt("leviathan", "duration-seconds", $level, 2));
        $procBonus = max(0, EnchantTuningManager::valueInt("leviathan", "proc-bonus-percent", $level, 10));
        EnchantCombatStateManager::disableCustomEnchants($victim, $duration);
        EnchantCombatStateManager::disablePets($victim, $duration);
        EnchantCombatStateManager::grantProcBonus($damager, $duration, $procBonus);

        $center = $victim->getPosition()->add(0, 1.0, 0);
        for ($i = 0; $i < 10; $i++) {
            $victim->getWorld()->addParticle($center->add(mt_rand(-9, 9) / 10, mt_rand(-2, 8) / 10, mt_rand(-9, 9) / 10), new PortalParticle());
        }
        PlayerUtils::playSound($damager, "mob.guardian.curse", 1.0, 1.0);
        PlayerUtils::playSound($victim, "mob.endermen.portal", 1.0, 0.75);

        EnchantProcManager::applyCooldownIfNeeded($result, static function (int $seconds) use ($session): void {
            $session?->addCooldown("leviathan", $seconds);
        });
        EnchantProcManager::debugAppliedValues($damager, $result, [
            "duration_seconds" => $duration,
            "proc_bonus_percent" => $procBonus,
        ]);
    }
}
