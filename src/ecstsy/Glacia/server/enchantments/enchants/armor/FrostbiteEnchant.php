<?php 

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\armor;

use ecstsy\Glacia\entity\MummifiedRemnant;
use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class FrostbiteEnchant implements EntityDamageByEntityEnchantmentHandler {
    /** @var array<string, int> */
    private static array $cooldowns = [];

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) return;

        $attacker = $event->getDamager();  
        $victim = $event->getEntity();  

        if (!$attacker instanceof Living) return;
        if (!$victim instanceof Player && !$victim instanceof MummifiedRemnant) return;

        $armor = $victim instanceof Player ? $victim->getArmorInventory()->getContents() : $victim->getCombatArmorContents();
        $hasFrostbite = false;

        foreach ($armor as $piece) {
            if (CustomEnchantmentManager::hasEnchantment($piece, "frostbite")) {
                $hasFrostbite = true;
                break; 
            }
        }

        if (!$hasFrostbite) return;

        $victimSession = $victim instanceof Player ? PlayerManager::getInstance()->getSession($victim) : null;
        $cooldownKey = spl_object_id($victim) . ":frostbite";

        $chance = EnchantTuningManager::chance("frostbite", $level, 5.0 * $level);
        $cooldownSeconds = EnchantTuningManager::cooldownSeconds("frostbite", $level, max(40, 65 - ($level * 5)));
        $result = EnchantProcManager::attempt(
            $victim,
            "frostbite",
            $level,
            $chance,
            $cooldownSeconds,
            static fn() => $victimSession?->isOnCooldown("frostbite") ? 1 : max(0, (self::$cooldowns[$cooldownKey] ?? 0) - time())
        );
        if (!$result->success) return;

        $duration = max(1, EnchantTuningManager::valueInt("frostbite", "slowness-duration-seconds", $level, 5));
        $amplifier = max(0, EnchantTuningManager::valueInt("frostbite", "slowness-amplifier", $level, 4));
        if ($victimSession !== null) {
            $victimSession->addCooldown("frostbite", $cooldownSeconds);
        } else {
            self::$cooldowns[$cooldownKey] = time() + $cooldownSeconds;
        }

        $attacker->getEffects()->add(new EffectInstance(
            VanillaEffects::SLOWNESS(),
            $duration * 20,
            $amplifier
        ));
        EnchantProcManager::debugAppliedValues($victim, $result, [
            "duration_seconds" => $duration,
            "amplifier" => $amplifier,
            "target" => $attacker->getNameTag(),
        ]);
    }
}
