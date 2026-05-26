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

final class FearEnchant implements EntityDamageByEntityEnchantmentHandler {

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) return;

        $damager = $event->getDamager();
        $victim  = $event->getEntity();

        if (!$damager instanceof Player || !$victim instanceof Living) return;

        $session = PlayerManager::getInstance()->getSession($damager);

        $item = $damager->getInventory()->getItemInHand();
        if (!CustomEnchantmentManager::hasEnchantment($item, "fear")) return;
        $chance = EnchantTuningManager::chance("fear", $level, 13.0);
        $cooldownSeconds = EnchantTuningManager::cooldownSeconds("fear", $level, 4);
        $durationTicks = max(1, EnchantTuningManager::valueInt("fear", "duration-ticks", $level, 40));
        $amplifier = max(0, EnchantTuningManager::valueInt("fear", "amplifier", $level, 0));
        $result = EnchantProcManager::attempt(
            $damager,
            "fear",
            $level,
            $chance,
            $cooldownSeconds,
            static fn() => $session->getCooldown("fear")
        );
        if (!$result->success) return;

        $victim->getEffects()->add(new EffectInstance(
            VanillaEffects::WEAKNESS(),
            $durationTicks,
            $amplifier
        ));
        if ($victim instanceof Player) {
            MirrorbrandProcManager::record($damager, "fear", static function (Player $reflector, Player $attacker) use ($durationTicks, $amplifier): bool {
                $attacker->getEffects()->add(new EffectInstance(
                    VanillaEffects::WEAKNESS(),
                    $durationTicks,
                    $amplifier
                ));
                return true;
            });
        }
        EnchantProcManager::applyCooldownIfNeeded($result, static fn(int $seconds) => $session->addCooldown("fear", $seconds));
        EnchantProcManager::debugAppliedValues($damager, $result, [
            "duration_ticks" => $durationTicks,
            "amplifier" => $amplifier,
        ]);
    }
}
