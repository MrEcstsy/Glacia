<?php 

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\axe;

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

final class ConfusionEnchant implements EntityDamageByEntityEnchantmentHandler {

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) return;

        $damager = $event->getDamager();
        $victim  = $event->getEntity();

        if (!$damager instanceof Player) return;
        if (!$victim instanceof Living) return;

        $session = PlayerManager::getInstance()->getSession($damager);

        $item = $damager->getInventory()->getItemInHand();

        if (!CustomEnchantmentManager::hasEnchantment($item, "confusion")) {
            return;
        }

        $chance = EnchantTuningManager::chance("confusion", $level, 13.0);
        $cooldownSeconds = EnchantTuningManager::cooldownSeconds("confusion", $level, 4);
        $durationTicks = max(1, EnchantTuningManager::valueInt("confusion", "duration-ticks", $level, 40));
        $result = EnchantProcManager::attempt(
            $damager,
            "confusion",
            $level,
            $chance,
            $cooldownSeconds,
            static fn() => $session->getCooldown("confusion")
        );
        if (!$result->success) {
            return;
        }

        $victim->getEffects()->add(
            new EffectInstance(
                VanillaEffects::NAUSEA(),
                $durationTicks, 
                0
            )
        );
        if ($victim instanceof Player) {
            MirrorbrandProcManager::record($damager, "confusion", static function (Player $reflector, Player $attacker) use ($durationTicks): bool {
                $attacker->getEffects()->add(new EffectInstance(
                    VanillaEffects::NAUSEA(),
                    $durationTicks,
                    0
                ));
                return true;
            });
        }
        EnchantProcManager::applyCooldownIfNeeded($result, static fn(int $seconds) => $session->addCooldown("confusion", $seconds));
        EnchantProcManager::debugAppliedValues($damager, $result, ["duration_ticks" => $durationTicks]);
    }
}
