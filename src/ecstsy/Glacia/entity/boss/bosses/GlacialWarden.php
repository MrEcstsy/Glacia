<?php

declare(strict_types=1);

namespace ecstsy\Glacia\entity\boss\bosses;

use ecstsy\Glacia\entity\boss\ability\AstralFracture;
use ecstsy\Glacia\entity\boss\ability\AstralMark;
use ecstsy\Glacia\entity\boss\ability\AstralOverload;
use ecstsy\Glacia\entity\boss\ability\AstralPull;
use ecstsy\Glacia\entity\boss\BossEntity;
use ecstsy\Glacia\entity\boss\minions\BossMinionStray;
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

final class GlacialWarden extends BossEntity {

    /** @var array<int, array{stacks:int, expire:int}> */
    private array $frostStacks = [];

    private const FROST_STACK_EXPIRE = 80; // ticks until stacks fall off
    private const FROST_STACK_MAX = 5;

    protected function initAbilities(): void {
        $this->abilities = [
            new AstralMark(),
            new AstralPull(),
            new AstralFracture(),
            new AstralOverload(),
        ];
    }

    public static function getNetworkTypeId(): string {
        return EntityIds::STRAY;
    }

    public function getName(): string {
        return "Glacial Warden";
    }

    protected function getBossDefaultConfig(): array
    {
        return [
            "stats" => [
                "max-health" => 2000.0,
                "melee-reach" => 2.2,
                "target-range" => 30.0,
                "follow-range" => 32.0,
                "knockback-resistance" => 0.9,
                "movement-speed" => [
                    "phase-1" => 0.26,
                    "phase-2" => 0.35,
                    "phase-3" => 0.42,
                ],
                "melee-damage" => [
                    "phase-1" => 12.0,
                    "phase-2" => 17.0,
                    "phase-3" => 23.0,
                ],
            ],
            "minions" => [
                "phase-2" => [
                    "name" => "&r&fWarden Watcher",
                    "health" => 110,
                    "damage" => 8.5,
                    "speed" => 0.25,
                    "target-range" => 24.0,
                    "attack-reach" => 2.3,
                    "attack-cooldown" => 11,
                ],
                "phase-3" => [
                    "name" => "&r&bFrozen Sentinel",
                    "health" => 135,
                    "damage" => 10.0,
                    "speed" => 0.28,
                    "target-range" => 24.0,
                    "attack-reach" => 2.3,
                    "attack-cooldown" => 9,
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
        return new EntitySizeInfo(2.4, 0.9);
    }

    protected function initEntity(CompoundTag $nbt): void {
        parent::initEntity($nbt);

        $maxHealth = (int) round($this->getConfiguredMaxHealth(2000.0));
        $this->setMaxHealth($maxHealth);
        $this->setHealth($maxHealth);

        $attr = $this->getAttributeMap();
        $attr->get(Attribute::MOVEMENT_SPEED)->setValue($this->getConfiguredMovementSpeed(1, 0.26));
        $attr->get(Attribute::FOLLOW_RANGE)->setValue($this->getConfiguredFollowRange(32.0));
        $attr->get(Attribute::KNOCKBACK_RESISTANCE)->setValue($this->getConfiguredKnockbackResistance(0.9));

        $this->setNameTagAlwaysVisible(true);
    }

    protected function onPhaseChange(int $phase): void {
        $attr = $this->getAttributeMap();
        $this->updateNameTag();

        match ($phase) {
            2 => [
                $attr->get(Attribute::MOVEMENT_SPEED)->setValue($this->getConfiguredMovementSpeed(2, 0.35)),
                PlayerUtils::playSound($this, "mob.wither.spawn"),
            ],
            3 => [
                $attr->get(Attribute::MOVEMENT_SPEED)->setValue($this->getConfiguredMovementSpeed(3, 0.42)),
                PlayerUtils::playSound($this, "mob.wither.ambient"),
            ],
            default => null,
        };
    }

    public function getBossName(): string {
        return match ($this->getPhase()) {
            2 => "&bGlacial Warden &r&7(Enraged)",
            3 => "&cGlacial Warden &r&7(Ascended)",
            default => "&3Glacial Warden",
        };
    }

    protected function getMinionIntermissionSpec(int $phase): array
    {
        return $this->getConfiguredMinionSpec($phase, [
            "class" => BossMinionStray::class,
            "name" => $phase >= 3 ? "&r&bFrozen Sentinel" : "&r&fWarden Watcher",
            "health" => $phase >= 3 ? 135 : 110,
            "damage" => $phase >= 3 ? 10.0 : 8.5,
            "speed" => $phase >= 3 ? 0.28 : 0.25,
            "target_range" => 24.0,
            "attack_reach" => 2.3,
            "attack_cooldown" => $phase >= 3 ? 9 : 11,
        ]);
    }

    protected function rewardPlayers(): void {
        $this->rewardConfiguredTopDamagers(
            "&r&l&3Glacial Warden Defeated!",
            "&b",
            "Glacial Warden",
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
                "&r&7#{$rank} &f{$player->getName()} &7- &b" . number_format((int)$damage) . " dmg"
            );
            $rank++;
        }

        foreach ($this->getWorld()->getPlayers() as $player) {
            $player->sendMessage(C::colorize("&r&l&3Glacial Warden Defeated!"));
            foreach ($lines as $line) {
                $player->sendMessage($line);
            }
        }
    }

}
