<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\spawn;

use ecstsy\Glacia\entity\InteractiveSpawnEntity;
use pocketmine\entity\Entity;
use pocketmine\player\Player;
use function array_values;
use function usort;

final class InteractiveSpawnSelection
{
    public static function findNearest(Player $player, float $radius = 7.0): ?InteractiveSpawnEntity
    {
        $entities = [];
        foreach ($player->getWorld()->getNearbyEntities($player->getBoundingBox()->expandedCopy($radius, $radius, $radius)) as $entity) {
            if ($entity instanceof InteractiveSpawnEntity) {
                $entities[] = $entity;
            }
        }

        if ($entities === []) {
            return null;
        }

        $origin = $player->getPosition();
        usort($entities, static function(InteractiveSpawnEntity $a, InteractiveSpawnEntity $b) use ($origin): int {
            $aEntity = $a instanceof Entity ? $a : null;
            $bEntity = $b instanceof Entity ? $b : null;
            if (!$aEntity instanceof Entity || !$bEntity instanceof Entity) {
                return 0;
            }

            return $origin->distanceSquared($aEntity->getPosition()) <=> $origin->distanceSquared($bEntity->getPosition());
        });

        return array_values($entities)[0] ?? null;
    }
}
