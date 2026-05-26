<?php 

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\pickaxe;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\handler\BlockBreakEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantProcManager;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use pocketmine\event\block\BlockBreakEvent;

final class LuckyEnchant implements BlockBreakEnchantmentHandler {

    public static function handle(BlockBreakEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) {
            return;
        }

        $player = $event->getPlayer();
        $item = $player->getInventory()->getItemInHand();

        if (!CustomEnchantmentManager::hasEnchantment($item, "lucky")) {
            return;
        }

        $chance = EnchantTuningManager::chance("lucky", $level, 8.0 * $level);
        $result = EnchantProcManager::attempt(
            $player,
            "lucky",
            $level,
            $chance,
            EnchantTuningManager::cooldownSeconds("lucky", $level, 0)
        );
        if (!$result->success) {
            return;
        }

        $xpGain = max(0, EnchantTuningManager::valueInt("lucky", "xp-gain", $level, $level * 3));
        $player->getXpManager()->addXp($xpGain);
        EnchantProcManager::debugAppliedValues($player, $result, [
            "xp_gain" => $xpGain,
        ]);
    }
}
