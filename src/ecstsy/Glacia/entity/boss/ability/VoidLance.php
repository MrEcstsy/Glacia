<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\boss\ability;

use ecstsy\Glacia\entity\boss\BossAbility;
use ecstsy\Glacia\entity\boss\BossEntity;
use ecstsy\Glacia\entity\boss\BossParticles;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\CustomParticleEffectManager;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\TextFormat as C;

/**
 * Astral Sovereign signature ability: a directional piercing beam.
 *
 * Casts in two stages: a 25-tick channel where the boss locks its forward
 * direction, then a single hit-pulse that strikes every player inside a
 * narrow forward cone. Players dodge by stepping perpendicular to the
 * boss's facing — a mechanic Astral Devourer's radial AoEs can't ask for.
 */
final class VoidLance extends BossAbility
{
    private const CHANNEL_TICKS = 25;
    private const CONE_DOT = 0.92;

    public function __construct()
    {
        parent::__construct(200);
    }

    protected function checkConditions(BossEntity $boss): bool
    {
        return $boss->hasTarget();
    }

    protected function onCast(BossEntity $boss): void
    {
        $boss->triggerGlobalAbilityCooldown(self::CHANNEL_TICKS + 10);
        $boss->broadcastAbility(C::colorize("&r&l&5Astral Sovereign &r&7channels a beam of pure void..."), 36);
        $boss->playAbilitySound("mob.endermen.scream", 1.0, 0.45, 36);

        $forward = $boss->getDirectionVector();
        $forwardLenSq = ($forward->x * $forward->x) + ($forward->z * $forward->z);
        if ($forwardLenSq <= 0.0001) {
            return;
        }
        $forwardLen = sqrt($forwardLenSq);
        $forwardX = $forward->x / $forwardLen;
        $forwardZ = $forward->z / $forwardLen;

        $phase = $boss->getPhase();
        $range = match ($phase) {
            1 => 16.0,
            2 => 19.0,
            3 => 23.0,
            default => 16.0,
        };
        $damage = match ($phase) {
            1 => 16.0,
            2 => 22.0,
            3 => 29.0,
            default => 16.0,
        };
        $witherTicks = match ($phase) {
            1 => 60,
            2 => 100,
            3 => 140,
            default => 60,
        };

        $scheduler = Loader::getInstance()->getScheduler();

        // Telegraphing trail every 4 ticks during the channel: a faint line of
        // portal particles along the boss's forward axis warns players which
        // direction the lance will fire.
        for ($t = 4; $t < self::CHANNEL_TICKS; $t += 4) {
            $scheduler->scheduleDelayedTask(new ClosureTask(
                static function () use ($boss, $forwardX, $forwardZ, $range): void {
                    if ($boss->isClosed() || !$boss->isAlive()) {
                        return;
                    }
                    $origin = $boss->getPosition()->add(0, 1.4, 0);
                    $endPoint = new Vector3(
                        $origin->x + ($forwardX * $range),
                        $origin->y,
                        $origin->z + ($forwardZ * $range)
                    );
                    BossParticles::line(
                        $boss->getWorld(),
                        $origin,
                        $endPoint,
                        BossParticles::portalParticle(),
                        max(8, (int) ($range * 0.65))
                    );
                    CustomParticleEffectManager::lineVariants(
                        $boss->getWorld(),
                        $origin,
                        $endPoint,
                        [
                            CustomParticleEffectManager::ASTRAL_BEAM,
                            CustomParticleEffectManager::ASTRAL_BEAM_CORE,
                            CustomParticleEffectManager::ASTRAL_BEAM_SPARK,
                        ],
                        max(16, (int) ($range * 1.3)),
                        48.0
                    );
                    CustomParticleEffectManager::pulse(
                        $boss->getWorld(),
                        $origin,
                        CustomParticleEffectManager::WARNING_RING,
                        CustomParticleEffectManager::ASTRAL_PULSE,
                        1.1,
                        12,
                        6,
                        48.0
                    );
                }
            ), $t);
        }

        $scheduler->scheduleDelayedTask(new ClosureTask(
            static function () use ($boss, $forwardX, $forwardZ, $range, $damage, $witherTicks): void {
                if ($boss->isClosed() || !$boss->isAlive()) {
                    return;
                }

                $boss->broadcastAbility(C::colorize("&r&l&5Astral Sovereign &r&dunleashes the lance!"), 36);
                $boss->playAbilitySound("mob.wither.shoot", 1.4, 0.65, 36);

                $bossPos = $boss->getPosition();
                $origin = $bossPos->add(0, 1.4, 0);
                $endPoint = new Vector3(
                    $origin->x + ($forwardX * $range),
                    $origin->y,
                    $origin->z + ($forwardZ * $range)
                );
                BossParticles::line(
                    $boss->getWorld(),
                    $origin,
                    $endPoint,
                    BossParticles::portalParticle(),
                    max(10, (int) ($range * 0.9))
                );
                CustomParticleEffectManager::lineVariants(
                    $boss->getWorld(),
                    $origin,
                    $endPoint,
                    [
                        CustomParticleEffectManager::ASTRAL_BEAM,
                        CustomParticleEffectManager::ASTRAL_BEAM_CORE,
                        CustomParticleEffectManager::ASTRAL_BEAM_SPARK,
                        CustomParticleEffectManager::ASTRAL_BEAM_CORE,
                    ],
                    max(28, (int) ($range * 2.4)),
                    56.0
                );
                CustomParticleEffectManager::columnVariants(
                    $boss->getWorld(),
                    $origin,
                    [
                        CustomParticleEffectManager::ASTRAL_PULSE,
                        CustomParticleEffectManager::ASTRAL_BEAM_SPARK,
                    ],
                    2.0,
                    7,
                    56.0
                );
                BossParticles::burst(
                    $boss->getWorld(),
                    $origin,
                    BossParticles::portalParticle(),
                    8,
                    0.35,
                    0.22
                );
                CustomParticleEffectManager::burstVariants(
                    $boss->getWorld(),
                    $origin,
                    [
                        CustomParticleEffectManager::ASTRAL_BEAM_CORE,
                        CustomParticleEffectManager::ASTRAL_BEAM_SPARK,
                        CustomParticleEffectManager::ASTRAL_PULSE,
                    ],
                    22,
                    0.60,
                    0.48,
                    56.0
                );
                CustomParticleEffectManager::burstVariants(
                    $boss->getWorld(),
                    $endPoint,
                    [
                        CustomParticleEffectManager::ASTRAL_PULSE,
                        CustomParticleEffectManager::ASTRAL_BEAM_CORE,
                        CustomParticleEffectManager::ASTRAL_BEAM_SPARK,
                    ],
                    20,
                    0.8,
                    0.55,
                    56.0
                );

                $rangeSq = $range * $range;

                foreach ($boss->getPlayersInRange($range) as $player) {
                    $rel = $player->getPosition()->subtractVector($bossPos);
                    $relLenSq = ($rel->x * $rel->x) + ($rel->z * $rel->z);
                    if ($relLenSq <= 0.0001 || $relLenSq > $rangeSq) {
                        continue;
                    }

                    $relLen = sqrt($relLenSq);
                    $dot = (($rel->x / $relLen) * $forwardX) + (($rel->z / $relLen) * $forwardZ);
                    if ($dot < self::CONE_DOT) {
                        continue;
                    }

                    $boss->dealBossTrueDamage($player, $damage);
                    $player->getEffects()->add(new EffectInstance(VanillaEffects::WITHER(), $witherTicks, 1));

                    $boss->applyBossMotion($player, new Vector3($forwardX * 0.85, 0.30, $forwardZ * 0.85));
                }
            }
        ), self::CHANNEL_TICKS);
    }
}
