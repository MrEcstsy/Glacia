<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\items\armor\abilities;

use ecstsy\Glacia\entity\mobs\GlaciaLightningStrike;
use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\AxisAlignedBB;
use pocketmine\player\Player;
use pocketmine\world\Position;

final class YijkiLightningStrike {

    public static function strike(Player $owner, Position $position, float $damage = 4.0, float $radius = 3.0): void {
        $world = $position->getWorld();

        $lightning = new GlaciaLightningStrike(new Location(
            $position->x + 0.5,
            $position->y,
            $position->z + 0.5,
            $world,
            0.0,
            0.0
        ));
        $lightning->spawnToAll();

        foreach ($world->getPlayers() as $viewer) {
            PlayerUtils::playSound($viewer, 'ambient.weather.thunder', 1.0, 1.0);
            PlayerUtils::playSound($viewer, 'ambient.weather.lightning.impact', 1.0, 1.0);
        }

        $bb = new AxisAlignedBB(
            $position->x - $radius,
            $position->y - $radius,
            $position->z - $radius,
            $position->x + $radius,
            $position->y + $radius,
            $position->z + $radius
        );

        foreach ($world->getNearbyEntities($bb) as $entity) {
            if (!$entity instanceof Player) {
                continue;
            }

            if (!$entity->isAlive()) {
                continue;
            }

            if ($entity->getId() === $owner->getId()) {
                continue;
            }

            $distance = $entity->getPosition()->distance($position);
            if ($distance > $radius) {
                continue;
            }

            $event = new EntityDamageByEntityEvent(
                $owner,
                $entity,
                EntityDamageEvent::CAUSE_MAGIC,
                $damage,
                [],
                0.0,
                0.0
            );
            $entity->attack($event);
        }
    }
}
