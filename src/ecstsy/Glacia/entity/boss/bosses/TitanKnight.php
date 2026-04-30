<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\boss\bosses;

use ecstsy\Glacia\entity\boss\ability\AstralEchoStorm;
use ecstsy\Glacia\entity\boss\ability\PhaseShift;
use ecstsy\Glacia\entity\boss\ability\RealityFracture;
use ecstsy\Glacia\entity\boss\ability\VoidLance;
use ecstsy\Glacia\entity\boss\BossEntity;
use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\entity\Attribute;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class TitanKnight extends BossEntity
{
    public static function getNetworkTypeId(): string
    {
        return "glacia:titan_knight";
    }

    public function getName(): string
    {
        return "Astral Sovereign";
    }

    protected function getInitialSizeInfo(): EntitySizeInfo
    {
        return new EntitySizeInfo(2.4, 1.0);
    }

    protected function initEntity(CompoundTag $nbt): void
    {
        parent::initEntity($nbt);

        $this->setMaxHealth(2500);
        $this->setHealth(2500);

        $attr = $this->getAttributeMap();
        $attr->get(Attribute::MOVEMENT_SPEED)->setValue(0.24);
        $attr->get(Attribute::FOLLOW_RANGE)->setValue(34);
        $attr->get(Attribute::KNOCKBACK_RESISTANCE)->setValue(0.95);

        $this->setNameTagAlwaysVisible(true);
    }

    protected function initAbilities(): void
    {
        $this->abilities = [
            new PhaseShift(),
            new VoidLance(),
            new RealityFracture(),
            new AstralEchoStorm(),
        ];
    }

    protected function canMeleeAttack(): bool
    {
        if (!$this->hasTarget() || $this->attackCooldown > 0) {
            return false;
        }

        $reach = $this->getMeleeReach();
        return $this->getPosition()->distanceSquared($this->target->getPosition()) <= ($reach ** 2);
    }

    protected function getMeleeReach(): float
    {
        return 3.4;
    }

    protected function getMeleeDamage(): float
    {
        return match ($this->phase) {
            1 => 8.0,
            2 => 10.0,
            3 => 12.5,
            default => 8.0,
        };
    }

    protected function doMeleeAttack(): void
    {
        if (!$this->target instanceof Player) {
            return;
        }

        $this->broadcastEntityAnimation("attack", "ctrl_action", "default", 0.1);

        $event = new EntityDamageByEntityEvent(
            $this,
            $this->target,
            EntityDamageByEntityEvent::CAUSE_ENTITY_ATTACK,
            $this->getMeleeDamage()
        );

        $this->target->attack($event);

        if (!$event->isCancelled()) {
            $this->attackCooldown = $this->isOverloaded() ? 10 : 16;
            PlayerUtils::playSound($this->target, "mob.iron_golem.hit", 0.8, 0.8);
        }
    }

    protected function onPhaseChange(int $phase): void
    {
        $attr = $this->getAttributeMap();
        $this->updateNameTag();

        match ($phase) {
            2 => [
                $attr->get(Attribute::MOVEMENT_SPEED)->setValue(0.30),
                PlayerUtils::playSound($this, "mob.iron_golem.hit"),
            ],
            3 => [
                $attr->get(Attribute::MOVEMENT_SPEED)->setValue(0.36),
                PlayerUtils::playSound($this, "mob.wither.spawn"),
            ],
            default => null,
        };
    }

    public function getBossName(): string
    {
        return match ($this->getPhase()) {
            2 => "&dAstral Sovereign &r&7(Awakened)",
            3 => "&5Astral Sovereign &r&7(Unbound)",
            default => "&8Astral Sovereign",
        };
    }

    protected function shouldJumpAhead(): bool
    {
        // The Sovereign visually hovers via animations, but physically it
        // still needs to step over terrain — otherwise it lodges itself in
        // walls / holes and can't recover. Defer to the parent's terrain
        // check so it climbs solid blocks in front of it.
        return parent::shouldJumpAhead();
    }

    protected function rewardPlayers(): void
    {
        $top = $this->getTopDamagers(5);
        if ($top === []) {
            return;
        }

        $rank = 1;
        foreach ($top as $playerId => $damage) {
            $player = $this->getWorld()->getServer()->getPlayerExact($playerId);
            if ($player instanceof Player) {
                $this->rewardPlayer($player, $rank, $damage);
                $rank++;
            }
        }

        $this->announceWinners($top);
    }

    private function rewardPlayer(Player $player, int $rank, float $damage): void
    {
        $coins = match ($rank) {
            1 => 350000,
            2 => 225000,
            3 => 125000,
            default => 50000,
        };

        Loader::getPlayerManager()->getSession($player)?->addBalance($coins);
        $player->sendMessage(C::colorize("&r&l&a[Boss] &r&aYou placed #{$rank} on the &5Astral Sovereign &aand earned &f$" . number_format($coins)));
        PlayerUtils::playSound($player, "random.levelup");
    }

    private function announceWinners(array $top): void
    {
        $lines = [];
        $rank = 1;

        foreach ($top as $playerId => $damage) {
            $player = $this->getWorld()->getServer()->getPlayerExact($playerId);
            if (!$player instanceof Player) {
                continue;
            }

            $lines[] = C::colorize("&r&7#{$rank} &f{$player->getName()} &7- &5" . number_format((int) $damage) . " dmg");
            $rank++;
        }

        foreach ($this->getWorld()->getPlayers() as $player) {
            $player->sendMessage(C::colorize("&r&l&5Astral Sovereign Defeated!"));
            foreach ($lines as $line) {
                $player->sendMessage($line);
            }
        }
    }

    public function jump(): void
    {
        if (!$this->onGround) {
            return;
        }

        $this->motion->y = 0.48;
    }
}
