<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\boss\bosses;

use ecstsy\Glacia\entity\boss\ability\AstralEchoStorm;
use ecstsy\Glacia\entity\boss\ability\PhaseShift;
use ecstsy\Glacia\entity\boss\ability\RealityFracture;
use ecstsy\Glacia\entity\boss\ability\VoidLance;
use ecstsy\Glacia\entity\boss\BossEntity;
use ecstsy\Glacia\entity\boss\minions\BossMinionEnderman;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\CustomParticleEffectManager;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\entity\Attribute;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\event\entity\EntityDamageEvent;
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

    protected function getBossDefaultConfig(): array
    {
        return [
            "stats" => [
                "max-health" => 2500.0,
                "melee-reach" => 3.4,
                "target-range" => 30.0,
                "follow-range" => 34.0,
                "knockback-resistance" => 0.95,
                "movement-speed" => [
                    "phase-1" => 0.28,
                    "phase-2" => 0.34,
                    "phase-3" => 0.40,
                ],
                "melee-damage" => [
                    "phase-1" => 18.0,
                    "phase-2" => 24.0,
                    "phase-3" => 31.0,
                ],
            ],
            "minions" => [
                "phase-2" => [
                    "name" => "&r&dAstral Riftguard",
                    "health" => 112,
                    "damage" => 7.5,
                    "speed" => 0.27,
                    "target-range" => 26.0,
                    "attack-reach" => 2.2,
                    "attack-cooldown" => 11,
                ],
                "phase-3" => [
                    "name" => "&r&5Sovereign Riftguard",
                    "health" => 138,
                    "damage" => 9.5,
                    "speed" => 0.30,
                    "target-range" => 26.0,
                    "attack-reach" => 2.2,
                    "attack-cooldown" => 9,
                ],
            ],
            "rewards" => [
                "top-damagers" => 5,
                "ranks" => [
                    1 => ["money" => 350000, "items" => []],
                    2 => ["money" => 225000, "items" => []],
                    3 => ["money" => 125000, "items" => []],
                    4 => ["money" => 50000, "items" => []],
                    5 => ["money" => 50000, "items" => []],
                ],
            ],
        ];
    }

    protected function getInitialSizeInfo(): EntitySizeInfo
    {
        return new EntitySizeInfo(2.4, 1.0);
    }

    protected function initEntity(CompoundTag $nbt): void
    {
        parent::initEntity($nbt);

        $maxHealth = (int) round($this->getConfiguredMaxHealth(2500.0));
        $this->setMaxHealth($maxHealth);
        $this->setHealth($maxHealth);

        $attr = $this->getAttributeMap();
        $attr->get(Attribute::MOVEMENT_SPEED)->setValue($this->getConfiguredMovementSpeed(1, 0.28));
        $attr->get(Attribute::FOLLOW_RANGE)->setValue($this->getConfiguredFollowRange(34.0));
        $attr->get(Attribute::KNOCKBACK_RESISTANCE)->setValue($this->getConfiguredKnockbackResistance(0.95));

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
        return $this->getConfiguredMeleeReach(3.4);
    }

    protected function getCloseRangeOrbitBuffer(): float
    {
        return 0.08;
    }

    protected function getMeleeFacingDotThreshold(): float
    {
        return 0.68;
    }

    protected function getMeleeDamage(): float
    {
        return $this->getConfiguredMeleeDamage([
            1 => 18.0,
            2 => 24.0,
            3 => 31.0,
        ]);
    }

    protected function getMinimumUnmitigatedDamage(float $baseDamage, int $cause): float
    {
        return match ($cause) {
            EntityDamageEvent::CAUSE_ENTITY_ATTACK => min(5.5, max(1.0, $baseDamage * 0.22)),
            EntityDamageEvent::CAUSE_MAGIC => min(4.25, max(0.75, $baseDamage * 0.16)),
            default => min(3.0, max(0.45, $baseDamage * 0.10)),
        };
    }

    protected function doMeleeAttack(): void
    {
        if (!$this->target instanceof Player) {
            return;
        }

        $this->broadcastEntityAnimation("attack", "ctrl_action", "default", 0.1);

        $event = $this->dealBossTrueDamage($this->target, $this->getMeleeDamage(), EntityDamageEvent::CAUSE_ENTITY_ATTACK);

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
                $attr->get(Attribute::MOVEMENT_SPEED)->setValue($this->getConfiguredMovementSpeed(2, 0.34)),
                PlayerUtils::playSound($this, "mob.iron_golem.hit"),
            ],
            3 => [
                $attr->get(Attribute::MOVEMENT_SPEED)->setValue($this->getConfiguredMovementSpeed(3, 0.40)),
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

    protected function getMinionIntermissionSpec(int $phase): array
    {
        return $this->getConfiguredMinionSpec($phase, [
            "class" => BossMinionEnderman::class,
            "name" => $phase >= 3 ? "&r&5Sovereign Riftguard" : "&r&dAstral Riftguard",
            "health" => $phase >= 3 ? 138 : 112,
            "damage" => $phase >= 3 ? 9.5 : 7.5,
            "speed" => $phase >= 3 ? 0.30 : 0.27,
            "target_range" => 26.0,
            "attack_reach" => 2.2,
            "attack_cooldown" => $phase >= 3 ? 9 : 11,
        ]);
    }

    protected function entityBaseTick(int $tickDiff = 1): bool
    {
        $hasUpdate = parent::entityBaseTick($tickDiff);

        if (($this->ticksLived % 10) === 0) {
            $center = $this->getPosition()->asVector3()->add(0, 0.15, 0);
            CustomParticleEffectManager::stackedRing(
                $this->getWorld(),
                $center,
                1.35,
                CustomParticleEffectManager::WARNING_RING,
                2,
                12,
                0.22,
                44.0
            );
            CustomParticleEffectManager::column(
                $this->getWorld(),
                $center->add(0, 0.25, 0),
                CustomParticleEffectManager::ASTRAL_PULSE,
                1.7,
                6,
                44.0
            );
        }

        return $hasUpdate;
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
        $this->rewardConfiguredTopDamagers(
            "&r&l&5Astral Sovereign Defeated!",
            "&5",
            "Astral Sovereign",
            [
                "boss_id" => $this->getBossConfigId(),
                "boss_name" => strtolower($this->getName()),
            ]
        );
    }

    private function rewardPlayer(Player $player, int $rank, float $damage): void
    {
        $coins = match ($rank) {
            1 => 350000,
            2 => 225000,
            3 => 125000,
            default => 50000,
        };

        if ($rank === 1) {
            Loader::getQuestBoardManager()->recordProgress($player, "boss_top_damage", 1);
        }

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
