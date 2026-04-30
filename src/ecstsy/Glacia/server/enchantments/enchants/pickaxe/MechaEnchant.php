<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\pickaxe;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\BlockBreakEnchantmentHandler;
use pocketmine\event\block\BlockBreakEvent;

final class MechaEnchant implements BlockBreakEnchantmentHandler
{
    /** @var array<string, int> */
    private static array $cooldowns = [];

    public static function handle(BlockBreakEvent $event, CustomEnchantment $enchant, int $level): void
    {
        $player = $event->getPlayer();
        $key = strtolower($player->getName());
        $now = time();

        if ((self::$cooldowns[$key] ?? 0) > $now) {
            return;
        }

        if ($player->getInventory()->firstEmpty() !== -1) {
            return;
        }

        self::$cooldowns[$key] = $now + 1;
        $player->getServer()->dispatchCommand($player, "sellall");
    }
}
