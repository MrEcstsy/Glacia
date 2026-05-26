<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity;

use pocketmine\entity\Attribute;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\Attribute as NetworkAttribute;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\types\entity\PropertySyncData;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

abstract class SpawnerEntity extends Living
{

    private int $stack = 1;

    protected function getInitialSizeInfo(): EntitySizeInfo {
        return new EntitySizeInfo(1, 1, 1);
    }

    public abstract function getName(): string;

    protected function initEntity(CompoundTag $nbt): void {
        parent::initEntity($nbt);

        $this->stack = max(1, $nbt->getInt("stack", 1));
        $this->updateNameTag();
    }

    protected function getInitialDragMultiplier(): float {
        return 0.05;
    }

    protected function getInitialGravity(): float {
        return 0.08;
    }

    public function getStack(): int {
        return $this->stack;
    }

    public function setStack(int $stack): void {
        $this->stack = max(1, $stack);
        $this->updateNameTag();
    }

    protected function entityBaseTick(int $tickDiff = 1): bool
    {
        $hasUpdate = parent::entityBaseTick($tickDiff);

        if (($this->ticksLived % 10) === 0) {
            $this->updateNameTag();
        }

        return $hasUpdate;
    }

    public function kill(): void {
        if ($this->getStack() > 1) {
            $this->setStack($this->getStack() - 1);

            $drops = $this->getDrops();
            $xp = $this->getXpDropAmount();
            $ev = new EntityDeathEvent($this, $drops, $xp);
            $ev->call();

            $world = $this->getPosition()->getWorld();
            $pos = $this->getPosition();
            foreach ($ev->getDrops() as $item) {
                $world->dropItem($pos, $item);
            }

            $world->dropExperience($pos, $ev->getXpDropAmount());

            // Peel removes one “life” from the stack but the same entity still exists; setHealth(0) never
            // zeroes it out in that path, so HP was stuck near death for every remaining stack member.
            $this->setHealth((float) $this->getMaxHealth());
            $this->noDamageTicks = 0;
            $this->updateNameTag();

            return;
        }

        parent::kill();
    }

    protected function sendSpawnPacket(Player $player): void {
        $networkSession = $player->getNetworkSession();
        $networkSession->sendDataPacket(AddActorPacket::create(
            $this->getId(),
            $this->getId(),
            $this->getNetworkTypeId(),
            $this->location->asVector3(),
            $this->getMotion(),
            $this->location->pitch,
            $this->location->yaw,
            $this->location->yaw, //TODO: head yaw
            $this->location->yaw, //TODO: body yaw (wtf mojang?)
            array_map(function (Attribute $attr): NetworkAttribute {
                return new NetworkAttribute($attr->getId(), $attr->getMinValue(), $attr->getMaxValue(), $attr->getValue(), $attr->getDefaultValue(), []);
            }, $this->attributeMap->getAll()),
            $this->getAllNetworkData(),
            new PropertySyncData([], []),
            []
        ));
        $networkSession->getEntityEventBroadcaster()->onMobArmorChange([$networkSession], $this);
    }

    public function updateNameTag(): void {
        $health = max(0, (int) ceil($this->getHealth()));
        $max = max(1, (int) ceil($this->getMaxHealth()));
        $hpColor = TextFormat::RED;
        $segments = 10;
        $filled = (int) floor(($health / $max) * $segments);
        $bar = str_repeat("❤", $filled) . TextFormat::GRAY . str_repeat("❤", max(0, $segments - $filled));

        $this->setNameTag(
            TextFormat::YELLOW . TextFormat::BOLD . $this->getName() . " x" . $this->getStack() .
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

    public function knockBack(float $x, float $z, float $force = self::DEFAULT_KNOCKBACK_FORCE, ?float $verticalLimit = self::DEFAULT_KNOCKBACK_VERTICAL_LIMIT) : void
    {
        // Ignore knockback from hits, projectiles, and explosions. Event listeners may run after we zero
        // knockback in attack(), so overriding this is the reliable fix.
    }

    public function saveNBT() : CompoundTag
    {
        return parent::saveNBT()->setInt("stack", $this->getStack());
    }
}