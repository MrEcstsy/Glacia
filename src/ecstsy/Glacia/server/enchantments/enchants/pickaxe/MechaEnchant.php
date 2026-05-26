<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\pickaxe;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\BlockBreakEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantDebugManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
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

        $cooldownSeconds = EnchantTuningManager::cooldownSeconds("mecha", $level, 1);
        self::$cooldowns[$key] = $now + $cooldownSeconds;
        $rawCmd = EnchantTuningManager::getValue("mecha", "sellall-command", "sellall");
        $cmd = trim(is_string($rawCmd) ? $rawCmd : "sellall");
        $player->getServer()->dispatchCommand($player, $cmd !== "" ? $cmd : "sellall");
        EnchantDebugManager::logPassiveValues($player, "mecha", $level, [
            "command" => $cmd !== "" ? $cmd : "sellall",
            "cooldown_seconds" => $cooldownSeconds,
        ]);
    }
}
