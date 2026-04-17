<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\boss\bosses;

use ecstsy\Glacia\entity\boss\ability\AstralFracture;
use ecstsy\Glacia\entity\boss\ability\AstralMark;
use ecstsy\Glacia\entity\boss\ability\AstralOverload;
use ecstsy\Glacia\entity\boss\ability\AstralPull;
use ecstsy\Glacia\entity\boss\BossEntity;
use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\entity\Attribute;
use pocketmine\entity\Location;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class GlacialWarden extends BossEntity {

    /** @var array<int, array{stacks:int, expire:int}> */
    private array $frostStacks = [];

    private const FROST_STACK_EXPIRE = 80; // ticks until stacks fall off
    private const FROST_STACK_MAX = 5;

    protected function initAbilities(): void {
        $this->abilities = [
            new AstralMark(),
            new AstralPull(),
            new AstralFracture(),
            new AstralOverload(),
        ];
    }

    public static function getNetworkTypeId(): string {
        return EntityIds::STRAY;
    }

    public function getName(): string {
        return "Glacial Warden";
    }

    protected function getInitialSizeInfo(): EntitySizeInfo {
        return new EntitySizeInfo(2.4, 0.9);
    }

    protected function initEntity(CompoundTag $nbt): void {
        parent::initEntity($nbt);

        $this->setMaxHealth(2000);
        $this->setHealth(2000);

        $attr = $this->getAttributeMap();
        $attr->get(Attribute::MOVEMENT_SPEED)->setValue(0.26);
        $attr->get(Attribute::FOLLOW_RANGE)->setValue(32);
        $attr->get(Attribute::KNOCKBACK_RESISTANCE)->setValue(0.9);

        $this->setNameTagAlwaysVisible(true);
    }

    protected function onPhaseChange(int $phase): void {
        $attr = $this->getAttributeMap();
        $this->updateNameTag();

        match ($phase) {
            2 => [
                $attr->get(Attribute::MOVEMENT_SPEED)->setValue(0.35),
                PlayerUtils::playSound($this, "mob.wither.spawn"),
            ],
            3 => [
                $attr->get(Attribute::MOVEMENT_SPEED)->setValue(0.42),
                PlayerUtils::playSound($this, "mob.wither.ambient"),
            ],
            default => null,
        };
    }

    public function getBossName(): string {
        return match ($this->getPhase()) {
            2 => "&bGlacial Warden &7(Enraged)",
            3 => "&cGlacial Warden &7(Ascended)",
            default => "&3Glacial Warden",
        };
    }

    protected function rewardPlayers(): void {
        $top = $this->getTopDamagers(5);

        if (empty($top)) {
            return;
        }

        $rank = 1;
        foreach ($top as $playerId => $damage) {
            $player = $this->getWorld()->getServer()->getPlayerExact($playerId);
            if (!$player instanceof Player) {
                continue;
            }

            $this->rewardPlayer($player, $rank, $damage);
            $rank++;
        }

        $this->announceWinners($top);
    }

    protected function rewardPlayer(Player $player, int $rank, float $damage): void {
        $coins = match ($rank) {
            1 => 250000,
            2 => 150000,
            3 => 75000,
            default => 25000,
        };

        Loader::getPlayerManager()
            ->getSession($player)
            ->addBalance($coins);

        $player->sendMessage(
            C::colorize("&r&l&a[Boss] &r&aYou placed #{$rank} and earned &f$" . number_format($coins))
        );

        PlayerUtils::playSound($player, "random.levelup");
    }

    protected function announceWinners(array $top): void {
        $lines = [];
        $rank = 1;

        foreach ($top as $playerId => $damage) {
            $player = $this->getWorld()->getServer()->getPlayerExact($playerId);
            if (!$player) continue;

            $lines[] = C::colorize(
                "&r&7#{$rank} &f{$player->getName()} &7- &b" . number_format((int)$damage) . " dmg"
            );
            $rank++;
        }

        foreach ($this->getWorld()->getPlayers() as $player) {
            $player->sendMessage(C::colorize("&r&l&3Glacial Warden Defeated!"));
            foreach ($lines as $line) {
                $player->sendMessage($line);
            }
        }
    }

}
