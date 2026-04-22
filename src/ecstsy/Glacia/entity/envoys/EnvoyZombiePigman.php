<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\envoys;

use ecstsy\MartianUtilities\entity\BaseHostileEntity;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class EnvoyZombiePigman extends BaseHostileEntity
{
    protected int $aiInterval = 2;

    use EnvoyGuardTrait;

    protected function getInitialSizeInfo(): EntitySizeInfo
    {
        return new EntitySizeInfo(1.8, 0.6);
    }

    public static function getNetworkTypeId(): string
    {
        return EntityIds::ZOMBIE_PIGMAN;
    }

    public function getName(): string
    {
        return "Envoy Pigman";
    }

    public function attack(EntityDamageEvent $source): void
    {
        $immune = [EntityDamageEvent::CAUSE_LAVA, EntityDamageEvent::CAUSE_FIRE, EntityDamageEvent::CAUSE_FIRE_TICK];

        if (in_array($source->getCause(), $immune, true)) {
            $source->cancel();
            return;
        }
        parent::attack($source);
    }

    public function getDrops(): array
    {
        return [
            VanillaBlocks::GOLD()->asItem()->setCount(mt_rand(1, 2)),
        ];
    }

    public function getXpDropAmount(): int
    {
        return 5;
    }

    protected function getMoveSpeed(): float
    {
        return 0.25;
    }

    protected function getAttackReach(): float
    {
        return 2.1;
    }

    protected function getAttackDamage(): float
    {
        return 7.0;
    }
}
