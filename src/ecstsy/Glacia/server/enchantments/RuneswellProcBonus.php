<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments;

use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use pocketmine\item\Axe;
use pocketmine\player\Player;

/**
 * +2% proc chance per Runeswell level for custom axe enchants.
 */
final class RuneswellProcBonus
{
    private const AXE_ENCHANTS = [
        "bleed", "cleave", "berserk", "confusion", "fear", "holy", "pummel", "phantom", "demonicfinisher", "insanity",
        "jeopardy", "thunderfamine", "runevein", "marauder", "thirdeye", "severance", "alembic", "lockjaw", "bossslayer", "destruction",
    ];

    public static function getForRoll(Player $player, string $enchantKey): int
    {
        if (!in_array(strtolower($enchantKey), self::AXE_ENCHANTS, true)) {
            return 0;
        }
        $h = $player->getInventory()->getItemInHand();
        if (!($h instanceof Axe)) {
            return 0;
        }
        $l = CustomEnchantmentManager::getLevel($h, "runevein");
        $per = max(0, EnchantTuningManager::getInt("runevein", "axe-proc-bonus-per-level", 2));
        return $l > 0 ? $per * $l : 0;
    }
}
