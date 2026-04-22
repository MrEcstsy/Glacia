<?php

namespace ecstsy\Glacia\entity\mobs;

use ecstsy\Glacia\entity\SpawnerEntity;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

class ZombiePigman extends SpawnerEntity
{

    protected function getInitialSizeInfo(): EntitySizeInfo {
        return new EntitySizeInfo(1.8, 0.6);
    }

    public static function getNetworkTypeId(): string {
        return EntityIds::ZOMBIE_PIGMAN;
    }


    public function getName(): string {
        return "Zombie Pigman";
    }

    public function getDrops(): array {
        return [
            VanillaBlocks::GOLD()->asItem()->setCount(mt_rand(1, 2)),
        ];
    }

    public function getXpDropAmount(): int {
        return 5;
    }
}