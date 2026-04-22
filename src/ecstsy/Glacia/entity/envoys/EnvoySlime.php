<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\envoys;

use ecstsy\MartianUtilities\entity\BaseHostileEntity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class EnvoySlime extends BaseHostileEntity
{
    protected int $aiInterval = 2;

    use EnvoyGuardTrait;

    protected function getInitialSizeInfo(): EntitySizeInfo
    {
        return new EntitySizeInfo(0.52, 0.52);
    }

    protected function initEntity(CompoundTag $nbt): void
    {
        parent::initEntity($nbt);
        $this->setScale($this->getScale());
    }

    public static function getNetworkTypeId(): string
    {
        return EntityIds::SLIME;
    }

    public function getName(): string
    {
        return "Envoy Slime";
    }

    public function getDrops(): array
    {
        return [
            VanillaItems::SLIMEBALL()->setCount(mt_rand(1, 2)),
        ];
    }

    public function getXpDropAmount(): int
    {
        return 7;
    }

    protected function getMoveSpeed(): float
    {
        return 0.26;
    }

    protected function getAttackReach(): float
    {
        return 1.7;
    }

    protected function getAttackDamage(): float
    {
        return 5.0;
    }
}
