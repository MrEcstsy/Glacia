<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\manager;

use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\enchantments\enchants\chestplate\BloodLustEnchant;
use pocketmine\block\VanillaBlocks;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\world\particle\BlockBreakParticle;

final class BleedManager {

    /** @var array<string, bool> */
    private static array $active = [];

    /**
     * Applies a bleed effect to a player.
     *
     * @param Player $player
     * @param int $level
     * @return void
     */
    public static function apply(Player $player, int $level): void {
        $name = strtolower($player->getName());

        if (isset(self::$active[$name])) {
            return;
        }

        self::$active[$name] = true;

        $cycles = max(1, EnchantTuningManager::getInt("bleed", "bleed-manager-tick-count", 2));
        $tickTicks = max(1, EnchantTuningManager::getInt("bleed", "tick-interval-ticks", 20));
        $dmgMin = max(0, EnchantTuningManager::getInt("bleed", "damage-per-tick-min-half-hearts", 1));
        $dmgMax = max($dmgMin, EnchantTuningManager::getInt("bleed", "damage-per-tick-max-half-hearts", 3));

        for ($i = 1; $i <= $cycles; $i++) {
            Loader::getInstance()->getScheduler()->scheduleDelayedTask(
                new ClosureTask(function() use ($player, $name, $dmgMin, $dmgMax): void {

                    if (!$player->isAlive()) {
                        unset(BleedManager::$active[$name]);
                        return;
                    }

                    $damage = mt_rand($dmgMin, $dmgMax);

                    $player->attack(new EntityDamageEvent(
                        $player,
                        EntityDamageEvent::CAUSE_MAGIC,
                        $damage
                    ));

                    BloodLustEnchant::triggerNearbyOwners($player);

                    $player->getWorld()->addParticle(
                        $player->getPosition(),
                        new BlockBreakParticle(VanillaBlocks::REDSTONE())
                    );

                }),
                $tickTicks * $i 
            );
        }

        Loader::getInstance()->getScheduler()->scheduleDelayedTask(
            new ClosureTask(function() use ($name): void {
                unset(BleedManager::$active[$name]);
            }),
            $tickTicks * ($cycles + 1)
        );
    }

    public static function clearPlayer(Player $player): void
    {
        unset(self::$active[strtolower($player->getName())]);
    }
}
