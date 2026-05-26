<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments;

use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\manager\AdvancedEnchantState;
use pocketmine\item\Axe;
use pocketmine\player\Player;

/**
 * When an axe custom enchant procs, refresh Runeswell's 30s soul discount window.
 */
final class RuneswellAfterProc
{
    public static function onProcSucceeded(Player $player, string $enchantKey): void
    {
        $h = $player->getInventory()->getItemInHand();
        if (!($h instanceof Axe) || CustomEnchantmentManager::getLevel($h, "runevein") < 1) {
            return;
        }
        if (RuneswellProcBonus::getForRoll($player, $enchantKey) < 1) {
            return;
        }
        AdvancedEnchantState::setRuneswellSoulDiscount($player, 30);
    }
}
