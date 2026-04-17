<?php 

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\helmet;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\handler\EntityEffectAddEnchantmentHandler;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\entity\EntityEffectAddEvent;
use pocketmine\player\Player;

final class ClarityEnchant implements EntityEffectAddEnchantmentHandler {

    public static function handle(EntityEffectAddEvent $event, CustomEnchantment $enchant, int $level): void
    {
        $entity = $event->getEntity();
        if(!$entity instanceof Player){
            return;
        }

        $effect = $event->getEffect();

        if(
            $effect->getType() !== VanillaEffects::BLINDNESS() &&
            $effect->getType() !== VanillaEffects::NAUSEA()
        ){
            return;
        }

        $chance = $level * 20; // 20% per level

        if(mt_rand(1, 100) <= $chance){
            $event->cancel();
        }
    }
}