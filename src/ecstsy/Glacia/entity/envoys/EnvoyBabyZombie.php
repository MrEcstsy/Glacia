<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\envoys;

use pocketmine\nbt\tag\CompoundTag;

final class EnvoyBabyZombie extends EnvoyZombie
{
    protected function initEntity(CompoundTag $nbt): void
    {
        parent::initEntity($nbt);
        $this->setScale(0.58);
    }

    public function getName(): string
    {
        return "Envoy Runt";
    }
}
