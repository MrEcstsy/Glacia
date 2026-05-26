<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\boss\bosses;

use ecstsy\Glacia\entity\boss\ability\AstralFracture;
use ecstsy\Glacia\entity\boss\ability\AstralMark;
use ecstsy\Glacia\entity\boss\ability\AstralOverload;
use ecstsy\Glacia\entity\boss\ability\AstralPull;
use ecstsy\Glacia\entity\boss\BossEntity;
use ecstsy\Glacia\entity\boss\minions\BossMinionEnderman;
use ecstsy\Glacia\Loader;
use ecstsy\MartianUtilities\utils\GeneralUtils;
use ecstsy\MartianUtilities\utils\PlayerUtils;
use pocketmine\entity\Attribute;
use pocketmine\entity\Location;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\event\entity\EntityDamageEvent;
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
        return EntityIds::IRON_GOLEM;
    }

    public function getName(): string {
        return "Astral Devourer";
    }

    protected function getBossDefaultConfig(): array
    {
        return [
            "stats" => [
                "max-health" => 1800.0,
                "melee-reach" => 2.8,
                "target-range" => 30.0,
                "follow-range" => 36.0,
                "knockback-resistance" => 0.90,
                "movement-speed" => [
                    "phase-1" => 0.38,
                    "phase-2" => 0.46,
                    "phase-3" => 0.54,
                ],
                "melee-damage" => [
                    "phase-1" => 16.0,
                    "phase-2" => 22.0,
                    "phase-3" => 29.0,
                ],
            ],
            "minions" => [
                "phase-2" => [
                    "name" => "&r&dAstral Shade",
                    "health" => 72,
                    "damage" => 5.5,
                    "speed" => 0.22,
                    "target-range" => 18.0,
                    "attack-reach" => 1.85,
                    "attack-cooldown" => 12,
                ],
                "phase-3" => [
                    "name" => "&r&5Devourer Shade",
                    "health" => 92,
                    "damage" => 7.0,
                    "speed" => 0.24,
                    "target-range" => 18.0,
                    "attack-reach" => 1.85,
                    "attack-cooldown" => 10,
                ],
            ],
            "rewards" => [
                "top-damagers" => 5,
                "ranks" => [
                    1 => ["money" => 250000, "items" => []],
                    2 => ["money" => 150000, "items" => []],
                    3 => ["money" => 75000, "items" => []],
                    4 => ["money" => 25000, "items" => []],
                    5 => ["money" => 25000, "items" => []],
                ],
            ],
        ];
    }

    protected function getInitialSizeInfo(): EntitySizeInfo {
        return new EntitySizeInfo(2.9, 1.4);
    }

    protected function initEntity(CompoundTag $nbt): void {
        parent::initEntity($nbt);

        $maxHealth = (int) round($this->getConfiguredMaxHealth(1800.0));
        $this->setMaxHealth($maxHealth);
        $this->setHealth($maxHealth);

        $attr = $this->getAttributeMap();
        $attr->get(Attribute::MOVEMENT_SPEED)->setValue($this->getConfiguredMovementSpeed(1, 0.38));
        $attr->get(Attribute::FOLLOW_RANGE)->setValue($this->getConfiguredFollowRange(36.0));
        $attr->get(Attribute::KNOCKBACK_RESISTANCE)->setValue($this->getConfiguredKnockbackResistance(0.90));

        $this->setNameTagAlwaysVisible(true);
    }

    protected function onPhaseChange(int $phase): void {
        $attr = $this->getAttributeMap();
        $this->updateNameTag();

        match ($phase) {
            2 => [
                $attr->get(Attribute::MOVEMENT_SPEED)->setValue($this->getConfiguredMovementSpeed(2, 0.46)),
                PlayerUtils::playSound($this, "mob.wither.spawn"),
            ],
            3 => [
                $attr->get(Attribute::MOVEMENT_SPEED)->setValue($this->getConfiguredMovementSpeed(3, 0.54)),
                PlayerUtils::playSound($this, "mob.wither.ambient"),
            ],
            default => null,
        };
    }

    public function getBossName(): string {
        return match ($this->getPhase()) {
            2 => "&dAstral Devourer &r&7(Enraged)",
            3 => "&cAstral Devourer &r&7(Ascended)",
            default => "&5Astral Devourer",
        };
    }

    protected function getMinionIntermissionSpec(int $phase): array
    {
        return $this->getConfiguredMinionSpec($phase, [
            "class" => BossMinionEnderman::class,
            "name" => $phase >= 3 ? "&r&5Devourer Shade" : "&r&dAstral Shade",
            "health" => $phase >= 3 ? 92 : 72,
            "damage" => $phase >= 3 ? 7.0 : 5.5,
            "speed" => $phase >= 3 ? 0.24 : 0.22,
            "target_range" => 18.0,
            "attack_reach" => 1.85,
            "attack_cooldown" => $phase >= 3 ? 10 : 12,
        ]);
    }

    protected function getMeleeReach(): float
    {
        return $this->getConfiguredMeleeReach(2.8);
    }

    protected function getMeleeDamage(): float
    {
        return $this->getConfiguredMeleeDamage([
            1 => 16.0,
            2 => 22.0,
            3 => 29.0,
        ]);
    }

    protected function getMinimumUnmitigatedDamage(float $baseDamage, int $cause): float
    {
        return match ($cause) {
            EntityDamageEvent::CAUSE_ENTITY_ATTACK => min(4.5, max(0.75, $baseDamage * 0.18)),
            EntityDamageEvent::CAUSE_MAGIC => min(3.5, max(0.5, $baseDamage * 0.14)),
            default => min(2.5, max(0.35, $baseDamage * 0.10)),
        };
    }

    protected function getAbilityCadenceTicks(): int
    {
        return max(14, parent::getAbilityCadenceTicks() - 4);
    }

    protected function getMovementAggressionMultiplier(): float
    {
        return parent::getMovementAggressionMultiplier() + 0.12;
    }

    protected function getMinionIntermissionCount(int $phase): int
    {
        $engaged = max(1, $this->getEngagedPlayerCount(18.0));
        return min(5, 2 + max(0, $phase - 1) + intdiv(max(0, $engaged - 1), 2));
    }

    protected function getMinionSpawnRadiusBase(): float
    {
        return 5.6;
    }

    protected function rewardPlayers(): void {
        $this->rewardConfiguredTopDamagers(
            "&r&l&5Astral Devourer Defeated!",
            "&d",
            "Astral Devourer",
            [
                "boss_id" => $this->getBossConfigId(),
                "boss_name" => strtolower($this->getName()),
            ]
        );
    }

    protected function rewardPlayer(Player $player, int $rank, float $damage): void {
        $coins = match ($rank) {
            1 => 250000,
            2 => 150000,
            3 => 75000,
            default => 25000,
        };

        if ($rank === 1) {
            Loader::getQuestBoardManager()->recordProgress($player, "boss_top_damage", 1);
        }

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
