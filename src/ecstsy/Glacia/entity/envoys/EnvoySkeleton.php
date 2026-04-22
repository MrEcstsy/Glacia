<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\envoys;

use ecstsy\MartianUtilities\entity\BaseHostileEntity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class EnvoySkeleton extends BaseHostileEntity
{
    protected int $aiInterval = 2;

    use EnvoyGuardTrait;

    protected function getInitialSizeInfo(): EntitySizeInfo
    {
        return new EntitySizeInfo(1.9, 0.6);
    }

    public static function getNetworkTypeId(): string
    {
        return EntityIds::SKELETON;
    }

    public function getName(): string
    {
        return "Envoy Skeleton";
    }

    public function getDrops(): array
    {
        return [
            VanillaItems::BONE()->setCount(mt_rand(1, 2)),
            VanillaItems::ARROW()->setCount(mt_rand(0, 1)),
        ];
    }

    public function getXpDropAmount(): int
    {
        return 5;
    }

    protected function getMoveSpeed(): float
    {
        return 0.23;
    }

    protected function getAttackReach(): float
    {
        return 2.0;
    }

    protected function getAttackDamage(): float
    {
        return 6.0;
    }
}
