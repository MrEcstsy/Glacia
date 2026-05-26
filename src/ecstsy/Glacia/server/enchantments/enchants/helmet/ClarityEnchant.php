<?php 

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\helmet;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityEffectAddEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
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

        $chance = EnchantTuningManager::chance("clarity", $level, 20.0);
        $result = EnchantProcManager::attempt($entity, "clarity", $level, $chance, EnchantTuningManager::cooldownSeconds("clarity", $level, 0));
        if(!$result->success){
            return;
        }

        $event->cancel();
        EnchantProcManager::debugAppliedValues($entity, $result, [
            "negated_effect" => $effect->getType()->getName(),
        ]);
    }
}
