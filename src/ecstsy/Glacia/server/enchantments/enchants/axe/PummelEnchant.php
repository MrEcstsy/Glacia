<?php 

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\axe;

use ecstsy\Glacia\player\PlayerManager;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

final class PummelEnchant implements EntityDamageByEntityEnchantmentHandler {

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) return;

        $damager = $event->getDamager();
        $victim  = $event->getEntity();

        if (!$damager instanceof Player || !$victim instanceof Player) return;

        $session = PlayerManager::getInstance()->getSession($damager);

        $item = $damager->getInventory()->getItemInHand();
        if (!CustomEnchantmentManager::hasEnchantment($item, "pummel")) return;

        $chance = $level * 5;
        if (mt_rand(1, 100) > $chance) return;

        if ($session->isOnCooldown("pummel")) return;

        $entities = $damager->getWorld()->getNearbyEntities($damager->getBoundingBox()->expandedCopy(5, 5, 5), $damager);

        foreach ($entities as $entity) {
            if (!$entity instanceof Living) continue;

            if ($entity instanceof Player && GlaciaUtils::isAlly($damager, $entity)) continue;

            $effect = new EffectInstance(VanillaEffects::SLOWNESS(), 20 * 3, $level - 1);
            $entity->getEffects()->add($effect);
        }

        $session->addCooldown("pummel", 60); 
    }
}