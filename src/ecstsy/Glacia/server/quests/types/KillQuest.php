<?php
declare(strict_types=1);

namespace ecstsy\Glacia\server\quests\types;

use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\player\Player;
use ecstsy\Glacia\server\quests\QuestInstance;
use pocketmine\event\entity\EntityDamageByEntityEvent;

final class KillQuest {

    public function onEntityDeath(EntityDeathEvent $event): void {
        $entity = $event->getEntity();
        $cause = $entity->getLastDamageCause();

        if ($cause instanceof EntityDamageByEntityEvent) {
            $damager = $cause->getDamager();
            
            if ($damager instanceof Player) {
                QuestInstance::addProgress($damager, "kill", $entity, 1);
            }
        }
    }
}
