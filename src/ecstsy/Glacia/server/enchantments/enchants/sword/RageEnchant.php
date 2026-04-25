<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\sword;

use ecstsy\Glacia\entity\FloatingTextEntity;
use ecstsy\Glacia\Loader;
use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\EntityDamageByEntityEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\ProcLuckManager;
use pocketmine\entity\Location;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\world\Position;

final class RageEnchant implements EntityDamageByEntityEnchantmentHandler {

    private static array $hits = [];
    private static array $active = [];
    private static array $lastHit = [];
    private const COOLDOWN_SECONDS = 4;

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
            1 => 6,
            2 => 8,
            3 => 10,
            4 => 12,
            5 => 14,
            default => 6
        };

        $procced = ProcLuckManager::roll($damager, "rage", $chance, 1, 6);

        if(!(self::$active[$name] ?? false)) {
            if(!$procced) return;

            self::$active[$name] = true;
            self::$hits[$name] = 1;
        } else {
            if ($procced) {
                self::$hits[$name]++;
            } else {
                self::$hits[$name] = max(1, self::$hits[$name] - 1);
            }
        }

        self::$hits[$name] = min(self::$hits[$name], 4);

        $stack = self::$hits[$name];

        $event->setBaseDamage($event->getBaseDamage() + ($stack * 0.5));

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
