<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\envoys;

use pocketmine\nbt\tag\CompoundTag;

trait EnvoyGuardTrait
{
    protected function initEntity(CompoundTag $nbt): void
    {
        if ($nbt->getTag("stack") === null) {
            $nbt->setInt("stack", 1);
        }

        parent::initEntity($nbt);
        $this->setNameTagAlwaysVisible();
    }
}
