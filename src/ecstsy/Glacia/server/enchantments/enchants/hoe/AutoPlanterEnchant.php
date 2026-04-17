<?php 

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\hoe;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\handler\BlockBreakEnchantmentHandler;
use ecstsy\Glacia\tasks\AutoPlanterTask;
use ecstsy\Glacia\utils\GlaciaUtils;
use pocketmine\block\Beetroot;
use pocketmine\block\Block;
use pocketmine\block\Carrot;
use pocketmine\block\NetherWartPlant;
use pocketmine\block\Potato;
use pocketmine\block\Wheat;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;

final class AutoPlanterEnchant implements BlockBreakEnchantmentHandler {

    private static ?AutoPlanterTask$task = null;
    public static function setTask(AutoPlanterTask $t): void {
        self::$task = $t;
    }

    public static function handle(BlockBreakEvent $event, CustomEnchantment $enchant, int $level): void {
        if ($event->isCancelled() || $level < 1) {
            return;
        }

        $player = $event->getPlayer();
        $block  = $event->getBlock();
        $hand   = $player->getInventory()->getItemInHand();

        if (!CustomEnchantmentManager::hasEnchantment($hand, "autoplanter")) {
            return;
        }

        if (!GlaciaUtils::isFullyGrownCrop($block)) {
            return;
        }

        $seed = self::getSeedForCrop($block);
        if ($seed === null || !$player->getInventory()->contains($seed)) {
            return;
        }

        if (self::$task !== null) {
            self::$task->add($block, $player, $seed);
        }
    }

    private static function getSeedForCrop(Block $block): ?Item {
        return match (true) {
            $block instanceof Wheat =>
                VanillaItems::WHEAT_SEEDS(),

            $block instanceof Carrot =>
                VanillaItems::CARROT(),

            $block instanceof Potato =>
                VanillaItems::POTATO(),

            $block instanceof Beetroot =>
                VanillaItems::BEETROOT_SEEDS(),

            $block instanceof NetherWartPlant =>
                VanillaItems::NETHER_WART(),

            default => null,
        };
    }
}