<?php 

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\sword;

use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
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

        if (!$damager instanceof Player) return;
        if (!$victim instanceof Living) return;

        $session = PlayerManager::getInstance()->getSession($damager);

        $item = $damager->getInventory()->getItemInHand();
        if (!CustomEnchantmentManager::hasEnchantment($item, "execute")) return;

        $chance = $level * 4;

        $maxHealth = $victim->getMaxHealth();
        if ($victim->getHealth() / $maxHealth > 0.45) return;

        if (mt_rand(1, 100) > $chance) return;
        if ($session->isOnCooldown("execute")) return;

        $damage = mt_rand(10, 50);

        $newHealth = max(0, $victim->getHealth() - $damage);
        $victim->setHealth($newHealth);

        $session->addCooldown("execute", 2); 

        $damager->sendMessage(C::colorize("&r&b&l** &r&bExecute &l** &rYou dealt massive damage!"));
    }
}