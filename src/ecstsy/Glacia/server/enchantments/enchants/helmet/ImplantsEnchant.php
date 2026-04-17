<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\helmet;

use pocketmine\player\Player;

final class ImplantsEnchant {

    /** @var array<string, int> */
    private static array $cooldowns = [];

    public static function tick(Player $player, int $level): void {
        $key = strtolower($player->getName());

        if ((self::$cooldowns[$key] ?? 0) > time()) {
            return;
        }

        self::$cooldowns[$key] = time() + max(2, 7 - $level);

        if ($player->isAlive()) {
            $player->setHealth(min($player->getMaxHealth(), $player->getHealth() + 1));
            $food = $player->getHungerManager();
            $food->setFood(min(20, $food->getFood() + 1));
        }
    }
}