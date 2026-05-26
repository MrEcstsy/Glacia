<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\boss\bosses;

use ecstsy\Glacia\entity\boss\ability\HydraFury;
use ecstsy\Glacia\entity\boss\ability\HydraIceStorm;
use ecstsy\Glacia\entity\boss\ability\HydraCrystalSpikes;
use ecstsy\Glacia\entity\boss\ability\HydraRiftFireball;
use ecstsy\Glacia\entity\boss\ability\HydraTailSweep;
use ecstsy\Glacia\entity\boss\ability\HydraVoidPull;
use ecstsy\Glacia\entity\boss\BossEntity;
use ecstsy\Glacia\entity\boss\minions\BossMinionStray;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\utils\CustomParticleEffectManager;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\entity\Attribute;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class GlacialHydra extends BossEntity
{
    public const HEAD_FROST = "frost";
    public const HEAD_VOID = "void";
    public const HEAD_RAGE = "rage";

    /** @var array<string, array{hp: float, max: float, regen_until: int}> */
    private array $heads = [];
    private int $headRespawnTicks = 20 * 18;
    /** @var array<int, int> */
    private array $crushExposure = [];

    public static function getNetworkTypeId(): string
    {
        return "glacia:glacial_hydra";
    }

    public function getName(): string
    {
        return "Glacial Hydra";
    }

    protected function getBossDefaultConfig(): array
    {
        return [
            "stats" => [
                "max-health" => 5800.0,
                "melee-reach" => 2.85,
                "target-range" => 30.0,
                "follow-range" => 36.0,
                "knockback-resistance" => 0.95,
                "movement-speed" => [
                    "phase-1" => 0.26,
                    "phase-2" => 0.33,
                    "phase-3" => 0.40,
                ],
                "melee-damage" => [
                    "phase-1" => 14.0,
                    "phase-2" => 19.0,
                    "phase-3" => 24.0,
                ],
            ],
            "minions" => [
                "phase-2" => [
                    "name" => "&r&fHydra Whelp",
                    "health" => 120,
                    "damage" => 8.0,
                    "speed" => 0.25,
                    "target-range" => 24.0,
                    "attack-reach" => 2.15,
                    "attack-cooldown" => 12,
                ],
                "phase-3" => [
                    "name" => "&r&bHydra Frostspawn",
                    "health" => 150,
                    "damage" => 10.0,
                    "speed" => 0.28,
                    "target-range" => 24.0,
                    "attack-reach" => 2.15,
                    "attack-cooldown" => 10,
                ],
            ],
            "rewards" => [
                "top-damagers" => 5,
                "ranks" => [
                    1 => ["money" => 450000, "items" => []],
                    2 => ["money" => 275000, "items" => []],
                    3 => ["money" => 150000, "items" => []],
                    4 => ["money" => 60000, "items" => []],
                    5 => ["money" => 60000, "items" => []],
                ],
            ],
        ];
    }

    protected function getInitialSizeInfo(): EntitySizeInfo
    {
        return new EntitySizeInfo(3.2, 1.6);
    }

    protected function initEntity(CompoundTag $nbt): void
    {
        parent::initEntity($nbt);

        $maxHealth = (int) round($this->getConfiguredMaxHealth(5800.0));
        $this->setMaxHealth($maxHealth);
        $this->setHealth($maxHealth);

        $attr = $this->getAttributeMap();
        $attr->get(Attribute::MOVEMENT_SPEED)->setValue($this->getConfiguredMovementSpeed(1, 0.26));
        $attr->get(Attribute::FOLLOW_RANGE)->setValue($this->getConfiguredFollowRange(36.0));
        $attr->get(Attribute::KNOCKBACK_RESISTANCE)->setValue($this->getConfiguredKnockbackResistance(0.95));

        $this->heads = [
            self::HEAD_FROST => ["hp" => 760.0, "max" => 760.0, "regen_until" => 0],
            self::HEAD_VOID => ["hp" => 760.0, "max" => 760.0, "regen_until" => 0],
            self::HEAD_RAGE => ["hp" => 760.0, "max" => 760.0, "regen_until" => 0],
        ];

        $this->setNameTagAlwaysVisible(true);
        $this->aiInterval = 1;
    }

    protected function initAbilities(): void
    {
        $this->abilities = [
            new HydraIceStorm(),
            new HydraRiftFireball(),
            new HydraVoidPull(),
            new HydraCrystalSpikes(),
            new HydraTailSweep(),
            new HydraFury(),
        ];
    }

    public function hasHeadActive(string $head): bool
    {
        return isset($this->heads[$head]) && $this->heads[$head]["hp"] > 0.0;
    }

    public function getActiveHeadCount(): int
    {
        $count = 0;
        foreach ($this->heads as $head) {
            if ($head["hp"] > 0.0) {
                $count++;
            }
        }
        return $count;
    }

    protected function getBossBarTitle(): string
    {
        return $this->decorateBossBarTitle("&r&l" . $this->getBossName() . " &r&7[" . $this->getActiveHeadCount() . "/3 heads]");
    }

    public function getBossName(): string
    {
        return match ($this->getPhase()) {
            2 => "&5Glacial Hydra &r&7(Unstable)",
            3 => "&cGlacial Hydra &r&7(Dominance)",
            default => "&bGlacial Hydra &r&7(Awakening)",
        };
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
        return $this->getConfiguredMeleeReach(2.85);
    }

    protected function getCloseRangeOrbitBuffer(): float
    {
        return 0.06;
    }

    protected function getMeleeDamage(): float
    {
        return $this->getConfiguredMeleeDamage([
            1 => 14.0,
            2 => 19.0,
            3 => 24.0,
        ]);
    }

    protected function getTargetLockTicks(): int
    {
        return 12;
    }

    protected function getAbilityCadenceTicks(): int
    {
        return max(11, parent::getAbilityCadenceTicks() - 8 - (max(0, $this->getEngagedPlayerCount(16.0) - 1) * 2));
    }

    protected function getMovementAggressionMultiplier(): float
    {
        return parent::getMovementAggressionMultiplier() + 0.12;
    }

    public function getPressureDamageMultiplier(): float
    {
        $extraPlayers = max(0, $this->getEngagedPlayerCount(16.0) - 1);
        $multiplier = 1.0 + min(0.24, $extraPlayers * 0.08);
        $multiplier += ($this->phase - 1) * 0.05;
        if ($this->isOverloaded()) {
            $multiplier += 0.08;
        }

        return $multiplier;
    }

    protected function getMinimumUnmitigatedDamage(float $baseDamage, int $cause): float
    {
        return match ($cause) {
            EntityDamageEvent::CAUSE_ENTITY_ATTACK => min(6.5, max(1.2, $baseDamage * 0.24)),
            EntityDamageEvent::CAUSE_MAGIC => min(5.25, max(0.9, $baseDamage * 0.18)),
            default => min(3.5, max(0.5, $baseDamage * 0.12)),
        };
    }

    protected function doMeleeAttack(): void
    {
        if (!$this->target instanceof Player) {
            return;
        }

        $this->broadcastEntityAnimation("attack", "", "", 0.1);
        $victims = $this->collectBiteVictims();
        if ($victims === []) {
            return;
        }

        $connectedHits = 0;
        $baseDamage = $this->getMeleeDamage() * $this->getPressureDamageMultiplier();

        foreach ($victims as $victim) {
            $damage = $baseDamage;
            if ($victim->getId() !== $this->target->getId()) {
                $damage *= 0.74;
            }

            $event = $this->dealBossTrueDamage($victim, $damage);

            if ($event->isCancelled()) {
                continue;
            }

            $connectedHits++;

            if ($this->hasHeadActive(self::HEAD_RAGE)) {
                $dx = $victim->getPosition()->x - $this->location->x;
                $dz = $victim->getPosition()->z - $this->location->z;
                $this->applyBossKnockback($victim, $dx, $dz, 0.82, 0.34);
            }
            if ($this->hasHeadActive(self::HEAD_FROST)) {
                $victim->getEffects()->add(new EffectInstance(VanillaEffects::SLOWNESS(), 50, 1));
            }

            PlayerUtils::playSound($victim, "mob.enderdragon.hit", 0.9, 0.8);
        }

        if ($connectedHits <= 0) {
            return;
        }

        $this->attackCooldown = $this->isOverloaded() ? 8 : 11;
    }

    protected function onPhaseChange(int $phase): void
    {
        $attr = $this->getAttributeMap();
        $this->updateNameTag();

        match ($phase) {
            2 => [
                $attr->get(Attribute::MOVEMENT_SPEED)->setValue($this->getConfiguredMovementSpeed(2, 0.33)),
                PlayerUtils::playSound($this, "mob.wither.spawn"),
            ],
            3 => [
                $attr->get(Attribute::MOVEMENT_SPEED)->setValue($this->getConfiguredMovementSpeed(3, 0.40)),
                PlayerUtils::playSound($this, "mob.enderdragon.growl"),
            ],
            default => null,
        };
    }

    protected function entityBaseTick(int $tickDiff = 1): bool
    {
        $this->tickHeads();
        if (($this->ticksLived % 10) === 0) {
            $this->emitHydraAura();
        }
        if (!$this->isMinionIntermissionActive()) {
            $this->tickCrushingPresence();
        }
        return parent::entityBaseTick($tickDiff);
    }

    protected function getMinionIntermissionSpec(int $phase): array
    {
        return $this->getConfiguredMinionSpec($phase, [
            "class" => BossMinionStray::class,
            "name" => $phase >= 3 ? "&r&bHydra Frostspawn" : "&r&fHydra Whelp",
            "health" => $phase >= 3 ? 150 : 120,
            "damage" => $phase >= 3 ? 10.0 : 8.0,
            "speed" => $phase >= 3 ? 0.28 : 0.25,
            "target_range" => 24.0,
            "attack_reach" => 2.15,
            "attack_cooldown" => $phase >= 3 ? 10 : 12,
        ]);
    }

    public function attack(EntityDamageEvent $source): void
    {
        parent::attack($source);

        if ($source->isCancelled() || !$source instanceof EntityDamageByEntityEvent || !($source->getDamager() instanceof Player)) {
            return;
        }

        $active = array_keys(array_filter($this->heads, static fn(array $head): bool => $head["hp"] > 0.0));
        if ($active === []) {
            return;
        }

        $headId = $active[array_rand($active)];
        $this->heads[$headId]["hp"] = max(0.0, $this->heads[$headId]["hp"] - max(1.0, $source->getFinalDamage() * 0.7));
        if ($this->heads[$headId]["hp"] > 0.0) {
            return;
        }

        $this->heads[$headId]["regen_until"] = $this->bossTick + $this->headRespawnTicks;
        $this->broadcastAbility(C::colorize("&r&7The Hydra's " . $this->formatHeadLabel($headId) . " &7head shatters apart!"), 36);
        $this->triggerGlobalAbilityCooldown(40);
        $this->attackCooldown = max($this->attackCooldown, 30);
    }

    private function tickHeads(): void
    {
        foreach ($this->heads as $id => $head) {
            if ($head["hp"] > 0.0 || $head["regen_until"] <= 0 || $head["regen_until"] > $this->bossTick) {
                continue;
            }

            $this->heads[$id]["hp"] = $this->heads[$id]["max"];
            $this->heads[$id]["regen_until"] = 0;
            $this->broadcastAbility(C::colorize("&r&aThe Hydra's " . $this->formatHeadLabel($id) . " &ahead regrows!"), 36);
            PlayerUtils::playSound($this, "random.levelup");
        }
    }

    /**
     * @return Player[]
     */
    private function collectBiteVictims(): array
    {
        $victims = [];
        $radiusSq = 4.15 ** 2;
        $primaryId = $this->target?->getId();
        $center = $this->getPosition();

        foreach ($this->getPlayersInRange(4.15) as $player) {
            if ($player->getPosition()->distanceSquared($center) > $radiusSq) {
                continue;
            }

            $victims[$player->getId()] = $player;
        }

        if ($primaryId !== null && $this->target instanceof Player) {
            $victims = [$primaryId => $this->target] + $victims;
        }

        return array_values($victims);
    }

    private function tickCrushingPresence(): void
    {
        $seen = [];
        $radiusSq = 2.15 ** 2;
        $threshold = max(10, 22 - (max(0, $this->getEngagedPlayerCount(10.0) - 1) * 2) - (($this->phase - 1) * 3));

        foreach ($this->getPlayersInRange(2.15) as $player) {
            if ($player->getPosition()->distanceSquared($this->getPosition()) > $radiusSq) {
                continue;
            }

            $id = $player->getId();
            $seen[$id] = true;
            $this->crushExposure[$id] = ($this->crushExposure[$id] ?? 0) + 1;

            if ($this->crushExposure[$id] < $threshold) {
                continue;
            }

            $this->crushExposure[$id] = 0;
            $damage = match ($this->phase) {
                1 => 7.0,
                2 => 10.0,
                3 => 13.5,
                default => 7.0,
            } * $this->getPressureDamageMultiplier();

            $this->dealBossTrueDamage($player, $damage);

            $delta = $player->getPosition()->subtractVector($this->getPosition());
            $this->applyBossKnockback($player, $delta->x, $delta->z, 0.72, 0.24);
            $player->sendActionBarMessage(C::colorize("&r&cThe Hydra crushes anyone who lingers beneath it!"));
        }

        foreach (array_keys($this->crushExposure) as $id) {
            if (!isset($seen[$id])) {
                unset($this->crushExposure[$id]);
            }
        }
    }

    private function formatHeadLabel(string $id): string
    {
        return match ($id) {
            self::HEAD_FROST => "&bFrost",
            self::HEAD_VOID => "&5Void",
            self::HEAD_RAGE => "&cRage",
            default => "&7Unknown",
        };
    }

    private function emitHydraAura(): void
    {
        $center = $this->getPosition()->asVector3()->add(0, 0.2, 0);

        CustomParticleEffectManager::stackedRing(
            $this->getWorld(),
            $center,
            1.9,
            CustomParticleEffectManager::HYDRA_WARNING,
            2,
            14,
            0.20,
            44.0
        );
        CustomParticleEffectManager::ringVariants(
            $this->getWorld(),
            $center,
            1.45,
            [
                CustomParticleEffectManager::HYDRA_WARNING,
                CustomParticleEffectManager::HYDRA_WARNING_ARC,
            ],
            12,
            0.12,
            44.0
        );

        if ($this->hasHeadActive(self::HEAD_FROST)) {
            CustomParticleEffectManager::burst(
                $this->getWorld(),
                $center->add(-0.85, 1.55, 0.15),
                CustomParticleEffectManager::HYDRA_FROST,
                7,
                0.28,
                0.36,
                44.0
            );
        }

        if ($this->hasHeadActive(self::HEAD_VOID)) {
            CustomParticleEffectManager::column(
                $this->getWorld(),
                $center->add(0.0, 1.1, 0.0),
                CustomParticleEffectManager::HYDRA_VOID,
                1.35,
                5,
                44.0
            );
        }

        if ($this->hasHeadActive(self::HEAD_RAGE)) {
            CustomParticleEffectManager::burst(
                $this->getWorld(),
                $center->add(0.85, 1.45, -0.1),
                CustomParticleEffectManager::HYDRA_WARNING,
                6,
                0.24,
                0.28,
                44.0
            );
        }
    }

    protected function rewardPlayers(): void
    {
        $this->rewardConfiguredTopDamagers(
            "&r&l&bGlacial Hydra Defeated!",
            "&b",
            "Glacial Hydra",
            [
                "boss_id" => $this->getBossConfigId(),
                "boss_name" => strtolower($this->getName()),
            ]
        );
    }

    public function jump(): void
    {
        if (!$this->onGround) {
            return;
        }

        $this->motion->y = 0.5;
    }
}
