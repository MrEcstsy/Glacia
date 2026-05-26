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

final class CurseEnchant implements EntityDamageByEntityEnchantmentHandler {
    /** @var array<string, int> */
    private static array $cooldowns = [];

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) return;

        $attacker = $event->getDamager();
        $victim   = $event->getEntity();

        if (!$attacker instanceof Living) return;
        if (!$victim instanceof Player && !$victim instanceof MummifiedRemnant) return;

        $hasCurse = false;
        $armorContents = $victim instanceof Player ? $victim->getArmorInventory()->getContents() : $victim->getCombatArmorContents();
        foreach ($armorContents as $piece) {
            if (CustomEnchantmentManager::hasEnchantment($piece, "curse")) {
                $hasCurse = true;
                break;
            }
        }

        if (!$hasCurse) return;

        $session = $victim instanceof Player ? PlayerManager::getInstance()->getSession($victim) : null;
        $cooldownKey = spl_object_id($victim) . ":curse";

        $chance = EnchantTuningManager::chance("curse", $level, 15.0 + (($level - 1) * 5.0));
        $cooldownSeconds = EnchantTuningManager::cooldownSeconds("curse", $level, 7);
        $result = EnchantProcManager::attempt(
            $victim,
            "curse",
            $level,
            $chance,
            $cooldownSeconds,
            static fn() => $session?->isOnCooldown("curse") ? 1 : max(0, (self::$cooldowns[$cooldownKey] ?? 0) - time())
        );
        if (!$result->success) {
            return;
        }

        $duration = max(1, EnchantTuningManager::valueInt("curse", "mining-fatigue-duration-seconds", $level, 2 + ($level - 1)));
        $amplifier = max(0, EnchantTuningManager::valueInt("curse", "mining-fatigue-amplifier", $level, $level >= 3 ? min(2, intdiv($level - 1, 2)) : 0));
        if ($session !== null) {
            $session->addCooldown("curse", $cooldownSeconds);
        } else {
            self::$cooldowns[$cooldownKey] = time() + $cooldownSeconds;
        }

        $attacker->getEffects()->add(
            new EffectInstance(
                VanillaEffects::MINING_FATIGUE(),
                $duration * 20,
                $amplifier
            )
        );
        EnchantProcManager::debugAppliedValues($victim, $result, [
            "duration_seconds" => $duration,
            "amplifier" => $amplifier,
            "target" => $attacker->getNameTag(),
        ]);
    }
}
