<?php 

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\sword;

use ecstsy\Glacia\entity\gkits\ForsakenChampion;
use ecstsy\Glacia\entity\MummifiedRemnant;
use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\enchantments\manager\MirrorbrandProcManager;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class BlindEnchant implements EntityDamageByEntityEnchantmentHandler {
    
    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) return;

        $damager = $event->getDamager();
        if (!$damager instanceof Living) return;

        $victim = $event->getEntity();
        if (!$victim instanceof Living) return;

        $item = $damager instanceof Player ? $damager->getInventory()->getItemInHand() : (($damager instanceof ForsakenChampion || $damager instanceof MummifiedRemnant) ? $damager->getCombatItem() : null);
        if ($item === null) return;
        if (!CustomEnchantmentManager::hasEnchantment($item, "blind")) return;

        $session = $damager instanceof Player ? PlayerManager::getInstance()->getSession($damager) : null;

        $chance = EnchantTuningManager::chance("blind", $level, 25.0);
        $cooldownSeconds = EnchantTuningManager::cooldownSeconds("blind", $level, 1);
        $durationTicks = max(1, EnchantTuningManager::valueInt("blind", "duration-ticks", $level, 60));
        $effectLevel = max(0, EnchantTuningManager::valueInt("blind", "amplifier", $level, 0));
        $result = EnchantProcManager::attempt(
            $damager,
            "blind",
            $level,
            $chance,
            $cooldownSeconds,
            static fn() => $session?->getCooldown("blind") ?? 0
        );
        if (!$result->success) {
            return;
        }

        $victim->getEffects()->add(
            new EffectInstance(
                VanillaEffects::BLINDNESS(),
                $durationTicks,
                $effectLevel
            )
        );
        if ($damager instanceof Player && $victim instanceof Player) {
            MirrorbrandProcManager::record($damager, "blind", static function (Player $reflector, Player $attacker) use ($durationTicks, $effectLevel): bool {
                $attacker->getEffects()->add(new EffectInstance(
                    VanillaEffects::BLINDNESS(),
                    $durationTicks,
                    $effectLevel
                ));
                return true;
            });
        }
        EnchantProcManager::applyCooldownIfNeeded($result, static fn(int $seconds) => $session?->addCooldown("blind", $seconds));
        EnchantProcManager::debugAppliedValues($damager, $result, [
            "duration_ticks" => $durationTicks,
            "amplifier" => $effectLevel,
        ]);
    }
}
