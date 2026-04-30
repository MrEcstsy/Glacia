<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\boss\ability;

use ecstsy\Glacia\entity\boss\BossAbility;
use ecstsy\Glacia\entity\boss\BossEntity;
use ecstsy\Glacia\entity\boss\BossParticles;
use ecstsy\Glacia\Loader;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\TextFormat as C;

/**
 * Astral Echo Storm — phase 2+ ult. Locks four stationary "echo" zones
 * in a square around the boss and detonates them on a fixed cadence
 * (4 pulses, 20 ticks apart). Each pulse damages everything inside 3
 * blocks of an echo. Forces the raid to weave between safe pockets — a
 * spatial puzzle Astral Devourer's single-source AoE doesn't create.
 */
final class AstralEchoStorm extends BossAbility
{
    private const ECHO_RING_RADIUS = 6.0;
    private const ECHO_DAMAGE_RADIUS = 3.0;
    private const PULSE_COUNT = 4;
    private const PULSE_INTERVAL_TICKS = 20;

    public function __construct()
    {
        parent::__construct(480);
    }

    protected function checkConditions(BossEntity $boss): bool
    {
        return $boss->getPhase() >= 2 && $boss->hasTarget();
    }

    protected function onCast(BossEntity $boss): void
    {
        $boss->broadcastAbility(C::colorize("&r&l&5Astral Sovereign &r&7anchors astral echoes in the air!"), 36);
        $boss->playAbilitySound("mob.wither.spawn", 1.2, 0.6, 36);

        $center = $boss->getPosition()->asVector3();
        $world = $boss->getWorld();

        $echoes = [];
        for ($i = 0; $i < 4; $i++) {
            $angle = ($i * (M_PI / 2)) + (M_PI / 4);
            $echoes[] = new Vector3(
                $center->x + (cos($angle) * self::ECHO_RING_RADIUS),
                $center->y,
                $center->z + (sin($angle) * self::ECHO_RING_RADIUS)
            );
        }

        $damage = match ($boss->getPhase()) {
            2 => 5.0,
            3 => 7.5,
            default => 5.0,
        };

        $scheduler = Loader::getInstance()->getScheduler();

        $totalLifeTicks = self::PULSE_COUNT * self::PULSE_INTERVAL_TICKS;

        // Anchor markers: every 5 ticks throughout the storm, redraw a small
        // damage ring around each of the four echoes so players can see
        // exactly where the safe gaps are.
        for ($t = 0; $t < $totalLifeTicks; $t += 5) {
            $scheduler->scheduleDelayedTask(new ClosureTask(
                static function () use ($world, $echoes): void {
                    foreach ($echoes as $echo) {
                        BossParticles::ring(
                            $world,
                            $echo,
                            self::ECHO_DAMAGE_RADIUS,
                            BossParticles::warningParticle(),
                            18,
                            0.05
                        );
                        BossParticles::ring(
                            $world,
                            $echo,
                            self::ECHO_DAMAGE_RADIUS * 0.4,
                            BossParticles::portalParticle(),
                            10,
                            0.7
                        );
                    }
                }
            ), $t);
        }

        for ($pulse = 1; $pulse <= self::PULSE_COUNT; $pulse++) {
            $delay = $pulse * self::PULSE_INTERVAL_TICKS;
            $isFinal = ($pulse === self::PULSE_COUNT);

            $scheduler->scheduleDelayedTask(new ClosureTask(
                static function () use ($boss, $world, $echoes, $damage, $isFinal): void {
                    if ($boss->isClosed() || !$boss->isAlive() || $boss->getWorld() !== $world) {
                        return;
                    }

                    $boss->playAbilitySound("mob.endermen.portal", 0.8, 1.6, 36);

                    foreach ($echoes as $echo) {
                        BossParticles::burst(
                            $world,
                            $echo,
                            BossParticles::portalParticle(),
                            22,
                            self::ECHO_DAMAGE_RADIUS * 0.7,
                            1.0
                        );
                    }

                    $hitRadiusSq = self::ECHO_DAMAGE_RADIUS * self::ECHO_DAMAGE_RADIUS;

                    foreach ($world->getPlayers() as $player) {
                        if (!$player->isAlive() || $player->isSpectator()) {
                            continue;
                        }

                        $playerPos = $player->getPosition();
                        $hit = false;

                        foreach ($echoes as $echo) {
                            $dx = $playerPos->x - $echo->x;
                            $dy = $playerPos->y - $echo->y;
                            $dz = $playerPos->z - $echo->z;
                            $distSq = ($dx * $dx) + ($dy * $dy * 0.5) + ($dz * $dz);
                            if ($distSq <= $hitRadiusSq) {
                                $hit = true;
                                break;
                            }
                        }

                        if (!$hit) {
                            continue;
                        }

                        $player->attack(new EntityDamageByEntityEvent($boss, $player, EntityDamageEvent::CAUSE_MAGIC, $damage, [], 0.0, 0.0));

                        if ($isFinal) {
                            $player->getEffects()->add(new EffectInstance(VanillaEffects::WEAKNESS(), 80, 0));
                        }
                    }

                    if ($isFinal) {
                        foreach ($echoes as $echo) {
                            $world->addParticle($echo, BossParticles::explosionParticle());
                        }
                        $boss->broadcastAbility(C::colorize("&r&5The echoes collapse inward."), 36);
                    }
                }
            ), $delay);
        }
    }
}
