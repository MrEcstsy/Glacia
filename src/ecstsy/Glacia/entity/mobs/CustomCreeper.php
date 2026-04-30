<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\mobs;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\utils\TextFormat;

class CustomCreeper extends Living
{
    protected function initEntity(CompoundTag $nbt): void
    {
        parent::initEntity($nbt);
        $this->updateNameTag();
    }


    protected function getInitialSizeInfo() : EntitySizeInfo{
        return new EntitySizeInfo(1.8, 0.6);
    }

    public static function getNetworkTypeId() : string{
        return EntityIds::CREEPER;
    }


    public function getName() : string{
        return "Creeper";
    }

    public function getDrops() : array{
        return [
            VanillaItems::GUNPOWDER()->setCount(mt_rand(0, 2)),
        ];
    }

    public function getXpDropAmount() : int{
        return 5;
    }

    protected function entityBaseTick(int $tickDiff = 1): bool
    {
        $hasUpdate = parent::entityBaseTick($tickDiff);
        if (($this->ticksLived % 10) === 0) {
            $this->updateNameTag();
        }
        return $hasUpdate;
    }

    protected function updateNameTag(): void
    {
        $health = max(0, (int) ceil($this->getHealth()));
        $max = max(1, (int) ceil($this->getMaxHealth()));
        $hpColor = TextFormat::RED;
        $segments = 10;
        $filled = (int) floor(($health / $max) * $segments);
        $bar = str_repeat("❤", $filled) . TextFormat::GRAY . str_repeat("❤", max(0, $segments - $filled));

        $this->setNameTag(
            TextFormat::GREEN . TextFormat::BOLD . $this->getName() .
            "\n" .
            TextFormat::RESET . $hpColor . $bar . TextFormat::DARK_GRAY . " (" . TextFormat::WHITE . $health . TextFormat::GRAY . "/" . TextFormat::WHITE . $max . TextFormat::DARK_GRAY . ")"
        );
        $this->setNameTagVisible(true);
        $this->setNameTagAlwaysVisible(true);
    }

    public function attack(EntityDamageEvent $source): void
    {
        parent::attack($source);
        $this->updateNameTag();
    }
}