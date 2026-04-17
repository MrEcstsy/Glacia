<?php 

declare(strict_types=1);

namespace ecstsy\Glacia\server\enchantments\enchants\pickaxe;

use ecstsy\Glacia\server\enchantments\CustomEnchantment;
use ecstsy\Glacia\server\enchantments\CustomEnchantmentManager;
use ecstsy\Glacia\server\enchantments\handler\InteractEnchantmentHandler;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\event\player\PlayerInteractEvent;

final class ObsidianDestroyerEnchant implements InteractEnchantmentHandler {

    public static function handle(PlayerInteractEvent $event, CustomEnchantment $enchant, int $level): void
    {
        if ($event->isCancelled()) return;

        $player = $event->getPlayer();
        $item = $event->getItem();
        $block = $event->getBlock();

        if ($event->getAction() !== PlayerInteractEvent::LEFT_CLICK_BLOCK) return;
        if (!CustomEnchantmentManager::hasEnchantment($item, "obsidiandestroyer")) return;
        if ($block->getTypeId() !== BlockTypeIds::OBSIDIAN) return;

        $chance = $level * 5;
        if (mt_rand(1, 100) > $chance) return;

        $position = $block->getPosition();
        $world = $position->getWorld();
        $world->setBlock($position, VanillaBlocks::AIR());
        $world->dropItem($position, VanillaBlocks::OBSIDIAN()->asItem());

        $event->cancel(); 
    }
}