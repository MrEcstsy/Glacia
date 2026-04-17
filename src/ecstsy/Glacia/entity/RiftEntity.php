<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity;

use pocketmine\block\VanillaBlocks;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\item\VanillaItems;
use pocketmine\math\AxisAlignedBB;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\scheduler\TaskHandler;
use pocketmine\Server;
use pocketmine\utils\TextFormat as C;
use pocketmine\world\particle\HugeExplodeParticle;
use pocketmine\world\sound\BlockBreakSound;

class RiftEntity extends Living {

    private const NETWORK_ID = EntityIds::ENDER_CRYSTAL;

    /** @var float $width */
    public float $width = 1;

    /** @var float $height */
    private float $height = 2.0;

    private array $damageTracker = [];

    public TaskHandler $task;

    private int $tickCounter = 0;

    public function initEntity(CompoundTag $nbt): void {
        $this->setNameTagAlwaysVisible(true);
        $this->setNameTagVisible(true);
        $this->setMaxHealth(2500);
        $this->setHealth(2500);
        $this->setNameTag($this->getNameTag());
        $this->setScale(2);
        $this->setNoClientPredictions(true);
        parent::initEntity($nbt);
    }

    public function getName(): string {
        return "Rift";
    }

    public function getNameTag(): string {
        return C::colorize("&l&b&k|&r &b&lR&3&li&9&lf&5&lt &d&lC&b&lo&3&lr&9&le &r&7⚡\n" . $this->getHealthBar());
    }

    protected function getInitialSizeInfo(): EntitySizeInfo
    {
        return new EntitySizeInfo($this->height, $this->width);
    }

    public static function getNetworkTypeId(): string {
        return self::NETWORK_ID;
    }

    public function getInitialDragMultiplier(): float {
        return 0.05;
    }

    public function getInitialGravity(): float {
        return 0.08;
    }

    public function entityBaseTick(int $tickDiff = 1): bool {
        parent::entityBaseTick($tickDiff);
        $this->setNameTag($this->getNameTag());
        return true;
    }

    public function getHealthBar(): string {
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

    public function spawnDeathParticles(): void {
        $world = $this->getWorld();
        $pos = $this->getPosition();

        $world->addParticle($pos->asVector3(), new HugeExplodeParticle());
    }

    public function kill(): void
    {
        parent::kill();

        $this->spawnDeathParticles();
        $this->getWorld()->addSound($this->getPosition()->asVector3(), new BlockBreakSound(VanillaBlocks::GLASS()));

        if ($this->task !== null) {
            $this->task->cancel();
        }

        arsort($this->damageTracker);

        $topDamagers = array_slice($this->damageTracker, 0, 3, true);

        $rewardIndex = 0;
        foreach ($topDamagers as $playerName => $damage) {
            if ($rewardIndex >= 3) {
                break;
            }
            $rewardIndex++;

            if ($player = Server::getInstance()->getPlayerExact($playerName)) {
                $reward = VanillaItems::DIAMOND()->setCount(mt_rand(1, 3));
                $player->getInventory()->addItem($reward);

                $player->sendMessage(C::colorize("&r&l&b[Victory] &r&7You were among the rift's top defenders!"));
                $player->sendMessage(C::colorize("&r&7Damage dealt: &a" . number_format((int)$damage) . "&r"));
            }
        }

        $names = ['—', '—', '—'];
        $damages = [0, 0, 0];
        $i = 0;
        foreach ($topDamagers as $playerName => $damage) {
            $names[$i] = $playerName;
            $damages[$i] = (int)$damage;
            $i++;
        }

        $messages = [
            "&r&l&d< &fRift Sealed &d>",
            "&r&7&oThe astral breach collapses into a cascade of starlight.",
            "&r&7&oShimmering motes of void-energy scatter and fade across the sky.",
            "&r&7&oFor a heartbeat, Glacia holds its breath as the wound mends.",
            "&r&fTop defenders: &b{top1} &7(&a{dmg1}&7) &f, &b{top2} &7(&a{dmg2}&7) &f, &b{top3} &7(&a{dmg3}&7).",
            "&r&7&lRewards have been bestowed upon those who stood tallest.",
        ];

        foreach ($messages as $message) {
            $line = str_replace(
                ['{top1}','{dmg1}','{top2}','{dmg2}','{top3}','{dmg3}'],
                [$names[0], number_format($damages[0]), $names[1], number_format($damages[1]), $names[2], number_format($damages[2])],
                $message
            );

            foreach (Server::getInstance()->getOnlinePlayers() as $player) {
                $player->sendMessage(C::colorize($line));
            }
        }


        $this->damageTracker = [];
    }

    public function knockBack(float $x = 0, float $z = 0, float $force = 0, ?float $verticalLimit = 0): void
    {
    }

    public function getBoundingBox(): AxisAlignedBB {
        return $this->boundingBox;
    }
}