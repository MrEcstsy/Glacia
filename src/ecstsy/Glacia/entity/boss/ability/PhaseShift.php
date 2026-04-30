<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\boss\ability;

use ecstsy\Glacia\entity\boss\BossAbility;
use ecstsy\Glacia\entity\boss\BossEntity;
use ecstsy\Glacia\entity\boss\BossParticles;
use ecstsy\Glacia\Loader;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\Location;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\TextFormat as C;

/**
 * Phase Shift — Astral Sovereign fragments out, becomes briefly damage
 * resistant + invisible, and reappears at a position chosen by its current
 * threat assessment.
 *
 *   AGGRESSIVE  (boss healthy AND target weak)
 *               → reforms BEHIND the target, ~3 blocks back, no heal.
 *               It's pressing the kill, not running.
 *
 *   FLANKING    (mixed / neutral state)
 *               → reforms 5-7 blocks to the target's side, modest heal.
 *
 *   DEFENSIVE   (boss hurt OR target full)
 *               → reforms 9-12 blocks away from the target, larger heal.
 *               Buys breathing room to phase-2/3 abilities later.
 *
 * Astral Devourer has no equivalent — this is the Sovereign's signature
 * positioning gimmick.
 */
final class PhaseShift extends BossAbility
{
    private const SHIFT_DELAY_TICKS = 30;

    private const MODE_DEFENSIVE = 0;
    private const MODE_FLANKING  = 1;
    private const MODE_AGGRESSIVE = 2;

    public function __construct()
    {
        parent::__construct(300);
    }

    protected function checkConditions(BossEntity $boss): bool
    {
        if (!$boss->hasTarget()) {
            return false;
        }

        return $boss->getMaxHealth() > 0;
    }

