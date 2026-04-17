<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\manager;

use ecstsy\Glacia\Loader;
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

        $cycles = 2;

        for ($i = 1; $i <= $cycles; $i++) {
            Loader::getInstance()->getScheduler()->scheduleDelayedTask(
                new ClosureTask(function() use ($player, $name): void {

                    if (!$player->isAlive()) {
                        unset(BleedManager::$active[$name]);
                        return;
                    }

                    $damage = mt_rand(1, 3);

                    $player->attack(new EntityDamageEvent(
                        $player,
                        EntityDamageEvent::CAUSE_MAGIC,
                        $damage
                    ));

                    $player->getWorld()->addParticle(
                        $player->getPosition(),
                        new BlockBreakParticle(VanillaBlocks::REDSTONE())
                    );

                }),
                20 * $i 
            );
        }

        Loader::getInstance()->getScheduler()->scheduleDelayedTask(
            new ClosureTask(function() use ($name): void {
                unset(BleedManager::$active[$name]);
            }),
            20 * ($cycles + 1)
        );
    }
}