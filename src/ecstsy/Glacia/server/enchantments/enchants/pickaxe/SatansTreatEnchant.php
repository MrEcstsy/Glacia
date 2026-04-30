<?php

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\pickaxe;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\handler\BlockBreakEnchantmentHandler;
use ecstsy\Glacia\server\enchantments\manager\EnchantTuningManager;
use ecstsy\Glacia\server\enchantments\manager\ProcLuckManager;
use ecstsy\Glacia\server\items\GlaciaItemFactory;
use ecstsy\Glacia\utils\RewardResolver;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

final class SatansTreatEnchant implements BlockBreakEnchantmentHandler
{
    public static function handle(BlockBreakEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) return;

        $player = $event->getPlayer();
        if (!$player instanceof Player) return;

        $chance = EnchantTuningManager::getLevelInt("satanstreat", "chance-by-level", $level, max(1, $level));
        if (!ProcLuckManager::roll($player, "satanstreat", $chance, 1, 5)) return;

        $entry = [
            "type" => "item",
            "id" => (string) EnchantTuningManager::getValue("satanstreat", "reward-item-id", "legendarybook"),
        ];
        $reward = RewardResolver::resolveItem($entry, $player) ?? GlaciaItemFactory::randomEnchantBook("mastery");
        foreach ($player->getInventory()->addItem($reward) as $leftover) {
            $player->getWorld()->dropItem($player->getPosition(), $leftover);
        }
        $player->sendMessage(C::colorize("&r&4Satan's Treat &r&7revealed a hidden key reward."));
    }
}

