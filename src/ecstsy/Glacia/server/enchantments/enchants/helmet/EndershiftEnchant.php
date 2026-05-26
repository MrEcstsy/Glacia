<?php 

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\helmet;

use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class EndershiftEnchant implements EntityDamageByEntityEnchantmentHandler {

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) {
            return;
        }

        $victim = $event->getEntity();
        if (!$victim instanceof Player) {
            return;
        }

        $hasEndershift = false;
        foreach ($victim->getArmorInventory()->getContents() as $armor) {
            if (CustomEnchantmentManager::hasEnchantment($armor, "endershift")) {
                $hasEndershift = true;
                break;
            }
        }

        if (!$hasEndershift) {
            return;
        }

        $session = PlayerManager::getInstance()->getSession($victim);
        if ($session === null) {
            return;
        }

        $activationMaxHealth = max(0.0, EnchantTuningManager::value("endershift", "activation-max-health", $level, 5.0));
        if ($victim->getHealth() >= $activationMaxHealth) {
            return;
        }

        $chance = EnchantTuningManager::chance("endershift", $level, 16.0);
        $cooldownSeconds = EnchantTuningManager::cooldownSeconds("endershift", $level, 45);
        $speedTicks = max(1, EnchantTuningManager::valueInt("endershift", "speed-duration-ticks", $level, 1200));
        $speedAmplifier = max(0, EnchantTuningManager::valueInt("endershift", "speed-amplifier", $level, 0));
        $absorptionTicks = max(1, EnchantTuningManager::valueInt("endershift", "absorption-duration-ticks", $level, 600));
        $absorptionAmplifier = max(0, EnchantTuningManager::valueInt("endershift", "absorption-amplifier", $level, 0));

        $result = EnchantProcManager::attempt(
            $victim,
            "endershift",
            $level,
            $chance,
            $cooldownSeconds,
            static fn() => $session->getCooldown("endershift")
        );
        if (!$result->success) {
            return;
        }

        $victim->getEffects()->add(new EffectInstance(VanillaEffects::SPEED(), $speedTicks, $speedAmplifier));
        $victim->getEffects()->add(new EffectInstance(VanillaEffects::ABSORPTION(), $absorptionTicks, $absorptionAmplifier));

        EnchantProcManager::applyCooldownIfNeeded($result, static fn(int $seconds) => $session->addCooldown("endershift", $seconds));
        EnchantProcManager::debugAppliedValues($victim, $result, [
            "activation_max_health" => $activationMaxHealth,
            "speed_ticks" => $speedTicks,
            "speed_amplifier" => $speedAmplifier,
            "absorption_ticks" => $absorptionTicks,
            "absorption_amplifier" => $absorptionAmplifier,
        ]);

        $victim->sendMessage("You were about to die, so you have entered the Ender Dimension!");
    }
}
