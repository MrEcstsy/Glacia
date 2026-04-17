<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity;

use ecstsy\MartianUtilities\entity\BaseHostileEntity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

class RiftWalkerEntity extends BaseHostileEntity {

    public const NETWORK_ID = EntityIds::ENDERMAN;

    public float $width = 1;
    public float $height = 2.0;

    /** @var string[] */
    public array $messages = [
        "You cannot stop the Rift...",
        "The void always takes back what it’s owed...",
        "Your struggle is meaningless.",
        "The Rift endures beyond your reach.",
        "Your fate was sealed the moment you looked into the void."
    ];

    public function initEntity(CompoundTag $nbt): void {
        $this->setNameTagAlwaysVisible(true);
        $this->setNameTagVisible(true);
        $this->setMaxHealth(450);
        $this->setHealth(450);
        $this->setNameTag($this->getNameTag());

        parent::initEntity($nbt);
    }
    
    public function getName(): string {
        return "Rift Walker";
    }

    public function getNameTag(): string
    {
        return C::colorize("&l&9R&5i&9f&5t &bW&9a&5l&9k&5e&9r &r&7❄\n" . $this->getHealthBar());
    }

    protected function getInitialSizeInfo(): EntitySizeInfo
    {
        return new EntitySizeInfo($this->height, $this->width);
    }

    public static function getNetworkTypeId(): string
    {
        return self::NETWORK_ID;
    }

    protected function getInitialDragMultiplier(): float {
        return 0.05;
    }

    protected function getInitialGravity(): float {
        return 0.08;
    }

    public function entityBaseTick(int $tickDiff = 1): bool {
        parent::entityBaseTick($tickDiff);
    
        $this->setNameTag($this->getNameTag());
        $this->setNameTagAlwaysVisible(true);
        return true;
    }

    public function kill(): void {
        parent::kill();

        $closestPlayer = $this->findClosestPlayer();
        if ($closestPlayer instanceof Player && !empty($this->messages)) {
            $randomMessage = $this->messages[array_rand($this->messages)];
            $closestPlayer->sendMessage(C::colorize("&d&lRift Walker >> &r&7" . $randomMessage));
        }
    }

    public function getHealthBar(): string
    {
        $health = (int)floor($this->getHealth());
        $maxHealth = (int)floor($this->getMaxHealth());
        $percent = $health / $maxHealth;

        if ($percent <= 0.25) {
            $color = "&c";
        } elseif ($percent <= 0.5) {
            $color = "&e";
        } else {
            $color = "&b";
        }

        return C::colorize("&r&l{$color}❤ &r&f{$health}/{$maxHealth}");
    }
}