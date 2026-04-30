<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\manager;

use ecstsy\Glacia\entity\FloatingTextEntity;
use ecstsy\Glacia\Loader;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\Location;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;

final class SilenceManager {

    /** @var array<string, EffectInstance[]> */
    private static array $stored = [];

    private const SILENCE_TEXT = "§r§l§8✦§5 SILENCED §8✦";

    public static function silence(Player $victim, float $durationSeconds): void {
        $name = strtolower($victim->getName());

        if (isset(self::$stored[$name])) {
            unset(self::$stored[$name]); 
        }

        $effects = $victim->getEffects()->all();
        $removed = [];

        foreach ($effects as $effect) {
            if (!$effect->getType()->isBad()) { 
                $removed[] = $effect;
                $victim->getEffects()->remove($effect->getType());
            }
        }

        self::spawnSilenceText($victim); 

        if (!empty($removed)) {
            self::$stored[$name] = $removed;
        }

        $ticks = (int)($durationSeconds * 20);

        Loader::getInstance()->getScheduler()->scheduleDelayedTask(
            new ClosureTask(function () use ($victim, $name): void {
                if (!isset(self::$stored[$name])) return;

                if (!$victim->isOnline()) {
                    unset(self::$stored[$name]);
                    return;
                }

                foreach (self::$stored[$name] as $effect) {
                    $victim->getEffects()->add(new EffectInstance($effect->getType(), $effect->getDuration(), $effect->getAmplifier(), $effect->isVisible(), $effect->isAmbient()));
                }

                unset(self::$stored[$name]);

            }), 
            $ticks
        );
    }

    private static function spawnSilenceText(Player $player): void {
        $loc = $player->getLocation();

        $offsetX = mt_rand(-5, 5) / 20;  
        $offsetZ = mt_rand(-5, 5) / 20;  
        $offsetY = mt_rand(0, 5) / 20;    

        $pos = $loc->add($offsetX, 0.3 + $offsetY, $offsetZ);

        $float = new FloatingTextEntity(new Location(
            $pos->x, 
            $pos->y, 
            $pos->z, 
            $player->getWorld(), 
            0, 0
        ));

        $float->setText(self::SILENCE_TEXT);
        $float->spawnToAll();

        Loader::getInstance()->getScheduler()->scheduleDelayedTask(
            new ClosureTask(function () use ($float): void {
                if(!$float->isClosed()){
                    $float->close(); 
                }
            }),
            20
        );
    }

    public static function clearPlayer(Player $player): void
    {
        unset(self::$stored[strtolower($player->getName())]);
    }
}