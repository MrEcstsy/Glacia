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
use ecstsy\Glacia\Loader;
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

    protected function getInitialSizeInfo(): EntitySizeInfo
    {
        return new EntitySizeInfo(3.2, 1.6);
    }

    protected function initEntity(CompoundTag $nbt): void
    {
        parent::initEntity($nbt);

        $this->setMaxHealth(5800);
        $this->setHealth(5800);

        $attr = $this->getAttributeMap();
        $attr->get(Attribute::MOVEMENT_SPEED)->setValue(0.26);
        $attr->get(Attribute::FOLLOW_RANGE)->setValue(36);
        $attr->get(Attribute::KNOCKBACK_RESISTANCE)->setValue(0.95);

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
        return "&r&l" . $this->getBossName() . " &r&7[" . $this->getActiveHeadCount() . "/3 heads]";
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
        return 2.85;
    }

    protected function getCloseRangeOrbitBuffer(): float
    {
        return 0.06;
    }

    protected function getMeleeDamage(): float
    {
        return match ($this->phase) {
            1 => 12.0,
            2 => 16.0,
            3 => 20.0,
            default => 12.0,
        };
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
        $multiplier = 1.0 + min(0.30, $extraPlayers * 0.10);
        $multiplier += ($this->phase - 1) * 0.06;
        if ($this->isOverloaded()) {
            $multiplier += 0.10;
        }

        return $multiplier;
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

            $event = new EntityDamageByEntityEvent(
                $this,
                $victim,
                EntityDamageByEntityEvent::CAUSE_ENTITY_ATTACK,
                $damage
            );
            $victim->attack($event);

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
                $attr->get(Attribute::MOVEMENT_SPEED)->setValue(0.33),
                PlayerUtils::playSound($this, "mob.wither.spawn"),
            ],
            3 => [
                $attr->get(Attribute::MOVEMENT_SPEED)->setValue(0.40),
                PlayerUtils::playSound($this, "mob.enderdragon.growl"),
            ],
            default => null,
        };
    }

    protected function entityBaseTick(int $tickDiff = 1): bool
    {
        $this->tickHeads();
        $this->tickCrushingPresence();
        return parent::entityBaseTick($tickDiff);
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
                1 => 8.5,
                2 => 12.5,
                3 => 17.0,
                default => 8.5,
            } * $this->getPressureDamageMultiplier();

            $player->attack(new EntityDamageByEntityEvent(
                $this,
                $player,
                EntityDamageEvent::CAUSE_ENTITY_ATTACK,
                $damage,
                [],
                0.0,
                0.0
            ));

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

    protected function rewardPlayers(): void
    {
        $top = $this->getTopDamagers(5);
        if ($top === []) {
            return;
        }

        $rank = 1;
        foreach ($top as $playerId => $damage) {
            $player = $this->getWorld()->getServer()->getPlayerExact($playerId);
            if (!$player instanceof Player) {
                continue;
            }

            $coins = match ($rank) {
                1 => 450000,
                2 => 275000,
                3 => 150000,
                default => 60000,
            };
            Loader::getPlayerManager()->getSession($player)?->addBalance($coins);
            $player->sendMessage(C::colorize("&r&l&a[Boss] &r&aYou placed #{$rank} on the &bGlacial Hydra &aand earned &f$" . number_format($coins)));
            PlayerUtils::playSound($player, "random.levelup");
            $rank++;
        }

        foreach ($this->getWorld()->getPlayers() as $player) {
            $player->sendMessage(C::colorize("&r&l&bGlacial Hydra Defeated!"));
        }
    }

    public function jump(): void
    {
        if (!$this->onGround) {
            return;
        }

        $this->motion->y = 0.5;
    }
}
