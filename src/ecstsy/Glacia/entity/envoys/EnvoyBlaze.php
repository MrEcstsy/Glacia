<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\envoys;

use ecstsy\MartianUtilities\entity\BaseHostileEntity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;

final class EnvoyBlaze extends BaseHostileEntity
{
    protected int $aiInterval = 2;

    use EnvoyGuardTrait;

    protected function initEntity(CompoundTag $nbt): void
    {
        parent::initEntity($nbt);
        $this->getNetworkProperties()->setInt(EntityMetadataProperties::VARIANT, 0);
    }

    protected function getInitialSizeInfo(): EntitySizeInfo
    {
        return new EntitySizeInfo(1.8, 0.5);
    }

    public static function getNetworkTypeId(): string
    {
        return EntityIds::BLAZE;
    }

    public function getName(): string
    {
        return "Envoy Blaze";
    }

    public function getDrops(): array
    {
        return [
            VanillaItems::BLAZE_ROD()->setCount(mt_rand(1, 2)),
        ];
    }

    public function getXpDropAmount(): int
    {
        return mt_rand(20, 25);
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

    protected function getMoveSpeed(): float
    {
        return 0.24;
    }

    protected function getAttackReach(): float
    {
        return 2.2;
    }

    protected function getAttackDamage(): float
    {
        return 7.0;
    }
}
