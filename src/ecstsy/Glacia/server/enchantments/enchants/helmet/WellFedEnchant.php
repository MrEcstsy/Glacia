<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\helmet;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\PlayerExhaustEnchantmentHandler;
use pocketmine\event\player\PlayerExhaustEvent;
use pocketmine\player\Player;

final class WellFedEnchant implements PlayerExhaustEnchantmentHandler {

    public static function handle(PlayerExhaustEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if($event->isCancelled()){
            return;
        }

        $player = $event->getPlayer();
        if(!$player instanceof Player){
            return;
        }

        $hunger = $player->getHungerManager();
        $restore = $level; 
        $new = min($hunger->getFood() + $restore, $hunger->getMaxFood());
        $hunger->setFood($new);

        $hunger->setExhaustion(
            max(0, $hunger->getExhaustion() - ($restore * 0.1))
        );
    }
}
