<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\sword;

use ecstsy\Glacia\entity\FloatingTextEntity;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use pocketmine\entity\Location;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\world\Position;

final class RageEnchant implements EntityDamageByEntityEnchantmentHandler {

    private static array $hits = [];
    private static array $active = [];
    private static array $lastHit = [];
    private const COOLDOWN_SECONDS = 5;

    public static function handle(EntityDamageByEntityEvent $event, CustomEnchantment $enchant, int $level): void {
        $damager = $event->getDamager();
        $victim  = $event->getEntity();

        if(!$damager instanceof Player) return;

        $name = strtolower($damager->getName());

        if (isset(self::$lastHit[$name])) {
            if (time() - self::$lastHit[$name] >= self::COOLDOWN_SECONDS) {
                self::reset($name);
            }
        }

        self::$lastHit[$name] = time();

        $chance = match($level) {
            1 => 25,
            2 => 30,
            3 => 35,
            4 => 40,
            5 => 45,
            default => 25
        };

        $procced = mt_rand(1, 100) <= $chance;

        if(!(self::$active[$name] ?? false)) {
            if(!$procced) return;

            self::$active[$name] = true;
            self::$hits[$name] = 1;
        } else {
            self::$hits[$name]++;
        }

        self::$hits[$name] = min(self::$hits[$name], 5);

        $stack = self::$hits[$name];

        $event->setBaseDamage($event->getBaseDamage() + $stack);

        self::spawnFloatingText($victim->getPosition(), $stack);
    }

    private static function spawnFloatingText(Position $pos, int $stack): void {
        $x = $pos->x + (mt_rand(-5, 5) / 10);
        $y = $pos->y + (mt_rand(3, 7) / 10);
        $z = $pos->z + (mt_rand(-5, 5) / 10);

        $ft = new FloatingTextEntity(new Location($x, $y, $z, $pos->getWorld(), 0, 0));

        $ft->setText("§6§lRage §r§7x" . $stack);
        $ft->spawnToAll();

        Loader::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(
            function() use ($ft) : void {
                if($ft->isClosed()) return;
                $ft->close(); 
            }
        ), 20);
    }

    public static function reset(string $playerName): void {
        $name = strtolower($playerName);
        unset(self::$hits[$name], self::$active[$name], self::$lastHit[$name]);
    }
}