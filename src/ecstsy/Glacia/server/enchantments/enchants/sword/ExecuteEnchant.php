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
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class ExecuteEnchant implements EntityDamageByEntityEnchantmentHandler {
    
    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) return;

        $damager = $event->getDamager();
        $victim  = $event->getEntity();

        if (!$damager instanceof Living) return;
        if (!$victim instanceof Living) return;

        $session = $damager instanceof Player ? PlayerManager::getInstance()->getSession($damager) : null;
        $item = $damager instanceof Player ? $damager->getInventory()->getItemInHand() : (($damager instanceof ForsakenChampion || $damager instanceof MummifiedRemnant) ? $damager->getCombatItem() : null);
        if ($item === null) return;
        if (!CustomEnchantmentManager::hasEnchantment($item, "execute")) return;

        $chance = EnchantTuningManager::chance("execute", $level, 4.0);

        $maxHealth = $victim->getMaxHealth();
        if ($maxHealth <= 0) return;

        $threshold = max(0.0, EnchantTuningManager::value("execute", "threshold-health", $level, 6.0));
        $strict = true;
        $h = $victim->getHealth();
        if ($strict ? ($h >= $threshold) : ($h > $threshold)) {
            return;
        }

        $cooldownSeconds = EnchantTuningManager::cooldownSeconds("execute", $level, 0);
        $result = EnchantProcManager::attempt(
            $damager,
            "execute",
            $level,
            $chance,
            $cooldownSeconds,
            static fn() => $session?->getCooldown("execute") ?? 0
        );
        if (!$result->success) return;

        $rawDamage = EnchantTuningManager::value("execute", "damage", $level, 1.5);
        $minFloor = max(0.0, EnchantTuningManager::value("execute", "damage-min-floor", $level, 1.5));
        $capFloor = max(0.0, EnchantTuningManager::value("execute", "damage-cap-min-floor", $level, 1.5));
        $capFrac = max(0.0, EnchantTuningManager::value("execute", "damage-cap-max-health-fraction", $level, 0.08));
        $damage = min(
            max($minFloor, $rawDamage),
            max($capFloor, $maxHealth * $capFrac)
        );

        $newHealth = max(0, $victim->getHealth() - $damage);
        $victim->setHealth($newHealth);
        if ($damager instanceof Player && $victim instanceof Player) {
            MirrorbrandProcManager::record($damager, "execute", static function (Player $reflector, Player $attacker) use ($damage): bool {
                $attacker->setHealth(max(0.0, $attacker->getHealth() - $damage));
                return true;
            });
        }
        EnchantProcManager::applyCooldownIfNeeded($result, static fn(int $seconds) => $session?->addCooldown("execute", $seconds));
        EnchantProcManager::debugAppliedValues($damager, $result, [
            "threshold_health" => $threshold,
            "damage" => $damage,
            "victim_health_after" => $newHealth,
        ]);

        if ($damager instanceof Player) {
            $damager->sendMessage(C::colorize("&r&b&l** &r&bExecute &l** &r&7You struck a finishing blow."));
        }
    }
}
