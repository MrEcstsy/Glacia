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
        $item = $damager instanceof Player ? $damager->getInventory()->getItemInHand() : ($damager instanceof ForsakenChampion ? $damager->getCombatItem() : null);
        if ($item === null) return;
        if (!CustomEnchantmentManager::hasEnchantment($item, "execute")) return;

        $chance = match($level) {
            1 => 4,
            2 => 5,
            3 => 6,
            4 => 7,
            5 => 8,
            default => 4
        };
        $chance = EnchantTuningManager::getLevelInt("execute", "chance-by-level", $level, $chance);

        $maxHealth = $victim->getMaxHealth();
        if ($maxHealth <= 0 || $victim->getHealth() / $maxHealth > 0.25) return;

        if (!ProcLuckManager::roll($damager, "execute", $chance, 2, 10)) return;
        if ($session !== null && $session->isOnCooldown("execute")) return;

        $damage = min(
            max(1.5, 0.5 + ($level * 0.4)),
            max(1.5, $maxHealth * 0.08)
        );

        $newHealth = max(0, $victim->getHealth() - $damage);
        $victim->setHealth($newHealth);

        if ($session !== null) {
            $session->addCooldown("execute", max(0, EnchantTuningManager::getInt("execute", "cooldown", 1)));
        }

        if ($damager instanceof Player) {
            $damager->sendMessage(C::colorize("&r&b&l** &r&bExecute &l** &r&7You struck a finishing blow."));
        }
    }
}
