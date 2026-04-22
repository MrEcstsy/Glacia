<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\envoys;

use ecstsy\MartianUtilities\entity\BaseHostileEntity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class EnvoyGhast extends BaseHostileEntity
{
    protected int $aiInterval = 2;

    use EnvoyGuardTrait;

    protected function getInitialSizeInfo(): EntitySizeInfo
    {
        return new EntitySizeInfo(4.0, 4.0);
    }

    public static function getNetworkTypeId(): string
    {
        return EntityIds::GHAST;
    }

    public function getName(): string
    {
        return "Envoy Ghast";
    }

    public function getDrops(): array
    {
        return [
            VanillaItems::GHAST_TEAR()->setCount(mt_rand(0, 1)),
        ];
    }

    public function getXpDropAmount(): int
    {
        return 10;
    }

    protected function getMoveSpeed(): float
    {
        return 0.20;
    }

    protected function getTargetRange(): float
    {
        return 20.0;
    }

    protected function getAttackReach(): float
    {
        return 3.0;
    }

    protected function getAttackDamage(): float
    {
        return 8.0;
    }
}
