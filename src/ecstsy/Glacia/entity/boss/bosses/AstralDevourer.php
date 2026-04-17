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

final class AstralDevourer extends BossEntity {

    protected function initAbilities(): void {
        $this->abilities = [
            new AstralMark(),
            new AstralPull(),
            new AstralFracture(),
            new AstralOverload(),
        ];
    }

    public static function getNetworkTypeId(): string {
        return EntityIds::WITHER_SKELETON;
    }

    public function getName(): string {
        return "Astral Devourer";
    }

    protected function getInitialSizeInfo(): EntitySizeInfo {
        return new EntitySizeInfo(2.4, 0.9);
    }

    protected function initEntity(CompoundTag $nbt): void {
        parent::initEntity($nbt);

        $this->setMaxHealth(1500);
        $this->setHealth(1500);

        $attr = $this->getAttributeMap();
        $attr->get(Attribute::MOVEMENT_SPEED)->setValue(0.28);
        $attr->get(Attribute::FOLLOW_RANGE)->setValue(32);
        $attr->get(Attribute::KNOCKBACK_RESISTANCE)->setValue(0.85);

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
            2 => "&dAstral Devourer &7(Enraged)",
            3 => "&cAstral Devourer &7(Ascended)",
            default => "&5Astral Devourer",
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
                "&r&7#{$rank} &f{$player->getName()} &7- &d" . number_format((int)$damage) . " dmg"
            );
            $rank++;
        }

        foreach ($this->getWorld()->getPlayers() as $player) {
            $player->sendMessage(C::colorize("&r&l&5Astral Devourer Defeated!"));
            foreach ($lines as $line) {
                $player->sendMessage($line);
            }
        }
    }

    public function jump(): void {
        if (!$this->onGround) {
            return;
        }

        $this->motion->y = 0.52;
    }
}
