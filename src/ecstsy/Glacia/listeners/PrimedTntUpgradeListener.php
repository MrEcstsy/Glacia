<?php

declare(strict_types=1);

namespace ecstsy\Glacia\listeners;

use ecstsy\Glacia\entity\GlaciaPrimedTNT;
use pocketmine\entity\Location;
use pocketmine\entity\object\PrimedTNT;
use pocketmine\event\entity\EntitySpawnEvent;
use pocketmine\event\Listener;

/**
 * Routes vanilla {@see PrimedTNT} to {@see GlaciaPrimedTNT} so cobweb / Glacia explosion logic applies everywhere.
 */
final class PrimedTntUpgradeListener implements Listener {

    public function onEntitySpawn(EntitySpawnEvent $event): void {
        $entity = $event->getEntity();
        if ($entity instanceof GlaciaPrimedTNT) {
            return;
        }
        if (!$entity instanceof PrimedTNT) {
            return;
        }

        if ($entity->isFlaggedForDespawn()) {
            return;
        }

        $loc = Location::fromObject(
            $entity->getLocation(),
            $entity->getWorld(),
            $entity->getLocation()->yaw,
            $entity->getLocation()->pitch
        );

        $fuse = $entity->getFuse();
        $motion = $entity->getMotion();
        $fall = $entity->getFallDistance();
        $underwater = $entity->worksUnderwater();

        $entity->flagForDespawn();

        $primed = new GlaciaPrimedTNT($loc, null);
        $primed->setFuse($fuse);
        $primed->setMotion($motion);
        $primed->setFallDistance($fall);
        $primed->setWorksUnderwater($underwater);
        $primed->spawnToAll();
    }
}