    protected function onCast(BossEntity $boss): void
    {
        $vanishTicks = match ($boss->getPhase()) {
            1 => 30,
            2 => 28,
            3 => 24,
            default => 30,
        };

        $resistanceLevel = match ($boss->getPhase()) {
            1 => 3,
            2 => 3,
            3 => 4,
            default => 3,
        };

        $boss->getEffects()->add(new EffectInstance(VanillaEffects::INVISIBILITY(), $vanishTicks + 4, 0, false));
        $boss->getEffects()->add(new EffectInstance(VanillaEffects::RESISTANCE(), $vanishTicks + 4, $resistanceLevel, false));

        $mode = $this->pickMode($boss);

        $vanishMessage = match ($mode) {
            self::MODE_AGGRESSIVE => "&r&l&5Astral Sovereign &r&dlocks onto its prey...",
            self::MODE_FLANKING   => "&r&l&5Astral Sovereign &r&7slips between the cracks of reality...",
            default               => "&r&l&5Astral Sovereign &r&7scatters into drifting fragments...",
        };
        $boss->broadcastAbility(C::colorize($vanishMessage), 36);
        $boss->playAbilitySound("mob.endermen.portal", 1.0, 0.7, 36);

        $boss->triggerGlobalAbilityCooldown($vanishTicks + 6);

        $world = $boss->getWorld();
        $startPos = $boss->getPosition()->asVector3();
        $targetName = $boss->getTarget()?->getName() ?? '';

        // Dispersal cloud at the boss's current spot — it visually "shatters"
        // before vanishing.
        BossParticles::burst(
            $world,
            $startPos->add(0, 1.0, 0),
            BossParticles::portalParticle(),
            45,
            1.2,
            1.6
        );
        BossParticles::ring(
            $world,
            $startPos->add(0, 0.05, 0),
            1.5,
            BossParticles::portalParticle(),
            18,
            0.0
        );

        Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(
            static function () use ($boss, $world, $startPos, $mode, $targetName): void {
                if ($boss->isClosed() || !$boss->isAlive() || $boss->getWorld() !== $world) {
                    return;
                }

                $target = $boss->getTarget();
                $newLoc = self::computeReformLocation($boss, $world, $startPos, $target, $mode);

                $boss->teleport($newLoc);
                $boss->setMotion(new Vector3(0, 0, 0));

                $heal = self::healAmount($boss, $mode);
                if ($heal > 0.0) {
                    $boss->setHealth(min($boss->getMaxHealth(), $boss->getHealth() + $heal));
                }

                $reformPos = new Vector3($newLoc->x, $newLoc->y, $newLoc->z);
                BossParticles::burst(
                    $world,
                    $reformPos->add(0, 1.0, 0),
                    BossParticles::portalParticle(),
                    50,
                    1.4,
                    1.8
                );
                BossParticles::ring(
                    $world,
                    $reformPos->add(0, 0.05, 0),
                    1.8,
                    BossParticles::portalParticle(),
                    22,
                    0.0
                );

                $reformMessage = match ($mode) {
                    self::MODE_AGGRESSIVE => $targetName !== ''
                        ? "&r&l&5Astral Sovereign &r&dappears behind &f" . $targetName . "&d!"
                        : "&r&l&5Astral Sovereign &r&dlunges from the void!",
                    self::MODE_FLANKING   => "&r&l&5Astral Sovereign &r&dflanks from the void!",
                    default               => "&r&l&5Astral Sovereign &r&dreforms in the void!",
                };
                $boss->broadcastAbility(C::colorize($reformMessage), 36);

                $reformPitch = match ($mode) {
                    self::MODE_AGGRESSIVE => 1.6,
                    self::MODE_FLANKING   => 1.3,
                    default               => 1.2,
                };
                $boss->playAbilitySound("mob.endermen.portal", 1.4, $reformPitch, 36);
            }
        ), self::SHIFT_DELAY_TICKS);
    }

    private function pickMode(BossEntity $boss): int
    {
        $bossPct = $boss->getHealth() / $boss->getMaxHealth();
        $target = $boss->getTarget();

        $targetPct = 1.0;
        if ($target !== null) {
            $tMax = $target->getMaxHealth();
            if ($tMax > 0) {
                $targetPct = $target->getHealth() / $tMax;
            }
        }

        // 0.0 = boss dying / target full, 1.0 = boss full / target dying
        $aggression = ((1.0 - $targetPct) * 0.6) + ($bossPct * 0.4);

        if ($aggression >= 0.65) {
            return self::MODE_AGGRESSIVE;
        }
        if ($aggression >= 0.40) {
            return self::MODE_FLANKING;
        }
        return self::MODE_DEFENSIVE;
    }

    private static function healAmount(BossEntity $boss, int $mode): float
    {
        $max = $boss->getMaxHealth();
        $phase = $boss->getPhase();

        return match ($mode) {
            self::MODE_AGGRESSIVE => 0.0,
            self::MODE_FLANKING => match ($phase) {
                1 => 0.0,
                2 => $max * 0.02,
                3 => $max * 0.04,
                default => 0.0,
            },
            default => match ($phase) {
                1 => 0.0,
                2 => $max * 0.04,
                3 => $max * 0.07,
                default => 0.0,
            },
        };
    }

    private static function computeReformLocation(
        BossEntity $boss,
        \pocketmine\world\World $world,
        Vector3 $startPos,
        ?Player $target,
        int $mode
    ): Location {
        $bossLoc = $boss->getLocation();

        if ($target === null) {
            $angle = mt_rand(0, 359) * (M_PI / 180.0);
            $distance = 9.0 + (mt_rand(0, 30) / 10.0);
            return new Location(
                $startPos->x + (cos($angle) * $distance),
                $startPos->y,
                $startPos->z + (sin($angle) * $distance),
                $world,
                $bossLoc->getYaw(),
                $bossLoc->getPitch()
            );
        }

        $tPos = $target->getPosition();
        $tYawRad = deg2rad($target->getLocation()->getYaw());
        $facingX = -sin($tYawRad);
        $facingZ = cos($tYawRad);

        switch ($mode) {
            case self::MODE_AGGRESSIVE:
                $distance = 2.6 + (mt_rand(0, 8) / 10.0);
                $cx = $tPos->x - $facingX * $distance;
                $cz = $tPos->z - $facingZ * $distance;
                break;

            case self::MODE_FLANKING:
                $side = mt_rand(0, 1) === 0 ? 1.0 : -1.0;
                $distance = 5.0 + (mt_rand(0, 20) / 10.0);
                $perpX = -$facingZ * $side;
                $perpZ = $facingX * $side;
                $forwardBias = (mt_rand(-5, 5) / 10.0);
                $cx = $tPos->x + $perpX * $distance + $facingX * $forwardBias;
                $cz = $tPos->z + $perpZ * $distance + $facingZ * $forwardBias;
                break;

            case self::MODE_DEFENSIVE:
            default:
                $awayAngle = atan2($startPos->z - $tPos->z, $startPos->x - $tPos->x);
                if (($startPos->x - $tPos->x) === 0.0 && ($startPos->z - $tPos->z) === 0.0) {
                    $awayAngle = mt_rand(0, 359) * (M_PI / 180.0);
                }
                $distance = 9.0 + (mt_rand(0, 30) / 10.0);
                $cx = $tPos->x + cos($awayAngle) * $distance;
                $cz = $tPos->z + sin($awayAngle) * $distance;
                break;
        }

        $cy = $tPos->y;

        $faceDx = $tPos->x - $cx;
        $faceDz = $tPos->z - $cz;
        $newYaw = (float) rad2deg(atan2(-$faceDx, $faceDz));

        return new Location($cx, $cy, $cz, $world, $newYaw, 0.0);
    }
}
