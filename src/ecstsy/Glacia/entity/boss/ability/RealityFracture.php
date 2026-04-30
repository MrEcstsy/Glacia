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
 * Reality Fracture — delayed ground rupture at the target's CURRENT
 * location. After a 30-tick telegraph the captured tile and everything
 * within a 4-block radius detonates with heavy magic damage and
 * blindness/slowness. Players survive by moving out of the marked area
 * during the telegraph, the inverse of a radial pull-in.
 */
final class RealityFracture extends BossAbility
{
    private const TELEGRAPH_TICKS = 30;

    public function __construct()
    {
        parent::__construct(220);
    }

    protected function checkConditions(BossEntity $boss): bool
    {
        return $boss->getTarget() !== null;
    }

    protected function onCast(BossEntity $boss): void
    {
        $target = $boss->getTarget();
        if ($target === null) {
            return;
        }

        $epicenter = $target->getPosition()->asVector3();
        $world = $boss->getWorld();

        $phase = $boss->getPhase();
        $radius = match ($phase) {
            1 => 4.0,
            2 => 4.5,
            3 => 5.0,
            default => 4.0,
        };
        $damage = match ($phase) {
            1 => 11.0,
            2 => 16.0,
            3 => 22.0,
            default => 11.0,
        };
        $blindTicks = match ($phase) {
            1 => 60,
            2 => 80,
            3 => 100,
            default => 60,
        };

        $boss->broadcastAbility(C::colorize("&r&l&5Astral Sovereign &r&7tears reality beneath &d" . $target->getName() . "&7!"), 36);
        $boss->playAbilitySound("mob.endermen.portal", 1.1, 1.4, 36);

        $target->sendActionBarMessage(C::colorize("&c&lMOVE!"));

        $scheduler = Loader::getInstance()->getScheduler();

        // Pulsing warning ring at the locked epicentre. Red dust outlines the
        // danger zone; portal sparkles hint at the cosmic detonation forming.
        for ($t = 2; $t <= self::TELEGRAPH_TICKS; $t += 3) {
            $scheduler->scheduleDelayedTask(new ClosureTask(
                static function () use ($world, $epicenter, $radius): void {
                    BossParticles::ring(
                        $world,
                        $epicenter,
                        $radius,
                        BossParticles::warningParticle(),
                        max(20, (int) ($radius * 6.0)),
                        0.05
                    );
                    BossParticles::ring(
                        $world,
                        $epicenter,
                        $radius * 0.55,
                        BossParticles::portalParticle(),
                        max(12, (int) ($radius * 3.0)),
                        0.4
                    );
                }
            ), $t);
        }

        $scheduler->scheduleDelayedTask(new ClosureTask(
            static function () use ($boss, $world, $epicenter, $radius, $damage, $blindTicks): void {
                if ($boss->isClosed() || !$boss->isAlive() || $boss->getWorld() !== $world) {
                    return;
                }

                $boss->playAbilitySound("random.explode", 1.3, 0.55, 36);
                $boss->broadcastAbility(C::colorize("&r&5The ground shatters with void energy!"), 36);

                $world->addParticle($epicenter, BossParticles::explosionParticle());
                BossParticles::burst(
                    $world,
                    $epicenter,
                    BossParticles::portalParticle(),
                    50,
                    $radius * 0.6,
                    1.2
                );

                $radiusSq = $radius * $radius;

                foreach ($world->getPlayers() as $player) {
                    if (!$player->isAlive() || $player->isSpectator()) {
                        continue;
                    }

                    $delta = $player->getPosition()->subtractVector($epicenter);
                    $distSq = ($delta->x * $delta->x) + ($delta->z * $delta->z);
                    if ($distSq > $radiusSq) {
                        continue;
                    }

                    $player->attack(new EntityDamageByEntityEvent($boss, $player, EntityDamageEvent::CAUSE_MAGIC, $damage, [], 0.0, 0.0));
                    $player->getEffects()->add(new EffectInstance(VanillaEffects::BLINDNESS(), $blindTicks, 0));
                    $player->getEffects()->add(new EffectInstance(VanillaEffects::SLOWNESS(), $blindTicks, 1));

                    $boss->applyBossMotion($player, new Vector3(0, 0.55, 0));
                }
            }
        ), self::TELEGRAPH_TICKS);
    }
}
